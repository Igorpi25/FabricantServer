<?php

require_once dirname(__FILE__).'/include/SimpleImage.php'; 
require_once dirname(__FILE__).'/include/DbHandlerProfile.php';
require_once dirname(__FILE__).'/include/DbHandlerFabricant.php';
require_once dirname(__FILE__).'/include/PassHash.php';

require_once dirname(__FILE__).'/libs/Slim/Slim.php';
require_once dirname(__FILE__).'/communicator/WebsocketClient.php';

define('WEBSOCKET_SERVER_PORT', 8666);

 
\Slim\Slim::registerAutoloader();
 
$app = new \Slim\Slim();
 
// User id from db - Global Variable
$user_id = NULL;

/**
 * It used to Slim testing during installation the server 
 */
$app->get('/hello/:name', function ($name) {
		
		//$body=file_get_contents("http://sms.ru/sms/send?api_id=A73F3F48-2F27-8D8D-D7A2-6AFF64E4F744&to=79142966292&text=".$name);
		//echo $body;
});

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoResponse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
 
    // setting response content type to json
    $app->contentType('application/json');
	
    echo json_encode($response,JSON_UNESCAPED_SLASHES);
	
}

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoResponse(200, $response);
        $app->stop();
    }
}
 
/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
		$response["emailNotValid"] = true;
        $response["message"] = 'Email address is not valid';
		$response["email"] = $email;
        echoResponse(200, $response);
        $app->stop();
    }
}

function validatePhone($phone){
	$app = \Slim\Slim::getInstance();
    if( (!preg_match("/^[0-9]{11}$/", $phone))||($phone[0]!=7) ) {
        $response["error"] = true;
		$response["phoneNotValid"] = true;
        $response["message"] = 'Phone number is not valid';
		$response["phone"] = $phone;
        echoResponse(200, $response);
        $app->stop();
    }
		
}

/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register/sms', function() use ($app) {
		// check for required params
		verifyRequiredParams(array('phone'));

		$response = array();

		// reading post params
		$phone = $app->request->post('phone');
		
		// validating phone address
		validatePhone($phone);
		
		$db = new DbHandlerProfile();
		
		if ($db->isUserWithPhoneExists($phone)) {
			$response["error"] = true;			
			$response["phoneAlreadyUsed"] = true;
			$response["message"] = 'Sorry, this phone already exists';
			echoResponse(200, $response);
			$app->stop();
		}
		
		if( $db->isUserWithPhoneExists($phone) ) {
			$response["error"] = true;
			$response["message"] = 'Sorry, this phone already exists';
			echoResponse(200, $response);
			$app->stop();
		}
		
		$code = $db->createSMSVerificationCode($phone);

		if ( (isset($code))&&($code != 0) ) {
			
			$body=file_get_contents("http://sms.ru/sms/send?api_id=A73F3F48-2F27-8D8D-D7A2-6AFF64E4F744&to=".$phone."&text=".$code);
		
			$response["success"] = 1;
			$response["error"] = false;
			$response["message"] = "SMS with code will be sent";
			$response["body"] = $body;
			echoResponse(200, $response);
		} else  {
			$response["success"] = 0;
			$response["error"] = true;
			$response["message"] = "Sorry, internal error. Try again later";
			echoResponse(200, $response);
		}
});
		

/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name','surname','patr','phone', 'code', 'password'));
			
            $response = array();
			
            // reading post params
            $name = $app->request->post('name');
			$surname = $app->request->post('surname');
			$patr = $app->request->post('patr');
			$email = $app->request->post('email');
			
            $phone = $app->request->post('phone');
			$code = $app->request->post('code');
            
			$password = $app->request->post('password');
			
            // validating phone address
            validatePhone($phone);
			
			//Validating email
			if(!empty($email)){
				validateEmail($email);
			}
			
			$db = new DbHandlerProfile();
			
			if( !$db->checkVerificationCode($phone,$code) ) {
				$response["error"] = true;
				$response["checkVerificationCode"] = true;
				$response["message"] = 'Verification code is not valid';
				echoResponse(200, $response);
				$app->stop();
			}
			
			
			$info=array();
			$info["name"]=ucfirst($name);
			$info["surname"]=ucfirst($surname);
			$info["patr"]=ucfirst($patr);
			$info["email"]=$email;
			
            $res = $db->createUserByPhone($name, $phone, $email, $password,$info);
			
            if ($res == USER_CREATED_SUCCESSFULLY) {
            	$response["success"] = 1;
                $response["error"] = false;
                $response["message"] = "You are successfully registered";
                echoResponse(200, $response);
            } else if ($res == USER_CREATE_FAILED) {
            	$response["success"] = 0;
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
                echoResponse(200, $response);
            } else if ($res == USER_ALREADY_EXISTED) {
            	$response["success"] = 0;
                $response["error"] = true;
				$response["phoneAlreadyUsed"] = true;
                $response["message"] = "Sorry, this phone already exists";
                echoResponse(200, $response);
            }
        });
		
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('phone', 'password'));
 
            // reading post params
            $phone = $app->request()->post('phone');
            $password = $app->request()->post('password');
            $response = array();
			
            $db = new DbHandlerProfile();
            // check for correct phone and password
            if ($db->checkLoginByPhone($phone, $password)) {
                // get the user by phone
                $user = $db->getUserByPhone($phone);
				
				
				if (isset($user)) {
                    $response["error"] = false;
                    $response['apiKey'] = $user['api_key'];
                    $response['user_id'] = $user['id'];
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials';
            }
 
            echoResponse(200, $response);
        });
		
