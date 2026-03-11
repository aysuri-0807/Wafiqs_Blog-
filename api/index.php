<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$data = [
  "Test" => "this should show when you request it"
];

//sends the data to the user
echo json_encode($data);