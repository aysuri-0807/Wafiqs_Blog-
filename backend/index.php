<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$data = [
    "id" => 1,
    "name" => "Alex",
    "status" => "Online"
];

echo json_encode($data);