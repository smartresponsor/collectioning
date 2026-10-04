<?php

declare(strict_types=1);

use App\Collectioning\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'test', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
