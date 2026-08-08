<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Shared-host / cPanel front controller
|--------------------------------------------------------------------------
|
| Some hosts cannot set the document root to /public. This file lets the
| project run from the folder root. Keep /public/index.php for artisan serve.
|
*/

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