/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
 
    // Verifying 'Api-Key' Header
    if (isset($headers['Api-Key'])) {
        $db = new DbHandlerProfile();
 
        // get the api key
        $api_key = $headers['Api-Key'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            $response["success"] = 0;
            echoResponse(200, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user = $db->getUserId($api_key);
            if ($user != NULL)
                $user_id = $user["id"];
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is missing";
        $response["success"] = 0;
        echoResponse(200, $response);
        $app->stop();
    }
}

$app->post('/testapikey', 'authenticate', function() {
    $response=array();
    $response["error"] = false;
    $response["message"] = "Api key is actual";
    $response["success"] = 1;
    echoResponse(200, $response);
});

//----------------Users-----------------------------------------

/**
 * Listing all users
 * method GET
 * url /users/all         
 */
$app->get('/users/all', 'authenticate', function() {
           
		global $user_id;
		
		$db = new DbHandlerProfile();
		
		// listing all users            
		$result = $db->getAllFriends($user_id);
 	    
 	    $response = array();
		$response["success"] = 1;
		$response["error"] = false;
		$response["users"]=$result;
		
        echoResponse(200, $response);
});

/**
 * Get user by id
 * method GET
 * url /users/:id         
 */
$app->get('/users/me', 'authenticate', function() {
           
            
		$db = new DbHandlerProfile();
		global $user_id;
		
		$response = array();
		
		$result = $db->getUserById($user_id);
 	    
 	    if($result==NULL){
 	    	$response["success"] = 0;
            $response["error"] = true;
			$response["message"] = "GetUser is NULL";
 	    }else{
			$response["success"] = 1;
			$response["error"] = false;
			
			$users=array();
			$users[]=$result;
			
			$response["users"]=$users;
        }
		
        echoResponse(200, $response);
});

/**
 * Save user's params
 * method POST
 * url /users         
 */
$app->post('/users', 'authenticate', function() use ($app) {
           
		    verifyRequiredParams(array('verifiedPassword'));
		   
            $db = new DbHandlerProfile();
            
            global $user_id;
            
            $user = $db->getUserById($user_id);
            
            // reading post params
            $name = $app->request->post('name');
			$surname = $app->request->post('surname');
			$patr = $app->request->post('patr');
			$email = $app->request->post('email');
			
			$info=$user["info"];
			if(isset($name))$info["name"]=$name;
			if(isset($surname))$info["surname"]=$surname;
			if(isset($patr))$info["patr"]=$patr;
			if(isset($email))$info["email"]=$email;
			
			$verifiedPassword = $app->request->post('verifiedPassword');
            
			if($db->checkVerifiedPassword($user_id,$verifiedPassword)){
				$response = $db->updateUserRegdata($user_id,$user["name"],$info);
				
				try{
					//Console command to notify users
					$json_header=array();
					$json_header["console"]="v2/index/users";
					$json_header["operation"]=M_CONSOLE_OPERATION_USER_CHANGED;
					$json_header["userid"]=$user_id;		
					consoleCommand($json_header);
				}catch(Exception $e){
					$response["consoleError"]=true;
					$response["consoleErrorMessage"]=$e->getMessage();
				}
				
				echoResponse(200, $response);
			}else{
				$response["success"]=0;
                $response["error"]=true;
				$response["verifiedPasswordNotCorrect"]=true;
                $response["message"]="Verified password is not correct";
				echoResponse(200, $response);
			}
			
			
});

/**
 * Change user password
 * method POST
 * url /change_password         
 */
$app->post('/change_password', 'authenticate', function() use ($app) {
           
		    verifyRequiredParams(array('password', 'verifiedPassword'));
		   
            $db = new DbHandlerProfile();
            
            global $user_id;
            
            $user = $db->getUserById($user_id);
            
            // reading post params
            $password = $app->request->post('password');			
			$verifiedPassword = $app->request->post('verifiedPassword');
            
			if($db->checkVerifiedPassword($user_id,$verifiedPassword)){				
				$response = $db->changeUserPassword($user_id,$password);
				echoResponse(200, $response);				
			}else{
				$response["success"]=0;
                $response["error"]=true;
				$response["verifiedPasswordNotCorrect"]=true;
                $response["message"]="Verified password is not correct";
				echoResponse(200, $response);
			}
			
});

//-----------------Photo Uploading--------------------------

function createThumb($image,$size,$path){	

        $image->thumbnail($size, $size);
        
        $format= $image->get_original_info()['format'];         
        $uniqid=uniqid();
        
        $filename=$uniqid. '.'. $format;
               
        if($image->save($path.$filename)){
        	return $filename;        
        }else{
        	return new Exception("Can not writeImage to ".$filename);
        }
}

/**
 * Upload new user's avatar
 * method POST
 * url - /avatars/upload
 */
$app->post('/avatars/upload', 'authenticate', function() use ($app) {
	
	
 
	// array for final json respone
	$response = array();
  	
  	try{
		
		
  		// Check if the file is missing
		if (!isset($_FILES['image']['name'])) {
			throw new Exception('Not received any file!F');
		}
		
		if($_FILES['image']['size'] > 2*1024*1024) { 
			throw new Exception('File is too big');
		}
			
	    $tmpFile = $_FILES["image"]["tmp_name"];
	    	
	    // Check if the file is really an image
	    list($width, $height) = getimagesize($tmpFile);    		
    	if ($width == null && $height == null) {
    		throw new Exception('File is not image!F');
    	}
 	
 		$image = new abeautifulsite\SimpleImage($tmpFile);
	  	
	  	$value_full=createThumb($image,size_full,$_SERVER['DOCUMENT_ROOT'].path_fulls);
	  	$value_avatar=createThumb($image,size_avatar,$_SERVER['DOCUMENT_ROOT'].path_avatars);
	  	$value_icon=createThumb($image,size_icon,$_SERVER['DOCUMENT_ROOT'].path_icons);
	  	
	  	global $user_id;
	  	$db = new DbHandlerProfile();
	  	if(!$db->createUserAvatar($user_id,$value_full,$value_avatar,$value_icon)){
	  		unlink($_SERVER['DOCUMENT_ROOT'].path_fulls.$value_full);
	  		unlink($_SERVER['DOCUMENT_ROOT'].path_avatars.$value_avatar);
	  		unlink($_SERVER['DOCUMENT_ROOT'].path_icons.$value_icon);
	  		throw new Exception('Failed to insert to DB');
	  	}	  	
 	     
	    $response['message'] = 'File uploaded successfully!';
	    $response['error'] = false;
	    $response['success'] = 1;
			
		//Console command to notify users
		$json_header=array();
		$json_header["console"]="v2/index/avatars/upload";
		$json_header["operation"]=M_CONSOLE_OPERATION_USER_CHANGED;
		$json_header["userid"]=$user_id;		
		$console_response=consoleCommand($json_header);
		
		$response['consoleCommand'] = $console_response["message"];
			
	} catch (Exception $e) {
		// Exception occurred. Make error flag true
	        $response['error'] = true;
	        $response['message'] = $e->getMessage();
	     	$response['success'] = 0;
	}
	
	echoResponse(200,$response);
   	      	
});

/**
 * Upload group panorama photo
 * method POST
 * url - /group_panorama/upload
 */
$app->post('/group_panorama/upload/:group_id', 'authenticate', function($group_id) use ($app) {
	
	global $user_id;
	$db = new DbHandlerProfile();
	
	// array for final json respone
	$response = array();
  	
  	try{
		//Checkin user permission to this operation
		$user_status_in_group=$db->getUserStatusInGroup($group_id,$user_id);		
		if( ($user_status_in_group!=0)&&($user_status_in_group!=1)&&($user_status_in_group!=2) ) {
			//User doesn't consist in group
			throw new Exception('No permission. User does not consist in group');
		} if( ($user_status_in_group!=1)&&($user_status_in_group!=2) ) {
			//User is Not Super-admin and not Admin
			throw new Exception('No permission. Only admin can change group-profile photo');
		}
	
  		// Check if the file is missing
		if (!isset($_FILES['image']['name'])) {
			throw new Exception('Not received any file!F');
		}
		
		if($_FILES['image']['size'] > 2*1024*1024) { 
			throw new Exception('File is too big');
		}
			
	    $tmpFile = $_FILES["image"]["tmp_name"];
	    	
	    // Check if the file is really an image
	    list($width, $height) = getimagesize($tmpFile);    		
    	if ($width == null && $height == null) {
    		throw new Exception('File is not image!F');
    	}
 	
 		$image = new abeautifulsite\SimpleImage($tmpFile);
	  	
	  	$value_full=createThumb($image,size_full,$_SERVER['DOCUMENT_ROOT'].path_fulls);
	  	$value_avatar=createThumb($image,size_avatar,$_SERVER['DOCUMENT_ROOT'].path_avatars);
	  	$value_icon=createThumb($image,size_icon,$_SERVER['DOCUMENT_ROOT'].path_icons);
	  	
	  	
	  	
	  	if(!$db->createGroupAvatar($group_id,$value_full,$value_avatar,$value_icon)){
	  		unlink($_SERVER['DOCUMENT_ROOT'].path_fulls.$value_full);
	  		unlink($_SERVER['DOCUMENT_ROOT'].path_avatars.$value_avatar);
	  		unlink($_SERVER['DOCUMENT_ROOT'].path_icons.$value_icon);
	  		throw new Exception('Failed to insert to DB');
	  	}	  	
 	     
	    $response['message'] = 'File uploaded successfully!';
	    $response['error'] = false;
	    $response['success'] = 1;
			
		
		//Console command to notify users
		$json_header=array();
		$json_header["console"]="v2/index/group_panorama/upload/".$group_id;
		$json_header["operation"]=M_CONSOLE_OPERATION_GROUP;
		$json_header["group_operationid"]=M_GROUPOPERATION_SAVE;
		$json_header["groupid"]=$group_id;
		$json_header["senderid"]=$user_id;
		$json_header["json"]='{dummy:"Dummy"}';
		$console_response=consoleCommand($json_header);
		
		$response['consoleCommand'] = $console_response["message"];
			
	} catch (Exception $e) {
		// Exception occurred. Make error flag true
	        $response['error'] = true;
	        $response['message'] = $e->getMessage();
	     	$response['success'] = 0;
	}
	
	echoResponse(200,$response);
   	      	
});

//------------------Group-------------------------------

//------------------Customer-------------------------------

$app->post('/create_customer', 'authenticate', function () use ($app)  {
	
	verifyRequiredParams(array('name','address'));
	 
	$response = array();
	
	try{
	
		global $user_id;	
		$db = new DbHandlerProfile();
		
		$name = $app->request->post('name');
		$address = $app->request->post('address');
		
		$groupid=$db->createCustomer($user_id,$name,$address);	
		
		//Console command notify group
		try{
			$json_header=array();
			$json_header["console"]="v2/create_customer";
			$json_header["operation"]=M_CONSOLE_OPERATION_GROUP_CHANGED;
			$json_header["groupid"]=$groupid;			
			
			$console_response=consoleCommand($json_header);			
		}catch(Exception $e){
			$response['consoleError']=true;
			$response['consoleErrorMessage'] = "Error:".$e->getMessage();
		}		
		
		$response['error'] = false;
		$response['message'] = "Customer group created";
		$response['groupid'] = $groupid;
		$response['success'] = 1;
		
	} catch (Exception $e) {
	
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}
	
	echoResponse(200, $response);

});

//-----------------Search-----------------------------------

/**
 * Search user by value
 * method POST
 * url - /search_user
 * param - string value
 * return - userid found user's id
 */
$app->post('/search_contact', 'authenticate', function () use ($app)  {
	
	// check for required params
    verifyRequiredParams(array('phone'));
	
	$response = array();
	
	try{	
			$db = new DbHandlerProfile();
	        global $user_id;	
			
			// reading post params
			$phone = $app->request->post('phone');
	        			
			$found_user=$db->getUserByPhone($phone);
			
			if(!isset($found_user))throw new Exception("No user found");
			
			$response['userid'] = $found_user["id"];
	        $response['error'] = false;
	        $response['success'] = 1;
			$response['users'] = array();
			$response['users'][] = $db->getUserById($found_user["id"]);
        
		} catch (Exception $e) {
        
	        $response['error'] = true;
	        $response['message'] = $e->getMessage();
	     	$response['success'] = 0;
	}
	
	echoResponse(200, $response);

});	

//-------------Orders-----------------------

//Временная url для теста get all oreders of contractor
$app->get('/orders/:contractorid', function ($contractorid){
	// check for required params
	
	$response = array();
	
	try{	
		$db_profile = new DbHandlerFabricant();
		$response=$db_profile->getAllOrdersOfContractorWeb($contractorid);
		
		
	} catch (Exception $e) {        
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}
	
	echoResponse(200, $response);
});

$app->post('/orders/create', 'authenticate', function () use ($app)  {
	
	// check for required params
    verifyRequiredParams(array('order'));
	
	
	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	global $user_id;	
		
	$response = array();
	
	try{	
		
		$json_order = $app->request->post('order');		
		$order=json_decode($json_order,true);
		
		$record=makeOrderRecord($order);
		
		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/create";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_CREATE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);
		
		$response['consoleCommand_create_order'] = $console_response["message"];
		
		$response['error'] = false;
		$response['message'] = "Order has been created";
		$response['order'] = $db_fabricant->getOrderById($console_response["orderid"]);
		$response['success'] = 1;
		$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
        
	} catch (Exception $e) {
        
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
	}
	
	echoResponse(200, $response);

});

$app->post('/orders/update', 'authenticate', function () use ($app)  {
	
	// check for required params
    verifyRequiredParams(array('order','orderid'));
	
	
	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	global $user_id;	
		
	$response = array();
	
	try{
		
		$json_order = $app->request->post('order');		
		$order=json_decode($json_order,true);
		
		$old_order_id = $app->request->post('orderid');
		$old_order=$db_fabricant->getOrderById($old_order_id);
		$old_order_record=json_decode($old_order["record"],JSON_UNESCAPED_UNICODE);
		
		checkUserPermissionToOrder($old_order_id,$order);		
		$record=makeOrderRecord($order);
		$record["id"]=$old_order_id;
		$record["created_at"]=$old_order_record["created_at"];		
		$record["updated"]=true;
		
		//Transfer
		if($record["customerid"]!=$old_order_record["customerid"]){
			$date_string=date('Y-m-d H:i:s',time());
				
			$old_order_record["transferred"]=true;
			$old_order_record["transferred_at"]=$date_string;
			
			//Console command
			$json_header=array();
			$json_header["console"]="v2/index/orders/transfer";
			$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
			$json_header["order_operationid"]=M_ORDEROPERATION_TRANSFER;
			$json_header["senderid"]=$user_id;
			$json_header["record"]=json_encode($old_order_record,JSON_UNESCAPED_UNICODE);

			$transfer_response=consoleCommand($json_header);
		}
		
		
		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/update";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_UPDATE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);	
		
		$update_response=consoleCommand($json_header);
		
		if(isset($transfer_response))
			$response['consoleCommand_transfer_order'] = $transfer_response["message"];
			
		$response['consoleCommand_update_order'] = $update_response["message"];		
		$response['error'] = false;
		$response['message'] = "Order has been updated";
		$response['order'] = $db_fabricant->getOrderById($old_order_id);
		$response['success'] = 1;
		//$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
        
	} catch (Exception $e) {
        
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		//$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
	}
	
	echoResponse(200, $response);

});

$app->post('/orders/accept', 'authenticate', function () use ($app)  {
	
	// check for required params
    verifyRequiredParams(array('orderid'));
	
	
	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	global $user_id;	
		
	$response = array();
	
	try{
		
		$orderid = $app->request->post('orderid');
		
		$order=$db_fabricant->getOrderById($orderid);
		
		$user_status_in_contractor=$db_profile->getUserStatusInGroup($order["contractorid"],$user_id);
		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
			throw new Exception('User have no permission');
		}
		
		if($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING){
			throw new Exception('Order status is not correct for accept operation');
		}
		
		$record=json_decode($order["record"],true);				
		$user=$db_profile->getUserById($user_id);
			
		$record["acceptUserId"]=$user_id;
		$record["acceptUserName"]=$user["name"];
		$record["accepted"]=true;
		
		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/accept";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_ACCEPT;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);
		
		$response['consoleCommand_accept_order'] = $console_response["message"];
		
		$response['error'] = false;
		$response['message'] = "Order has been accepted";
		$response['order'] = $db_fabricant->getOrderById($orderid);
		$response['success'] = 1;
        
	} catch (Exception $e) {
        
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}
	
	echoResponse(200, $response);

});

