<?php
/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. *
 */
include("./functions.php");
header('Content-Type: application/json');
/*
$args = array (
    'bh_route' => FILTER_SANITIZE_STRING,
    'action' => FILTER_SANITIZE_STRING,
    'bh_active' => FILTER_VALIDATE_INT,
    'bh_lifespan' => FILTER_VALIDATE_INT,
    'bh_index' => FILTER_VALIDATE_INT,
    'bh_customer_id' => FILTER_VALIDATE_INT,
    'bh_user_role' => FILTER_VALIDATE_INT,
    'bh_owner_id' => FILTER_SANITIZE_STRING,
    'bh_comment' => FILTER_SANITIZE_STRING,
    'bh_requestor' => FILTER_SANTIZE_STRING
    );
*/

$input = filter_input_array(INPUT_POST);

$request = json_encode($input);
$response = sendToProcessingEngine($request);
list($result, $msg) = emailNotification($request);
/* not doing anything with the above yet */
echo json_encode($response);
?>
