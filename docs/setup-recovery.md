# Повторная настройка и восстановление

`php artisan cms:setup --redo` изменяет настройки и существующий первый superadmin
по id. Он не запускает migrations/seeds, не создаёт нового администратора и не
снимает installed marker. DB driver/host/port/database/schema/socket/prefix,
активный public root и APP_KEY фиксированы; перенос экземпляра или ротация ключа
не входят в setup. Проверка читает и исходный env, поэтому старый config cache
не скрывает изменение этих параметров. Интеграционные неизвестные env-поля и ключи
сохраняются. Сначала показывается цель, затем требуется интерактивное подтверждение.

На readonly Docker/VPS:

```bash
php docker/artisan.php cms:setup --redo --from-env --force --no-interaction
```

Эта команда читает admin name/login/email из исходного dotenv и может изменять
эти поля; существующий password hash сохраняется даже при другом
CMS_ADMIN_PASSWORD. Повторный обычный `--from-env` сохраняет весь аккаунт. Изменение
email/login или интерактивного пароля отзывает существующие sessions/PATs.

Перед изменением сохраняется private journal `storage/app/private/setup-redo.json`
с уникальным operation UUID и режимом 0600. Он содержит исходные env/config cache,
marker, admin и его sessions/tokens, поэтому резервировать/читать его нужно как
секрет. Операция использует общий exclusive gate с updater и maintenance. Ошибка
восстанавливает снимок, включая прежний maintenance status. Если восстановление
не завершилось, journal и maintenance сохраняются; новые redo блокируются.

При прерывании процесса/потере доступа к файловой системе:

1. Исправьте свободное место/доступ к исходному env и private runtime directories.
2. Сохраните journal. Возьмите operation UUID из CLI вывода либо private JSON.
3. Выполните на том же экземпляре:

```bash
php artisan cms:setup:recover OPERATION-UUID
# Docker, чтобы правильно прочитать private local keys и UID:
php docker/artisan.php cms:setup:recover OPERATION-UUID
```

Recovery отказывается от чужого operation/instance UUID и не применяет journal
к другой БД. Повтор после успешного recovery безопасен: при отсутствии journal
это no-op. В фазе completed восстанавливается только прежний maintenance status,
поскольку изменения уже зафиксированы. Не удаляйте journal/marker/down вручную
вместо recovery и не меняйте DB identity для обхода проверки.

Legacy экземпляр без привязанного UUID продолжает обслуживать HTTP. Перед
автоматическим init явно выполните штатные migrations и проверьте DB/public root,
исходный env и наличие superadmin, затем:

```bash
php artisan cms:setup --adopt-existing --force --no-interaction
```

Adoption проверяет готовую schema/admin и привязывает существующий marker/DB без
переписывания env, account или content. Для пустой БД с прежним marker требуется
восстановить соответствующие DB/storage; автоматического reset нет.