$app->post('/orders/remove', 'authenticate', function () use ($app)  {
	
	// check for required params
    verifyRequiredParams(array('orderid','comment'));
	
	
	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	global $user_id;	
		
	$response = array();
	
	try{
		
		$orderid = $app->request->post('orderid');
		$comment = $app->request->post('comment');
		
		$order=$db_fabricant->getOrderById($orderid);
		
		$user_status_in_contractor=$db_profile->getUserStatusInGroup($order["contractorid"],$user_id);
		
		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
			$user_status_in_customer=$db_profile->getUserStatusInGroup($order["customerid"],$user_id);
			if(($user_status_in_customer!=1)&&($user_status_in_customer!=2)){
				throw new Exception('User have no permission');
			}else{
				if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING) ){
					throw new Exception('Order status is not correct for remove operation');
				}
			}
		}else{		
			if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CONFIRMED)&&($order["status"]!=$db_fabricant::STATUS_ORDER_ONWAY) ){
				throw new Exception('Order status is not correct for remove operation');
			}
		}
		
		$record=json_decode($order["record"],true);				
		$user=$db_profile->getUserById($user_id);
		
		$record["removeUserId"]=$user_id;
		$record["removeUserName"]=$user["name"];
		$record["removeComment"]=$comment;
		$record["removed"]=true;
		
		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/remove";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_REMOVE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);
		
		$response['consoleCommand_remove_order'] = $console_response["message"];
		
		$response['error'] = false;
		$response['message'] = "Order has been removed";
		$response['order'] = $db_fabricant->getOrderById($orderid);
		$response['success'] = 1;
        
	} catch (Exception $e) {
        
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}
	
	echoResponse(200, $response);

});

