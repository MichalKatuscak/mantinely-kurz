<?php

use App\Tests\Support\TestDatabase;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Testovací databáze vzniká z migrací jednou; testy si pak kopírují čistou šablonu.
TestDatabase::buildTemplate();
