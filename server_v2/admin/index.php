<?php

require_once dirname(__FILE__).'/../include/SimpleImage.php';
require_once dirname(__FILE__).'/../include/DbHandlerProfile.php';
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
require_once dirname(__FILE__).'/../include/PassHash.php';

require_once dirname(__FILE__).'/../libs/Slim/Slim.php';
require_once dirname(__FILE__).'/../communicator/WebsocketClient.php';


define('WEBSOCKET_SERVER_PORT', 8666);


\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;
$api_key = NULL;
/**
 * It used to Slim testing during installation the server
 */

$app->get('/hello/:name', function ($name) {
	$response["error"] = false;
	$response["message"] = "Hello, ".$name;
	$response["success"] = 0;
	echoResponse(100, $response);
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
		echoResponse(400, $response);
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
		$response["message"] = 'Email address is not valid';
		echoResponse(400, $response);
		$app->stop();
	}
}
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
/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('email', 'password'));
	// reading post params
	$email = $app->request()->post('email');
	$password = $app->request()->post('password');
	$response = array();
	$db = new DbHandlerProfile();
	// check for correct email and password
	if ($db->checkLogin($email, $password)) {
		// get the user by email
		$user = $db->getUserByEmail($email);
		if ($user != NULL) {
			$response["error"] = false;
			$response['apiKey'] = $user['api_key'];
			$response['user_id'] = $user['id'];
		} else {
			// unknown error occurred
			$response['error'] = true;
			$response['message'] = "An error occurred. Please try again";
		}
	}
	else {
		// user credentials are wrong
		$response['error'] = true;
		$response['message'] = 'Login failed. Incorrect credentials';
	}
    echoResponse(200, $response);
});

//--------------------Admin panel----------------------------
/**
 * Listing all users (POST method, datatables)
 * method POST
 * url /users/all
 */
$app->post('/users/all/dt', 'authenticate', function() use ($app) {

	global $user_id;

	$db = new DbHandlerProfile();
	$users = $db->getAllUsersForMonitor();
	$response = array();
	if ($users != NULL || empty($users)) {
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(count($users));
		$response["recordsFiltered"] = intval(count($users));
		$response["data"] = $users;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get users. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all groups of user (POST method)
 * method POST
 * url /users/groups/:id
 */
$app->post('/users/groups/:id', 'authenticate', function($id) use ($app) {

	global $user_id;

	$db = new DbHandlerProfile();
	$groups = $db->getGroupsOfUser($id);
	$response = array();
	if ($groups) {
		$response["error"] = false;
		$response["data"] = $groups;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get user groups. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Adding user to group
 * method POST
 * url /groups
 */
$app->post('/users/groups/status/change', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('groupid', 'userid', 'status'));
	// reading put params
	$groupid = $app->request->post('groupid');
	$userid = $app->request->post('userid');
	$status = $app->request->post('status');
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionAdminInGroup($user_id, $groupid, $db);

	$result = $db->changeUserStatusInGroup($groupid, $userid, $status);
	$response = array();
	if ($result != -1) {
		$response["error"] = false;
		$response["message"] = "User status changed in group successfully";

		consoleCommandGroupUpdated($groupid);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to change status in group. Please try again";
	}
	echoResponse(201, $response);
});
/**
 * Adding user to group
 * method POST
 * url /groups
 */
$app->post('/users/groups/status/admin', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('groupid', 'userid'));
	// reading put params
	$groupid = $app->request->post('groupid');
	$userid = $app->request->post('userid');
	$status = 2;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionAdminInGroup($user_id, $groupid, $db);

	$result = $db->changeUserStatusInGroup($groupid, $userid, $status);
	$response = array();
	if ($result != -1) {
		$response["error"] = false;
		$response["message"] = "User status changed in group successfully";

		consoleCommandGroupUpdated($groupid);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to change status in group. Please try again";
	}
	echoResponse(201, $response);
});
/**
 * Adding user to group
 * method POST
 * url /groups
 */
$app->post('/users/groups/status/common', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('groupid', 'userid'));
	// reading put params
	$groupid = $app->request->post('groupid');
	$userid = $app->request->post('userid');
	$status = 0;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionAdminInGroup($user_id, $groupid, $db);

	$result = $db->changeUserStatusInGroup($groupid, $userid, $status);
	$response = array();
	if ($result != -1) {
		$response["error"] = false;
		$response["message"] = "User status changed in group successfully";

		consoleCommandGroupUpdated($groupid);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to change status in group. Please try again";
	}
	echoResponse(201, $response);
});

/**
 * Listing all users in group
 * method POST
 * url /groups/users/:contractorid
 */
$app->post('/groups/users/:contractorid', 'authenticate', function($contractorid) use ($app) {

	global $user_id;

	$db = new DbHandlerProfile();
	$groupsuser = $db->getUsersInGroupForMonitor($contractorid);
	$response = array();
	if ($groupsuser || empty($groupsuser)) {
		$response["error"] = false;
		$response["data"] = $groupsuser;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get users in group. Please try again";
	}
	echoResponse(200, $response);
});

/**
 * Adding user to group
 * method POST
 * url /groups
 */
$app->post('/groups/adduser', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('groupid', 'userid'));
	// reading put params
	$groupid = $app->request->post('groupid');
	$userid = $app->request->post('userid');
	$status = 0;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionAdminInGroup($user_id, $groupid, $db);

	$result = $db->addUserToGroup($groupid, $userid, 0);
	$response = array();
	$response["error"] = false;
	$response["message"] = "User added successfully";

	consoleCommandGroupUpdated($groupid);

	echoResponse(201, $response);
});
/**
 * Adding user to group
 * method POST
 * url /groups
 */
$app->post('/groups/removeuser', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('groupid', 'userid'));
	// reading put params
	$groupid = $app->request->post('groupid');
	$userid = $app->request->post('userid');
	$status = 4;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionAdminInGroup($user_id, $groupid, $db);

	$result = $db->changeUserStatusInGroup($groupid, $userid, $status);
	$response = array();
	if ($result != -1) {
		$response["error"] = false;
		$response["message"] = "User removed from group successfully";

		consoleCommandGroupUpdated($groupid);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to remove user from group. Please try again";
	}
	echoResponse(201, $response);
});
//--------------------Products----------------------------