$app->post('/orders/make_paid', 'authenticate', function () use ($app)  {
	
	// check for required params
    verifyRequiredParams(array('orderid'));
	
	
	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	global $user_id;	
		
	$response = array();
	
	try{
		
		$orderid = $app->request->post('orderid');
		
		$order=$db_fabricant->getOrderById($orderid);
		
		$user_status_in_contractor=$db_profile->getUserStatusInGroup($order["contractorid"],$user_id);
		
		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){					
			throw new Exception('User have no permission');			
		}
		
		if( ($order["status"]!=$db_fabricant::STATUS_ORDER_CONFIRMED)&&($order["status"]!=$db_fabricant::STATUS_ORDER_ONWAY)&&($order["status"]!=$db_fabricant::STATUS_ORDER_DELIVERED) ){
			throw new Exception('Order status is not correct for remove operation');
		}
		
		$record=json_decode($order["record"],true);
				
		$user=$db_profile->getUserById($user_id);
		
		$record["paidUserId"]=$user_id;
		$record["paidUserName"]=$user["name"];	
		$record["paid"]=true;
		
		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/remove";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_MAKE_PAID;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);
		
		$response['consoleCommand_make_order_paid'] = $console_response["message"];
		
		$response['error'] = false;
		$response['message'] = "Order has been paid";
		$response['order'] = $db_fabricant->getOrderById($orderid);
		$response['success'] = 1;
        
	} catch (Exception $e) {
        
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}
	
	echoResponse(200, $response);

});

