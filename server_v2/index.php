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
$api_key = NULL;

/**
 * It used to Slim testing during installation the server
 */
//$app->get('/sms/:phone/:text', function ($phone,$text) {
//
//		$body=file_get_contents("http://sms.ru/sms/send?api_id=A73F3F48-2F27-8D8D-D7A2-6AFF64E4F744&to=".$phone."&from=fabricant&text=".$text);
//		echo $body;
//});

$app->get('/test', function () {

		//$db=new DbHandlerProfile();
		//$body=$db->getAllGroups();
		$body="Hello, from Fabricant's server!";
		echoResponse(200,$body);
});

$app->post('/test_post', function () use ($app) {

		$id = $app->request->post('id');
		$last_timestamp = $app->request->post('last_timestamp');

		$body="Hello, from Fabricant's server! ID=".($id+1)." last_timestamp=".$last_timestamp;
		echoResponse(200,$body);
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
			$email = "missing_email@fabricant.pro";//$app->request->post('email');

            $phone = $app->request->post('phone');
			$code = $app->request->post('code');

			$password = $app->request->post('password');

            // validating phone address
            validatePhone($phone);

			//Validating email
			if(!empty($email)){
				validateEmail($email);
			}else{
				$email="missing_email@fabricant.pro";
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
				sendSMS("UserRegistered".$phone);
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
				sendSMS("UserRegisteringFailPhoneUsed".$phone);
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

        // validating api key
        if (!$db->isValidApiKey($headers['Api-Key'])) {

			// api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            $response["success"] = 0;
            echoResponse(200, $response);
            $app->stop();
        } else {

			$user = $db->getUserId($headers['Api-Key']);

            if ($user != NULL){
				global $api_key;
				global $user_id;
                $user_id = $user["id"];

				$api_key = $db->getApiKeyById($user_id)["api_key"];
			}
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

//------------------Contractor-------------------------------

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
	sendSMS("Group".$groupid."CreatedSender".$user_id);

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

//-----------------Orders-----------------------

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

	$record=array();

	//try{

		$json_order = $app->request->post('order');
		$order=json_decode($json_order,true);

		if(!isset($order["contractorid"]) || !isset($order["customerid"])){
			throw new Exception("Missing contractorid or customerid in order");
		}

		checkUserPermissionToGroups($order["contractorid"],$order["customerid"]);

		$record=makeOrderRecord($order);
		
		//Customer user info to record
		$customer_user=$db_profile->getUserById($user_id);
		$record["customerUserId"]=$user_id;
		$record["customerUserName"]=$customer_user["name"];
		$record["customerUserPhone"]=$customer_user["phone"];

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


	/*} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
	}*/

	echoResponse(200, $response);

	//sendSMS("CreateOrderid".$response['order']['id']."customerid".$response['order']['customerid']);

});

$app->post('/orders/update', 'authenticate', function () use ($app)  {

	// check for required params
    verifyRequiredParams(array('order','orderid'));
	
	$old_order_id = $app->request->post('orderid');
	$json_order = json_decode($app->request->post('order'),true);
	
	global $user_id;
	
	$response=updateOrder($old_order_id,$json_order,$user_id);
	
	echoResponse(200, $response);
	if($response["success"]==1){
		//sendSMS("UpdateOrderid".$response['order']['id']."customerid".$response['order']['customerid']);
	}

});

$app->post('/orders/accept', 'authenticate', function () use ($app)  {

	// check for required params
    verifyRequiredParams(array('orderid'));

	$orderid = $app->request->post('orderid');
	
	global $user_id;
	
	$response=acceptOrder($orderid,$user_id);

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

		if( ($order["status"]==$db_fabricant::STATUS_ORDER_HIDDEN) ){
			throw new Exception('Order status is not correct for update operation');
		}

		$user_status_in_contractor=$db_profile->getUserStatusInGroup($order["contractorid"],$user_id);

		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
			$user_status_in_customer=$db_profile->getUserStatusInGroup($order["customerid"],$user_id);
			if(($user_status_in_customer!=1)&&($user_status_in_customer!=2)&&($user_status_in_customer!=0)){
				throw new Exception('User have no permission');
			}else{
				if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING) ){
					throw new Exception('Order status is not correct for remove operation');
				}
			}
		}

		if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CONFIRMED)&&($order["status"]!=$db_fabricant::STATUS_ORDER_ONWAY) ){
				throw new Exception('Order status is not correct for remove operation');
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

		if( ($order["status"]==$db_fabricant::STATUS_ORDER_HIDDEN) ){
			throw new Exception('Order status is not correct for update operation');
		}

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

$app->post('/orders/hide', 'authenticate', function () use ($app)  {

	// check for required params
    verifyRequiredParams(array('orderid'));


	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	global $user_id;

	$response = array();

	try{

		$orderid = $app->request->post('orderid');

		$order=$db_fabricant->getOrderById($orderid);

		if( $user_id!=1 && $user_id!=3 ){
			throw new Exception('You have no permission. Only Fabricant Admin has permission');
		}

		$record=json_decode($order["record"],true);
		$user=$db_profile->getUserById($user_id);

		$record["hideUserId"]=$user_id;
		$record["hideUserName"]=$user["name"];
		$record["hideComment"]="no comment";
		$record["hidden"]=true;

		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/hide";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_HIDE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);

		$response['consoleCommand_hide_order'] = $console_response["message"];

		$response['error'] = false;
		$response['message'] = "Order has been hidden";
		$response['order'] = $db_fabricant->getOrderById($orderid);
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);

});

$app->post('/orders/create_minor', 'authenticate', function () use ($app)  {

	// check for required params
    verifyRequiredParams(array('contractorid','customerid'));


	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	global $user_id;

	$response = array();

	$record=array();

	//try{

		$contractorid = $app->request->post('contractorid');
		$customerid = $app->request->post('customerid');

		if(!isset($contractorid) || !isset($customerid)){
			throw new Exception("Missing contractorid or customerid in order");
		}

		$user=$db_profile->getUserById($user_id);

		$order=array();
		$order['contractorid']=$contractorid;
		$order['customerid']=$customerid;
		$order['phone']=$user["phone"];
		$order['comment']="Minor order userid=".$user_id." username=".$user["name"];

		checkUserPermissionToGroups($contractorid,$customerid);

		$record=makeOrderRecord($order);

		//Console command to create
		$json_header=array();
		$json_header["console"]="v2/index/orders/create_minor";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_CREATE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);

		//Preparing record to hide operation
		$order=$db_fabricant->getOrderById($console_response["orderid"]);
		$record=json_decode($order["record"],true);
		$user=$db_profile->getUserById($user_id);
		$record["hideUserId"]=$user_id;
		$record["hideUserName"]=$user["name"];
		$record["hideComment"]="Minor created order hiding";
		$record["hidden"]=true;

		//Console command to hide
		$json_header=array();
		$json_header["console"]="v2/index/orders/hide_minor";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_HIDE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);


		$response['error'] = false;
		$response['message'] = "Minor order has been created";
		$response['order'] = $db_fabricant->getOrderById($record["id"]);
		$response['success'] = 1;


	/*} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
	}*/

	echoResponse(200, $response);

	sendSMS("CreateMinorOrderid".$response['order']['id']."customerid".$response['order']['customerid']);

});

