<?php
require 'vendor/autoload.php';
$client = new \Etcd\Client('127.0.0.1:2379');

// enable authentication
$client->authDisable();

$r = $client->getKeysWithPrefix('/key-prefix');
if (isset($r['kvs'])) {
    $str = $result = json_encode($r['kvs']);
    file_put_contents("result.json", $str);
    echo "ok";
    exit;
}