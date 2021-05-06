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

    //BEGIN AUTH Script


    if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1){
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        !isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage('Access Token is missing from the header'):false;
        strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage('Access Token cant be blank'):false;
        $response->send();
        exit();
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    try{
        $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if($rowCount === 0){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Invalid access token");
            $response->send();
            exit;
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];


        if($returned_useractive !== 'Y'){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("User account is inactive");
            $response->send();
            exit;
        }

        if($returned_loginattempts >= 3){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("User account is currently locked");
            $response->send();
            exit;
        }

        if(strtotime($returned_accesstokenexpiry) < time()){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Access Token is expired");
            $response->send();
            exit;
        }

    }
    catch(PDOException $ex){
        $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue authenticating -  please try again");
            $response->send();
            exit;
    }
    //END of AUTH Script




    //Get task by id
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
                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                
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
                $query = $writeDB->prepare('delete from tbltasks where id= :taskid and userid= :userid ');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
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

            try{

                if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('content type header not set to json');
                    $response->send();
                    exit;
                }
                
                //read body>raw json data
                $rawPatchData = file_get_contents('php://input');

                if(!$jsonData = json_decode($rawPatchData)){        //decode=> json2array
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('Request Body is not valid JSON');
                    $response->send();
                    exit;
                }

                $title_updated = false;
                $description_updated = false;
                $deadline_updated = false;
                $completed_updated = false;

                //for dynamic query
                $queryFields = "";

                if(isset($jsonData->title)){
                   $title_updated = true; 
                   $queryFields .= "title= :title, ";
                }

                if(isset($jsonData->description)){
                    $description_updated = true;
                    $queryFields .= "description= :description, ";
                }
                if(isset($jsonData->deadline)){
                    $deadline_updated = true;
                    $queryFields .= "deadline= STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
                }

                if(isset($jsonData->completed)){
                    $completed_updated = true;
                    $queryFields .= "completed= :completed, ";
                }
                
                $queryFields = rtrim($queryFields, ", ");

                if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('No task fields provided for update');
                    $response->send();
                    exit;
                }
                //retrieving the original data of id =taskid 
                $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id= :taskid and userid = :userid');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount === 0 ){
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessage('No task found to update');
                    $response->send();
                    exit;
                }

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                }
                //dynamic query for update
                $queryString = "update tbltasks set ".$queryFields." where id = :taskid and userid = :userid";
                $query = $writeDB->prepare($queryString);
               
                //binding if fields has been updated
                if($title_updated === true){
                    $task->setTitle($jsonData->title);  //updated title set
                    $up_title = $task->getTitle();  //updated title retrieved
                    $query->bindParam(':title', $up_title, PDO::PARAM_STR);
                }

                if($description_updated === true){
                    $task->setDescription($jsonData->description);  //updated desc set
                    $up_description = $task->getDescription();  //updated desc retrieved
                    $query->bindParam(':description', $up_description, PDO::PARAM_STR);
                }

                if($deadline_updated === true){
                    $task->setDeadline($jsonData->deadline);  //updated desc set
                    $up_deadline = $task->getDeadline();  //updated desc retrieved
                    $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
                }

                if($completed_updated === true){
                    $task->setCompleted($jsonData->completed);  //updated desc set
                    $up_completed = $task->getCompleted();  //updated desc retrieved
                    $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
                }

                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                //checking if the row has been updated
                $rowCount = $query->rowCount();
                if($rowCount === 0){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('Task not updated');
                    $response->send();
                    exit;
                }

                $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id= :taskid userid= :userid');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('No task found after update');
                    $response->send();
                    exit;
                }

                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->addMessage('Task updated');
                $response->setData($returnData);
                $response->send();
                exit;


            }catch(TaskException $ex){
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage($ex->getMessage());
                $response->send();
                exit;
            }catch(PDOException $ex){
                error_log("Database query error - ".$ex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to update task - check your data for errors");
                $response->send();
                exit;
            }
            
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

                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where completed = :completed and userid= :userid');
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
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
    //Get tasks  with Pagination 
    elseif(array_key_exists('page', $_GET)){

        if($_SERVER['REQUEST_METHOD'] === 'GET'){

            $page= $_GET['page'];

            if($page == '' || !is_numeric($page)){
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("Page number cant be blank and must be numeric");
                $response->send();
                exit;
            }

            $limitPerPage = 20;

            try{

                $query = $readDB->prepare('select count(id) as totalNoOfTasks from tbltasks where userid= :userid');
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $row = $query->fetch(PDO::FETCH_ASSOC);
                $taskCount = intval($row['totalNoOfTasks']);    //total number of row of task that has been read

                $numOfPages = ceil($taskCount/$limitPerPage);   //num of page formed to display tasks 20task/page

                if($numOfPages == 0){
                    $numOfPages =1;
                }

                if($page > $numOfPages || $page == 0){
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->addMessage("Page not Found");
                    $response->send();
                    exit;
                }  


                $offset = ($page == 1? 0: ($limitPerPage*($page-1)));
                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid= :userid limit :pglimit offset :offset');
                $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->bindParam(':offset', $offset, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
                
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['total_rows'] = $taskCount;
                $returnData['total_pages'] = $numOfPages;
                ($page < $numOfPages ? $returnData['has_next_page'] = true: $returnData['has_next_page']= false);
                ($page > 1 ? $returnData['has_previous_page'] = true: $returnData['has_previous_page']= false);
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->toCache(true);
                $response->setData($returnData);
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
                error_log('Database query error - '.$ex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage('Failed to get tasks');
                $response->send();
                exit;
            }

        }else {
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->addMessage('Request Method Not Allowed');
            $response->send();
            exit;
        }

    }
   
    elseif(empty($_GET)){
         //Get all tasks
        if($_SERVER['REQUEST_METHOD'] === 'GET'){

            try{

                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline , completed from tbltasks where userid= :userid');
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray(); 
                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;
                

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->toCache(true);
                $response->setData($returnData);
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

        }
        //Create a task
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
            try{

                if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('Content type header is not set to JSON');
                    $response->send();
                    exit;
                }
                $rawPOSTData = file_get_contents('php://input');

                if(!$jsonData = json_decode($rawPOSTData)){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage('Request Body is not valid JSON');
                    $response->send();
                    exit;
                }

                if(!isset($jsonData->title) || !isset($jsonData->completed)){
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    !isset($jsonData->title)?$response->addMessage('Title field is mandatory and must be provided'):false;
                    !isset($jsonData->completed)?$response->addMessage('Completed field is mandatory and must be provided'):false;
                    $response->send();
                    exit;
                }

                $newTask = new Task(null, $jsonData->title, (isset($jsonData->description)?$jsonData->description:null), (isset($jsonData->deadline)?$jsonData->deadline:null), $jsonData->completed);
                $title = $newTask->getTitle();
                $description = $newTask->getDescription();
                $deadline = $newTask->getDeadline();
                $completed = $newTask->getCompleted();

                $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed, userid) values (:title, :description, STR_TO_DATE(:deadline,\'%d/%m/%Y %H:%i\'), :completed, :userid)');
                $query->bindParam(':title', $title, PDO::PARAM_STR);
                $query->bindParam(':description', $description, PDO::PARAM_STR);
                $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                
                $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0){
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage('Failed to create task');
                    $response->send();
                    exit;
                }

                //Showing the inserted data 'cause with every post request it should also return object we've created 
                $lastTaskID = $writeDB->lastInsertId();
                $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
                $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
                $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
                
                $query->execute();

                $rowCount = $query->rowCount();
                if($rowCount === 0){
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->addMessage('Failed to retrieve task after creation');
                    $response->send();
                    exit;
                }

                $taskArray = array();
                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }

                $returnData =array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(201);
                $response->setSuccess(true);
                $response->addMessage('Task created');
                $response->setData($returnData);
                $response->send();
                exit;


            }catch(TaskException $ex){
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage($ex->getMessage());
                $response->send();
                exit;
            }catch(PDOException $ex){
                // Send error message to the server log if error connecting to the database
                $err = error_log("Database query error - ".$ex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to insert task into database - check submitted data for error.");
                $response->send();
                exit;
            }
        }
        else {
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->addMessage("Request Method Not Allowed");
            $response->send();
            exit;
        }
    }else {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("End Point Not Found");
        $response->send();
        exit;
    }





