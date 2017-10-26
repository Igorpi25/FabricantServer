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

$mode_1c_synch = false;

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
				sendSMS("UserRegistered_".$phone);
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
				sendSMS("UserRegisteringFailPhoneUsed_".$phone);
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

$app->post('/change_password_stepanova', function() use ($app) {

		    verifyRequiredParams(array('password'));

            $db = new DbHandlerProfile();

            $user_id=192;

            $user = $db->getUserById($user_id);

            // reading post params
            $password = $app->request->post('password');


			$response = $db->changeUserPassword($user_id,$password);
			echoResponse(200, $response);
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

		//Проверяем нет ли уже такой группы

		$groups_of_user=$db->getGroupsOfUser($user_id);

		$group_already_exists=false;
		$groupid=0;

		foreach($groups_of_user as $group_of_user){
			if( ($group_of_user["name"]==$name)&&($group_of_user["address"]==$address) && ($group_of_user["status"]==1) && ($group_of_user["type"]==1) && ($group_of_user["status_in_group"]==1) ){
				error_log("create_customer group_already_exists groupid=".$groupid." user_id=".$user_id);
				$group_already_exists=true;
				$groupid=$group_of_user["id"];
				break;
			}

		}
		if(!$group_already_exists){
			$groupid=$db->createCustomer($user_id,$name,$address);
		}



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

		sendSMS("Group_".$groupid."_CreatedSender_".$user_id);

	} catch (Exception $e) {

		error_log("create_customer exception message=".$e->getMessage());
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

	$json_order = $app->request->post('order');

	global $user_id;

	$response=createOrder($json_order,$user_id);

	echoResponse(200, $response);
	if($response["success"]==1){
		//sendSMS("CreateOrderid".$response['order']['id']."customerid".$response['order']['customerid']);
	}

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

		//Если стоит запрет на изменение заказов импортированных в 1С
		check1CSynchronizingPermissions($orderid);

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

		//Если стоит запрет на изменение заказов импортированных в 1С
		check1CSynchronizingPermissions($orderid);

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


		//Если стоит запрет на изменение заказов импортированных в 1С
		check1CSynchronizingPermissions($orderid);


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

$app->post('/orders/add_visa', 'authenticate', function () use ($app)  {

	// check for required params
    verifyRequiredParams(array('orderid'));

	$orderid = $app->request->post('orderid');

	global $user_id;

	$response=addVisaToOrder($orderid,$user_id);

	echoResponse(200, $response);

});

$app->post('/orders/remove_visa', 'authenticate', function () use ($app)  {

	// check for required params
    verifyRequiredParams(array('orderid'));

	$orderid = $app->request->post('orderid');

	global $user_id;

	$response=removeVisaFromOrder($orderid,$user_id);

	echoResponse(200, $response);

});

//-----------------1C Синхронизация------------------------------

/**
 * При проведении заказа в 1с
 * method POST
 * file - XLS файл с количеством товаров
 */
$app->post('/orders/1c_orders_pass', function() use ($app) {

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

//----------------------Kustuk-----------------------------------

/**
 * При проведении заказа в 1с
 * method POST
 * file - XLS файл с количеством товаров
 */
$app->post('/1c_orders_pass_kustuk', function() use ($app) {

	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	
	error_log("-------------1c_orders_kustuk_pass----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."|");


	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

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

	global $api_key,$user_id,$mode_1c_synch;
	$api_key=$user["api_key"];
	$user_id=$user["id"];//Это нужно чтобы в функциях updateOrder и acceptOrder
	$mode_1c_synch=true;//Чтобы не было запрета на редактирование изза визы в функции check1CSynchronizingPermissions



	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);



	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	//Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	//Check the file size >100MB
	if($_FILES["json"]["size"] > 100*1024*1024) {
		throw new Exception('File is too big');
	}


	$tmpFile = $_FILES["json"]["tmp_name"];
	//Считываем закодированный файл json в строку
	$data = file_get_contents($tmpFile);
	//Декодируем строку из base64 в нормальный вид
	$data = base64_decode($data);
	
	//Запись в /v2/reports для лога
	try{
		$filename = '1c_orders_kustuk_pass'.date(" Y-m-d H-i-s ").uniqid().".json";
		error_log("logged in file: ".$filename);		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}

	//Берем данные в массив
	$incoming_orders = json_decode($data,true);

	if(!isset($incoming_orders)){
		throw new Exception('File is not json');
	}

	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	error_log("Incoming orders count: ".count($incoming_orders));
	
	$success_orders=array();
	
	$current_order_index=0;

	foreach ($incoming_orders as $incoming_order) {

		try{
			$current_order_index++;

			$orderid = $incoming_order["orderid"];
			$ordercode = $incoming_order["ordercode"];
			$orderdate = $incoming_order["date"];
			$orderstatus = $incoming_order["status"];

			$contragentid = $incoming_order["customerid"];
			$contragentcode = $incoming_order["customercode"];
			$contragentname = $incoming_order["customerName"];
			$contragentaddress = $incoming_order["address"];
			$contragentphone = $incoming_order["phone"];

			$customerUserId = $incoming_order["customerUserId"];
			$customerUserCode = $incoming_order["customerUserCode"];
			$customerUserName = $incoming_order["customerUserName"];

			$visaAddedUserId = $incoming_order["visaAddedUserId"];
			$visaAddedUserCode = $incoming_order["visaAddedUserCode"];
			$visaAddedUserName = $incoming_order["visaAddedUserName"];

			$comment = $incoming_order["comment"];

			error_log("orderid: ".$orderid);
			error_log("ordercode: ".$ordercode);
			error_log("orderdate: ".$orderdate);
			error_log("orderstatus: ".$orderstatus);

			error_log("contragentid: ".$contragentid);
			error_log("contragentcode: ".$contragentcode);
			error_log("contragentname: ".$contragentname);
			error_log("contragentaddress: ".$contragentaddress);
			error_log("contragentphone: ".$contragentphone);

			error_log("customerUserId: ".$customerUserId);
			error_log("customerUserCode: ".$customerUserCode);
			error_log("customerUserName: ".$customerUserName);

			error_log("visaAddedUserId: ".$visaAddedUserId);
			error_log("visaAddedUserCode: ".$visaAddedUserCode);
			error_log("visaAddedUserName: ".$visaAddedUserName);

			error_log("comment: ".$comment);

			if(!isset($ordercode)){				
				error_log('Ordercode is missing');
				throw new Exception('Ordercode is missing');
			}



			$incoming_order_items=$incoming_order["items"];

			$rows=array();
			error_log("recieved order rows count=".count($incoming_order_items));

			$comment_for_dublicates="";

			foreach ($incoming_order_items as $index=>$incoming_order_item) {
				$cells = array();
				$code = $incoming_order_item["code"];
				$nomenclature=$incoming_order_item["name"];
				$id = intval($incoming_order_item["id"]);
				$price=floatval($incoming_order_item["price"]);
				$count = intval($incoming_order_item["count"]);
				$amount=floatval($incoming_order_item["amount"]);

				$product=$db_fabricant->getProductByCode($contractorid,$code);
				//Если код продукта не существует, то пропускаем этот продукт
				if(!isset($product)){
					error_log("Product code=".$code." index=".($index+1)." not found");
					$row=array();
					$row["code"]=$code;
					$row["productid"]=-1;
					$row["name"]=$nomenclature;
					$row["price"]=$price;
					$row["count"]=$count;
					$row["amount"]=$amount;
					throw new Exception("Product code=".$code." index=".($index+1)." not found");
				}else{
					$row=array();
					$row["code"]=$code;
					$row["productid"]=$product["id"];
					$row["name"]=$nomenclature;
					$row["price"]=$price;
					$row["count"]=$count;
					$row["amount"]=$amount;
				}
				//error_log(($index+1)."). code=".$row["code"]." productid=".$row["productid"]." price=".$row["price"]." count=".$row["count"]." amount=".$row["amount"]);

				$found_dublicate=false;
				foreach($rows as $row_index=>$existing_row){
					//Найден второй товар с таким же именем
					if($row["productid"]==$existing_row["productid"]){

						//Если цена совпадает, то просто суммируем количество
						if($row["price"]==$existing_row["price"]){
							$rows[$row_index]["count"]+=$row["count"];
							$rows[$row_index]["amount"]+=($row["price"]*$row["count"]);

							$comment_for_dublicates+=" Дубликат товара суммирован: (".$row["name"]."), Кол:".$row["count"].".";

						//Если цена ранее была нулевой, то заменяем
						}else if($existing_row["price"]==0){
							$rows[$row_index]=$row;

							$comment_for_dublicates+=" Дубликат с нулевой ценой замещен: (".$existing_row["name"]."), Кол:".$existing_row["count"].".";
						}else{
							$comment_for_dublicates+=" Дубликат товара удален: (".$row["name"]."), Цена:".$row["price"].", Кол:".$row["count"].", Сумма:".$row["amount"].".";
						}

						$found_dublicate=true;
						break;
					}
				}

				if(!$found_dublicate){
					$rows[]=$row;
				}

			}

			//Находим заказ по коду
			$order=$db_fabricant->getOrderByCode($ordercode);

			$changed_flag=false;

			if(!isset($order)){

				error_log("Creating new order: ordercode=".$ordercode." contragentcode=".$contragentcode);

				$customerid=$db_profile->getCustomerIdInContractorByCode($contragentcode,$contractorid);

				//Если заказчик не существует то создаем его, и привязываем его id к contracgentcode
				if( !isset($customerid) && isset($contragentcode) ){

					//Создаем нового заказчика
					error_log("creating new customer");

					if(!isset($contragentaddress)){
						$contragentaddress="Адрес не указан";
					}

					if(!isset($contragentphone)){
						$contragentphone="";
					}

					$agent_id=null;

					//Добавление агента в создаваемую группу в качестве админа
					if(!empty($visaAddedUserCode) ){
						error_log("adding agentid to new customer group as admin");
						error_log("agentid: visaAddedUserCode=".$visaAddedUserCode);

						$visaAddedUserId_by_code=$db_profile->getUserIdInContractorByCode($visaAddedUserCode,$contractorid);

						if(!empty($visaAddedUserId_by_code)){

							if($visaAddedUserId_by_code!=$user_id){

								$agent_id=$visaAddedUserId_by_code;
								error_log("success. agentid=".$agent_id);
							}else{
								error_log("canceled. agentid equals user_id=".$user_id);
							}

						}else{
							error_log("canceled. visaAddedUserCode is not associated with userid");
						}
					}

					$create_customer_response=createCustomer($contragentname,$contragentaddress,$contragentphone,"{}",$user_id,$agent_id);

					if( isset($create_customer_response["id"]) ){
						error_log("created");
						$customerid=$create_customer_response["id"];

						error_log("set customer code in contarctor");
						//Связка созданного заказчика с контрагентом 1С
						$db_profile->setCustomerCodeInContractor($customerid, $contragentcode,$contractorid);

					}else{
						error_log("failed");
						throw new Exception("failed when create customer");
					}
				}

				//Если заказ не существует, то создаем новый на основе полученных данных
				$json_order=array();
				$json_order["contractorid"]=$contractorid;
				$json_order["customerid"]=$customerid;
				$json_order["phone"]=$phone;
				$json_order["comment"]=$comment;
				$json_order_items=array();
				foreach($rows as $row){
					$json_order_items["".$row["productid"]]=$row["count"];
				}
				$json_order["items"]=$json_order_items;
				error_log("creating order user_id=".$user_id." phone=".$phone." contractorid=".$contractorid." customerid=".$customerid);
				$create_order_response=createOrder(json_encode($json_order,JSON_UNESCAPED_UNICODE),$user_id);

				if(!isset($create_order_response['order'])){
					error_log("failed. when send through communicator");
					error_log("error message: ".$create_order_response['message']);
				}

				$order=$create_order_response['order'];
				$record=json_decode($order["record"],true);

				error_log("created. orderid=".$order["id"]);

				//Установления кода 1с
				error_log("setting code1c to created order");
				error_log("code1c=".$ordercode);
				if($db_fabricant->updateOrderCode($order["id"], $ordercode)){
					error_log("success. code1c set");
				}else{
					error_log("failed. db_fabricant sql error");
				}


			}else{

				error_log("Changing order: orderid=".$order["id"]);

				//Если заказ существует, то обновляем данные

				$record=json_decode($order["record"],true);
				$items=$record["items"];

				if(!isset($items))$items=array();
				error_log("existing order items count=".count($items));
				foreach($items as $key=>$item){
					$item["code"]=$db_fabricant->getProductCodeById($item["productid"]);
					//error_log(($key+1)."). code=".$item["code"]." productid=".$item["productid"]." price=".$item["price"]." count=".$item["count"]." amount=".$item["amount"]);
				}
				$added_items=array_udiff($rows,$items,"order_item_id_compare");
				$deleted_items=array_udiff($items,$rows,"order_item_id_compare");
				$rows_intersect=array_uintersect($rows,$items,"order_item_id_compare");
				$items_intersect=array_uintersect($items,$rows,"order_item_id_compare");

				$changed_items=array();
				foreach($rows_intersect as $a){
					$found=false;
					foreach($items_intersect as $b){
						if(order_item_id_price_count_compare($a,$b)==0){
							$found=true;
							break;
						}
					}
					if(!$found)$changed_items[]=$a;
				}

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

				if($orderstatus==1 && $order["status"]!=1){
					$update_flag=true;
				}

				if($update_flag){
					$changed_flag=true;

					$json_order=array();
					$json_order["contractorid"]=$contractorid;
					$json_order["customerid"]=$order["customerid"];
					$json_order["phone"]=$record["phone"];
					$json_order["comment"]=$comment;
					$json_order_items=array();
					foreach($rows as $row){
						$json_order_items["".$row["productid"]]=$row["count"];
					}

					$json_order["items"]=$json_order_items;
					$orderid=$order["id"];
					error_log("updateOrder orderid=".$orderid." user_id=".$user_id);
					$result=updateOrder($orderid,$json_order,$user_id);

					$order=$db_fabricant->getOrderById($orderid);
					$record=json_decode($order["record"],true);

				}

			}

			//Добавление визы агента привязанного к этому контрагенту
			if(!empty($visaAddedUserCode)){
				error_log("visaAddedUserCode exists");
				error_log("adding visa by visaAddedUserCode");

				$visaAddedUserId_by_code=$db_profile->getUserIdInContractorByCode($visaAddedUserCode,$contractorid);

				if(!empty($visaAddedUserId_by_code)){
					error_log("userid=".$visaAddedUserId_by_code." found for visaAddedUserCode=".$visaAddedUserCode);
					try{
						checkVisaPermissionForUserId($contractorid,$order["customerid"],$visaAddedUserId_by_code);

					}catch(Exception $e){
						error_log("failed. user id=".$visaAddedUserId_by_code." has no permission to add visa to order of customer id=".$order["customerid"]);
						error_log("failed. exception message: ".$e);

					}

					$add_visa_to_order_response=addVisaToOrder($order["id"],$visaAddedUserId_by_code);

					if(isset($add_visa_to_order_response["order"])){
						$order=$add_visa_to_order_response["order"];
						$record=json_decode($order["record"],true);
						error_log("added. visa successfully added to order");
					}else{
						error_log("failed. when addVisaToOrder orderid=".$order["id"]);
						error_log("failed. exception message: ".$add_visa_to_order_response["message"]);
					}

				}else{
					error_log("failed. visaAddedUserCode=".$visaAddedUserCode." is not associated with user");
				}
			}

			$record=json_decode($order["record"],true);

			if(empty($record["visa"])){
				error_log("visa is still not exists");
				error_log("adding admin visa by checking user_id");

				$add_visa_to_order_response=addVisaToOrder($order["id"],$user_id);

				if(isset($add_visa_to_order_response["order"])){
					$order=$add_visa_to_order_response["order"];
					$record=json_decode($order["record"],true);
					error_log("added. visa successfully added to order");
				}else{
					error_log("failed. when addVisaToOrder");
				}
			}

			$orderid=$order["id"];

			if($orderstatus==2 && $order["status"]!=2){
				$changed_flag=true;
				error_log("accepting order orderid=".$orderid." user_id=".$user_id);
				$result=acceptOrder($orderid,$user_id);
				error_log("accepted");
				$order=$db_fabricant->getOrderById($orderid);
				$record=json_decode($order["record"],true);
			}

			if($orderstatus==4 && $order["status"]!=4){
				$changed_flag=true;
				error_log("removing order orderid=".$orderid." user_id=".$user_id);
				$result=removeOrder($orderid,$user_id,"Removed from 1C");
				error_log("removed");
				$order=$db_fabricant->getOrderById($orderid);
				$record=json_decode($order["record"],true);
			}

			if(!$changed_flag){
				error_log("No changes in order");
			}
			
			$success_orders[]=$ordercode;
			
			error_log("----------------");

		} catch (Exception $e) {
			error_log($e->getMessage());
		}
	}

	error_log("Incoming orders count: ".count($incoming_orders)." above");
	error_log("success_orders count: ".count($success_orders));
	error_log("");
	
	$response["error"]=false;
	$response["success"]=1;
	$response["message"]="Success orders count=".count($success_orders)." from ".count($incoming_orders);
	$response["success_orders"]=$success_orders;
	echoResponse(200, $response);
});

/**
 * Одноразовый скрипт для связки кодов 1С и id уже существующих в системе контрагентов Кустук
 * method POST
 * file - XLS файл со связкой
 * Столбцы в excel файле: 1-id; 2-code1c в 127 заказчике
 */
$app->post('/relate_codes1c_of_customers_in_contractor_127_with_customerid', function() use ($app) {

	// array for final json response
	$response = array();

	verifyRequiredParams(array('phone', 'password'));

	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	//Проверяем логин и пароль
	if(!$db_profile->checkLoginByPhone($phone,$password)){
		//Проверяем доступ админской части группы
		$response['error'] = true;
		$response['message'] = 'Login failed. Incorrect credentials';
		echoResponse(200,$response);
		return;
	}

	$contractorid=127;//Указываем id контрактора

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

		$filename = date('dmY').'-'.uniqid('1c_customerid_and_code1c_kustuk-tmp-').".xls";
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;

		//Считываем закодированный файл xls в строку
		$data = file_get_contents($tmpFile);

		//Декодируем строку из base64 в нормальный вид
		//$data = base64_decode($data);

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


		error_log("-------------relate_codes1c_of_customers_in_contractor_127_with_customerid----------------");
		error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."|");

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

		for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
			$cells = array();

			$customerid= intval($worksheet->getCellByColumnAndRow(0, $rowIndex)->getValue());
			$customercode = $worksheet->getCellByColumnAndRow(1, $rowIndex)->getValue();
			$trackid = $worksheet->getCellByColumnAndRow(2, $rowIndex)->getValue();


			//Пустые, там где нет кода 1С пропускаем
			if(empty($customerid) || empty($customercode))continue;

			//Существует ли группа?
			$customer=$db_profile->getGroupById($customerid);
			if(!isset($customer))continue;

			//Группа - это заказчик?
			if(!$db_profile->isCustomer($customerid))continue;

			//$db_profile->setCustomerCodeInContractor($customerid, $customercode, $contractorid);

			$row=array();
			$row["customerid"]=$customerid;
			$row["customercode"]=$customercode;
			$row["trackid"]=$trackid;

			if(!empty($trackid)){

				$userid=getUserIdByTrackId($trackid);

				if(!empty($userid)){
					$db_profile->addUserToGroup($customerid,$userid,8);
					$row["userid"]=$userid;
				}
			}


			$rows[]=$row;

		}

		error_log("Count of rows: ".count($rows));


		error_log(" ");


	//} catch (Exception $e) {
		// Exception occurred. Make error flag true
		//$response["error"] = true;
		//$response["message"] = $e->getMessage();
		//$response["success"] = 0;
		//$response = $e->getMessage();
	//}

	echoResponse(200, $rows);
});

/**
 * Одноразовый скрипт для связки кодов 1С и id уже существующих в системе контрагентов Кустук
 * method POST
 * file - XLS файл со связкой
 * Столбцы в excel файле: 1-id; 2-code1c в 127 заказчике
 */
$app->get('/relate_user_with_track/:password/:phone/:incoming_trackid', function($password,$phone,$incoming_trackid) use ($app) {

	// array for final json response
	$response = array();

	//verifyRequiredParams(array('password','phone', 'incoming_trackid'));

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	//Проверяем логин и пароль
	if(!$db_profile->checkLoginByPhone($phone,$password)){
		//Проверяем доступ админской части группы
		$response['error'] = true;
		$response['message'] = 'Login failed. Incorrect phone or password';
		echoResponse(200,$response);
		return;
	}

	$contractorid=127;//Указываем id контрактора

	$user=$db_profile->getUserByPhone($phone);
	$userid=$user["id"];

	//try{


		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/29092017-1c_customerid_and_code1c_kustuk-tmp-59cdd5374d6f5.xls';


		error_log("-------------relate_user_with_track----------------");
		error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."incoming_trackid=".$incoming_trackid."|");

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

		for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
			$cells = array();

			$customerid= intval($worksheet->getCellByColumnAndRow(0, $rowIndex)->getValue());
			$customercode = $worksheet->getCellByColumnAndRow(1, $rowIndex)->getValue();
			$trackid = $worksheet->getCellByColumnAndRow(2, $rowIndex)->getValue();
			$name = $worksheet->getCellByColumnAndRow(3, $rowIndex)->getValue();
			$address = $worksheet->getCellByColumnAndRow(6, $rowIndex)->getValue();



			//Пустые, там где нет кода 1С пропускаем
			if(empty($customerid) || empty($customercode))continue;

			//Существует ли группа?
			$customer=$db_profile->getGroupById($customerid);
			if(!isset($customer))continue;

			//Группа - это заказчик?
			if(!$db_profile->isCustomer($customerid))continue;

			//$db_profile->setCustomerCodeInContractor($customerid, $customercode, $contractorid);

			$row=array();
			$row["customerid"]=$customerid;
			$row["customercode"]=$customercode;
			$row["name"]=$name;
			$row["address"]=$address;
			$row["trackid"]=$trackid;

			if(!empty($trackid)){

				if($incoming_trackid==$trackid){
					$db_profile->addUserToGroup($customerid,$userid,8);
					$row["userid"]=$userid;
				}
			}


			$rows[]=$row;

		}

		error_log("Count of rows: ".count($rows));


		error_log(" ");


	//} catch (Exception $e) {
		// Exception occurred. Make error flag true
		//$response["error"] = true;
		//$response["message"] = $e->getMessage();
		//$response["success"] = 0;
		//$response = $e->getMessage();
	//}

	echoResponse(200, $rows);
});

