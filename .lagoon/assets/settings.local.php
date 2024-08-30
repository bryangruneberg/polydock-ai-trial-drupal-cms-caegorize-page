<?php

$databases['default']['default'] = [
    'driver' => 'mysql',
    'database' => getenv('MARIADB_DATABASE') ?: 'drupal',
    'username' => getenv('MARIADB_USERNAME') ?: 'drupal',
    'password' => getenv('MARIADB_PASSWORD') ?: 'drupal',
    'host' => getenv('MARIADB_HOST') ?: 'mariadb',
    'port' => getenv('MARIADB_PORT') ?: 3306,
    'prefix' => '',
]