//-----------------1C Синхронизация------------------------------

/**
 * При проведении заказа в 1с
 * method POST
 * file - XLS файл с количеством товаров
 */
$app->post('/orders/1c_order_pass', function() use ($app) {
	
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password','orderid'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');
	$orderid = $app->request->post('orderid');


	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$order=$db_fabricant->getOrderById($orderid);
	if(!isset($order)){
		//Заказ с таким id не найден
		$response['error'] = true;
		$response['message'] = 'Order with orderid='+$orderid+' not found';
		echoResponse(200,$response);
		return;
	}

	//Проверяем логин и пароль
	if(!$db_profile->checkLoginByPhone($phone,$password)){
		//Проверяем доступ админской части группы
		$response['error'] = true;
		$response['message'] = 'Login failed. Incorrect credentials';
		echoResponse(200,$response);
		return;
	}

	$user=$db_profile->getUserByPhone($phone);
	permissionAdminInGroup($user["id"],$contractorid,$db_profile);

	global $api_key,$user_id;
	
	$api_key=$user["api_key"];	
	$user_id=$user["id"];//Это нужно чтобы в функциях updateOrder и acceptOrder
	

	//try{

		if (!isset($_FILES["xls"])) {
			throw new Exception('Param xls is missing');
		}
		//Check if the file is missing
		if (!isset($_FILES["xls"]["name"])) {
			throw new Exception('Property name of xls param is missing');
		}
		//Check the file size >100MB
		if($_FILES["xls"]["size"] > 100*1024*1024) {
			throw new Exception('File is too big');
		}

		$tmpFile = $_FILES["xls"]["tmp_name"];

		$filename = date('dmY').'-'.uniqid('1c_order_pass-tmp-').".xls";
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;

		//Считываем закодированный файл xls в строку
		$data = file_get_contents($tmpFile);

		//Декодируем строку из base64 в нормальный вид
		$data = base64_decode($data);

		//Теперь нормальную строку сохраняем в файл
		$success=false;
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
			$success=true;
		}

		//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
		unset($data);

		//Ошибка декодинга
		if(!$success){
			throw new Exception('Failed when decoding the recieved file');
		}
		
		
		error_log("-------------1c_order_pass filename=".$filename."----------------");
		error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."_orderid=".$orderid."|");

		// Подключаем класс для работы с excel
		require_once dirname(__FILE__).'/libs/PHPExcel/PHPExcel.php';
		// Подключаем класс для вывода данных в формате excel
		require_once dirname(__FILE__).'/libs/PHPExcel/PHPExcel/IOFactory.php';

		$objPHPExcel = PHPExcel_IOFactory::load($path);
		// Set and get active sheet
		$objPHPExcel->setActiveSheetIndex(0);
		$worksheet = $objPHPExcel->getActiveSheet();
		$worksheetTitle = $worksheet->getTitle();
		$highestRow = $worksheet->getHighestRow();
		$highestColumn = $worksheet->getHighestColumn();
		$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
		$nrColumns = ord($highestColumn) - 64;

		$rows=array();
		
		error_log("Recieved xls file:");
		
		for ($rowIndex = 3; $rowIndex <= $highestRow; ++$rowIndex) {
			$cells = array();

			$code = intval($worksheet->getCellByColumnAndRow(0, $rowIndex)->getValue());
			$nomenclature=$worksheet->getCellByColumnAndRow(1, $rowIndex)->getValue();
			$price=floatval($worksheet->getCellByColumnAndRow(2, $rowIndex)->getValue());
			$count = intval($worksheet->getCellByColumnAndRow(3, $rowIndex)->getValue());
			$amount=floatval($worksheet->getCellByColumnAndRow(4, $rowIndex)->getValue());

			//Перекодируем nomenclature
			$string = iconv('utf-8', 'cp1252', $nomenclature);
			$nomenclature = iconv('cp1251', 'utf-8', $string);

			

			$product=$db_fabricant->getProductByCode($contractorid,$code);

			//Если код продукта не существует, то пропускаем этот продукт
			if(!isset($product)){
				error_log("Product code=".$code." not found");

				/* $row=array();
				$row["productid"]=-1;
				$row["name"]=$nomenclature;
				$row["price"]=$price;
				$row["count"]=$count;
				$row["amount"]=$amount; */
			}else{

				$row=array();
				$row["productid"]=$product["id"];
				$row["name"]=$nomenclature;
				$row["price"]=$price;
				$row["count"]=$count;
				$row["amount"]=$amount;
			}
			
			
			error_log(($rowIndex-2)."). productid=".$row["productid"]." price=".$row["price"]." count=".$row["count"]." amount=".$row["amount"]);
			
			$rows[]=$row;
		}

		$record=json_decode($order["record"],true);
		$items=$record["items"];
		
		error_log("Existing order with orderid=".$orderid.":");
		
		foreach($items as $key=>$item){
			error_log(($key+1)."). productid=".$item["productid"]." price=".$item["price"]." count=".$item["count"]." amount=".$item["amount"]);
		}

		$added_items=array_udiff($rows,$items,"order_item_id_compare");
		$deleted_items=array_udiff($items,$rows,"order_item_id_compare");
		
		$rows_intersect=array_uintersect($rows,$items,"order_item_id_compare");
		$items_intersect=array_uintersect($items,$rows,"order_item_id_compare");
		
		$changed_items=array_udiff($rows_intersect,$items_intersect,"order_item_id_price_count_compare");
		
		$update_flag=false;
		
		if(count($added_items)>0){
			$update_flag=true;
			error_log(count($added_items)." items added");
			foreach ($added_items as $key=>$item) {
				error_log(($key+1)."). productid=".$item["productid"]." price=".$item["price"]." count=".$item["count"]." amount=".$item["amount"]);
			}
		}else{
			error_log("No items added");
		}

		if(count($deleted_items)){
			$update_flag=true;
			error_log(count($deleted_items)." items deleted");
			foreach ($deleted_items as $key=>$item) {
				error_log(($key+1)."). productid=".$item["productid"]." price=".$item["price"]." count=".$item["count"]." amount=".$item["amount"]);
			}
		}else{
			error_log("No items deleted");
		}

		if(count($changed_items)){
			$update_flag=true;
			error_log(count($changed_items)." items changed");
			foreach ($changed_items as $key=>$item) {
				error_log(($key+1)."). productid=".$item["productid"]." price=".$item["price"]." count=".$item["count"]." amount=".$item["amount"]);
			}
		}else{
			error_log("No items changed");
		}
		
		if($update_flag){
			$json_order=array();
			$json_order["contractorid"]=$contractorid;
			$json_order["customerid"]=$order["customerid"];
			$json_order["phone"]=$record["phone"];
			$json_order["comment"]=$record["comment"]." (Принят, с изменениями при проведении в 1С)";
			
			$json_order_items=array();			
			foreach($rows as $row){
				$json_order_items["".$row["productid"]]=$row["count"];
			}			
			$json_order["items"]=$json_order_items;
			
			error_log("updateOrder orderid=".$orderid." user_id=".$user_id);
			
			$result=updateOrder($orderid,$json_order,$user_id);
			
			if($result["success"]){
				error_log("acceptOrder orderid=".$orderid." user_id=".$user_id);
				$result=acceptOrder($orderid,$user_id);		
				$response[]=$result["success"];
			}else{
				$response[]=0;
			}
		}else{			
			error_log("acceptOrder orderid=".$orderid." user_id=".$user_id);
			$result=acceptOrder($orderid,$user_id);		
			$response[]=$result["success"];
		}
		
		error_log(" ");


	//} catch (Exception $e) {
		// Exception occurred. Make error flag true
		//$response["error"] = true;
		//$response["message"] = $e->getMessage();
		//$response["success"] = 0;
		//$response = $e->getMessage();
	//}
	echoResponse(200, $response);
});

