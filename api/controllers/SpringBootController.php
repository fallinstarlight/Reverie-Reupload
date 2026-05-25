<?php

/* Function to soft delete an employee, sends the request to springboot using cURL */
function deleteEmployee(int $id, string $url){
    if($_SESSION['user_id'] == $id){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>"You cannot deactivate yourself"]);
        exit(); 
    }
    if($id <= 0){
        throw new InvalidArgumentException("Insert a valid id");
    }

    $ch = curl_init();
    $path = "{$url}employees/{$id}"; // request to springboot

    /* Options to set up the request */
    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");     
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    /* Execution of the request */
    try{
        curl_exec($ch);
        http_response_code(200);
        echo json_encode(["status"=>"success", "message"=>"Employee succesfully discharged"]); 
    }
    catch(Exception $e){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>$e->getMessage()]); 
    }
}

/* Soft deletes a product using cURL to request SpringBoot */
function deleteProduct(string $id, string $url){
    $ch = curl_init();
    $path = "{$url}products/{$id}";

    /* cURL options */
    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");     
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    /* Execution */
    try{
        curl_exec($ch);
        http_response_code(200);
        echo json_encode(["status"=>"success", "message"=>"Product succesfully discontinued"]); 
    }
    catch(Exception $e){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>$e->getMessage()]); 
    }
}

/* Function to send a patch request to springboot to re activate an employye via cURL */
function reviveEmployee(int $id, string $url){
    if($id <= 0){
        throw new InvalidArgumentException("Insert a valid id");
    }

    $ch = curl_init();
    $path = "{$url}employees/{$id}/reactivate";

    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");     
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    try{
        curl_exec($ch);
        http_response_code(200);
        echo json_encode(["status"=>"success", "message"=>"Employee succesfully reactivated"]); 
    }
    catch(Exception $e){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>$e->getMessage()]); 
    }
}

/* Function to send a patch request to springboot to re activate a product via cURL */
function reviveProduct(string $id, string $url){
    $ch = curl_init();
    $path = "{$url}products/{$id}/reactivate";

    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");     
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    try{
        curl_exec($ch);
        http_response_code(200);
        echo json_encode(["status"=>"success", "message"=>"Product succesfully brought back"]); 
    }
    catch(Exception $e){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>$e->getMessage()]); 
    }
}