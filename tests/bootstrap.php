<?php

declare(strict_types=1);

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';
$_SERVER['CONTEMPT_LOAD_DOTENV'] = '0';
$_ENV['CONTEMPT_LOAD_DOTENV'] = '0';

if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';

    if (!is_file($autoload) || is_link($autoload)) {
        throw new \RuntimeException('Composer autoload is unavailable. Run composer install before executing tests.');
    }

    require $autoload;
}
