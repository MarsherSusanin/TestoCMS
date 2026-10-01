#!/usr/bin/env python3
"""Real production HTTP installer proof against a disposable PostgreSQL database.

Copy current CMS source/vendor/build into a NEW /private/tmp/testocms_p1* directory,
excluding .env, storage and bootstrap/cache/*.php. Set WIZARD_DB_PASSWORD in the
process environment, then run this script with --allow-disposable-db. It creates
and drops only the supplied testocms_p1_* database and starts/stops its own PHP
server. It refuses an installed/non-cold root or an existing database. Never use
this against a production root/database. The summary omits credentials and CSRF.
"""
import argparse
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import time
from html.parser import HTMLParser
from urllib import error, parse, request


class NoRedirect(request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class CsrfParser(HTMLParser):
    token = None

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'input' and values.get('name') == '_token':
            self.token = values.get('value')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', required=True)
    parser.add_argument('--database', default='testocms_p1_wizard')
    parser.add_argument('--db-host', default='127.0.0.1')
    parser.add_argument('--db-port', default='56241')
    parser.add_argument('--db-user', default='postgres')
    parser.add_argument('--port', type=int, default=18976)
    parser.add_argument('--allow-disposable-db', action='store_true')
    parser.add_argument('--log', default='/private/tmp/testocms-fullwizard-result.json')
    args = parser.parse_args()
    root = Path(args.root).resolve()
    if not (root.parent == Path('/private/tmp') and root.name.startswith('testocms_p1')):
        parser.error('--root must be a direct /private/tmp/testocms_p1* isolated directory')
    if not args.allow_disposable_db or not re.fullmatch(r'testocms_p1_[a-z0-9_]+', args.database):
        parser.error('Explicit opt-in and a testocms_p1_* database name are required')
    if args.db_host not in ('127.0.0.1', 'localhost'):
        parser.error('Only localhost disposable PostgreSQL is supported')
    if not os.environ.get('WIZARD_DB_PASSWORD'):
        parser.error('Set WIZARD_DB_PASSWORD; passwords must not be passed as command arguments')
    if not (root/'vendor/autoload.php').is_file() or not (root/'html_public/index.php').is_file():
        parser.error('The isolated root needs CMS source, vendor and public entrypoint')
    for relative in ['.env', 'storage/installed', 'storage/app/private/setup.key', 'bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php']:
        if (root/relative).exists():
            parser.error('Root is not cold: '+relative+' exists; create a new isolated copy')
    log = Path(args.log).resolve()
    if log.parent != Path('/private/tmp'):
        parser.error('--log must be directly in /private/tmp')
    php = shutil.which('php')
    if not php:
        parser.error('PHP CLI with pdo_pgsql is required')
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', args.port))
    for relative in ['storage/app/private', 'storage/app/public', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache']:
        (root/relative).mkdir(parents=True, exist_ok=True)
    db_env = os.environ.copy()
    db_env.update(WIZARD_DB_HOST=args.db_host, WIZARD_DB_PORT=args.db_port, WIZARD_DB_USER=args.db_user, WIZARD_DB_NAME=args.database)
    pdo = '$pdo=new PDO("pgsql:host=".getenv("WIZARD_DB_HOST").";port=".getenv("WIZARD_DB_PORT").";dbname=".$argv[1],getenv("WIZARD_DB_USER"),getenv("WIZARD_DB_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'

    def sql(code, database=None):
        result = subprocess.run([php, '-r', pdo+code, database or args.database], env=db_env, capture_output=True, text=True, timeout=60)
        if result.returncode != 0:
            raise RuntimeError('Disposable PostgreSQL operation failed (details withheld to protect credentials)')
        return result.stdout.strip()

    created = False
    server = None
    output = None
    report = {'passed': False, 'production_http': True, 'root': str(root), 'database': args.database, 'checks': []}

    def check(name, condition):
        if not condition:
            raise AssertionError(name)
        report['checks'].append(name)
        print('PASS '+name, flush=True)

    try:
        check('Database does not already exist', sql('echo (int)$pdo->query("SELECT count(*) FROM pg_database WHERE datname=".$pdo->quote(getenv("WIZARD_DB_NAME")))->fetchColumn();', 'postgres') == '0')
        sql('$pdo->exec("CREATE DATABASE \\\"".getenv("WIZARD_DB_NAME")."\\\"");', 'postgres')
        created = True
        check('Cold root has no env/key/marker; DB has no tables', sql('echo (int)$pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema=\'public\'")->fetchColumn();') == '0')
        server_env = {k: v for k, v in os.environ.items() if not k.startswith(('APP_', 'DB_', 'CMS_', 'SESSION_', 'CACHE_', 'REDIS_', 'LARAVEL_', 'WIZARD_'))}
        server_env.update(APP_ENV='production', APP_DEBUG='false', LARAVEL_BASE_PATH=str(root), LARAVEL_PUBLIC_PATH='html_public')
        output = (root/'storage/logs/wizard-http.log').open('w')
        server = subprocess.Popen([php, '-S', '127.0.0.1:'+str(args.port), '-t', str(root/'html_public'), str(root/'html_public/index.php')], cwd=root, env=server_env, stdout=output, stderr=output)
        base = 'http://127.0.0.1:'+str(args.port)
        cookies = http.cookiejar.CookieJar()
        browser = request.build_opener(request.HTTPCookieProcessor(cookies), NoRedirect())

        def send_request(path, fields=None):
            data = parse.urlencode(fields, doseq=True).encode() if fields is not None else None
            req = request.Request(base+path, data=data, headers={'Accept': 'text/html'})
            try:
                response = browser.open(req, timeout=180)
            except error.HTTPError as response_error:
                response = response_error
            return response.status, response.headers, response.read().decode('utf-8', errors='replace')

        deadline = time.monotonic()+20
        while True:
            try:
                status, _, _ = send_request('/up')
                if status == 200:
                    break
            except (error.URLError, ConnectionError):
                pass
            if server.poll() is not None or time.monotonic() > deadline:
                raise RuntimeError('Own PHP HTTP server did not become ready')
            time.sleep(0.1)

        def page(number):
            status, _, body = send_request('/setup/step/'+str(number))
            check('GET wizard step '+str(number)+' returns200', status == 200)
            parsed = CsrfParser()
            parsed.feed(body)
            if number >= 2:
                check('Step '+str(number)+' contains CSRF token', bool(parsed.token))
            return parsed.token

        def submit(number, fields, token):
            status, headers, _ = send_request('/setup/step/'+str(number), {**fields, '_token': token})
            check('POST step '+str(number)+' redirects to next step', status == 302 and parse.urlparse(headers.get('Location', '')).path == '/setup/step/'+str(number+1))

        page(1)
        token = page(2)
        key_path = root/'storage/app/private/setup.key'
        key_hash = hashlib.sha256(key_path.read_bytes()).hexdigest()
        check('Private bootstrap key exists with mode0600', key_path.is_file() and key_path.stat().st_mode & 0o777 == 0o600)
        db = {'db_connection': 'pgsql', 'db_host': args.db_host, 'db_port': args.db_port, 'db_database': args.database, 'db_username': args.db_user, 'db_password': 'wrong-disposable-password'}
        submit(2, db, token)
        token = page(3)
        submit(3, {'deployment_profile': 'docker_vps', 'app_name': 'Cold Wizard Audit', 'app_url': base, 'supported_locales[]': ['ru', 'en'], 'default_locale': 'ru'}, token)
        token = page(4)
        admin_password = os.environ.get('WIZARD_ADMIN_PASSWORD', 'DisposableWizard123!')
        submit(4, {'admin_name': 'Wizard Admin', 'admin_email': 'wizard@audit.local', 'admin_login': 'wizard_admin', 'admin_password': admin_password, 'admin_password_confirmation': admin_password}, token)
        token = page(5)
        check('Review GET does not write env/marker/tables', not (root/'.env').exists() and not (root/'storage/installed').exists() and sql('echo (int)$pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema=\'public\'")->fetchColumn();') == '0')
        status, _, _ = send_request('/setup/step/5', {})
        check('Finalization POST without CSRF returns419', status == 419)
        check('Rejected CSRF leaves env/marker absent', not (root/'.env').exists() and not (root/'storage/installed').exists())
        status, _, body = send_request('/setup/step/5', {'_token': token})
        check('Bad DB credentials render real finalizer failure', status == 200 and 'Установка завершена с ошибками' in body)
        check('Failure writes env but no installed marker/tables', (root/'.env').is_file() and not (root/'storage/installed').exists() and sql('echo (int)$pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema=\'public\'")->fetchColumn();') == '0')
        token = page(2)
        check('Cookie session and bootstrap key survive failure', hashlib.sha256(key_path.read_bytes()).hexdigest() == key_hash)
        db['db_password'] = os.environ['WIZARD_DB_PASSWORD']
        submit(2, db, token)
        token = page(5)
        status, _, body = send_request('/setup/step/5', {'_token': token})
        check('Retry runs real finalizer successfully', status == 200 and 'TestoCMS успешно установлена' in body and 'Установка завершена с ошибками' not in body)
        check('Installed marker exists only after successful retry', (root/'storage/installed').is_file())
        counts = json.loads(sql('echo json_encode(["migrations"=>(int)$pdo->query("SELECT count(*) FROM migrations")->fetchColumn(),"users"=>(int)$pdo->query("SELECT count(*) FROM users")->fetchColumn(),"superadmins"=>(int)$pdo->query("SELECT count(*) FROM model_has_roles m JOIN roles r ON r.id=m.role_id WHERE r.name=\'superadmin\'")->fetchColumn()]);'))
        expected_migrations = len(list((root/'database/migrations').glob('*.php')))
        check('All migrations and one superadmin provisioned', counts == {'migrations': expected_migrations, 'users': 1, 'superadmins': 1})
        report['database_counts'] = counts
        configuration = subprocess.run([php, '-r', 'require $argv[1]."/vendor/autoload.php"; $e=Dotenv\\Dotenv::parse(file_get_contents($argv[1]."/.env")); $c=require $argv[1]."/bootstrap/cache/config.php"; echo json_encode(["session_driver"=>$c["session"]["driver"],"matches_env"=>$c["session"]["driver"]===$e["SESSION_DRIVER"],"installer_cookie_cached"=>str_starts_with($c["session"]["cookie"],"testocms_setup_"),"key_preserved"=>hash_equals(trim(file_get_contents($argv[1]."/storage/app/private/setup.key")),$e["APP_KEY"])]);', str(root)], capture_output=True, text=True, timeout=30)
        check('Installed optimized config can be loaded', configuration.returncode == 0)
        configured = json.loads(configuration.stdout)
        report['installed_session_driver'] = configured['session_driver']
        check('Installed config uses configured database sessions and normal cookie', configured['session_driver'] == 'database' and configured['matches_env'] and not configured['installer_cookie_cached'])
        check('Generated env preserves the private bootstrap APP_KEY', configured['key_preserved'])
        status, _, body = send_request('/admin/login')
        check('Installed admin login returns200', status == 200)
        csrf = CsrfParser()
        csrf.feed(body)
        check('Installed login has a real CSRF token', bool(csrf.token))
        status, headers, _ = send_request('/admin/login', {'email': 'wizard@audit.local', 'password': admin_password, '_token': csrf.token})
        check('Provisioned superadmin login succeeds', status == 302 and parse.urlparse(headers.get('Location', '')).path == '/admin')
        status, _, _ = send_request('/admin/posts')
        check('Authenticated admin content screen returns200', status == 200)
        check('Installed login session is physically persisted in PostgreSQL', int(sql('echo (int)$pdo->query("SELECT count(*) FROM sessions WHERE user_id IS NOT NULL")->fetchColumn();')) >= 1)
        status, _, _ = send_request('/setup/step/1')
        check('Installed wizard is closed with404', status == 404)
        report['passed'] = True
    except Exception as exc:
        report['failure'] = type(exc).__name__+': '+str(exc)
    finally:
        if server is not None:
            server.terminate()
            try:
                server.wait(timeout=10)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait(timeout=10)
        if output is not None:
            output.close()
        report['own_http_server_stopped'] = server is None or server.poll() is not None
        if created:
            try:
                sql('$pdo->exec("DROP DATABASE \\\"".getenv("WIZARD_DB_NAME")."\\\" WITH (FORCE)");', 'postgres')
                report['own_database_dropped'] = True
            except Exception:
                report['own_database_dropped'] = False
                report['passed'] = False
        log.write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
        print(json.dumps(report, ensure_ascii=False), flush=True)
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