//-------------Orders Utils------------------------

function makeOrderRecord($order){

		$logs=array();
	
		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();
		
		global $user_id;	
		
		//Get and check contractor
		$contractorid=$order["contractorid"];			
		$contractor=$db_profile->getGroupById($contractorid)[0];			
		if($contractor["type"]!=0){
			throw new Exception('Contractor type is incorrect');
		}
		if($contractor["status"]!=1){
			throw new Exception('Contractor status is incorrect');
		}
		
		//Get and check customer
		$customerid=$order["customerid"];
		$customer=$db_profile->getGroupById($customerid)[0];			
		if($customer["type"]!=1){
			throw new Exception('Customer type is incorrect');
		}
		if($customer["status"]!=1){
			throw new Exception('Customer status is incorrect');
		}
		
		//Check is user in contractor group. Contractor can create order to any customer
		$user_status_in_contractor=$db_profile->getUserStatusInGroup($contractorid,$user_id);
		if( ($user_status_in_contractor!=0)&&($user_status_in_contractor!=1)&&($user_status_in_contractor!=2) ){
			//Check is user in customer group
			$user_status_in_customer=$db_profile->getUserStatusInGroup($customerid,$user_id);
			if( ($user_status_in_customer!=0)&&($user_status_in_customer!=1)&&($user_status_in_customer!=2) ){
				
				throw new Exception('User have no permission to create order');
			}		
		}	
		
		
		//Сustomer User
		$customer_user=$db_profile->getUserById($user_id);
		
		//Installment	
		$logs[]="Installment";		
		$installment=getInstallment($order,$contractor);		
		$price_installment_name=null;
		if( ($installment!=null) && (groupHasTag($installment["tag_customer"],$customer)) ){
			$price_installment_name=$installment["price_name"];
		}
		
		$logs[]="price_installment_name=".$price_installment_name;
		
		//Basket	
		$logs[]="Basket";		
		$items=$order["items"];
		if(count($items)<1){
			throw new Exception('items are not found');
		}
		$basket=array();
		$basket_price=0;
		foreach($items as $productid=>$count){
				$logs[]="productid=".$productid." count=".$count;
				$product=$db_fabricant->getProductById($productid);
				
				if(!isset($product))continue;//Product id not found
				if($product["contractorid"]!=$contractorid)continue;//Another contractor product
				if($product["status"]!=2)continue;//Product status incorrect					
				if($count==0)continue;//Don't add empty products
				
				if(isset($price_installment_name)){
					$item["price"]=getProductPriceInstallmentValue($price_installment_name,$product);					
					if(!isset($item["price"]))continue;					
				}else{
					$item["price"]=$product["price"];
				}
				
				$item["productid"]=$productid;
				$item["name"]=$product["name"];
				$item["count"]=$count;
				$item["amount"]=$item["price"]*$count;
				$logs[]="amount=".$item["amount"];
				
				$sale=getProductSale($product,$contractor,$customer);
				if(isset($sale)){
					$item["sale"]=$sale;	
					
					$save_value=$product["price"]*$product["count"]*$salerate["rate"];	
					
					$item["amount"]-=$save_value;
					$logs[]="sale=".json_encode($sale,JSON_UNESCAPED_UNICODE);
				}
				
				$basket_price+=$item["amount"];
				
				$basket[]=$item;				
		}		
		
		//Costs		
		$logs[]="Costs";
		$costs=array();
		$costs["itemsAmount"]=$basket_price;
		$total_cost=$basket_price;
		
		//Discount
		$logs[]="Discount";
		$discount=getDiscount($basket_price,$contractor,$customer);		
		if(isset($discount)){
			$costs["discount"]=$discount;
			$total_cost+=$discount["value"];
		}
		
		
		//Record of order
		$logs[]="Record of order";		
		$record=array();
		$record["phone"]=$order["phone"];
		$record["address"]=$customer["address"];		
		$record["contractorid"]=$contractorid;
		$record["contractorName"]=$contractor["name"];		
		$record["customerid"]=$customerid;	
		$record["customerName"]=$customer["name"];	
		$record["customerUserId"]=$user_id;
		$record["customerUserName"]=$customer_user["name"];
		$record["customerUserPhone"]=$customer_user["phone"];
		
		$record["items"]=$basket;
		$record["costs"]=$costs;	
		$record["totalCost"]=$total_cost;
		
		//Installment
		if(isset($installment["id"])){	
			$record["installment_time_notification"]=$installment["time_notification"];
			$record["installmentid"]=$installment["id"];
			$record["installmentName"]=$installment["name_full"];
		}
		
		//Comment
		if(isset($order["comment"])){		
			$record["comment"]=$order["comment"];
		}
		
		$record["make_record_logs"]=$logs;
		
		return $record;
}

