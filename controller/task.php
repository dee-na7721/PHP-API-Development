<?php

    require_once('db.php');
    require_once('../model/task.php');
    require_once('../model/response.php');

    try{
        $writeDB = DB::connectWriteDB();
        $readDB = DB::connectReadDB();   
    }catch(PDOException $ex){
        error_log("Connection Error- ".$ex, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("Database Connection err");
        $response->send();
        exit;
    }

    if(array_key_exists('taskid', $_GET)){
        $taskid = $_GET['taskid'];

        if($taskid == '' ||  !is_numeric($taskid)){
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->addMessage("Task Id cant be blank and must be numeric");
            $response->send();
            exit;
        }

        if($_SERVER['REQUEST_METHOD'] == 'GET'){

            try{
                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0 ){
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessage("Task not found");
                    $response->send();
                    exit;

                }

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task =new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[]= $task->returnTaskArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response= new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->toCache(true);
                $response->setData($returnData);
                $response->addMessage("Task found");
                $response->send();
                exit;

            }catch(PDOException $ex){
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage($ex->getMessage());
                $response->send();
                exit;
            }catch(PDOException $ex){
                error_log("Database query Error- ".$ex, 0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to get task");
                $response->send();
                exit;
            }

        }
        elseif($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            
            try{
                $query = $writeDB->prepare('delete from tbltasks where id= :taskid');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->execute();
                //if deleted rowCount = 1
                $rowCount = $query->rowCount();

                if($rowCount === 0){
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessage("Task not found");
                    $response->send();
                    exit; 
                }

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->addMessage("Task Deleted");
                $response->send();
                exit;
            }catch(PDOException $ex){

                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("failed to delete task");
                $response->send();
                exit;
            }
        }
        elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            # code...
        }
        else {
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->addMessage("request method not allowed");
            $response->send();
            exit;
            
        }
    }
    //Get all incomplete and incomplete task
    elseif (array_key_exists("completed", $_GET)) {
        
        $completed = $_GET['completed'];

        if($completed !== 'Y' && $completed !== 'N'){
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Completed filter must be Y or N");
            $response->send();
            exit;
        }

        if($_SERVER['REQUEST_METHOD'] === 'GET'){

            try{

                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where completed = :completed');
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->execute();

                $rowCount = $query->rowCount();
                $taskArray = array()    ;

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                    $taskArray[] = $task->returnTaskArray();
                }
                $returnData = array();
                $returnData['rows returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response = new response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->toCache(true);
                $response->setData($returnData);
                // $response->addMessage("Completed filter must be Y or N");
                $response->send();
                exit;


            }catch(TaskException $ex){
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage($ex->getMessage());
                $response->send();
                exit;
            }
            catch(PDOException $ex){
                error_log("Database query error - ".$ex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to get tasks");
                $response->send();
                exit;
            }

        }else {
            $response=new Response;
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->addMessage("Request Method not allowed");
            $response->send();
            exit;
        }
    }



