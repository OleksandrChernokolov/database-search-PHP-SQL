<?php

require_once './function.php';

$request = trim(file_get_contents("php://input"));
$request = json_decode($request, true);

$action = $request['action'];

switch($action) {
    case 'inputSearch':
        inputSearch();
        break;
    case 'loadCityInfo':
        loadCityInfo();
        break;
    
}