/**
 * Одноразовый скрипт для связки кодов 1С и id уже существующих в системе контрагентов Кустук
 * method POST
 * file - XLS файл со связкой
 * Столбцы в excel файле: 1-id; 2-code1c в 127 заказчике
 */
$app->get('/recalculate_in_order_rest', function() use ($app) {

	// array for final json response

	$contractorid=127;
	$products_main=array();//empty
	consoleCommandRecalculateProductsRest($contractorid,$products_main);

	echoResponse(200, $products_main);
});

//Нужен для того чтобы найти userid агента по номеру маршрута
function getUserIdByTrackId($trackid){
	switch($trackid){
		case 4 : return 232;
	}
	return null;
}

//Используется в 1c_order_pass_kustuk
function createCustomer($name,$address,$phone,$info,$creater_id,$agent_id){
	// creating new contracotor
	$db = new DbHandlerProfile();

	$status = 1;
	$type = 1;
	$new_id = $db->createGroupWeb($name, $address, $phone, $status, $type, $info);


	$response = array();
	if ($new_id != NULL) {


		//Супер-админ
		$db->addUserToGroup($new_id,$creater_id,1);

		//Агент
		if(!empty($agent_id)){
			$db->addUserToGroup($new_id,$agent_id,8);
		}

		$response["error"] = false;
		$response["message"] = "Customer created successfully";
		$response["id"] = $new_id;

		//Console command notify group
		try{
			$json_header=array();
			$json_header["console"]="createCustomer";
			$json_header["operation"]=M_CONSOLE_OPERATION_GROUP_CHANGED;
			$json_header["groupid"]=$new_id;

			$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			$response['consoleError']=true;
			$response['consoleErrorMessage'] = "Error:".$e->getMessage();
		}

	}else {
		$response["error"] = true;
		$response["message"] = "Failed to create customer. Please try again";
	}

	return $response;
}