/**
 * Listing all products of contractor (POST method, datatables)
 * method POST
 * url /products/dt/:contractorid
 */
$app->post('/products/dt/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all products
	$fdb = new DbHandlerFabricant();
	$result = $fdb->getProductsOfContractor($contractorid);
	$response = array();
	if ($result != NULL || empty($result)) {
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(count($result));
		$response["recordsFiltered"] = intval(count($result));
		$response["data"] = ($result == NULL) ? array() : $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get products of contractor. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all contractor products
 * method GET
 * url /products
 */
$app->get('/products/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all users
	$fdb = new DbHandlerFabricant();
	$result = $fdb->getProductsOfContractor($contractorid);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["products"] = $result;

	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get products list. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Creating contractor product
 * method POST
 * url /products/create/empty
 */
$app->post('/products/create/empty', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('contractorid'));
	// reading post params
	$contractorid = $app->request->post('contractorid');
	$price = 0;
	$status = 1;
	// creating new product
	$fdb = new DbHandlerFabricant();
	$productid = $fdb->createProduct($contractorid, "", $price, "", $status, 0);
	$response = array();
	if ($productid != NULL) {
		$response["error"] = false;
		$response["message"] = "Product created successfully";
		$response["id"] = $productid;
		$response["contractorid"] = $contractorid;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create product. Please try again";
	}
	echoResponse(201, $response);
});
/**
 * Creating contractor product
 * method POST
 * url /products/create
 */
$app->post('/products/create', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('contractorid', 'name', 'price', 'info'));
	// reading post params
	$contractorid = $app->request->post('contractorid');
	$name = $app->request->post('name');
	$price = $app->request->post('price');
	$info = $app->request->post('info');
	$code1c = $app->request->post('code1c');
	if(!isset($code1c) || empty($code1c)){
		$code1c = 0;
	}
	$status = 1;
	// creating new product
	$fdb = new DbHandlerFabricant();
	$productid = $fdb->createProduct($contractorid, $name, $price, $info, $status, $code1c);
	$response = array();
	if ($productid != NULL) {
		$response["error"] = false;
		$response["message"] = "Product created successfully";
		$response["id"] = $productid;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create product. Please try again";
	}
	echoResponse(201, $response);
});
/**
 * Updating contractor product
 * method POST
 * url /products/:id
 */
$app->post('/products/update', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('id','name', 'price', 'info', 'status', 'code'));
	// reading put params
	$id = $app->request->post('id');
	$name = $app->request->post('name');
	$price = $app->request->post('price');
	$info = $app->request->post('info');
	$status = $app->request->post('status');
	$code = $app->request->post('code');
	// updating product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->updateProduct($id, $name, $price, $info, $status);
	$resultCode = $fdb->updateProductCode($id, $code);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product updated successfully";

		consoleCommandProductUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product failed to update. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Deleting contractor product
 * method POST
 * url /products/remove/:id
 */
