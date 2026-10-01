<?php

// PHP CLI and FPM workers share ownership of locks, caches and uploaded files.
return static function (): void {
    if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
        return;
    }
    $user = posix_getpwnam('www-data');
    if ($user === false || ! posix_initgroups('www-data', $user['gid'])
        || ! posix_setgid($user['gid']) || ! posix_setuid($user['uid'])) {
        throw new RuntimeException('Cannot select the shared container runtime user.');
    }
};