//--------------------Orders Utils------------------------

function createOrder($json_order,$user_id){

	$response = array();

	try{

		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();

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

		$usercode=$db_profile->getUserCodeInContractorById($user_id,$order["contractorid"]);
		if(isset($usercode)){
			$record["customerUserCode"]=$usercode;
		}

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


	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
		//$response['make_record_logs'] = "make_record_logs: ".implode (" , ",$record["make_record_logs"]);
	}

	return $response;

}

function updateOrder($old_order_id,$order,$user_id) {

	try{

		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();

		$response = array();

		$old_order=$db_fabricant->getOrderById($old_order_id);
		$old_order_record=json_decode($old_order["record"],JSON_UNESCAPED_UNICODE);

		checkUserPermissionToOrder($old_order_id,$order);
		check1CSynchronizingPermissions($old_order_id);//Если стоит запрет на изменение заказов импортированных в 1С

		$record=makeOrderRecord($order);
		$record["id"]=$old_order_id;
		$record["created_at"]=$old_order_record["created_at"];
		$record["updated"]=true;

		//Customer user info to record
		if(isset($old_order_record["customerUserId"]))$record["customerUserId"]=$old_order_record["customerUserId"];
		if(isset($old_order_record["customerUserName"]))$record["customerUserName"]=$old_order_record["customerUserName"];
		if(isset($old_order_record["customerUserPhone"]))$record["customerUserPhone"]=$old_order_record["customerUserPhone"];
		if(isset($old_order_record["customerUserCode"]))$record["customerUserCode"]=$old_order_record["customerUserCode"];

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

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

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

		//Если стоит запрет на изменение заказов импортированных в 1С
		check1CSynchronizingPermissions($orderid);

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

function removeOrder($orderid,$user_id,$comment) {

	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$console_response=array();

	try{

		$order=$db_fabricant->getOrderById($orderid);

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



	} catch (Exception $e) {
		error_log($e->getMessage());
	}

	return  $console_response;
}

function addVisaToOrder($orderid,$user_id) {

	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$response = array();

	try{

		$order=$db_fabricant->getOrderById($orderid);
		$record=json_decode($order["record"],true);
		$user=$db_profile->getUserById($user_id);

		//Права на установку визы
		checkVisaPermission($order["contractorid"],$order["customerid"]);

		//Не стоит ли виза уже
		if(isset($record["visa"])&&($record["visa"]==true)){
			throw new Exception("Visa already added");
		}

		//Правильный ли статус заказа для того чтобы ставить визу
		if($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING){
			throw new Exception('Order status is not correct to add visa');
		}

		//Если стоит запрет на изменение заказов импортированных в 1С
		check1CSynchronizingPermissions($orderid);

		$record["visaAddedUserId"]=$user_id;
		$record["visaAddedUserName"]=$user["name"];
		$record["visaAddedTimestamp"]=time();
		$record["visa"]=true;

		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/add_visa";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_UPDATE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);

		$response['consoleCommand_add_visa'] = $console_response["message"];

		$response['error'] = false;
		$response['message'] = "Visa has been added";
		$response['order'] = $db_fabricant->getOrderById($orderid);
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	return  $response;
}

function removeVisaFromOrder($orderid,$user_id) {

	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$response = array();

	try{

		$order=$db_fabricant->getOrderById($orderid);
		$record=json_decode($order["record"],true);
		$user=$db_profile->getUserById($user_id);

		//Права на установку визы
		checkVisaPermission($order["contractorid"],$order["customerid"]);

		//Если виза еще не стоит
		if((!isset($record["visa"]))||($record["visa"]==false)){
			throw new Exception("Visa is not added yet");
		}

		//Правильный ли статус заказа для того чтобы ставить визу
		if($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING){
			throw new Exception('Order status is not correct to remove visa');
		}

		//Если стоит запрет на изменение заказов импортированных в 1С
		check1CSynchronizingPermissions($orderid);

		$record["visaRemovedUserId"]=$user_id;
		$record["visaRemovedUserName"]=$user["name"];
		$record["visaRemovedTimestamp"]=time();
		$record["visa"]=false;

		//Console command
		$json_header=array();
		$json_header["console"]="v2/index/orders/remove_visa";
		$json_header["operation"]=M_CONSOLE_OPERATION_ORDER;
		$json_header["order_operationid"]=M_ORDEROPERATION_UPDATE;
		$json_header["senderid"]=$user_id;
		$json_header["record"]=json_encode($record,JSON_UNESCAPED_UNICODE);
		$console_response=consoleCommand($json_header);

		$response['consoleCommand_remove_visa'] = $console_response["message"];

		$response['error'] = false;
		$response['message'] = "Visa has been removed";
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
		checkUserPermissionToGroups($contractorid,$customerid);


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

		$customercode=$db_profile->getCustomerCodeInContractorById($customerid,$contractorid);
		if(isset($customercode)){
			$record["customercode"]=$customercode;
		}

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

			if(($user_status_in_customer!=0)&&($user_status_in_customer!=1)&&($user_status_in_customer!=2)){

				if(($user_status_in_customer!=8)||($user_status_in_contractor!=8)){
					throw new Exception('User have no permission');
				}
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

		checkUserPermissionToGroups($contractorid,$customerid);

		if( ($order["status"]!=$db_fabricant::STATUS_ORDER_PROCESSING)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CONFIRMED)&&($order["status"]!=$db_fabricant::STATUS_ORDER_ONWAY)&&($order["status"]!=$db_fabricant::STATUS_ORDER_CANCELED) ){
			throw new Exception('Order status is not correct for this operation');
		}

		if($new_order["contractorid"]!=$contractorid){
			throw new Exception('contractorid can not be changed');
		}

}

/**
 * Если установлена синхронизация 1С у поставщика
 * Запрет на изменение заказа после того как заказ был импортирован в 1С
 */
function check1CSynchronizingPermissions($orderid){

		$db_profile = new DbHandlerProfile();
		$db_fabricant = new DbHandlerFabricant();

		global $user_id;
		global $mode_1c_synch;

		$order=$db_fabricant->getOrderById($orderid);

		if(!isset($order)){
			throw new Exception('check1CSynchronizingPermissions orderid is not correct');
		}

		$contractorid=$order["contractorid"];

		$contractor=$db_profile->getGroupById($contractorid)[0];
		$contractor_info=json_decode($contractor["info"],true);

		//Если установлена синхронизация 1С у поставщика
		if( isset($contractor_info["1c_synchronized"]) && $contractor_info["1c_synchronized"] ){

				//Запрет на изменение заказа после того как заказ был импортирован в 1С
				if( isset($contractor["code1c"]) && !$mode_1c_synch ){
					throw new Exception("Order already in 1C and cannot be changed");
				}
		}

}

/**
 * Включена ли синхронизация 1С у поставщика
 */
function check1CSynchronizingEnabledInContractor($contractorid,$db_profile){

	$contractor=$db_profile->getGroupById($contractorid)[0];
	$contractor_info=(isset($contractor["info"]))?json_decode($contractor["info"],true):null;

	//Если установлена синхронизация 1С у поставщика
	if( isset($contractor_info["1c_synchronized"]) && $contractor_info["1c_synchronized"] ){
		return;
	}

	$response["error"] = true;
	$response["message"] = "You have no permission. 1C Synch is not enable for this contarctor";
	$response["success"] = 0;
	echoResponse(200, $response);

	global $app;
	$app->stop();
}

function checkVisaPermission($contractorid,$customerid) {
	//Проверка возможности ставить визу для текущего пользователя
	global $user_id;

	checkVisaPermissionForUserId($contractorid,$customerid,$user_id);

}

function checkVisaPermissionForUserId($contractorid,$customerid,$userid) {
	//Используется для зафиксирования заказа. Например, дать понять поставщику, что заказ одобрен агентом
	//Визу может ставить Агент или админ поставщика

	$db_profile = new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$user_status_in_contractor=$db_profile->getUserStatusInGroup($contractorid,$userid);
	$user_status_in_customer=$db_profile->getUserStatusInGroup($customerid,$userid);

	//Если админ в группе поставщика, либо агент в поставщике и одновременно агент или админ в группе заказчика. Иначе выброс исключения
	if(!(
		( ($user_status_in_contractor==1)||($user_status_in_contractor==2) ) ||
		( ($user_status_in_contractor==8)&&(($user_status_in_customer==1)||($user_status_in_customer==2)||($user_status_in_customer==8)) )
	)){
		throw new Exception('No permission to visa');
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
define("M_CONSOLE_OPERATION_NOTIFY_PRODUCTS", 7);
define("M_CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST", 8);

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
define("M_ORDEROPERATION_ADD_VISA", 7);
define("M_ORDEROPERATION_REMOVE_VISA", 8);

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

function consoleCommandRecalculateProductsRest($contractorid,$products){

		$json_header=array();
		$json_header["console"]="recalculate_products_rest";
		$json_header["operation"]=M_CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST;
		$json_header["contractorid"] = $contractorid;
		$json_header["products"] = $products;

		try{
		$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение остатков не пойдет по коммуникатору
		}
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
	
	
	sendSMS("Starting_server_user_id_".$user_id."_".$db->getUserById($user_id)["phone"]);
	

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