function order_item_id_compare($a,$b){
  return (intval($a["productid"])-intval($b["productid"]));
    
}

function order_item_id_price_count_compare($a,$b){
  if( (intval($a["productid"])-intval($b["productid"]) == 0 ) && ( (strcmp(strval(floatval($a["price"])),strval(floatval($b["price"])))==0)&&(strcmp(strval(floatval($a["count"])),strval(floatval($b["count"])))==0) ) ){
    return 0;
  }else{
    return 100;
  }
}

//-------------Orders Utils------------------------

function updateOrder($old_order_id,$order,$user_id) {
	
	//try{
		
		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();

		$response = array();

		$old_order=$db_fabricant->getOrderById($old_order_id);
		$old_order_record=json_decode($old_order["record"],JSON_UNESCAPED_UNICODE);

		checkUserPermissionToOrder($old_order_id,$order);
		
		$record=makeOrderRecord($order);
		$record["id"]=$old_order_id;
		$record["created_at"]=$old_order_record["created_at"];
		$record["updated"]=true;
		
		//Customer user info to record
		$record["customerUserId"]=$old_order_record["customerUserId"];
		$record["customerUserName"]=$old_order_record["customerUserName"];
		$record["customerUserPhone"]=$old_order_record["customerUserPhone"];

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

	//} catch (Exception $e) {
    //
	//	$response['error'] = true;
	//	$response['message'] = $e->getMessage();
	//	$response['success'] = 0;
	//}
	
	return $response;
}