function checkUserPermissionToOrder($old_order_id,$new_order){

		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();
		
		global $user_id;	
		
		$order=$db_fabricant->getOrderById($old_order_id);
		
		if(!isset($order)){			
			throw new Exception('orderid  is not correct');
		}
		
		$contractorid=$order["contractorid"];
		$customerid=$order["customerid"];
		
		$user_status_in_contractor=$db_profile->getUserStatusInGroup($contractorid,$user_id);
		
		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
		
			$user_status_in_customer=$db_profile->getUserStatusInGroup($customerid,$user_id);
			
			if(($user_status_in_customer!=1)&&($user_status_in_customer!=2)){
				throw new Exception('User have no permission');
			}else{
				if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING) ){
					throw new Exception('Order status is not correct for update operation');
				}
			}
			
		}else{		
			if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CONFIRMED)&&($order["status"]!=$db_fabricant::STATUS_ORDER_ONWAY) ){
				throw new Exception('Order status is not correct for remove operation');
			}
		}
		
		if($new_order["contractorid"]!=$contractorid){
			throw new Exception('contractorid can not be changed');
		}
		
}

function getProductPriceInstallmentValue($price_name,$product){
	
	if(!isset($price_name)){
		return null;
	}
	
	if(!isset($product["info"])){
		return null;
	}
	
	$info=json_decode($product["info"],true);
	
	if(!isset($info)){
		return null;
	}
	
	if(!isset($info["prices"])){
		return null;
	}
	
	$prices=$info["prices"];
	
	
	for($i=0;$i<count($prices);$i++){
		$price=$prices[$i];
		
		if( isset($price["name"]) && isset($price["value"]) && ($price["name"]==$price_name) ){
			return $price["value"];
		}
		
	}
		
	return null;
}

function getInstallment($order,$contractor){
	
	if(!isset($order["installmentid"]))
		return null;
	
	if(!isset($contractor["info"]))
		return null;
		
	$contractor_info=json_decode($contractor["info"],true);
	
	if(!isset($contractor_info["sales"]))
		return null;
		
	$sales=$contractor_info["sales"];
	
	foreach($sales as $sale){
		if( ($sale["type"]==6) && ($sale["id"]==$order["installmentid"]) ){
			return $sale;
		}
	}
	
	return null;
}

function groupHasTag($tag,$group){
	
	
	if(!isset($group["info"]))
		return false;
		
	$group_info=json_decode($group["info"],true);
	
	if(!isset($group_info["tags"]))
		return false;
		
	return in_array($tag, $group_info["tags"]);
}

function productHasTag($tag,$product){
	
	
	if(!isset($product["info"]))
		return false;
		
	$product_info=json_decode($product["info"],true);
	
	if(!isset($product_info["tags"]))
		return false;
		
	return in_array($tag, $product_info["tags"]);
}

function getProductSale($product,$contractor,$customer){
	
	
	if(!isset($contractor["info"]))
		return null;
		
	$contractor_info=json_decode($contractor["info"],true);
	
	if(!isset($contractor_info["sales"]))
		return null;
	
	
	$sales=$contractor_info["sales"];
	
	$salerate=null;
		
	foreach($sales as $sale){
		if( ($sale["type"]==4) && isset($sale["tag_customer"]) && groupHasTag($sale["tag_customer"],$customer) && productHasTag($sale["tag_product"],$product) ){
			
			if( ($salerate==null)||($sale["rate"]<$salerate["rate"]) ){
				$salerate=$sale;				
			}
		}
	}
	
	return $salerate;	
}

function getDiscount($basket_price,$contractor,$customer){
	
	
	if(!isset($contractor["info"]))
		return null;
		
	$contractor_info=json_decode($contractor["info"],true);
	
	if(!isset($contractor_info["sales"]))
		return null;
	
	
	$sales=$contractor_info["sales"];
	
	$discount=null;
		
	foreach($sales as $sale){
		if( ($sale["type"]==5) && groupHasTag($sale["tag_customer"],$customer) && ($basket_price>=$sale["min_summ"]) && ($basket_price<$sale["max_summ"])  ){
			
			
			
			if( ($discount==null)||($sale["rate"]<$discount["rate"]) ){	
				$sale["basket_price"]=$basket_price;
				$sale["value"]=ceil($basket_price*($sale["rate"]-1.0));			
				$discount=$sale;		
			}				
		}
	}
	
	return $discount;
	
}

