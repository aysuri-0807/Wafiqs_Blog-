<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$data = [
  "Test" => "this should show when you request it"
];

echo json_encode($data);