function acceptOrder($orderid,$user_id) {
	
	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$response = array();

	try{

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
	
	return  $response;
}

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
		if($contractor["status"]==4){
			throw new Exception('Contractor status is incorrect');
		}

		//Get and check customer
		$customerid=$order["customerid"];
		$customer=$db_profile->getGroupById($customerid)[0];
		if($customer["type"]!=1){
			throw new Exception('Customer type is incorrect');
		}
		if(($customer["status"]!=1)&&($customer["status"]!=2)){
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


		//Installment
		$logs[]="Installment";
		$installment=getInstallment($order,$contractor);
		$price_installment_name=null;
		if( ($installment!=null) && ( (isset($installment["for_all_customers"]) && $installment["for_all_customers"]==true) || groupHasTag($installment["tag_customer"],$customer) ) ){
			$price_installment_name=$installment["price_name"];
		}

		$logs[]="price_installment_name=".$price_installment_name;

		//Basket подготовка корзины: чистим ее от всякого неодеквата и применяем rate-скидки к продуктам
		$logs[]="Basket";


		if(isset($order["items"])){
			$items=$order["items"];
		}else{
			$items=array();
		}

		$basket=array();
		$basket_products=array();

		$basket_price=0;
		foreach($items as $productid=>$count){
				$product=$db_fabricant->getProductById($productid);

				if(!isset($product))continue;//Product id not found
				if($product["contractorid"]!=$contractorid)continue;//Another contractor product
				if($product["status"]!=2)continue;//Product status incorrect
				if($count==0)continue;//Don't add empty products


				$item=array();

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

				//Нет в наличии
				if(productHasTag("not_in_stock",$product)){
					$item["name"]="(НЕТ В НАЛИЧИИ) ".$item["name"];
				}

				$sale=null;
				$sale=getProductSale($product,$contractor,$customer,$installment);
				if(isset($sale)){

					//Вычитаемая сумма
					$save_value=ceil(round($item["amount"]*(-1.0+$sale["rate"]),4));

					//Сохраняем информацию о скидке
					$sale_info=array();
					$sale_info["id"]=$sale["id"];
					$sale_info["name"]=$sale["name"];
					$sale_info["label"]=$sale["label"];
					$sale_info["rate"]=$sale["rate"];
					$sale_info["amount_no_sale"]=$item["amount"];
					$sale_info["price_with_sale"]=round($item["price"]*$sale["rate"],4);
					$sale_info["value"]=$save_value;
					$sale_info["cash_only"]=$sale["cash_only"];
					$sale_info["for_all_customers"]=$sale["for_all_customers"];

					$item["amount"]+=$save_value;
					$item["sale"]=$sale_info;

				}

				$basket_price+=$item["amount"];


				$basket[]=$item;

				//Это нужно для определения дисконтов
				$item["product"]=$product;
				$basket_products[]=$item;
		}

		//Costs
		$costs=array();
		$costs["itemsAmount"]=$basket_price;
		$total_cost=$basket_price;


		$logs[]="Discounts";

		//Берем все дисконты сортированные по rate
		$discounts=getDiscounts($contractor,$customer,$installment);

		foreach($discounts as $discount){

			if(count($basket_products)==0)break;

			$discount_products=array();
			$discount_products_summ=0;
			$discount_products_ids=array();
			foreach($basket_products as $item){
				if(productHasDiscount($item["product"],$discount)){

					$item["productHasDiscount"]=productHasDiscount($item["product"],$discount);
					$item["isDiscountForAllProducts"]=isDiscountForAllProducts($discount);
					$item["productHasTag"]=productHasTag($discount["tag_product"],$product);

					$discount_products[]=$item;
					$discount_products_ids[]=$item["productid"];
					$discount_products_summ+=$item["amount"];
				}
			}

			if($discount_products_summ>=$discount["min_summ"] && $discount_products_summ<$discount["max_summ"]){

				//Записываем иформацию о дисконте
				$costs_discounts_item=array();
				$costs_discounts_item["id"]=$discount["id"];
				$costs_discounts_item["rate"]=$discount["rate"];
				$costs_discounts_item["value"]=ceil(round($discount_products_summ*(-1.0+$discount["rate"]),4));
				$costs_discounts_item["value_float"]=$discount_products_summ*(-1.0+$discount["rate"]);
				$costs_discounts_item["name"]=$discount["name"];
				$costs_discounts_item["products_count"]=count($discount_products_ids);
				$costs_discounts_item["products_amount"]=$discount_products_summ;
				$costs_discounts_item["min_summ"]=$discount["min_summ"];
				$costs_discounts_item["max_summ"]=$discount["max_summ"];
				$costs_discounts_item["products"]=$discount_products_ids;
				$costs_discounts_item["cash_only"]=$discount["cash_only"];
				$costs_discounts_item["for_all_customers"]=$discount["for_all_customers"];
				$costs_discounts_item["for_all_products"]=$discount["for_all_products"];

				if(!isset($costs["discounts"]))$costs["discounts"]=array();

				//Вычитаем сумму дисконта от общей стоимости
				$costs["discounts"][]=$costs_discounts_item;
				$total_cost+=$costs_discounts_item["value"];

				//Убираем из корзины продукты к которым был применен дисконт
				$basket_products=array_udiff($basket_products,$discount_products,'compareProductsId');

			}
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

		return $record;
}

function checkUserPermissionToGroups($contractorid,$customerid){

		$db_profile = new DbHandlerProfile();

		global $user_id;

		$user_status_in_contractor=$db_profile->getUserStatusInGroup($contractorid,$user_id);

		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){

			$user_status_in_customer=$db_profile->getUserStatusInGroup($customerid,$user_id);

			if(($user_status_in_customer!=1)&&($user_status_in_customer!=2)&&($user_status_in_customer!=0)){
				throw new Exception('User have no permission');
			}
		}

}

function checkUserPermissionToOrder($old_order_id,$new_order){

		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();

		global $user_id;

		$order=$db_fabricant->getOrderById($old_order_id);

		if(!isset($order)){
			throw new Exception('orderid  is not correct');
		}

		if( ($order["status"]==$db_fabricant::STATUS_ORDER_HIDDEN) ){
			throw new Exception('Order status is not correct for update operation');
		}

		$contractorid=$order["contractorid"];
		$customerid=$order["customerid"];

		$user_status_in_contractor=$db_profile->getUserStatusInGroup($contractorid,$user_id);

		if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){

			$user_status_in_customer=$db_profile->getUserStatusInGroup($customerid,$user_id);

			if(($user_status_in_customer!=1)&&($user_status_in_customer!=2)&&($user_status_in_customer!=0)){
				throw new Exception('User have no permission');
			}
		}

		if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CONFIRMED)&&($order["status"]!=$db_fabricant::STATUS_ORDER_ONWAY)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CANCELED) ){
			throw new Exception('Order status is not correct for this operation');
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

function getProductSale($product,$contractor,$customer,$installment){


	if(!isset($contractor["info"]))
		return null;

	$contractor_info=json_decode($contractor["info"],true);

	if(!isset($contractor_info["sales"]))
		return null;


	$sales=$contractor_info["sales"];

	$salerate=null;

	foreach($sales as $sale){
		if( ($sale["type"]==4) && isset($sale["tag_customer"]) && ( isSaleForAllCustomers($sale) || groupHasTag($sale["tag_customer"],$customer) ) && productHasTag($sale["tag_product"],$product) ){

			//Если только за наличные и заказчик выбрал рассрочку, то далее
			if( isSaleForCashOnly($sale) && isset($installment) )continue;

			if( ($salerate==null)||($sale["rate"]<$salerate["rate"]) ){
				$salerate=$sale;
			}
		}
	}

	return $salerate;
}

function getDiscounts($contractor,$customer,$installment){

	$discounts=array();

	if(!isset($contractor["info"]))
		return $discounts;

	$contractor_info=json_decode($contractor["info"],true);

	if(!isset($contractor_info["sales"]))
		return $discounts;

	$sales=$contractor_info["sales"];

	foreach($sales as $sale){
		if( ($sale["type"]==5) && ( isSaleForAllCustomers($sale) || (isset($sale["tag_customer"]) && groupHasTag($sale["tag_customer"],$customer)) ) ){

			//Если только за наличные и заказчик выбрал рассрочку, то далее
			if( isSaleForCashOnly($sale) && isset($installment) )continue;

			$discounts[]=$sale;
		}
	}

	if(usort($discounts,"compareDiscountRate")){
		return $discounts;
	}else{
		throw new Exception("getDiscounts error, while usort");
	}
}

function compareDiscountRate($a, $b){
    return ($a['rate'] - $b['rate']);
}

function compareProductsId($a, $b){
    return ($a['productid'] - $b['productid']);
}

//--------Boolean Functions--------------

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

function productHasDiscount($product,$discount){
	return isDiscountForAllProducts($discount) || ( isset($discount["tag_product"]) && productHasTag($discount["tag_product"],$product) );
}

function isSaleForCashOnly($sale){
	return isset($sale["cash_only"]) && ($sale["cash_only"]==true);
}

function isSaleForAllCustomers($sale){
	return (isset($sale["for_all_customers"]) && $sale["for_all_customers"]==true);
}

function isDiscountForAllProducts($discount){
	return isset($discount["for_all_products"]) && $discount["for_all_products"]==true;
}

//-----------------Sales-----------------------

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
		$json_header["contractorid"] = filter_var($app->request->post('contractorid'),FILTER_VALIDATE_INT);
		$json_header["type"] = filter_var($app->request->post('type'),FILTER_VALIDATE_INT);
		$json_header["label"] = $app->request->post('label');
		$json_header["name"] = $app->request->post('name');
		$json_header["name_full"] = $app->request->post('name_full');
		$json_header["summary"] = $app->request->post('summary');
		$json_header["alias"] = $app->request->post('alias');
		$json_header["for_all_customers"] = filter_var($app->request->post('for_all_customers'),FILTER_VALIDATE_BOOLEAN);

		if(!isset($json_header["type"])){
			throw new Exception("Sale create error. type is not found");
		}

		//Params of Installment sale
		switch($json_header["type"]){
			case M_SALE_TYPE_SALE:

				$json_header["rate"] = filter_var($app->request->post('rate'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["rate"])){
					throw new Exception("Sale create error. rate is not found");
				}

				$json_header["cash_only"] = filter_var($app->request->post('cash_only'),FILTER_VALIDATE_BOOLEAN);
				if(!isset($json_header["cash_only"])){
					throw new Exception("Sale create error. cash_only is not found");
				}

				break;

			case M_SALE_TYPE_DISCOUNT:

				$json_header["rate"] = filter_var($app->request->post('rate'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["rate"])){
					throw new Exception("Sale create error. rate is not found");
				}
				$json_header["min_summ"] = filter_var($app->request->post('min_summ'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["min_summ"])){
					throw new Exception("Sale create error. min_summ is not found");
				}
				$json_header["max_summ"] = filter_var($app->request->post('max_summ'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["max_summ"])){
					throw new Exception("Sale create error. max_summ is not found");
				}

				$json_header["cash_only"] = filter_var($app->request->post('cash_only'),FILTER_VALIDATE_BOOLEAN);
				if(!isset($json_header["cash_only"])){
					throw new Exception("Sale create error. cash_only is not found");
				}

				$json_header["for_all_products"] = filter_var($app->request->post('for_all_products'),FILTER_VALIDATE_BOOLEAN);
				if(!isset($json_header["for_all_products"])){
					throw new Exception("Sale create error. for_all_products is not found");
				}

				break;

			case M_SALE_TYPE_INSTALLMENT:

				$json_header["time_notification"] = filter_var($app->request->post('time_notification'),FILTER_VALIDATE_INT);
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

		echoResponse(200, $response);
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
		$json_header["saleid"] = filter_var($app->request->post('saleid'),FILTER_VALIDATE_INT);

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

		echoResponse(200, $response);
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
		$json_header["saleid"] = filter_var($app->request->post('saleid'),FILTER_VALIDATE_INT);
		$json_header["label"] = $app->request->post('label');
		$json_header["name"] = $app->request->post('name');
		$json_header["name_full"] = $app->request->post('name_full');
		$json_header["summary"] = $app->request->post('summary');
		$json_header["alias"] = $app->request->post('alias');
		$json_header["for_all_customers"] = filter_var($app->request->post('for_all_customers'),FILTER_VALIDATE_BOOLEAN);

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

				$json_header["rate"] = filter_var($app->request->post('rate'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["rate"])){
					throw new Exception("Sale update error. rate is not found");
				}

				$json_header["cash_only"] = filter_var($app->request->post('cash_only'),FILTER_VALIDATE_BOOLEAN);
				if(!isset($json_header["cash_only"])){
					throw new Exception("Sale update error. cash_only is not found");
				}

				break;

			case M_SALE_TYPE_DISCOUNT:

				$json_header["rate"] = filter_var($app->request->post('rate'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["rate"])){
					throw new Exception("Sale update error. rate is not found");
				}

				$json_header["min_summ"] = filter_var($app->request->post('min_summ'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["min_summ"])){
					throw new Exception("Sale update error. min_summ is not found");
				}

				$json_header["max_summ"] = filter_var($app->request->post('max_summ'),FILTER_VALIDATE_FLOAT);
				if(!isset($json_header["max_summ"])){
					throw new Exception("Sale update error. max_summ is not found");
				}

				$json_header["cash_only"] = filter_var($app->request->post('cash_only'),FILTER_VALIDATE_BOOLEAN);
				if(!isset($json_header["cash_only"])){
					throw new Exception("Sale update error. cash_only is not found");
				}

				$json_header["for_all_products"] = filter_var($app->request->post('for_all_products'),FILTER_VALIDATE_BOOLEAN);
				if(!isset($json_header["for_all_products"])){
					throw new Exception("Sale update error. for_all_products is not found");
				}

				break;

			case M_SALE_TYPE_INSTALLMENT:

				$json_header["time_notification"] = filter_var($app->request->post('time_notification'),FILTER_VALIDATE_INT);

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

		echoResponse(200, $response);
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
		$json_header["saleid"] = filter_var($app->request->post('saleid'),FILTER_VALIDATE_INT);
		$json_header["customerid"] = filter_var($app->request->post('customerid'),FILTER_VALIDATE_INT);

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

		echoResponse(200, $response);
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
		$json_header["saleid"] = filter_var($app->request->post('saleid'),FILTER_VALIDATE_INT);
		$json_header["customerid"] = filter_var($app->request->post('customerid'),FILTER_VALIDATE_INT);

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

		echoResponse(200, $response);
	}
});

