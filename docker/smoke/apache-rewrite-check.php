<?php

function request(string $path, string $method = 'GET', array $extra = []): array
{
    $c = curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $extra]);
    if ($method === 'HEAD') {
        curl_setopt($c, CURLOPT_NOBODY, true);
    }
    $raw = curl_exec($c);
    if ($raw === false) {
        throw new RuntimeException('Apache HTTP request failed.');
    }
    $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $size = curl_getinfo($c, CURLINFO_HEADER_SIZE);
    $headers = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $headers[strtolower($parts[0])] = trim($parts[1]);
        }
    }
    $body = substr($raw, $size);
    curl_close($c);

    return compact('status', 'headers', 'body');
}
function check(bool $ok, string $label): void
{
    if (! $ok) {
        throw new RuntimeException($label);
    }
}
try {
    check(file_get_contents('/var/www/html/storage/installed') === 'Apache media-only fixture', 'Use only the isolated Apache media-only fixture');
    $get = request('/storage/clip.txt');
    check($get['status'] === 200 && $get['body'] === '0123456789', 'authoritative media over stale physical file');
    $head = request('/storage/clip.txt', 'HEAD');
    check($head['status'] === 200 && $head['body'] === '' && ($head['headers']['content-length'] ?? '') === '10', 'HEAD');
    $range = request('/storage/clip.txt', 'GET', ['Range: bytes=2-5']);
    check($range['status'] === 206 && $range['body'] === '2345', 'Range');
    $replacement = 'replacement-content';
    file_put_contents('/var/www/html/storage/app/public/clip.txt', $replacement);
    $overwritten = request('/storage/clip.txt');
    check($overwritten['status'] === 200 && $overwritten['body'] === $replacement, 'overwrite GET new content');
    $overwrittenHead = request('/storage/clip.txt', 'HEAD');
    check($overwrittenHead['status'] === 200 && $overwrittenHead['body'] === '' && ($overwrittenHead['headers']['content-length'] ?? '') === (string) strlen($replacement), 'overwrite HEAD new length');
    check(isset($get['headers']['etag'],$overwritten['headers']['etag']) && $get['headers']['etag'] !== $overwritten['headers']['etag'], 'overwrite ETag changed');
    $conditional = request('/storage/clip.txt', 'GET', ['If-None-Match: '.$get['headers']['etag']]);
    check($conditional['status'] === 200 && $conditional['body'] === $replacement, 'old ETag conditional GET new content');
    $script = request('/storage/unsafe.php');
    check($script['status'] === 404 && ! str_contains($script['body'], 'SHOULD_NOT_EXECUTE'), 'physical PHP file denied');
    $dot = request('/storage/.secret');
    check($dot['status'] === 404, 'hidden file denied');
    unlink('/var/www/html/storage/app/public/clip.txt');
    $deleted = request('/storage/clip.txt');
    check($deleted['status'] === 404, 'deleted authoritative file never falls back to stale copy');
    file_put_contents('/var/www/html/storage/app/public/clip.txt', '0123456789');
    echo json_encode(['server' => $get['headers']['server'] ?? null, 'php' => $get['headers']['x-powered-by'] ?? null, 'media_get' => $get['status'], 'authoritative_body' => true, 'overwrite_get' => $overwritten['status'], 'overwrite_head_length' => $overwrittenHead['headers']['content-length'], 'overwrite_etag_changed' => true, 'conditional_old_etag' => $conditional['status'], 'overwrite_new_body' => true, 'media_head' => $head['status'], 'media_range' => $range['status'], 'physical_php_denied' => $script['status'], 'hidden_denied' => $dot['status'], 'deleted_with_stale_physical_copy' => $deleted['status'], 'media_csp' => $get['headers']['content-security-policy'] ?? null, 'symlink_absent' => ! is_link('/var/www/html/html_public/storage')], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Apache smoke failed: '.$e->getMessage()."\n");
    exit(1);
}
