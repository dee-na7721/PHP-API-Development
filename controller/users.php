<?php

require_once('db.php');
require_once('../model/response.php');

try{
    $writeDB = DB::connectWriteDB();
}catch(PDOException $ex){
    error_log("Connection Error: ".$ex,0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database Connection error");
    $response->send();
    exit;
}
//Create a User SIGN UP USER 

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request Method not allowed");
    $response->send();
    exit;
}

if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Content-type header not set to json");
    $response->send();
    exit;
}

$rawPostData = file_get_contents('php://input');
if(!$jsonData = json_decode($rawPostData)){
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Request body is not valid json");
    $response->send();
    exit;
}
// $jsonData = json_decode($rawPostData);
//check if fields are set
if(!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    !isset($jsonData->fullname)?$response->addMessage("Fullname not supplied"):false;
    !isset($jsonData->username)?$response->addMessage("Username not supplied"):false;
    !isset($jsonData->password)?$response->addMessage("Password not supplied"):false;
    $response->send();
    exit;
}

//check length 
if(strlen($jsonData->fullname)<1 || strlen($jsonData->fullname)>255 || strlen($jsonData->username)<1 || strlen($jsonData->username) > 255 || strlen($jsonData->password)<1 || strlen($jsonData->password) > 255){
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    !strlen($jsonData->fullname)<1 ?$response->addMessage("Fullname cant be blank"):false;
    !strlen($jsonData->fullname)>255 ?$response->addMessage("Fullname cant be greater than 255"):false;
    !strlen($jsonData->username)<1 ?$response->addMessage("Username cant be blank"):false;
    !strlen($jsonData->username)>255 ?$response->addMessage("Username cant be greater than 255"):false;
    !strlen($jsonData->password)<1 ?$response->addMessage("Password cant be blank"):false;
    !strlen($jsonData->password)>255?$response->addMessage("Password cant be greater than 255"):false;
    $response->send();
    exit;
}

//remove space at end if exist
$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try{
    //check if another id exist with same username i.e checking for same username
    $query = $writeDB->prepare('select id from tblusers where username= :username');
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();
    //if same username exists rowCount =1 
    if($rowCount !== 0){
        $response = new Response();
        $response->setHttpStatusCode(409); //409->CONFLICT
        $response->setSuccess(false);
        $response->addMessage("username already exist!");
        $response->send();
        exit;
    }
    //hashing pw
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = $writeDB->prepare('insert into tblusers (fullname, username, password) values (:fullname, :username, :password)');
    $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);;
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $query->execute();

    $rowCount= $query->rowCount();

    if($rowCount === 0){
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("There was an issue creating a user - please try again!");
        $response->send();
        exit;
    }
    //Display Created User's Credentials except pw 
    $lastUserID = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    $response = new Response();
        $response->setHttpStatusCode(201);  //201-creation successful
        $response->setSuccess(true);
        $response->addMessage("Userr Created");
        $response->setData($returnData);
        $response->send();
        exit;




}catch(PDOException $ex){
    error_log("Database query error: ".$ex,0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("There was an issue creating a user account - please try again!");
    $response->send();
    exit;
}