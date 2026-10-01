<?php

use App\Modules\Setup\Services\PublicMediaService;
use Illuminate\Contracts\Console\Kernel;

$selectRuntimeUser = require __DIR__.'/runtime-user.php';
$selectRuntimeUser();
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app->make(PublicMediaService::class)->prepare();
