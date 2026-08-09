<?php

$logFile = __DIR__ . '/requests.log';

$data = [
    'time'    => date('Y-m-d H:i:s'),
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
    'url'     => ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' .
                 ($_SERVER['HTTP_HOST'] ?? '') .
                 ($_SERVER['REQUEST_URI'] ?? ''),
//    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'get'     => $_GET,
    'post'    => $_POST,
    'body'    => file_get_contents('php://input'),
];

file_put_contents(
    $logFile,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

echo "OK";