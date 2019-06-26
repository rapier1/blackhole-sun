<?php

// Basic example of PHP script to handle with jQuery-Tabledit plug-in.
// Note that is just an example. Should take precautions such as filtering the input data.
include("./trfunctions.php");
include("./functions.php");
header('Content-Type: application/json');
$args = array (
    'bh_route' => FILTER_SANITIZE_STRING,
    'action' => FILTER_SANITIZE_STRING,
    'bh_active' => FILTER_VALIDATE_INT,
    'bh_lifespan' => FILTER_VALIDATE_INT,
    'bh_index' => FILTER_VALIDATE_INT
);

$input = filter_input_array(INPUT_POST, $args);

$request = json_encode($input);
$response = sendToProcessingEngine($request);

echo json_encode($response);
?>