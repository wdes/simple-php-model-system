<?php

declare(strict_types = 1);

return [
    'database' => [
        'production' => [
            'adapter' => 'mysql',
            'host' => getenv('TEST_MYSQL_HOST'),
            'name' => getenv('TEST_MYSQL_DB'),
            'user' => getenv('TEST_MYSQL_USER'),
            'pass' => getenv('TEST_MYSQL_PASS'),
            'port' => '3306',
            'charset' => 'utf8',
        ],
    ],
    'currentDatabaseEnv' => 'production',
];
