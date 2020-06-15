<?php
require 'vendor/autoload.php';
$client = new \Etcd\Client('127.0.0.1:2379');

// enable authentication
$client->authDisable();

$result = json_decode(file_get_contents("result.json"), true);

foreach ($result as $index => $val) {
    $client->put($val["key"], $val["value"]);
}