$app->post('/sales/set_default_installment', 'authenticate', function () use ($app)  {

	$response = array();

	try{
		// check for required params
		verifyRequiredParams(array('saleid','customerid'));

		global $user_id;


		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/add_default_installment";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_SET_DEFAULT_INSTALLMENT;
		$json_header["senderid"]=$user_id;

		//Required params
		$json_header["saleid"] = filter_var($app->request->post('saleid'),FILTER_VALIDATE_INT);
		$json_header["customerid"] = filter_var($app->request->post('customerid'),FILTER_VALIDATE_INT);

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

		echoResponse(200, $response);
	}
});

$app->post('/sales/clear_default_installment', 'authenticate', function () use ($app)  {
	try{
		// check for required params
		verifyRequiredParams(array('contractorid','customerid'));

		global $user_id;

		$response = array();

		//Console command params
		$json_header=array();
		$json_header["console"]="v2/index/sales/remove_default_installment";
		$json_header["operation"]=M_CONSOLE_OPERATION_SALE;
		$json_header["sale_operationid"]=M_SALE_OPERATION_CLEAR_DEFAULT_INSTALLMENTS;
		$json_header["senderid"]=$user_id;

		//Required params
		$json_header["contractorid"] = filter_var($app->request->post('contractorid'),FILTER_VALIDATE_INT);
		$json_header["customerid"] = filter_var($app->request->post('customerid'),FILTER_VALIDATE_INT);

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

		echoResponse(200, $response);
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
define("M_ORDEROPERATION_HIDE", 6);

define("M_SALE_OPERATION_CREATE", 0);
define("M_SALE_OPERATION_REMOVE", 1);
define("M_SALE_OPERATION_UPDATE", 2);
define("M_SALE_OPERATION_ADD_TO_CUSTOMER", 3);
define("M_SALE_OPERATION_REMOVE_FROM_CUSTOMER", 4);
define("M_SALE_OPERATION_SET_DEFAULT_INSTALLMENT", 5);
define("M_SALE_OPERATION_CLEAR_DEFAULT_INSTALLMENTS", 6);

define("M_SALE_TYPE_SALE", 4);
define("M_SALE_TYPE_DISCOUNT", 5);
define("M_SALE_TYPE_INSTALLMENT", 6);


function consoleCommand($header_json){

	global $api_key;
	$header_json["Api-Key"]=$api_key;

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

function sendSMS($text){
	$phone="79142966292";
	$body=file_get_contents("http://sms.ru/sms/send?api_id=A73F3F48-2F27-8D8D-D7A2-6AFF64E4F744&to=".$phone."&from=fabricant&text=".$text);
	return $body;
}

//--------------------Permission--------------------------------

function permissionFabricantAdmin($userid){

	if( ($userid==1) || ($userid==3) )return;

	$response["error"] = true;
	$response["message"] = "You have no permission. Only fabricant admin has permission";
	$response["success"] = 0;
	echoResponse(200, $response);
	
	global $app;
	$app->stop();
}

function permissionInGroup($userid,$groupid,$db_profile){

	$status=$db_profile->getUserStatusInGroup($groupid,$userid);

	if($userid==1 || $userid==3)return;

	if( ($status == 0)||($status == 2) || ($status == 1))return;

	$response["error"] = true;
	$response["message"] = "You have no permission. Only user in group has permission";
	$response["success"] = 0;
	echoResponse(200, $response);
	
	global $app;
	$app->stop();
}

function permissionAdminInGroup($userid,$groupid,$db_profile){

	$status=$db_profile->getUserStatusInGroup($groupid,$userid);

	if($userid==1 || $userid==3)return;

	if( ($status == 2) || ($status == 1))return;

	$response["error"] = true;
	$response["message"] = "You have no permission. Only group admin has permission";
	$response["success"] = 0;
	echoResponse(200, $response);
	
	global $app;	
	$app->stop();
}

function permissionSuperAdminInGroup($userid,$groupid,$db_profile){

	$status=$db_profile->getUserStatusInGroup($groupid,$userid);

	if($userid==1 || $userid==3)return;

	if($status == 1)return;

	$response["error"] = true;
	$response["message"] = "You have no permission. Only group super admin has permission";
	$response["success"] = 0;
	echoResponse(200, $response);
	
	global $app;
	$app->stop();
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