//--------------Sales-----------------------

$app->post('/sales/create', 'authenticate', function () use ($app)  {
	
	try{
	
		global $user_id;	
		
		$response = array();
		
		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/create";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_CREATE;
		$json_header["senderid"]=$user_id;
		
		//Required params
		$json_header["contractorid"] = $app->request->post('contractorid');
		$json_header["type"] = $app->request->post('type');
		$json_header["label"] = $app->request->post('label');
		$json_header["name"] = $app->request->post('name');
		$json_header["name_full"] = $app->request->post('name_full');
		$json_header["summary"] = $app->request->post('summary');
		
		if(!isset($json_header["type"])){
			throw new Exception("Sale create error. type is not found");
		}
		
		//Params of Installment sale		
		switch($json_header["type"]){
			case M_SALE_TYPE_SALE:
			
				$json_header["rate"] = $app->request->post('rate');				
				if(!isset($json_header["rate"])){
					throw new Exception("Sale create error. rate is not found");
				}
				
				break;
				
			case M_SALE_TYPE_DISCOUNT:
			
				$json_header["rate"] = $app->request->post('rate');				
				if(!isset($json_header["rate"])){
					throw new Exception("Sale create error. rate is not found");
				}
				$json_header["min_summ"] = $app->request->post('min_summ');				
				if(!isset($json_header["min_summ"])){
					throw new Exception("Sale create error. min_summ is not found");
				}
				$json_header["max_summ"] = $app->request->post('max_summ');				
				if(!isset($json_header["max_summ"])){
					throw new Exception("Sale create error. max_summ is not found");
				}
				
				break;
				
			case M_SALE_TYPE_INSTALLMENT:
				
				$json_header["time_notification"] = $app->request->post('time_notification');				
				if(!isset($json_header["time_notification"])){
					throw new Exception("Sale create error. time_notification is not found");
				}
				
				break;
		}
		
		$console_response=consoleCommand($json_header);
		
		//Respone of console
		$response['error'] = $console_response["error"];
		$response['message'] = $console_response["message"];
		$response['success'] = $console_response["success"];
				
		echoResponse($console_response["status"], $response);
		
	} catch (Exception $e) {
	
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		
		echoResponse(500, $response);		
	}
	

});	

$app->post('/sales/remove', 'authenticate', function () use ($app)  {
	
	try{
	
		global $user_id;	
		
		$response = array();
		
		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/remove";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_REMOVE;
		$json_header["senderid"]=$user_id;
		
		//Required params
		$json_header["saleid"] = $app->request->post('saleid');
		
		if(!isset($json_header["saleid"])){
			throw new Exception("Sale remove error. saleid is not found");
		}
				
		$console_response=consoleCommand($json_header);
		
		//Respone of console
		$response['error'] = $console_response["error"];
		$response['message'] = $console_response["message"];
		$response['success'] = $console_response["success"];
				
		echoResponse($console_response["status"], $response);
		
	} catch (Exception $e) {
	
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		
		echoResponse(500, $response);		
	}
	

});	

$app->post('/sales/update', 'authenticate', function () use ($app)  {
	
	try{
	
		global $user_id;	
		
		$db_fabricant = new DbHandlerFabricant();
		
		$response = array();
		
		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/update";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_UPDATE;
		$json_header["senderid"]=$user_id;
		
		//Required params
		$json_header["saleid"] = $app->request->post('saleid');
		$json_header["label"] = $app->request->post('label');
		$json_header["name"] = $app->request->post('name');
		$json_header["name_full"] = $app->request->post('name_full');
		$json_header["summary"] = $app->request->post('summary');
		
		if(!isset($json_header["saleid"])){
			throw new Exception("Sale update error. saleid param missing");
		}
		
		$sale=$db_fabricant->getSaleById($json_header["saleid"]);
		
		if(!isset($sale)){
			throw new Exception("Sale update error. saleid is not found");
		}
		
		$condition=json_decode($sale["condition"],true);
		
		$json_header["type"]=$condition["type"];
		
		//Params specific sale		
		switch($json_header["type"]){
			case M_SALE_TYPE_SALE:		
			
				$json_header["rate"] = $app->request->post('rate');				
				if(!isset($json_header["rate"])){
					throw new Exception("Sale update error. rate is not found");
				}		
	
				break;
				
			case M_SALE_TYPE_DISCOUNT:		
			
				$json_header["rate"] = $app->request->post('rate');				
				if(!isset($json_header["rate"])){
					throw new Exception("Sale update error. rate is not found");
				}		

				$json_header["min_summ"] = $app->request->post('min_summ');				
				if(!isset($json_header["min_summ"])){
					throw new Exception("Sale update error. min_summ is not found");
				}	
				
				$json_header["max_summ"] = $app->request->post('max_summ');				
				if(!isset($json_header["max_summ"])){
					throw new Exception("Sale update error. max_summ is not found");
				}					
								
				break;
				
			case M_SALE_TYPE_INSTALLMENT:	
			
				$json_header["time_notification"] = $app->request->post('time_notification');				
				if(!isset($json_header["time_notification"])){
					throw new Exception("Sale update error. time_notification is not found");
				}		
				
				break;
		}
		
		$console_response=consoleCommand($json_header);
		
		//Respone of console
		$response['error'] = $console_response["error"];
		$response['message'] = $console_response["message"];
		$response['success'] = $console_response["success"];
				
		echoResponse($console_response["status"], $response);
		
	} catch (Exception $e) {
	
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		
		echoResponse(500, $response);		
	}
	

});	

