<?php

// Basic example of PHP script to handle with jQuery-Tabledit plug-in.
// Note that is just an example. Should take precautions such as filtering the input data.
include("./trfunctions.php");
include("./functions.php");
header('Content-Type: application/json');
/*$args = array (
    'bh_community' => FILTER_SANITIZE_STRING,
    'bh_route' => FILTER_SANITIZE_STRING,
    'action' => FILTER_SANITIZE_STRING,
    'bh_active' => FILTER_VALIDATE_INT,
    'bh_lifespan' => FILTER_VALIDATE_INT,
    'bh_index' => FILTER_VALIDATE_INT
);
*/
/*
$test = array (
          'bh_lifespan' => 96,
          'action' => 'edit',
          'bh_community' => 'Monkeybutters',
          'bh_route' => '1.2.3.4/24 0.0.0.0',
          'bh_index' => 1,
          'bh_active' => 0
);
*/

$input = filter_input_array(INPUT_POST, $args);

$request = json_encode($input);
$response = sendToProcessingEngine($request);

echo json_encode($response);
?>