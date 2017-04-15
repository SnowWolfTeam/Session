<?php
return [
    'gc_probability' => 1,
    'gc_divisor' => 5,
    'gc_on' => true,
    'table_name' => 'session',
    'key_field_name' => 'name',
    'data_field_name' => 'data',
    'expire_field_name' => 'expire',
    'lost_time' => 0,
    'pdo' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=test',
        'user' => 'root',
        'password' => 'root'
    ]
];