$app->post('/sales/add_to_customer', 'authenticate', function () use ($app)  {
	
	$response = array();
	
	try{
		// check for required params
		verifyRequiredParams(array('saleid','customerid'));
	
		global $user_id;	
		
		
		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/add_to_customer";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_ADD_TO_CUSTOMER;
		$json_header["senderid"]=$user_id;
		
		//Required params
		$json_header["saleid"] = $app->request->post('saleid');
		$json_header["customerid"] = $app->request->post('customerid');
		
		$console_response=consoleCommand($json_header);
		
		//Respone of console
		$response['error'] = $console_response["error"];
		$response['message'] = $console_response["message"];
		$response['success'] = $console_response["success"];
				
		echoResponse($console_response["status"], $response);
		
	} catch (Exception $e) {
	
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		
		echoResponse(500, $response);		
	}
});

$app->post('/sales/remove_from_customer', 'authenticate', function () use ($app)  {
	try{
		// check for required params
		verifyRequiredParams(array('saleid','customerid'));
	
		global $user_id;	
		
		$response = array();
		
		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/remove_from_customer";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_REMOVE_FROM_CUSTOMER;
		$json_header["senderid"]=$user_id;
		
		//Required params
		$json_header["saleid"] = $app->request->post('saleid');
		$json_header["customerid"] = $app->request->post('customerid');
		
		$console_response=consoleCommand($json_header);
		
		//Respone of console
		$response['error'] = $console_response["error"];
		$response['message'] = $console_response["message"];
		$response['success'] = $console_response["success"];
				
		echoResponse($console_response["status"], $response);
		
	} catch (Exception $e) {
	
		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		
		echoResponse(500, $response);		
	}
});

//-----------------Console command------------------

//Operation numbers from WebsocketServer
define("M_CONSOLE_OPERATION_USER_CHANGED", 0);
define("M_CONSOLE_OPERATION_GROUP", 1);
define("M_CONSOLE_OPERATION_CHECK_SERVER", 2);
define("M_CONSOLE_OPERATION_ORDER", 3);
define("M_CONSOLE_OPERATION_SALE", 4);
define("M_CONSOLE_OPERATION_GROUP_CHANGED", 5);
define("M_CONSOLE_OPERATION_PRODUCT_CHANGED", 6);

define("M_GROUPOPERATION_ADD_USERS", 0);
define("M_GROUPOPERATION_SAVE", 1);
define("M_GROUPOPERATION_CREATE", 2);

define("M_ORDEROPERATION_CREATE", 0);
define("M_ORDEROPERATION_UPDATE", 1);
define("M_ORDEROPERATION_ACCEPT", 2);
define("M_ORDEROPERATION_REMOVE", 3);
define("M_ORDEROPERATION_TRANSFER", 4);
define("M_ORDEROPERATION_MAKE_PAID", 5);

define("M_SALE_OPERATION_CREATE", 0);
define("M_SALE_OPERATION_REMOVE", 1);
define("M_SALE_OPERATION_UPDATE", 2);
define("M_SALE_OPERATION_ADD_TO_CUSTOMER", 3);
define("M_SALE_OPERATION_REMOVE_FROM_CUSTOMER", 4);

define("M_SALE_TYPE_SALE", 4);
define("M_SALE_TYPE_DISCOUNT", 5);
define("M_SALE_TYPE_INSTALLMENT", 6);


function consoleCommand($header_json){

	$client = new WebsocketClient;
	
	$response="{'message': 'ConsoleCommand. begin', 'status':'0'}";
	
	if($client->connect($header_json, '127.0.0.1', WEBSOCKET_SERVER_PORT,"/")){	
		
		$data = fread($client->_Socket, 1024);
		$message_array = $client->_hybi10Decode($data);//implode(",",);
		$response=$message_array["payload"];
	
	}else{
		$response="{'message':'ConsoleCommand. Connecting failed', 'status':'0'}";
	}
	
	$client->disconnect();
	
	$json=(array)json_decode($response);
	
	return $json;

}

//--------------------Restart Server----------------------------

/**
 * Auto-start the server from the Android client if server is fall
 * method POST
 * url - /communicator/start
 * return - status and error
 */
$app->get('/communicator/start', 'authenticate', function () use ($app)  {
	
	$db = new DbHandlerProfile();
	global $user_id;
	
	//Console command to notify users
	$json_header=array();
	$json_header["console"]="v2/communicator/start";
	$json_header["operation"]=M_CONSOLE_OPERATION_CHECK_SERVER;
	$json_header["userid"]=$user_id;		
	
	$console_response["status"]=0;
	$console_response["error"]=true;
	$console_response["message"]="ServerError. catch exception";
	
	try{
		$console_response=consoleCommand($json_header);
	}catch(Exception $e){
		
	}
	
	if($console_response["status"]==1){
		$console_response["error"]=false;		
		echoResponse(200, $console_response);
		return;
	}
	
	$config = array(
		'pid' => 'communicator/out/websocket_pid.txt',
		'websocket' => 'tcp://0.0.0.0:'.WEBSOCKET_SERVER_PORT,
		'log' => 'communicator/out/websocket_log.txt'
	);
	
	//Log
	$message="ServerRestart. user_id=".$user_id;	
	if($config['log']){
		file_put_contents($config['log'], "pid:".posix_getpid()." ".date("Y-m-d H:i:s")." ".$message."\n",FILE_APPEND); 
	}
	
	require dirname(__FILE__).'/communicator/WebsocketServer.php';
	
	$websocketserver = new WebsocketServer($config);	

	$websocketserver->Start();
	
});	

$app->run();

?>