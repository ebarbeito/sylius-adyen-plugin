<?php

declare(strict_types=1);

const APPLICATION_BOOTSTRAP = '/tests/Application/config/bootstrap.php';

$rootDirectory = \dirname(__DIR__) . '/..';

require $rootDirectory . '/vendor/autoload.php';

if (file_exists($rootDirectory . APPLICATION_BOOTSTRAP)) {
    require $rootDirectory . APPLICATION_BOOTSTRAP;
}