$app->post('/products/remove/:id', 'authenticate', function($id) use ($app) {
	// deleting product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->removeProduct($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product deleted successfully";

		consoleCommandProductUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product failed to delete. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Publishing contractor product (changing status)
 * method POST
 * url /products/publish/:id
 */
$app->post('/products/publish/:id', 'authenticate', function($id) use ($app) {
	// updating status of product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->publishProduct($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product published successfully";

		consoleCommandProductUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product failed to publish. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Unpublish contractor product (changing status)
 * method POST
 * url /products/unpublish/:id
 */
$app->post('/products/unpublish/:id', 'authenticate', function($id) use ($app) {
	// updating status of product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->unpublishProduct($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product unpublished successfully";

		consoleCommandProductUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product failed to unpublish. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Updating contractor product 1C code
 * method POST
 * url /products/publish/:id
 */
$app->post('/products/code1c/update', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('pk','value'));
	// reading put params
	$id = $app->request->post('pk');
	$code = $app->request->post('value');
	$response = array();
	if (!is_numeric($code)) {
		$response["error"] = true;
		$response["message"] = "Product code is not valid. Please try again";
		echoResponse(200, $response);

		$app->stop();
	}
	// updating status of product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->updateProductCode($id, $code);
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product code updated successfully";

		consoleCommandProductUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product code failed to update. Please try again";
	}
	echoResponse(200, $response);
});

//--------------------Contractors----------------------------

/**
 * Listing all contractor customers (POST method, datatables)
 * method POST
 * url /contractors/customers/dt/:id
 */
$app->post('/contractors/customers/dt/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all customers
	$db = new DbHandlerProfile();
	$groups = $db->getContractorCustomers($contractorid);
	$response = array();
	if ($groups != NULL || empty($groups)) {
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(count($groups));
		$response["recordsFiltered"] = intval(count($groups));
		$response["data"] = $groups;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Select contractor by id
 * method GET
 * url /contractors/:contractorid
 */
$app->get('/contractors/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all contractor
	$db = new DbHandlerProfile();
	$result = $db->getGroupById($contractorid);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["group"] = $result[0];
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get contractor. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all contractors
 * method GET
 * url /contractors
 */
$app->get('/contractors', 'authenticate', function() use ($app) {
	// listing all contractor
	$db = new DbHandlerProfile();
	$type = 0;
	$result = $db->getAllGroupsWeb($type);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["contractors"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get contractors list. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all contractors
 * method POST
 * url /contractors
 */
$app->post('/contractors/all/dt', 'authenticate', function() use ($app) {
	// listing all contractor
	$db = new DbHandlerProfile();
	$type = 0;
	$result = $db->getAllGroupsWeb($type);
	$response = array();
	if ($result != NULL || empty($result)) {
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(count($result));
		$response["recordsFiltered"] = intval(count($result));
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Creating contractor
 * method POST
 * url /contractor
 */
$app->post('/contractors/create/empty', 'authenticate', function() use ($app) {

	global $user_id;
	// creating new contracotor
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);
	$status = 0;
	$type = 0;
	$new_id = $db->createGroupEmptyWeb("", $status, $type, "");
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Contractor created successfully";
		$response["id"] = $new_id;

		consoleCommandGroupUpdated($new_id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create contractor. Please try again";
	}

	echoResponse(201, $response);
});
/**
 * Creating contractor
 * method POST
 * url /contractor
 */
$app->post('/contractors/create', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('name', 'address', 'phone', 'info'));
	// reading put params
	$name = $app->request->post('name');
	$address = $app->request->post('address');
	$phone = $app->request->post('phone');
	$info = $app->request->post('info');
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);

	$status = 0;
	$type = 0;
	$new_id = $db->createGroupWeb($name, $address, $phone, $status, $type, $info);
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Contractor created successfully";
		$response["id"] = $new_id;

		consoleCommandGroupUpdated($new_id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create contractor. Please try again";
	}

	echoResponse(201, $response);
});
/**
 * Updating contractor
 * method PUT
 * url /contractors/:id
 */
$app->put('/contractors/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('name', 'address', 'phone', 'info'));
	// reading put params
	$name = $app->request->put('name');
	$address = $app->request->put('address');
	$phone = $app->request->put('phone');
	$info = $app->request->put('info');
	// updating contractor
	$db = new DbHandlerProfile();

	permissionInGroup($user_id, $id, $db);
	$result = $db->updateGroupWeb($id, $name, $address, $phone, $info);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Contractor updated successfully";

		consoleCommandGroupUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Contractor failed to update. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Deleting contractor
 * method DELETE
 * url /contractors/:id
 */
$app->delete('/contractors/:id', 'authenticate', function($id) use ($app) {
	// deleting contractors
	$db = new DbHandlerProfile();
	$result = $db->removeGroupWeb($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Contractor deleted successfully";

		consoleCommandGroupUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Contractor failed to delete. Please try again";
	}
	echoResponse(200, $response);
});

//---------Contractor status operations------------------------

$app->post('/contractors/publish/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);


	try{

		if(!$db->isContractor($id)){
			throw new Exception("Group is not contractor");
		}

		$status=1;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Error when change group status query");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

$app->post('/contractors/remove/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);

	try{

		if(!$db->isContractor($id)){
			throw new Exception("Group is not contractor");
		}

		$status=4;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Mysql error when change group status");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

$app->post('/contractors/make_processing/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);

	try{

		if(!$db->isContractor($id)){
			throw new Exception("Group is not contractor");
		}

		$status=0;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Mysql error when change group status");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

//--------------------Customers----------------------------

/**
 * Listing all customers
 * method GET
 * url /customers
 */
$app->get('/customers', function() use ($app) {
	// listing all customers
	$db = new DbHandlerProfile();
	$type = 1;
	$result = $db->getAllGroupsWeb($type);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["customers"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get contractors list. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all customers
 * method GET
 * url /customers
 */
$app->post('/customers/all/dt', function() use ($app) {
	// listing all customers
	$db = new DbHandlerProfile();
	$type = 1;
	$result = $db->getAllGroupsWeb($type);
	$response = array();
	if ($result != NULL || empty($result)) {
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(count($result));
		$response["recordsFiltered"] = intval(count($result));
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Creating customers
 * method POST
 * url /customers
 */
$app->post('/customers/create/empty', function() use ($app) {

	global $user_id;
	// creating new customers
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);
	$status = 0;
	$type = 1;
	$new_id = $db->createGroupEmptyWeb("", $status, $type, "");
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Customer created successfully";
		$response["id"] = $new_id;

		consoleCommandGroupUpdated($new_id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create customer. Please try again";
	}
	echoResponse(201, $response);
});
/**
 * Creating customer
 * method POST
 * url /contractor
 */
$app->post('/customers/create', 'authenticate', function() use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('name', 'address', 'phone', 'info'));
	// reading put params
	$name = $app->request->post('name');
	$address = $app->request->post('address');
	$phone = $app->request->post('phone');
	$info = $app->request->post('info');
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);

	$status = 0;
	$type = 1;
	$new_id = $db->createGroupWeb($name, $address, $phone, $status, $type, $info);
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Customer created successfully";
		$response["id"] = $new_id;

		consoleCommandGroupUpdated($new_id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create customer. Please try again";
	}

	echoResponse(201, $response);
});
/**
 * Updating customers
 * method PUT
 * url /customers/:id
 */
$app->put('/customers/:id', 'authenticate',  function($id) use ($app) {

	global $user_id;
	// check for required params
	verifyRequiredParams(array('name', 'address', 'info'));
	// reading put params
	$name = $app->request->put('name');
	$address = $app->request->put('address');
	$phone = $app->request->put('phone');
	$info = $app->request->put('info');
	// updating customers
	$db = new DbHandlerProfile();

	permissionInGroup($user_id, $id, $db);
	$result = $db->updateGroupWeb($id, $name, $address, $phone, $info);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Customer updated successfully";

		consoleCommandGroupUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Customer failed to update. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Deleting customers
 * method DELETE
 * url /customers/:id
 */
$app->delete('/customers/:id', function($id) use ($app) {
	// deleting customers
	$db = new DbHandlerProfile();
	$result = $db->removeGroupWeb($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Customer deleted successfully";

		consoleCommandGroupUpdated($id);
	}
	else {
		$response["error"] = true;
		$response["message"] = "Customer failed to delete. Please try again";
	}
	echoResponse(200, $response);
});

//---------Customer status operations------------------------

$app->post('/customers/make_processing/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);

	try{

		if(!$db->isCustomer($id)){
			throw new Exception("Group is not customer");
		}

		$status=0;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Mysql error when change group status");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

$app->post('/customers/make_verified/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);

	try{

		if(!$db->isCustomer($id)){
			throw new Exception("Group is not customer");
		}

		$status=2;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Mysql error when change group status");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

$app->post('/customers/make_not_verified/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);

	try{

		if(!$db->isCustomer($id)){
			throw new Exception("Group is not customer");
		}

		$status=1;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Mysql error when change group status");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

$app->post('/customers/remove/:id',  'authenticate', function($id) use ($app) {

	global $user_id;
	$db = new DbHandlerProfile();
	permissionFabricantAdmin($user_id);

	try{

		if(!$db->isCustomer($id)){
			throw new Exception("Group is not customer");
		}

		$status=4;

		if(!$db->changeGroupStatus($status, $id)){
			throw new Exception("Mysql error when change group status");
		}

		consoleCommandGroupUpdated($id);

		$response['error'] = false;
		$response['message'] = "Group status changed";
		$response['group'] = $db->getGroupById($id)[0];
		$response['success'] = 1;

	} catch (Exception $e) {

		$response['error'] = true;
		$response['message'] = $e->getMessage();
		$response['success'] = 0;
	}

	echoResponse(200, $response);
});

//--------------------Orders----------------------------

/**
 * New order notify
 * method GET
 * url /newordernotify/:id
 */
$app->get('/orders/notify/:id', 'authenticate', function($id) use ($app) {
	$db = new DbHandlerFabricant();
	$result = $db->newOrderNotify($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["count"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get orders list. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all orders
 * method POST
 * url /orders/dt/:contractorid
 */
$app->post('/orders/dt/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all orders
	$db = new DbHandlerFabricant();
	$result = $db->getAllOrdersOfContractorWeb($contractorid);
	$response = array();
	if ($result != NULL || empty($result)) {
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(10);
		$response["recordsFiltered"] = intval(10);
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get orders list. Please try again";
	}
	echoResponse(200, $response);
});

//--------------------Uploads----------------------------

/**
 * Save 2 excel for 1c
 * method GET
 * url /savetoexcelc/:id
 */
$app->get('/savetoexcelc/:id', 'authenticate', function($id) use ($app) {
	$db = new DbHandlerFabricant();
	$response = array();
	try {
		$selectedstring = $app->request->get('selected');
		if (empty($selectedstring))
			throw new Exception('Ни одна строка не выделена');

		$selected = json_decode($selectedstring);
		$orders = $db->getAllOrdersOfContractorWeb($id);
		if ($orders == null)
			throw new Exception('Нет заказов в базе данных');

		$data = array();
		foreach ($orders as $order) {
			if (in_array($order["id"], $selected)) {
				foreach ($order["items"] as $item) {
					$tmp = array();
					$tmp["id"] = $order["id"];
					$tmp["created_at"] = $order["created_at"];
					$tmp["customerid"] = $order["customerid"];
					$tmp["customerName"] = $order["customerName"];
					$tmp["customerUserName"] = $order["customerUserName"];
					$tmp["address"] = $order["address"];
					$tmp["phone"] = $order["phone"];
					$tmp["itemid"] = $db->getProductCodeById($item["productid"]);
					$tmp["itemname"] = $item["name"];
					$tmp["itemcount"] = $item["count"];
					if (isset($item["sale"]) && !empty($item["sale"]) && isset($item["sale"]["price_with_sale"]) && !empty($item["sale"]["price_with_sale"]))
						$tmp["itemprice"] = $item["sale"]["price_with_sale"];
					else
						$tmp["itemprice"] = $item["price"];
                    if (isset($order["installment_time_notification"]) && !empty($order["installment_time_notification"]))
                        $tmp["installment_time_notification"] = $order["installment_time_notification"];
                    else
                        $tmp["installment_time_notification"] = "";
					$data[] = $tmp;
				}
			}
		}
		// Подключаем класс для работы с excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
		// Подключаем класс для вывода данных в формате excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/Writer/Excel5.php';
		// New PHPExcel class
		$xls = new PHPExcel();
		// Set and get active sheet
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();
		// Sheet title
		$sheet->setTitle('Заказы');
		$sheet->setCellValue("A1", 'Номер заказа');
		$sheet->getColumnDimension('A')->setAutoSize(true);
		$sheet->setCellValue("B1", 'Дата заказа');
		$sheet->getColumnDimension('B')->setAutoSize(true);
		$sheet->setCellValue("C1", 'ИД покупателя');
		$sheet->getColumnDimension('C')->setAutoSize(true);
		$sheet->setCellValue("D1", 'Наименование покупателя');
		$sheet->getColumnDimension('D')->setAutoSize(true);
		$sheet->setCellValue("E1", 'ФИО покупателя');
		$sheet->getColumnDimension('E')->setAutoSize(true);
		$sheet->setCellValue("F1", 'Адрес покупателя');
		$sheet->getColumnDimension('F')->setAutoSize(true);
		$sheet->setCellValue("G1", 'Телефон покупателя');
		$sheet->getColumnDimension('G')->setAutoSize(true);
		$sheet->setCellValue("H1", 'ИД товара');
		$sheet->getColumnDimension('H')->setAutoSize(true);
		$sheet->setCellValue("I1", 'Наименование товара');
		$sheet->getColumnDimension('I')->setAutoSize(true);
		$sheet->setCellValue("J1", 'Количество товара');
		$sheet->getColumnDimension('J')->setAutoSize(true);
		$sheet->setCellValue("K1", 'Цена товара');
		$sheet->getColumnDimension('K')->setAutoSize(true);
        $sheet->setCellValue("L1", 'Рассрочка');
        $sheet->getColumnDimension('L')->setAutoSize(true);
		foreach ($data as $orderskey => $order) {
			$i=0;
			foreach ($order as $recordkey => $record) {
				if($i==7) {
					$type = PHPExcel_Cell_DataType::TYPE_STRING;
					$sheet->getCellByColumnAndRow($i++, $orderskey+2)->setValueExplicit(strval($record), $type);
				}
				else{
					$sheet->setCellValueByColumnAndRow($i++, $orderskey+2, $record);
				}
			}
		}
		$filename = date('dmY').'-'.uniqid('1c-').".xls";
		$objWriter = new PHPExcel_Writer_Excel5($xls);
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;
		$objWriter->save($path);

		$response["error"] = false;
		$response["message"] = 'Файл успешно создан';
		$response["url"] = 'http://'.$_SERVER["HTTP_HOST"].'/v2/reports/'.$filename;
	}
	catch (Exception $e) {
		// Exception occurred. Make error flag true
		$response["error"] = true;
		$response["message"] = $e->getMessage();
	}
	echoResponse(200, $response);
});

/**
 * Save 2 excel
 * method GET
 * url /savetoexcel/:id
 */
$app->get('/savetoexcel/:id', 'authenticate', function($id) use ($app) {
	$db = new DbHandlerFabricant();
	$response = array();
	try {
		$selectedstring = $app->request->get('selected');
		if (empty($selectedstring))
			throw new Exception('Ни одна строка не выделена');

		$selected = json_decode($selectedstring);
		$orders = $db->getAllOrdersOfContractorWeb($id);
		if ($orders == null)
			throw new Exception('Нет заказов в базе данных');

		$productsid = $productsname = array();
		$productsname[] = "Покупатель";
		$productsres = $db->getPublishedProductsOfContractor($id);
		if ($productsres == null)
			throw new Exception('Нет продуктов в базе данных');
		else {
			foreach ($productsres as $value) {
				$productsid[] = $value["id"];
				$productsname[] = $value["name"];
			}
		}
		$data = array();

		foreach ($orders as $order) {
            if (in_array($order["id"], $selected)) {
                $orderproducts = array();
                $orderproducts[] = $order["customerName"];
                foreach ($productsid as $product) {
                    $cnt = 0;
                    foreach ($order["items"] as $item) {
                        if ($item["productid"] == $product) {
                            $cnt = $item["count"];
                        }
                    }
                    $orderproducts[] = $cnt;
                }
                $data[] = $orderproducts;
            }
        }
		// Подключаем класс для работы с excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
		// Подключаем класс для вывода данных в формате excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/Writer/Excel5.php';
		// New PHPExcel class
		$xls = new PHPExcel();
		// Set and get active sheet
		$xls->setActiveSheetIndex(0);
		$sheet = $xls->getActiveSheet();
		// Sheet title
		$sheet->setTitle('Заказы');

		foreach ($productsname as $i=>$product) {
			$sheet->setCellValueByColumnAndRow($i, 1, $product);
		}

		foreach ($data as $orderskey => $order) {
			$i=0;
			foreach ($order as $itemkey => $item) {
				$sheet->setCellValueByColumnAndRow($itemkey, $orderskey+2, $item);
			}
		}

		$filename = date('dmY').'-'.uniqid().".xls";
		//header('Content-type:application/vnd.ms-excel');
		//header('Content-Disposition:attachment;filename="'.$filename.'"');
		$objWriter = new PHPExcel_Writer_Excel5($xls);
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;

		$objWriter->save($path);

		$response["error"] = false;
		$response["message"] = 'Файл успешно создан';
		$response["url"] = 'http://'.$_SERVER["HTTP_HOST"].'/v2/reports/'.$filename;
	}
	catch (Exception $e) {
		// Exception occurred. Make error flag true
		$response["error"] = true;
		$response["message"] = $e->getMessage();
	}
	echoResponse(200, $response);
});

/**
 * Uploading xls file
 * method POST
 * url /xls/upload:id
 */
$app->post('/xls/upload/:id', 'authenticate', function($id) use ($app) {
	// array for final json response
	$response = array();
	try{
		// Check if the file is missing
		if (!isset($_FILES["xls"]["name"])) {
			throw new Exception('Not received any file!F');
		}
		// Check the file size
		if($_FILES["xls"]["size"] > 100*1024*1024) {
			throw new Exception('File is too big');
		}

		// Подключаем класс для работы с excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
		// Подключаем класс для вывода данных в формате excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

		$tmpFile = $_FILES["xls"]["tmp_name"];

		$filename = date('dmY').'-'.uniqid('1c-import-').".xls";
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;

		if (move_uploaded_file($tmpFile, $path)) {
			$objPHPExcel = PHPExcel_IOFactory::load($path);
			// Set and get active sheet
			$objPHPExcel->setActiveSheetIndex(0);
			$worksheet = $objPHPExcel->getActiveSheet();
			$worksheetTitle = $worksheet->getTitle();
			$highestRow = $worksheet->getHighestRow();
			$highestColumn = $worksheet->getHighestColumn();
			$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
			$nrColumns = ord($highestColumn) - 64;

			$fdb = new DbHandlerFabricant();
			for ($row = 3; $row <= $highestRow; ++$row) {
				$val = array();
				for ($col = 1; $col < $highestColumnIndex; ++$col) {
					$cell = $worksheet->getCellByColumnAndRow($col, $row);
					$val[] = $cell->getValue();
				}

				$info = array();

				$info["name"] = array("text" => $val[0]);
				$info["name_full"] = array("text" => $val[0]);
				$info["price"] = $val[5];
				$info["summary"] = array("text" => "");
				$info["prices"] = array();
				$info["prices"][] = array("name" => "installment_49", "value" => ceil($val[5] * 1.03));
				$info["tags"] = array();
				$info["icon"] = array("image_url" => "");

				$slides = array();
				$slides[] = array("photo" => $info["icon"], "title" => $info["summary"]);
				$details = array();
				$details[] = array("type" => 2, $slides);
				//$info["details"] = $details;

				$infojson = json_encode($info, JSON_UNESCAPED_UNICODE);
				$status = 1;

				$result = $fdb->createProduct($id, $val[0], $val[5], $infojson, $status, $val[1]);

				//$fdb->publishProduct($result);
			}

			$response["message"] = 'Proucts updated successfully!';
			$response["error"] = false;
			$response["success"] = 1;
		}
		else {
			throw new Exception('Can not upload image!F');
		}
	} catch (Exception $e) {
		// Exception occurred. Make error flag true
		$response["error"] = true;
		$response["message"] = $e->getMessage();
		$response["success"] = 0;
	}
	echoResponse(200, $response);
});

/**
 * Получает остатки товаров и высталяет наличие или не-наличие
 * method POST
 */
$app->post('/1c_products', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_state_".$_FILES["xls"]["name"]."----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."|");

	$db_profile=new DbHandlerProfile();

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


	//try{

		if (!isset($_FILES["xls"])) {
			throw new Exception('Param xls is missing');
		}
		// Check if the file is missing
		if (!isset($_FILES["xls"]["name"])) {
			throw new Exception('Property name of xls param is missing');
		}
		// Check the file size >100MB
		if($_FILES["xls"]["size"] > 100*1024*1024) {
			throw new Exception('File is too big');
		}

		$tmpFile = $_FILES["xls"]["tmp_name"];

		$filename = date('dmY').'-'.uniqid('1cstate-tmp-').".xls";
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

		//ошибка декодинга
		if(!$success){
			throw new Exception('Failed when decoding the recieved file');
		}

		// Подключаем класс для работы с excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
		// Подключаем класс для вывода данных в формате excel
		require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

		$objPHPExcel = PHPExcel_IOFactory::load($path);
		// Set and get active sheet
		$objPHPExcel->setActiveSheetIndex(0);
		$worksheet = $objPHPExcel->getActiveSheet();
		$worksheetTitle = $worksheet->getTitle();
		$highestRow = $worksheet->getHighestRow();
		$highestColumn = $worksheet->getHighestColumn();
		$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
		$nrColumns = ord($highestColumn) - 64;


		$db_fabricant = new DbHandlerFabricant();
		
		$count_of_changed_products=0;
		$count_of_products=0;
		$count_of_unknown_products=0;
		
		$products=array();

		for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
			$cells = array();
			
			$count_of_products++;

			for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
				$cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
				$cells[] = $cell->getValue();
			}

			$code=intval($cells[0]);
			$nomenclature=$cells[1];
			$before_rest=floatval($cells[2]);
			$after_rest=floatval($cells[3]);
			$move_in=floatval($cells[4]);
			$move_out=floatval($cells[5]);

			$product=$db_fabricant->getProductByCode($contractorid,$code);

			//Если код продукта не существует в контракторе, создаем новый продукт
			if(!isset($product)){
				/*$name="(Empty)";
				try{	
					$string = iconv('utf-8', 'cp1252', $nomenclature);					
					$name = iconv('cp1251', 'utf-8', $string);
				//$name=mb_convert_encoding($nomenclature,"ISO-8859-15","CP1251");
				}catch(Exception $e){
					error_log("Product code=".$code." iconv error");
					$name=$nomenclature;
				}
				
				$productid = $db_fabricant->createProduct($contractorid, $name, 0.0, "", 1, $code);
				$product=$db_fabricant->getProductById($productid);
				*/
				error_log("Product code=".$code." missing");
				$count_of_unknown_products++;
				$product=array();
				$product["id"]=0;
				$product["code1c"]=$code;				
				$product["name"]=$nomenclature;
				$product["price"]=0;
				$product["info"]="{}";
				$product["status"]=-1;
				$product["changed_at"]=0;
				
			}
			
			//Сохраняем эти данные
			$product["after_rest"]=$after_rest;

			//Находим минимальный остаток
			$min_rest=getMinRest($product);

			if( $move_in!=0 || $move_out!=0 ){

				if($after_rest>$min_rest){
					//Продукт только-что появился в наличии
					//error_log("Product code=".$code." id=".$product["id"]." reciepte"." move_in=".$move_in." move_out=".$move_out." before=".$before_rest." after=".$after_rest." min_rest=".$min_rest);
					$db_fabricant->makeProductInStock($product["id"]);
					consoleCommandProductUpdated($product["id"]);
					$count_of_changed_products++;
				}else if($after_rest<=$min_rest){
					//Продукт только-что закончился
					//error_log("Product code=".$code." id=".$product["id"]." not in stock"." move_in=".$move_in." move_out=".$move_out." before=".$before_rest." after=".$after_rest." min_rest=".$min_rest);
					$db_fabricant->makeProductNotInStock($product["id"]);
					consoleCommandProductUpdated($product["id"]);
					$count_of_changed_products++;
				}

			}
			
			$products[]=$product;

		}
		error_log(" ");

		if($count_of_products>0){
			error_log("count_of_products=$count_of_products");
			$response["all"] = $count_of_products;
		}
		if($count_of_changed_products>0){
			error_log("count_of_changed_products=$count_of_changed_products");
			$response["changed"] = $count_of_changed_products;
		}
		if($count_of_unknown_products>0){
			error_log("count_of_missing_products=$count_of_unknown_products");
			$response["unknown"] = $count_of_unknown_products;
		}


	//} catch (Exception $e) {
		// Exception occurred. Make error flag true
		//$response["error"] = true;
		//$response["message"] = $e->getMessage();
		//$response["success"] = 0;
		//$response = $e->getMessage();
	//}
	
	$xls_out=getExcelOfProducts($products);
	
	// Выводим содержимое файла
	$objWriter = new PHPExcel_Writer_Excel5($xls_out);
	$objWriter->save('php://output');
	
});

/**
 * Возвращает отчет о всех товарах в виде Excel файла
 * method POST
 */
$app->post('/1c_products_report', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_products_report----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."|");

	$db_profile=new DbHandlerProfile();
		
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

	$db_fabricant = new DbHandlerFabricant();
	$products=$db_fabricant->getProductsOfContractor($contractorid);

	$xls_out=getExcelOfProducts($products);
	
	// Выводим содержимое файла
	$objWriter = new PHPExcel_Writer_Excel5($xls_out);
	$objWriter->save('php://output');
	
});

function getExcelOfProducts($products){
	
	// Подключаем класс для работы с excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
	// Подключаем класс для вывода данных в формате excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';
		
	// New PHPExcel class
	$xls = new PHPExcel();

	$sheet = $xls->setActiveSheetIndex(0);
	
	//Заполнение шапки
	$sheet->setCellValue("A1", 'ИД');
	$sheet->setCellValue("B1", 'Код');		
	$sheet->setCellValue("C1", 'Наименование');
	$sheet->setCellValue("D1", 'Цена');
	$sheet->setCellValue("E1", 'Нет в наличии');
	$sheet->setCellValue("F1", 'Статус');
	
	$row_index=2;
	
	foreach($products as $product){
		
		$sheet->setCellValue("A$row_index", $product["id"]);
		$sheet->setCellValue("B$row_index", $product["code1c"]);
		$sheet->setCellValue("C$row_index", $product["name"]);
		$sheet->setCellValue("D$row_index", $product["price"]);
		
		try{
			$info=json_decode($product["info"],true);
			$tags=$info["tags"];
			
			if(in_array("not_in_stock",$info["tags"])){
				$sheet->setCellValue("E$row_index", "Нет в наличии(".$product["after_rest"].")");
			}else{
				$sheet->setCellValue("E$row_index", $product["after_rest"]);
			}
		}catch(Exception $e){}
		
		$status="Неизвестный";
		switch($product["status"]){
			case -1: $status="Отсутствует";break;
			case 1: $status="Создан";break;
			case 2: $status="Опубликован";break;
			case 3: $status="Снят с публикации";break;
			case 4: $status="Удален";break;			
		}		
		$sheet->setCellValue("F$row_index", $status);
		
		if($product["changed_at"]>0)
			$sheet->setCellValue("G$row_index", date('Y-m-d H:i:s',$product["changed_at"]));
		
		$row_index++;
	}
	
	// Выводим HTTP-заголовки
	 header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
	 header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
	 header ( "Cache-Control: no-cache, must-revalidate" );
	 header ( "Pragma: no-cache" );
	 header ( "Content-type: application/vnd.ms-excel" );
	 header ( "Content-Disposition: attachment; filename=matrix.xls" );

	return $xls;
}

/**
 * Возвращает дельту заказов (новые, измененные)
 * method POST
 */
$app->post('/1c_orders/:phone/:password/:contractorid/:last_timestamp', function($phone,$password,$contractorid,$last_timestamp) use ($app) {
	// array for final json response
	$response = array();

	/*verifyRequiredParams(array('contractorid', 'phone', 'password',"last_timestamp"));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');
	$last_timestamp = $app->request->post('last_timestamp');
	*/

	$phone="7".$phone;

	error_log("-------------1c_orders----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."_lasttimestamp=".$last_timestamp."|");

	//Формируем timestamp для последних 3-х дней
	$date = date("M-d-Y", mktime(0, 0, 0, date('m'), date('d') - 3, date('Y')));
	$last_timestamp=strtotime($date);

	$db_profile=new DbHandlerProfile();

	//Проверяем логин и пароль
	if(!$db_profile->checkLoginByPhone($phone,$password)){
		//Проверяем доступ админской части группы
		$response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
		echoResponse(200,$response);
		return;
	}

	//Проверяем доступ к группе
	$user=$db_profile->getUserByPhone($phone);
	permissionAdminInGroup($user["id"],$contractorid,$db_profile);

	$db_fabricant=new DbHandlerFabricant();
	$orders=$db_fabricant->getOrdersDeltaOfContractor($contractorid,$last_timestamp);

	// Подключаем класс для работы с excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
	// Подключаем класс для вывода данных в формате excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/Writer/Excel5.php';

	// New PHPExcel class
	$xls = new PHPExcel();

	$sheet_index = 0;
	foreach($orders as $order){
		
		//Только заказы в обработке импортируются в 1С
		if($order["status"]!=1){
			continue;
		}
	
		//Создание нового листа, первый создается по умолчанию
		if($sheet_index > 0){
			$xls->createSheet();
		}

		//Заголовок листа
		$sheet = $xls->setActiveSheetIndex($sheet_index);
		$sheet->setTitle('Заказ_'.$order["id"]);

		//Заполнение шапки
		$sheet->setCellValue("B1", 'ID Заказа');
		$sheet->setCellValue("C1", $order["id"]);		
		$sheet->setCellValue("D1", date('Y-m-d H:i:s',$order["changed_at"]));
		$sheet->setCellValue("B2", 'Статус заказа');
		$sheet->setCellValue("C2", $order["status"]);
		
		$sheet->setCellValue("B3", 'ID Заказчика');
		$sheet->setCellValue("C3", $order["customerid"]);

		$record=json_decode($order["record"],true);

		$sheet->setCellValue("B4", 'Имя заказчика');
		$sheet->setCellValue("C4", $record["customerName"]);
		$sheet->setCellValue("B5", 'ID сотрудника заказчика');
		$sheet->setCellValue("C5", $record["customerUserId"]);
		$sheet->setCellValue("B6", 'Имя сотрудника заказчика');
		$sheet->setCellValue("C6", $record["customerUserName"]);
		$sheet->setCellValue("B7", 'Телефон сотрудника заказчика');
		$sheet->setCellValue("C7", $record["customerUserPhone"]);

		$row_index=8;

		//Ставим заголовки таблицы
		$sheet->setCellValue("A$row_index", 'Код');
		$sheet->getColumnDimension('A')->setAutoSize(true);
		$sheet->setCellValue("B$row_index", 'Наименование');
		$sheet->getColumnDimension('B')->setAutoSize(true);
		$sheet->setCellValue("C$row_index", 'ID');
		$sheet->getColumnDimension('C')->setAutoSize(true);
		$sheet->setCellValue("D$row_index", 'Цена');
		$sheet->getColumnDimension('D')->setAutoSize(true);
		$sheet->setCellValue("E$row_index", 'Количество');
		$sheet->getColumnDimension('E')->setAutoSize(true);
		$sheet->setCellValue("F$row_index", 'Сумма');
		$sheet->getColumnDimension('F')->setAutoSize(true);

		foreach ($record["items"] as $item) {

			$tmp = array();

			$tmp["id"]=$item["productid"];
			$tmp["code"] = $db_fabricant->getProductCodeById($item["productid"]);
			$tmp["name"] = $item["name"];
			$tmp["count"] = $item["count"];
			if (isset($item["sale"]) && !empty($item["sale"]) && isset($item["sale"]["price_with_sale"]) && !empty($item["sale"]["price_with_sale"]))
				$tmp["price"] = $item["sale"]["price_with_sale"];
			else
				$tmp["price"] = $item["price"];

			$tmp["amount"] = $item["amount"];

			$row_index++;

			$sheet->setCellValue("A$row_index", $tmp["code"]);
			$sheet->setCellValue("B$row_index", $tmp["name"]);
			$sheet->setCellValue("C$row_index", $tmp["id"]);
			$sheet->setCellValue("D$row_index", $tmp["price"]);
			$sheet->setCellValue("E$row_index", $tmp["count"]);
			$sheet->setCellValue("F$row_index", $tmp["amount"]);
		}

		$sheet_index++;
	}

	// Выводим HTTP-заголовки
	 header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
	 header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
	 header ( "Cache-Control: no-cache, must-revalidate" );
	 header ( "Pragma: no-cache" );
	 header ( "Content-type: application/vnd.ms-excel" );
	 header ( "Content-Disposition: attachment; filename=matrix.xls" );

	// Выводим содержимое файла
	 $objWriter = new PHPExcel_Writer_Excel5($xls);
	 $objWriter->save('php://output');

});

/**
* temp url for copy contractor products to test group
* method GET
* url /copy49to127
*/
$app->get('/copyproducts49to149', 'authenticate', function() use ($app) {

	global $user_id;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);

	$dbf = new DbHandlerFabricant();

	$contractorid = 49;
	$testContractorId = 149;
	
	//Удаляем все продукты тестового контрактора
	$deleted_count=$dbf->removeAllProductsOfContractor($testContractorId);
	
	$products = $dbf->getProductsOfContractor($contractorid);

	$response = array();

	if ($products) {
		foreach ($products as $product) {
			$copy = $dbf->createProduct($testContractorId, $product["name"], $product["price"], $product["info"], $product["status"], $product["code1c"]);
			if (!$copy) {
				// products copy error
				$response["error"] = true;
				$response["message"] = "Product " . $product["id"] . ": " . $product["name"] . " copy error.";
				echoResponse(500, $response);
				$app->stop();
			}
		}

	} else {
		// get contractor products error
		$response["error"] = true;
		$response["message"] = "Contractor " . $contractorid . " products get error.";
		echoResponse(500, $response);
		$app->stop();
	}

	$response["error"] = false;
	$response["message"] = "Removed all ".$deleted_count." products from contractor(".$contractorid."). And copied ".count($products)."from contractor(".$contractorid.") to contractor(".$testContractorId.").";
	
	error_log($response["message"]);

	echoResponse(200, $response);

});

/**
 * Uploading slides images
 * method POST
 * url /slides/upload/:prefix
 */
$app->post('/slides/upload/:prefix', 'authenticate', function($prefix) use ($app) {
	// array for final json response
	$response = array();
	try{
		// Check if the file is missing
		if (!isset($_FILES["image"]["name"])) {
			throw new Exception('Not received any file!F');
		}
		// Check the file size
		if($_FILES["image"]["size"] > 2*1024*1024) {
			throw new Exception('File is too big');
		}

		$tmpFile = $_FILES["image"]["tmp_name"];

		// Check if the file is really an image
		list($width, $height) = getimagesize($tmpFile);
		if ($width == null && $height == null) {
			throw new Exception('File is not image!F');
		}

		$postfix = $_FILES["image"]["type"];
		$filename = uniqid($prefix).'.'.substr($postfix, strrpos($postfix,'/')+1);
		if ($prefix[0] == "c")
			$dest = '/v2/images/contractors/'.$filename;
		else
			$dest = '/v2/images/products/'.$filename;

		$path = $_SERVER["DOCUMENT_ROOT"].$dest;

		if (move_uploaded_file($tmpFile, $path)) {
			$response["message"] = 'File uploaded successfully!';
			$response["url"] = $_SERVER["HTTP_HOST"].$dest;
			$response["error"] = false;
			$response["success"] = 1;
		}
		else {
			throw new Exception('Can not upload image!F');
		}

	} catch (Exception $e) {
		// Exception occurred. Make error flag true
		$response["error"] = true;
		$response["message"] = $e->getMessage();
		$response["success"] = 0;
	}
	echoResponse(200, $response);
});
/**
 * Uploading avatars
 * method POST
 * url /avatar/upload/:id
 */
$app->post('/avatar/upload/:id', 'authenticate', function($id) use ($app) {
	// array for final json response
	$response = array();
	try{
		// Check if the file is missing
		if (!isset($_FILES["image"]["name"])) {
			throw new Exception('Not received any file!F');
		}
		// Check the file size
		if($_FILES["image"]["size"] > 2*1024*1024) {
			throw new Exception('File is too big');
		}

		$tmpFile = $_FILES["image"]["tmp_name"];

		// Check if the file is really an image
		list($width, $height) = getimagesize($tmpFile);
		if ($width == null && $height == null) {
			throw new Exception('File is not image!F');
		}

		$image = new abeautifulsite\SimpleImage($tmpFile);

		$db = new DbHandlerProfile();
		$value_full=createThumb($image,size_full,$_SERVER['DOCUMENT_ROOT'].path_fulls);
		$value_avatar=createThumb($image,size_avatar,$_SERVER['DOCUMENT_ROOT'].path_avatars);
		$value_icon=createThumb($image,size_icon,$_SERVER['DOCUMENT_ROOT'].path_icons);

		if (!$db->createGroupAvatar($id,$value_full,$value_avatar,$value_icon)) {
			unlink($_SERVER['DOCUMENT_ROOT'].path_fulls.$value_full);
			unlink($_SERVER['DOCUMENT_ROOT'].path_avatars.$value_avatar);
			unlink($_SERVER['DOCUMENT_ROOT'].path_icons.$value_icon);
			throw new Exception('Failed to insert to DB');
		}

		$response["message"] = 'File uploaded successfully!';
		$response["url"] = $_SERVER["HTTP_HOST"].'/v2/images/avatars/'.$value_avatar;
		$response["error"] = false;
		$response["success"] = 1;

	} catch (Exception $e) {
		// Exception occurred. Make error flag true
		$response["error"] = true;
		$response["message"] = $e->getMessage();
		$response["success"] = 0;
	}
	echoResponse(200, $response);
});

function createThumb($image,$size,$path) {
	$image->thumbnail($size, $size);
	$format= $image->get_original_info()['format'];
	$uniqid=uniqid();
	$filename=$uniqid. '.'. $format;
	if ($image->save($path.$filename)) {
		return $filename;
	}
	else {
		return new Exception("Can not writeImage to ".$filename);
	}
}

//Возвращает минимальный остаток товара, меньше которого считается 'нет в наличии'
function getMinRest($product){
	//За мин.остаток берем одну упаковку
	try{
		$info=json_decode($product["info"],true);
		$units=$info["units"];
		foreach($units as $unit){
			if($unit["value"]>10){
				return $unit["value"];
			}
		}
	}catch(Exception $e){

	}
	//Если упаковка не задана то
	return 10;
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

//-------------------Console----------------------------------

//Operation numbers from WebsocketServer
define("M_CONSOLE_OPERATION_USER_CHANGED", 0);
define("M_CONSOLE_OPERATION_GROUP_CHANGED", 5);
define("M_CONSOLE_OPERATION_PRODUCT_CHANGED", 6);

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

function consoleCommandGroupUpdated($groupid){

		$json_header=array();
		$json_header["console"]="v2/index/create_installment";
		$json_header["operation"]=M_CONSOLE_OPERATION_GROUP_CHANGED;
		$json_header["groupid"] = $groupid;
		try{
		$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение продукта не пойдет по коммуникатору
		}

}

function consoleCommandProductUpdated($productid){

		$json_header=array();
		$json_header["console"]="v2/index/create_installment";
		$json_header["operation"]=M_CONSOLE_OPERATION_PRODUCT_CHANGED;
		$json_header["productid"] = $productid;

		try{
		$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение продукта не пойдет по коммуникатору
		}
}

$app->run();

?>
