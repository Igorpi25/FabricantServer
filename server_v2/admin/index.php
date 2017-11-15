<?php

require_once dirname(__FILE__).'/../include/SimpleImage.php';
require_once dirname(__FILE__).'/../include/DbHandlerProfile.php';
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
require_once dirname(__FILE__).'/../include/PassHash.php';

require_once dirname(__FILE__).'/../libs/Slim/Slim.php';
require_once dirname(__FILE__).'/../communicator/WebsocketClient.php';

require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

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
$app->post('/users/all', 'authenticate', function() use ($app) {

	global $user_id;

	$db = new DbHandlerProfile();
	$users = $db->getAllUsersForMonitor();
	$response = array();
	if (isset($users)) {
		$response["error"] = false;
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
 * Listing all products (POST method, datatables)
 * method POST
 * url /products/all
 */
$app->post('/products/all', 'authenticate', function() use ($app) {
	// listing all products
	$fdb = new DbHandlerFabricant();
	$result = $fdb->getAllProducts;
	$response = array();
	if (isset($result)) {
		$response["error"] = false;
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get products of contractor. Please try again";
	}
	echoResponse(200, $response);
});
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
	if (!is_numeric($id)) {
		$response["error"] = true;
		$response["message"] = "Product id is not valid. Please try again";
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
$app->post('/contractors/customers/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all customers
	$db = new DbHandlerProfile();
	$groups = $db->getContractorCustomers($contractorid);
	$response = array();
	if (isset($groups)) {
		$response["error"] = false;
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
$app->post('/contractors/all', 'authenticate', function() use ($app) {
	// listing all contractor
	$db = new DbHandlerProfile();
	$type = 0;
	$result = $db->getAllGroupsWeb($type);
	$response = array();
	if (isset($result)) {
		$response["error"] = false;
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
$app->post('/customers/all', function() use ($app) {
	// listing all customers
	$db = new DbHandlerProfile();
	$type = 1;
	$result = $db->getAllGroupsWeb($type);
	$response = array();
	if (isset($result)) {
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
 * customercode - это временное решение для UID Кустук
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
	$customercode = $app->request->put('customercode');//Временное решение для UID Кустук

	$response=createCustomer($name,$address,$phone,$info,$user_id);

	//Временное решение для UID Кустук
	if(!empty($customercode)){
		$db = new DbHandlerProfile();
		$db->setCustomerCodeInContractor($response["id"], $customercode, 127);
	}

	echoResponse(201, $response);
});
/**
 * Updating customers
 * method PUT
 * url /customers/:id
 * customercode - это временное решение для UID Кустук
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
	$customercode = $app->request->put('customercode');//Временное решение для UID Кустук

	// updating customers
	$db = new DbHandlerProfile();

	permissionInGroup($user_id, $id, $db);
	$result = $db->updateGroupWeb($id, $name, $address, $phone, $info);

	//Временное решение для UID Кустук
	if(!empty($customercode)){
		$db->setCustomerCodeInContractor($id, $customercode, 127);
	}

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

function createCustomer($name,$address,$phone,$info,$user_id){
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);

	$status = 1;
	$type = 1;
	$new_id = $db->createGroupWeb($name, $address, $phone, $status, $type, $info);
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Customer created successfully";
		$response["id"] = $new_id;

		consoleCommandGroupUpdated($new_id);

	}else {
		$response["error"] = true;
		$response["message"] = "Failed to create customer. Please try again";
	}

	return $response;
}

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
	if ($id == 0) {
		$result = $db->newOrderNotifyAll();
	} else {
		$result = $db->newOrderNotify($id);
	}

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
 * Listing all orders of cuntractor
 * method POST
 * url /orders/dt/:contractorid
 */
$app->post('/orders/contractor/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all orders
	$db = new DbHandlerFabricant();
	$result = $db->getAllOrdersOfContractorWeb($contractorid);
	$response = array();
	if (isset($result)) {
		$response["error"] = false;
		$response["ordersOfContractors"]="asdasd";
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get orders list. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all orders of cuntractor in range
 * method POST
 * url /orders/contractor/interval/:contractorid
 */
$app->post('/orders/contractor/interval/:contractorid', 'authenticate', function($contractorid) use ($app) {
	// listing all orders
	$db = new DbHandlerFabricant();
	$interval = 7;
	$result = $db->getAllOrdersOfContractorIntervalWeb($contractorid, $interval);
	$response = array();
	if (isset($result)) {
		$response["error"] = false;
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get orders list. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all orders of customer
 * method POST
 * url /orders/customer/:customerid
 */
$app->post('/orders/customer/:customerid', 'authenticate', function($customerid) use ($app) {
	// listing all orders
	$db = new DbHandlerFabricant();
	$result = $db->getAllOrdersOfCustomerWeb($customerid);
	$response = array();
	if (isset($result)) {
		$response["error"] = false;
		$response["data"] = $result;
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
 * url /orders/all
 */
$app->post('/orders/all/groups', 'authenticate', function() use ($app) {
	// listing all orders
	$db = new DbHandlerFabricant();
	$interval = 7;
	$result = $db->getAllOrdersWeb($interval);
	$response = array();
	if (isset($result)) {
		$response["error"] = false;
		$response["data"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get orders list. Please try again";
	}
	echoResponse(200, $response);
});

//---------------Пакетная обработка-------------

/**
 * Пакетная обработка
 * ЗАМЕНЯЕТ(не обновляет) информацию товаров
 * Создает если товар не создан. Если передан id, то создает товар с переданным id
 * method POST
 */
$app->post('/excel_form_products0', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------excel_form_products".$_FILES["xls"]["name"]."----------------");
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


	//-------------------Берем Excel файл----------------------------

	if (!isset($_FILES["xls"])) {
		throw new Exception('Param xls is missing');
	}

	// Check if the file is missing
	if (!isset($_FILES["xls"]["name"])) {
		throw new Exception('Property name of xls param is missing');
	}

	// Check the file size > 100MB
	if($_FILES["xls"]["size"] > 100*1024*1024) {
		throw new Exception('File is too big');
	}

	$tmpFile = $_FILES["xls"]["tmp_name"];

	$filename = date('dmY').'-'.uniqid('excel-form-products-').".xls";
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

	$products=array();
	for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
		$cells = array();

		for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
			$cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
			$cells[] = $cell->getValue();
		}

		$code1c=intval($cells[0]);
		$id=intval($cells[1]);
		$name=(empty($cells[2]))?"":$cells[2];
		$price=floatval($cells[3]);
		$group=$cells[4];
		$summary=(empty($cells[5]))?"":$cells[5];


		try{
			$photos=json_decode($cells[6],true);
		}catch(Exception $e){
			$photos=array();
		}

		try{
			$units=json_decode($cells[7],true);
		}catch(Exception $e){
			$units=array();
		}

		$details=array();
		$slides=array();

		if(count($photos)>0){

			$slider=array();
			$slider["type"]=2;

			foreach($photos as $photo){
				$slide=array();

				$slide["photo"]=array();
				$slide["photo"]["image_url"]=URL_HOME.path_products. ltrim(str_replace(trim(" \ "),"/",$photo),"/\ ");
				$slide["title"]=array();
				$slide["title"]["text"]="";

				$slides[]=$slide;
			}

			$slider["slides"]=$slides;

			$details[]=$slider;
		}

		$info=array();

		$info["name"]=array();
		$info["name"]["text"]=$name;

		$info["name_full"]=array();
		$info["name_full"]["text"]=$name;

		$info["price"]=$price;

		$info["summary"]=array();
		$info["summary"]["text"]=$summary;


		$info["icon"]=array();
		$info["icon"]["image_url"]=(count($slides)>0)?$slides[0]["photo"]["image_url"]:"";

		$info["tags"]=array();
		if(!empty(trim($group))){
			$info["tags"][]=$group;
		}

		$info["prices"]=array();
		$info["prices_data"]=array();
		$info["priority"]=0;

		$info["details"]=$details;

		$info["units"]=$units;

		$info["placeholder"]=array();
		$info["placeholder"]["image_url"]="";

		$product=$db_fabricant->getProductById($id);

		//Если код продукта не существует в контракторе, создаем новый продукт
		if(!isset($product)){

			if(empty($id)){
				$product=$db_fabricant->createProduct($contractorid, $name, $price, json_encode($info,JSON_UNESCAPED_UNICODE), 1, $code1c);
				$import_status="created";
			}else{
				$product=$db_fabricant->createProductWithId($id,$contractorid, $name, $price, json_encode($info,JSON_UNESCAPED_UNICODE), 1, $code1c);
				$import_status="createdWithId";
			}

			error_log($rowIndex.". ".$import_status." id=".$product["id"]." code1c=".$product["code1c"]." ".$product["name"]." price=".$product["price"]." group=".$group." slides:".count($slides)." units:".count($units));

		}else{

			$db_fabricant->updateProduct($id, $name, $price, json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
			$db_fabricant->updateProductCode($id, $code1c);
			error_log($rowIndex.". updated id=".$id." code1c=".$code1c." ".$name." price=".$price." group=".$group." slides:".count($slides)." units:".count($units));
		}

		$products[]=$product;
	}

	$response=array();
	$response["error"]=false;
	$response["success"]=1;
	$response["products"]=$products;

	echoResponse(200,$response);

});

/**
 * Пакетная обработка
 * Обновляет только информацию о товарах
 * method POST
 */
$app->post('/excel_form_products_update_info', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------excel_form_products_update_info".$_FILES["xls"]["name"]."----------------");
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


	//-------------------Берем Excel файл----------------------------

	if (!isset($_FILES["xls"])) {
		throw new Exception('Param xls is missing');
	}

	// Check if the file is missing
	if (!isset($_FILES["xls"]["name"])) {
		throw new Exception('Property name of xls param is missing');
	}

	// Check the file size > 100MB
	if($_FILES["xls"]["size"] > 100*1024*1024) {
		throw new Exception('File is too big');
	}

	$tmpFile = $_FILES["xls"]["tmp_name"];

	$filename = date('dmY').'-'.uniqid('excel_form_products_kustuk-').".xls";
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

	$updated_products=array();
	for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
		$cells = array();

		for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
			$cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
			$cells[] = $cell->getValue();
		}

		$id=intval($cells[1]);
		$name=$cells[2];
		$price=$cells[3];
		$group=$cells[4];
		$summary=$cells[5];

		try{
			$photos=json_decode($cells[6],true);
		}catch(Exception $e){
			$photos=null;
		}

		try{
			$units=json_decode($cells[7],true);
		}catch(Exception $e){
			$units=null;
		}

		try{
			$info_details=json_decode($cells[8]);
		}catch(Exception $e){
			$info_details=null;
		}

		// Приоритет, столбец после info
		$priority = $cells[9];

		$product=$db_fabricant->getProductById($id);

		//Если код продукта не существует в контракторе, создаем новый продукт
		if(!isset($product)){
			continue;
		}else{

			$info=json_decode($product["info"],true);

			//Чтоб не менять наименование
			/*if(isset($name)){
				$product["name"]=$name;

				$info["name"]=array();
				$info["name"]["text"]=$name;
				$info["name_full"]=array();
				$info["name_full"]["text"]=$name;

			}*/

			if(isset($summary)){
				$info["summary"]=array();
				$info["summary"]["text"]=$summary;
			}

			/*if(isset($price)){
				$product["price"];

				$info["price"]=$price;
			}*/

			if(isset($photos)>0){

				$slides=array();

				foreach($photos as $photo){
					$slide=array();

					$slide["photo"]=array();
					$slide["photo"]["image_url"]=URL_HOME.path_products. ltrim(str_replace(trim(" \ "),"/",$photo),"/\ ");
					$slide["title"]=array();
					$slide["title"]["text"]="";

					$slides[]=$slide;
				}

				if(count($slides)>0){

					$slider=array();
					$slider["type"]=2;
					$slider["slides"]=$slides;
					$details=array();
					$details[]=$slider;

					$info["details"]=$details;
					$info["icon"]=array();
					$info["icon"]["image_url"]=(count($slides)>0)?$slides[0]["photo"]["image_url"]:"";
				}
			}

			if (isset($info_details) && count($info_details) > 0) {
				$details = array();
				// если установлены слайды добавляем
				if (isset($info["details"]) && count($info["details"]) > 0) {
					$details = $info["details"];
				}

				foreach($info_details as $info_detail){
					$info_detail_obj = array();

					$info_detail_obj_explode = explode("\r\n", $info_detail, 2);

					if (count($info_detail_obj_explode) > 1) {
						$info_detail_obj["type"] = 1;
						$info_detail_obj["title"] = array();
						$info_detail_obj["title"]["text"] = $info_detail_obj_explode[0];

						$info_detail_obj["photo"] = array();
						$info_detail_obj["photo"]["image_url"] = "";

						$info_detail_obj["value"] = array();
						$info_detail_obj["value"]["text"] = $info_detail_obj_explode[1];

						$details[] = $info_detail_obj;
					}
				}

				$info["details"]=$details;
			}

			if(isset($units)){
				$info["units"]=$units;
			}

			if(isset($group)){
				if(!empty(trim($group))){
					$info["tags"]=array();
					$info["tags"][]=$group;
				}
			}

			if(isset($priority) && intval($priority) != 0) {
				$info["priority"]=intval($priority);
			}

			$db_fabricant->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);

			error_log($rowIndex.". updated id=".$id." price=".$price." group=".$group);
			$updated_products[]=$product;
		}

	}

	//Уведомляем по коммуникатору измененные продукты
	if(count($updated_products)>0){
		consoleCommandNotifyProducts($updated_products);
	}

	$response=array();
	$response["error"]=false;
	$response["success"]=1;
	$response["updated_products"]=$updated_products;

	echoResponse(200,$response);

});

/**
* temp url for copy cash to installment
* method GET
* url /copy49to127
*/
$app->post('/cashtoinstallment', 'authenticate', function() use ($app) {

	global $user_id;
	// creating new contracotor
	$db_fabricant = new DbHandlerFabricant();

	permissionFabricantAdmin($user_id);

	verifyRequiredParams(array('contractorid', 'installment_tag'));

	$contractorid = $app->request->post('contractorid');
	$installment_tag = $app->request->post('installment_tag');

	$products = $db_fabricant->getProductsOfContractor($contractorid);

	$response = array();

	if ($products) {
		foreach ($products as $product) {
			$info=json_decode($product["info"],true);

			if(!isset($info))$info=array();
			if(!isset($info["prices"]))$info["prices"]=array();

			$prices=array();
			foreach($info["prices"] as $price){
				if($price["name"]!=$installment_tag)$prices[]=$price;
			}

			$new_price=array();
			$new_price["name"]=$installment_tag;
			$new_price["value"]=$product["price"];

			$prices[]=$new_price;

			$info["prices"]=$prices;

			$db_fabricant->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
			//consoleCommandProductUpdated($product["id"]);
		}

	} else {
		// get contractor products error
		$response["error"] = true;
		$response["message"] = "Contractor " . $contractorid . " products get error.";
		echoResponse(500, $response);
		$app->stop();
	}

	$response["error"] = false;
	$response["message"] = "Count of changed products = ".count($products);

	echoResponse(200, $response);

});

//----------------------Статистика----------------------
$app->post('/excel_massive_test', function() use ($app) {
	$response=array();
	$response["error"]=false;

	echoResponse(200,$response);
});
/**
 * Обработка массива
 * method POST
 */
$app->post('/excel_massive', 'authenticate', function() use ($app) {
	global $user_id;
    $db_profile = new DbHandlerProfile();
    // только для кустука
    $contractorid = 127;
    $readAverageData = true;
    $returnResult = false;
	permissionAdminInGroup($user_id, $contractorid, $db_profile);

	$current = $app->request()->post('current');
	$count = $app->request()->post('count');
	$averageDataUrl = $app->request()->post('averageDataUrl');

    //-------------------Берем Excel файл----------------------------
    try {
        if (!isset($_FILES["statics1"]) || !isset($_FILES["statics2"])) {
	        throw new Exception('Files static is missing');
	    }
	    if (!isset($_FILES["massive"])) {
	        throw new Exception('Files massive is missing');
	    }
    	if (!isset($_FILES["statics1"]["name"]) || !isset($_FILES["statics2"]["name"])) {
        	throw new Exception('Property name of file static is missing');
    	}
    	if (!isset($_FILES["massive"]["name"])) {
		    throw new Exception('Property name of file massive is missing');
		}
	    // Check the file size > 100MB
    	if($_FILES["statics1"]["size"] > 10*1024*1024 || $_FILES["statics2"]["size"] > 100*1024*1024) {
	        throw new Exception('File static is too big');
	    }
	    if($_FILES["massive"]["size"] > 100*1024*1024) {
	        throw new Exception('File massive is too big');
	    }
	    if (!isset($current) || !isset($count) || !isset($averageDataUrl)) {
		    throw new Exception('Incorrect input data');
		}
		if ($current > $count) {
		    throw new Exception('Incorrect input data');
		}
		if ($current == $count) {
			$returnResult = true;
		}
		if (empty($averageDataUrl)) {
			$readAverageData = false;
		}
    	// max_execution_time на 5 минут 
    	set_time_limit(300);
    	ini_set('memory_limit', '256M');

    	$static1ext = pathinfo($_FILES["statics1"]["name"], PATHINFO_EXTENSION);
    	$static2ext = pathinfo($_FILES["statics2"]["name"], PATHINFO_EXTENSION);
    	if ($static1ext == "xlsm") {
    		$baseTmpFile = $_FILES["statics1"]["tmp_name"];
    		$articlesTmpFile = $_FILES["statics2"]["tmp_name"];
    	} else {
    		$baseTmpFile = $_FILES["statics2"]["tmp_name"];
    		$articlesTmpFile = $_FILES["statics1"]["tmp_name"];
    	}

        $massiveTmpFile = $_FILES["massive"]["tmp_name"];

		// настройки PHPExcel (на хабре еаписали, что помогает при работе с большими файлами)
        // https://habrahabr.ru/post/148203/
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '256M');
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

        // чтение файла с артикулами
        error_log("-------------excel_massive----------------");
        error_log("current file = ".$current);
        error_log("count of files = ".$count);
        error_log("average file url = ".$averageDataUrl);
		error_log("start of read articles file");
        $articlesReader = PHPExcel_IOFactory::createReader('Excel5');
        $articlesReader->setReadDataOnly(true);
        $articlesPHPExcel = $articlesReader->load($articlesTmpFile);
        $articlesPHPExcel->setActiveSheetIndex(0);
        $articlesWorksheet = $articlesPHPExcel->getActiveSheet();

        $articlesHighestRow = $articlesWorksheet->getHighestRow();
        $articlesHighestColumn = $articlesWorksheet->getHighestColumn();
        $articlesHighestColumnIndex = PHPExcel_Cell::columnIndexFromString($articlesHighestColumn);
        // массив с названиями колонок newform
        $newformColumnNames = array();
        // массив с артикулами, вида [ "AA" => ["0000-00" => count] ]
        $articles = array();
        for ($rowIndex = 2; $rowIndex <= $articlesHighestRow; ++$rowIndex) {
            $columnName = $articlesWorksheet->getCellByColumnAndRow(0, $rowIndex)->getValue();
            $newformColumnNames[$columnName] = $articlesWorksheet->getCellByColumnAndRow(1, $rowIndex)->getValue();
            $articles_items = array();
            for ($colIndex = 3; $colIndex < $articlesHighestColumnIndex; ++$colIndex) {
                $cellValue = trim($articlesWorksheet->getCellByColumnAndRow($colIndex, $rowIndex)->getValue());
                if (!empty($cellValue) && !in_array($cellValue, $articles_items)) {
                    $articles_items[] = $cellValue;
                }
            }
            if (count($articles_items) > 0) {
                $articles[$columnName] = $articles_items;
            }
        }
        unset($articlesReader);
        unset($articlesPHPExcel);

        error_log("start read base file");
        // чтение файла базы
        // названия столбцов для чтения из базы и индексы создаваемого массива
        //  0   С - city
        //  1   D - city add
        //  2   E - dist
        //  3   F - code
        //  4   G - ETP
        //  5   H - merch
        //  6   I - retail
        //  7   J - union name
        //  8   L - namett
        //  9   M - adrestt
        //  10  N - typett
        //  11  O - princess
        //  12  BS - active

        $baseReadSheet = 'Общая база розничных т_т_на сен';
        $baseStartRow = 9;
        $baseReader = PHPExcel_IOFactory::createReader('Excel2007');
        $baseReader->setReadDataOnly(true);
        $baseReader->setLoadSheetsOnly($baseReadSheet);
        $basePHPExcel = $baseReader->load($baseTmpFile);
        $baseWorksheet = $basePHPExcel->getSheetByName($baseReadSheet);

        $baseHighestRow = $baseWorksheet->getHighestRow();
        $baseHighestColumn = $baseWorksheet->getHighestColumn();
        $baseHighestColumnIndex = PHPExcel_Cell::columnIndexFromString($baseHighestColumn);
        $baseHighestColumn++;
        // массив с магазинами
        $checkCustomers = array();
        // последний заполненный столбец
        $activeColName = 'BS';
        // поиск последнего заполненного столбеца
        for ($colIndex = 'O'; $colIndex != $baseHighestColumn; ++$colIndex) {
            $notEmptyColName = trim($baseWorksheet->getCell($colIndex.'8')->getValue());
            if (!empty($notEmptyColName)) {
                $activeColName = $colIndex;
            }
        }
        $checkCustomersWithEmptyCode = array();
        for ($rowIndex = $baseStartRow; $rowIndex <= $baseHighestRow; ++$rowIndex) {
            $rowCustomerActivity = trim($baseWorksheet->getCell($activeColName.$rowIndex)->getValue());
            if (intval($rowCustomerActivity) === 1) {
            	$rowCustomerCity = $baseWorksheet->getCell('C'.$rowIndex)->getValue();
            	$rowCustomerCityAddit = $baseWorksheet->getCell('D'.$rowIndex)->getValue();
            	$rowCustomerDistr = $baseWorksheet->getCell('E'.$rowIndex)->getValue();

            	$rowCustomerETP = $baseWorksheet->getCell('G'.$rowIndex)->getValue();
            	$rowCustomerMerch = $baseWorksheet->getCell('H'.$rowIndex)->getValue();
            	$rowCustomerRetail = $baseWorksheet->getCell('I'.$rowIndex)->getValue();
            	$rowCustomerUnionName = $baseWorksheet->getCell('J'.$rowIndex)->getValue();
            	$rowCustomerPrincess = $baseWorksheet->getCell('O'.$rowIndex)->getValue();

                $rowCustomerCode = trim($baseWorksheet->getCell('F'.$rowIndex)->getValue());
                $rowCustomerName = $baseWorksheet->getCell('L'.$rowIndex)->getValue();
                $rowCustomerAddress = $baseWorksheet->getCell('M'.$rowIndex)->getValue();
                $rowCustomerType = $baseWorksheet->getCell('N'.$rowIndex)->getValue();
                if (empty($rowCustomerCode)) {
                	$rowCustomerCode = 'tmpCode-'.uniqid();
	                $checkCustomersWithEmptyCode[$rowCustomerCode] = [
	                    'name' => $rowCustomerName,
	                    'address' => $rowCustomerAddress,
	                    'type' => $rowCustomerType,
	                    'city' => $rowCustomerCity,
	                    'cityAddit' => $rowCustomerCityAddit,
	                    'distr' => $rowCustomerDistr,
	                    'etp' => $rowCustomerETP,
	                    'merch' => $rowCustomerMerch,
	                    'retail' => $rowCustomerRetail,
	                    'unionName' => $rowCustomerUnionName,
	                    'princess' => $rowCustomerPrincess
	                ];
                }
                $checkCustomers[$rowCustomerCode] = [
                    'name' => $rowCustomerName,
                    'address' => $rowCustomerAddress,
                    'type' => $rowCustomerType,
                    'city' => $rowCustomerCity,
                    'cityAddit' => $rowCustomerCityAddit,
                    'distr' => $rowCustomerDistr,
                    'etp' => $rowCustomerETP,
                    'merch' => $rowCustomerMerch,
                    'retail' => $rowCustomerRetail,
                    'unionName' => $rowCustomerUnionName,
                    'princess' => $rowCustomerPrincess
                ];
            }
        }
        unset($baseReader);
        unset($basePHPExcel);

        error_log("start read massive file");
        // названия столбцов для чтения из массива и индексы создаваемого массива
        // 0 - e - tp
        // 1 - f - kodtt
        // 2 - k - namett
        // 3 - l - adrestt
        // 4 - n - nndok
        // 5 - o - datadok
        // 6 - t - kodtov
        // 7 - v - astr
        // 8 - w - cat
        // 9 - x - rbp
        // 10 - y - Saleunit
        // 11 - z - Salerur
        // 12 - aa - Salekg
        // 13 - ab - sum_salerur

        $massiveReadColumns = array(
            //'E', 'F', 'K', 'L', 'N', 'O', 'T', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB'
            'E', 'F', 'K', 'L', 'T'
        );
        $massiveFilter = new excelReadFilterByColumn($massiveReadColumns);
        $massiveReader = PHPExcel_IOFactory::createReader("Excel5");
        $massiveReader->setReadFilter($massiveFilter);
        $massiveReader->setReadDataOnly(true);
        $massivePHPExcel = $massiveReader->load($massiveTmpFile);
        $massivePHPExcel->setActiveSheetIndex(0);
        $massiveWorksheet = $massivePHPExcel->getActiveSheet();

        $massiveHighestRow = $massiveWorksheet->getHighestRow();
        $massiveHighestColumn = $massiveWorksheet->getHighestColumn();
        $massiveHighestColumnIndex = PHPExcel_Cell::columnIndexFromString($massiveHighestColumn);
        $massiveHighestColumn++;
        
        error_log("end of read massive file");

        // сгруппированный массив по названию и адресу точки
        if ($readAverageData) {
        	error_log("reading average data");
	   		$tradePoints = json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$averageDataUrl), TRUE);
	   		error_log("readed average data from url = ".$_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$averageDataUrl);
        } else {
        	error_log("created new average data");
        	$tradePoints = array();
        }

        for ($rowIndex = 2; $rowIndex <= $massiveHighestRow; ++$rowIndex) {
            $rowTradePointCode = trim($massiveWorksheet->getCell('F'.$rowIndex)->getValue());
            $rowItemArticle = trim($massiveWorksheet->getCell('T'.$rowIndex)->getValue());
            if (!isset($tradePoints[$rowTradePointCode])) {
                $rowTradePointName = $massiveWorksheet->getCell('K'.$rowIndex)->getValue();
                $rowTradePointAddress = $massiveWorksheet->getCell('L'.$rowIndex)->getValue();
                // если наименование или адрес пустые, пропускаем
                if (!empty($rowTradePointName) && !empty($rowTradePointAddress)) {
                    $tradePoints[$rowTradePointCode] = array();
                    $tradePoints[$rowTradePointCode]['name'] = $rowTradePointName;
                    $tradePoints[$rowTradePointCode]['address'] = $rowTradePointAddress;
                    $tradePoints[$rowTradePointCode]['items'] = array();
                    foreach ($articles as $colName => $colArticles) {
                        if (in_array($rowItemArticle, $colArticles)) {
                            $tradePoints[$rowTradePointCode]['items'][$colName][$rowItemArticle] = 1;
                            break;
                        }
                    }
                }
            } else {
                foreach ($articles as $colName => $colArticles) {
                    if (in_array($rowItemArticle, $colArticles)) {
                        $tradePoints[$rowTradePointCode]['items'][$colName][$rowItemArticle] = 1;
                        break;
                    }
                }
            }
        }
        unset($massiveReader);
        unset($massivePHPExcel);

        $response = array();
        if (!$returnResult) {
        	if (empty($averageDataUrl)) {
        		$newAverageDataName = uniqid('newform-average-data-').'.json';
	        	$averageDataUrl = $newAverageDataName;
	        }
        	$jsondata = json_encode($tradePoints);
        	error_log("saving average data");
		   	//write json data into data.json file
		   	if(file_put_contents($_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$averageDataUrl, $jsondata)) {
		   		error_log("average data saved");
		    } else {
		    	error_log("failed to save average data");
		    }
		    $response["error"] = false;
	        $response["success"] = false;
	        $response["current"] = $current;
	        $response["count"] = $count;
	        $response["averageDataUrl"] = $averageDataUrl;
        } else {
        	error_log("start exoprt data");
	        // New PHPExcel class
	        $newformFile = new PHPExcel();
	        // Set and get active sheet
	        $newformFile->setActiveSheetIndex(0);
	        $newformDataWorkSheet = $newformFile->getActiveSheet();
	        // Sheet title
	        $newformDataWorkSheet->setTitle('Данные для newform');

	        $newformLogWorkSheet = new PHPExcel_Worksheet($newformFile, 'Данные для newform по артикулам');
	        $newformMassiveDataWorkSheet = new PHPExcel_Worksheet($newformFile, 'Логи');

	        $newformFile->addSheet($newformLogWorkSheet, 1);
	        $newformFile->addSheet($newformMassiveDataWorkSheet, 2);

	        // Заголовки листов
	        //$newformMassiveDataWorkSheet->setCellValue('H1', 'Код торговой точки');
	        //$newformMassiveDataWorkSheet->setCellValue('I1', 'Название торговой точки');
	        //$newformMassiveDataWorkSheet->setCellValue('J1', 'Адрес');
	        $newformDataWorkSheet->setCellValue('H1', 'Единое наименование сети (только для сетей)');
	        $newformDataWorkSheet->setCellValue('I1', 'Название торговой точки');
	        $newformDataWorkSheet->setCellValue('J1', 'Адрес');

	        $newformDataWorkSheet->setCellValue('A1', '№');
	        $newformDataWorkSheet->setCellValue('B1', 'Город главн.');
	        $newformDataWorkSheet->setCellValue('C1', 'Город доп.');
	        $newformDataWorkSheet->setCellValue('D1', 'Дистрибьютор');
	        $newformDataWorkSheet->setCellValue('E1', 'ЭТП');
	        $newformDataWorkSheet->setCellValue('F1', 'Мерчендайзер');
	        $newformDataWorkSheet->setCellValue('G1', 'Ключевая розница (отметить "1")');
	        $newformDataWorkSheet->setCellValue('K1', 'Тип торговой точки');
	        $newformDataWorkSheet->setCellValue('L1', 'Асс. миним. по Принцессам (20, 15, 22, 26, 35, 44)');

	        $newformLogWorkSheet->setCellValue('H1', 'Единое наименование сети (только для сетей)');
	        $newformLogWorkSheet->setCellValue('I1', 'Название торговой точки');
	        $newformLogWorkSheet->setCellValue('J1', 'Адрес');

	        $newformLogWorkSheet->setCellValue('A1', '№');
	        $newformLogWorkSheet->setCellValue('B1', 'Город главн.');
			$newformLogWorkSheet->setCellValue('C1', 'Город доп.');
			$newformLogWorkSheet->setCellValue('D1', 'Дистрибьютор');
			$newformLogWorkSheet->setCellValue('E1', 'ЭТП');
			$newformLogWorkSheet->setCellValue('F1', 'Мерчендайзер');
			$newformLogWorkSheet->setCellValue('G1', 'Ключевая розница (отметить "1")');
			$newformLogWorkSheet->setCellValue('K1', 'Тип торговой точки');
			$newformLogWorkSheet->setCellValue('L1', 'Асс. миним. по Принцессам (20, 15, 22, 26, 35, 44)');

			$newformMassiveDataWorkSheet->setCellValue('A1', 'Строка в Данные для newform');
	        $newformMassiveDataWorkSheet->setCellValue('B1', 'Код торговой точки');
	        $newformMassiveDataWorkSheet->setCellValue('C1', 'Название торговой точки');
	        $newformMassiveDataWorkSheet->setCellValue('D1', 'Адрес');

	        foreach ($newformColumnNames as $colIndex => $colName) {
	            //$newformMassiveDataWorkSheet->setCellValue($colIndex.'1', $colName);
	            $newformDataWorkSheet->setCellValue($colIndex.'1', $colName);
	            $newformLogWorkSheet->setCellValue($colIndex.'1', $colName);
	        }
	        // Данные по артикулам
	        $rowIndex = 2;
	        foreach ($checkCustomers as $checkCustomerCode => $checkCustomer) {
	        	$newformLogWorkSheet->setCellValue('H'.$rowIndex, $checkCustomer["unionName"]);
	            $newformLogWorkSheet->setCellValue('I'.$rowIndex, $checkCustomer["name"]);
	            $newformLogWorkSheet->setCellValue('J'.$rowIndex, $checkCustomer["address"]);

	            $newformLogWorkSheet->setCellValue('B'.$rowIndex, $checkCustomer["city"]);
	            $newformLogWorkSheet->setCellValue('C'.$rowIndex, $checkCustomer["cityAddit"]);
	            $newformLogWorkSheet->setCellValue('D'.$rowIndex, $checkCustomer["distr"]);
	            $newformLogWorkSheet->setCellValue('E'.$rowIndex, $checkCustomer["etp"]);
	            $newformLogWorkSheet->setCellValue('F'.$rowIndex, $checkCustomer["merch"]);
	            $newformLogWorkSheet->setCellValue('G'.$rowIndex, $checkCustomer["retail"]);
	            $newformLogWorkSheet->setCellValue('K'.$rowIndex, $checkCustomer["type"]);
	            $newformLogWorkSheet->setCellValue('L'.$rowIndex, $checkCustomer["princess"]);

	            if (isset($tradePoints[$checkCustomerCode])) {
	            	foreach ($tradePoints[$checkCustomerCode]["items"] as $colIndex => $colArticles) {
	            		$newformLogWorkSheet->setCellValue($colIndex.$rowIndex, implode(";", array_keys($colArticles)));
		            }
	            }
	            $rowIndex++;
	        }

	        // суммируем знаения по колонке
	        foreach ($tradePoints as $tradePointKey => $tradePoint) {
	            foreach ($tradePoint["items"] as $colName => $colArticles) {
	                $summ = 0;
	                foreach ($colArticles as $article => $count) {
	                    $summ += $count;
	                }
	                if ($summ > 0) {
	                    $tradePoints[$tradePointKey]["items"][$colName] = $summ;
	                } else {
	                    unset($tradePoints[$tradePointKey]["items"][$colName]);
	                }
	            }
	        }
	        // Данные указанные в базе
	        $rowIndex = 2;
	        $logIndex = 2;
	        foreach ($checkCustomers as $checkCustomerCode => $checkCustomer) {
	        	$newformDataWorkSheet->setCellValue('H'.$rowIndex, $checkCustomer["unionName"]);
	            $newformDataWorkSheet->setCellValue('I'.$rowIndex, $checkCustomer["name"]);
	            $newformDataWorkSheet->setCellValue('J'.$rowIndex, $checkCustomer["address"]);

	            $newformDataWorkSheet->setCellValue('B'.$rowIndex, $checkCustomer["city"]);
	            $newformDataWorkSheet->setCellValue('C'.$rowIndex, $checkCustomer["cityAddit"]);
	            $newformDataWorkSheet->setCellValue('D'.$rowIndex, $checkCustomer["distr"]);
	            $newformDataWorkSheet->setCellValue('E'.$rowIndex, $checkCustomer["etp"]);
	            $newformDataWorkSheet->setCellValue('F'.$rowIndex, $checkCustomer["merch"]);
	            $newformDataWorkSheet->setCellValue('G'.$rowIndex, $checkCustomer["retail"]);
	            $newformDataWorkSheet->setCellValue('K'.$rowIndex, $checkCustomer["type"]);
	            $newformDataWorkSheet->setCellValue('L'.$rowIndex, $checkCustomer["princess"]);

	            if (isset($tradePoints[$checkCustomerCode])) {
	            	foreach ($tradePoints[$checkCustomerCode]["items"] as $colIndex => $count) {
		                $newformDataWorkSheet->setCellValue($colIndex.$rowIndex, $count);
		            }
	            } else {
	            	/*$newformDataWorkSheet->getStyle('I'.$rowIndex)
					    ->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
					if (strpos( $checkCustomerCode, 'tmpCode') !== FALSE) {
						$newformDataWorkSheet->getStyle('I'.$rowIndex)
					    	->getFill()->getStartColor()->setARGB('FFFF0000');
					} else {
						$newformDataWorkSheet->getStyle('I'.$rowIndex)
					    	->getFill()->getStartColor()->setARGB('FFFFFF00');
					}*/
					$newformMassiveDataWorkSheet->setCellValue('A'.$logIndex, $rowIndex);
					$newformMassiveDataWorkSheet->setCellValue('B'.$logIndex, $checkCustomerCode);
					$newformMassiveDataWorkSheet->setCellValue('C'.$logIndex, $checkCustomer["name"]);
					$newformMassiveDataWorkSheet->setCellValue('D'.$logIndex, $checkCustomer["address"]);
					
					if (strpos( $checkCustomerCode, 'tmpCode') !== FALSE) {
						$newformMassiveDataWorkSheet->setCellValue('E'.$logIndex, 'Код не установлен в базе');
					} else {
						$newformMassiveDataWorkSheet->setCellValue('E'.$logIndex, 'Код не найден в массиве');
					}
					$logIndex++;
	            }
	            $rowIndex++;
	        }
	        // Все данные в массиве
	        //$rowIndex = 2;
	        //foreach ($tradePoints as $tradePointCode => $tradePoint) {
	        //	$newformMassiveDataWorkSheet->setCellValue('H'.$rowIndex, $tradePointCode);
	        //    $newformMassiveDataWorkSheet->setCellValue('I'.$rowIndex, $tradePoint["name"]);
	        //    $newformMassiveDataWorkSheet->setCellValue('J'.$rowIndex, $tradePoint["address"]);
	        //    foreach ($tradePoint["items"] as $colIndex => $count) {
	        //        $newformMassiveDataWorkSheet->setCellValue($colIndex.$rowIndex, $count);
	        //    }
	        //    $rowIndex++;
	        //}

	        //$newformLogWorkSheet->setCellValue('A1', 'Коды не найдены в массиве');
	        //$newformLogWorkSheet->setCellValue('A'.$logIndex, 'Коды не установлен в базе');
	        //$newformFile->setActiveSheetIndex(2);
	        //$newformFile->getActiveSheet()->getStyle('A1')->getFill()
	        //	->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
	        //	->getStartColor()->setARGB('FFFFFF00');
		    //$newformFile->getActiveSheet()->getStyle('A'.$rowIndex)->getFill()
		    //	->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
		    //	->getStartColor()->setARGB('FFFF0000');
	        //$logIndex++;
	        //foreach ($checkCustomersWithEmptyCode as $code => $checkCustomer) {
	        //	$newformLogWorkSheet->setCellValue('A'.$logIndex, $code);
	        //	$newformLogWorkSheet->setCellValue('B'.$logIndex, $checkCustomer["name"]);
	        //	$newformLogWorkSheet->setCellValue('C'.$logIndex, $checkCustomer["address"]);
	        //	$logIndex++;
	        //}

	        $newformFileName = date('dmY-').uniqid('newform-data-').'.xls';
	        $newformFileNamePath = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$newformFileName;

	        //$newformFile->setActiveSheetIndex(0);
	        $objWriter = new PHPExcel_Writer_Excel5($newformFile);
	        $objWriter->save($newformFileNamePath);
	        $response["error"] = false;
	        $response["success"] = true;
	        $response["filename"] = 'http://fabricant.pro/v2/reports/'.$newformFileName;
        }

        // если error ругается bootstrap-fileinput
        //$response["error"] = false;
        //$response["uploaded"] = 'OK';
        //$response["success"] = true;
        //$response["files"] = $_FILES["xls"];
        //$response["filename"] = 'http://fabricant.pro/v2/reports/'.$newformFileName;
        //$response["exec_time"] = date('i:s', time() - $startTime);
        //$response["checkCustomers"] = $checkCustomers;
        //$response["checkCustomersCount"] = count($checkCustomers);

        //header('Content-Type: application/vnd.ms-excel');
		//header('Content-Disposition: attachment;filename="newform.xls"');
		//header('Cache-Control: max-age=0');
        // Write file to the browser
        //$objWriter = PHPExcel_IOFactory::createWriter($newformFile, 'Excel5');
        //$objWriter->save('php://output');
    }
    catch (Exception $e) {
        // Exception occurred. Make error flag true
        $response["error"] = true;
        $response["success"] = false;
        $response["uploaded"] = 'ERROR';
        $response["message"] = $e->getMessage();
        error_log("exception e:".$e->getMessage());
    }

    echoResponse(200,$response);
});

class excelReadFilterByColumn implements PHPExcel_Reader_IReadFilter  {
    private $_columns  = array();

    /**  Get the list of rows and columns to read  */
    public function __construct($columns) {
        $this->_columns  = $columns;
    }

    public function readCell($column, $row, $worksheetName = '') {
        //  Only read the rows and columns that were configured
        if (in_array($column, $this->_columns)) {
            return true;
        }
        return false;
    } }

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
 * Get all reports of contractor
 * method GET
 * url /contractors/reports/:groupid
 */
$app->get('/contractors/reports/:groupid', 'authenticate', function($groupid) use ($app) {
	$db_profile = new DbHandlerProfile();
	$db = new DbHandlerFabricant();

	global $user_id;

	permissionAdminInGroup($user_id, $groupid, $db_profile);

	$result = $db_profile->getGroupById($groupid);

	$response = array();

	$info = json_decode($result[0]["info"], true);

	$reports = NULL;

	if (isset($info["reports"]) && count($info["reports"]) > 0) {
		$reports = $info["reports"];
	}

	if ($reports != NULL) {
		$response["error"] = false;
		$response["reports"] = $reports[0];
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get reports. Please try again.";
	}
	echoResponse(200, $response);
});

/**
 * Save orders 2 excel for castomer 220
 * method GET
 * url /reports/orders/excel/220
 */
$app->get('/reports/orders/excel/220/:id', 'authenticate', function($id) use ($app) {
	$db_profile = new DbHandlerProfile();
	$db = new DbHandlerFabricant();

	global $user_id;

	$response = array();

	permissionAdminInGroup($user_id, $id, $db_profile);

	try {
		$selectedstring = $app->request->get('selected');
		if (empty($selectedstring))
			throw new Exception('Ни одна строка не выделена');

		$selected = json_decode($selectedstring);
		$orders = $db->getAllOrdersOfContractorWeb($id);
		if ($orders == null)
			throw new Exception('Нет заказов в базе данных');

		$productsid = $colnames = array();
		$colnames[0] = "";
		$colnames[1] = "Наименование магазина";
		$colnames[2] = "Адрес";
		$productsres = $db->getActiveProductsOfContractor($id);

		if ($productsres == null)
			throw new Exception('Нет продуктов в базе данных');
		else {
			foreach ($productsres as $value) {
				$colindex = -1;
				switch ($value["id"]) {
				    case 5168:
				        $colindex = 3;
				        break;
				    case 5167:
				        $colindex = 4;
				        break;
				    case 5165:
				        $colindex = 5;
				        break;
				    case 5166:
				        $colindex = 6;
				        break;
				    case 5164:
				        $colindex = 7;
				        break;
				    case 5169:
				        $colindex = 8;
				        break;
				    case 5170:
				        $colindex = 9;
				        break;
				    case 5161:
				        $colindex = 10;
				        break;
				    case 5162:
				        $colindex = 11;
				        break;
				    case 5163:
				        $colindex = 12;
				        break;
				    case 5160:
				        $colindex = 13;
				        break;
				    case 5176:
				        $colindex = 14;
				        break;
				    case 5171:
				        $colindex = 15;
				        break;
				    case 5173:
				        $colindex = 16;
				        break;
				    case 5172:
				        $colindex = 17;
				        break;
				    case 5174:
				        $colindex = 18;
				        break;

				}
				if ($colindex != -1) {
					$productsid[$colindex] = $value["id"];
					$colnames[$colindex] = $value["name"];
				}
			}
		}

		$data = array();

		$orderindex=0;
		foreach ($orders as $order) {
            if (in_array($order["id"], $selected)) {
            	$orderindex++;
                $orderproducts = array();
                $orderproducts[0] = $orderindex;
                $orderproducts[1] = $order["customerName"];
                $orderproducts[2] = $order["address"];
                foreach ($productsid as $i => $productid) {
                    $cnt = 0;
                    foreach ($order["items"] as $item) {
                        if ($item["productid"] == $productid) {
                            $cnt = $item["count"];
                        }
                    }
                    $orderproducts[$i] = $cnt;
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

		foreach ($colnames as $i=>$colname) {
			$sheet->setCellValueByColumnAndRow($i, 1, $colname);
			$sheet->getColumnDimensionByColumn($i)->setWidth(10);
		}
		$sheet->getColumnDimension('A')->setAutoSize(true);
		$sheet->getColumnDimension('B')->setAutoSize(true);
		$sheet->getColumnDimension('C')->setAutoSize(true);

		foreach ($data as $orderskey => $order) {
			$i=0;
			//$sheet->setCellValueByColumnAndRow(0, $orderskey+2, $i+1);
			foreach ($order as $itemkey => $item) {
				$sheet->setCellValueByColumnAndRow($itemkey, $orderskey+2, $item);
			}
		}

		$filename = date('dmY').'-orders-excel-'.uniqid().".xls";
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
 * Save products table 2 excel
 * method GET
 * url products/export/excel/all/:id
 */
$app->get('/products/export/excel/all/:contractorid', 'authenticate', function($contractorid) use ($app) {
	$fdb = new DbHandlerFabricant();
	$db = new DbHandlerProfile();
	$response = array();
	try {
		$products = $fdb->getProductsOfContractor($contractorid);
		$contractor = $db->getGroupById($contractorid);

		if ($products == null)
			throw new Exception('Нет данных для выгрузки');

		$colnames = array();

        $colnames[] = "id";
        $colnames[] = "name_document";
        $colnames[] = "name";
        $colnames[] = "name_full";
        $colnames[] = "price";
        $colnames[] = "summary";

        $colnames[] = "groups";
        $colnames[] = "priority";
        $colnames[] = "icon";
        $colnames[] = "placeholder";

		$colnames[] = "slides_count";
		$colnames[] = "info_count";
        $colnames[] = "units_count";


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
		$sheet->setTitle('Продукты');

		foreach ($colnames as $i=>$colname) {
			$sheet->setCellValueByColumnAndRow($i, 1, $colname);
			$sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
		}

		$contractor_info = json_decode($contractor[0]["info"], true);
		$contractor_groups = $contractor_info["products_groups"];

		foreach ($products as $productkey => $product) {
			$i = 0;
			$info = json_decode($product["info"], true);

			$sheet->setCellValueByColumnAndRow(0, $productkey+2, $product["id"]);
			$sheet->setCellValueByColumnAndRow(1, $productkey+2, $product["name"]);
			$sheet->setCellValueByColumnAndRow(2, $productkey+2, $info["name"]["text"]);
			$sheet->setCellValueByColumnAndRow(3, $productkey+2, $info["name_full"]["text"]);
			$sheet->setCellValueByColumnAndRow(4, $productkey+2, $product["price"]);
			$sheet->setCellValueByColumnAndRow(5, $productkey+2, $info["summary"]["text"]);

			$info_count = 0;
			$slides_count = 0;

			foreach ($info["details"] as $detail) {
				if ($detail["type"] == 1) {
					$info_count++;
				} else if ($detail["type"] == 2) {
					$slides_count++;
				}
			}

			$info_tags = "";
			foreach ($info["tags"] as $value) {
				foreach ($contractor_groups as $val) {
					if ($value == $val["tag_product"])
						$info_tags .= $val["title"]["text"];
				}
			}

			$sheet->setCellValueByColumnAndRow(6, $productkey+2, $info_tags);
			$sheet->setCellValueByColumnAndRow(7, $productkey+2, $info["priority"]);
			$sheet->setCellValueByColumnAndRow(8, $productkey+2, $info["icon"]["image_url"]);
			$sheet->setCellValueByColumnAndRow(9, $productkey+2, $info["placeholder"]["image_url"]);

			$sheet->setCellValueByColumnAndRow(10, $productkey+2, $slides_count);
			$sheet->setCellValueByColumnAndRow(11, $productkey+2, $info_count);
			$sheet->setCellValueByColumnAndRow(12, $productkey+2, count($info["units"]));
		}

		$filename = date('dmY').'-products-tmp-'.uniqid().".xls";
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

//------------------------Kustuk----------------------------------

function makeUnitsFromString($units_pairs_string){

	$info_units=array();

	try{

		$units_pairs=json_decode($units_pairs_string,true);

		$label_of_base=null;//Название базовой единицы
		$label_of_box=null;//Название минимальной коробки(упаковки)
		$value_of_box=0;//Значение минимальной коробки(упаковки)

		$temp_units=array();



		foreach($units_pairs as $key=>$value){

			$unit=array(
				'label'=>$key,
				'value'=>$value
			);

			if($value==1){
				$label_of_base=$value;
			}

			if(($label_of_box==null)||($value<$value_of_box)){
				$label_of_box=$key;
				$value_of_box=$value;
			}

			$temp_units[]=$unit;
		}

		//Если базовая единица отсутствует, то создаем по умолчанию "шт"
		if($label_of_base==null){
			$unit=array(
				'label'=>'шт',
				'value'=>'1'
			);
			$label_of_base='шт';

			$temp_units[]=$unit;
		}

		foreach($temp_units as $unit){

			$unit=array(
				'label'=>$key,
				'value'=>$value
			);

			if($unit['value']==1){
				//Описание для базовой единицы
				$unit['summary']=(($label_of_box==null) || (count($temp_units)==1) )?'Количество указано в '.$unit['label']:'В одной '.$label_of_box.' '.$value_of_box.' '.$unit['label'];
			}else{
				//Описание для упаковок, коробок, кг и т.д.
				$unit['summary']='В одной '.$unit['label'].' '.$unit['value'].' '.$label_of_base;
			}

			$info_units[]=$unit;//Записываем в конечный результат юнитов
		}



	}catch(Exception $e){
		error_log("makeUnitsFromString Exception e=".$e);
	}

	return $info_units;
}

/**
 * Получает информацию о товарах
 * method POST
 */
$app->post('/1c_products_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_products_kustuk----------------");
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

	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	// Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	// Check the file size >100MB
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
		$filename = '1c_products_kustuk'.date(" Y-m-d H-i-s ").uniqid().".json";
		error_log("logged in file: ".$filename);		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}

	$incoming_products = json_decode($data,true);

	//error_log("data: ".$data);

	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$db_fabricant = new DbHandlerFabricant();

	$changed_products=array();
	$created_products=array();
	$not_changed_products=array();

	$products=array();

	$count_of_products=0;

	error_log("incoming_products count=".count($incoming_products));

	foreach ($incoming_products as $incoming_product) {

		$count_of_products++;

		$code=$incoming_product["code"];
		$nomenclature=$incoming_product["name"];
		$name_full=$incoming_product["name_full"];
		$price=$incoming_product["price"];
		$article=$incoming_product["article"];
		//$units_pairs_string=$incoming_product["units"];

		//Если столбцы не указаны
		if(!isset($code))$code=null;
		if(!isset($nomenclature))$nomenclature=null;
		if(!isset($name_full))$name_full=null;
		if(!isset($price))$price=0;
		if(!isset($article))$article=null;
		//if(!isset($units_pairs_string))$units_pairs_string=null;

		//Превращаем юниты в массив
		//$info_units=makeUnitsFromString($units_pairs_string);

		$product=$db_fabricant->getProductByCode($contractorid,$code);

		//Если код продукта не существует в контракторе, создаем новый продукт
		if(!isset($product)){

			$product=array();

			$product["contractorid"]=$contractorid;
			$product["code1c"]=$code;
			$product["name"]=$nomenclature;
			$product["price"]=$price;
			$product["article"]=$article;

			$product["info"] = array(
			  'name' => array(
				'text'=>$nomenclature
			  ),
			  'name_full' => array(
				'text'=>$name_full
			  ),
			  'price' => $price
			);

			//if(count($info_units)>0)$product["info"]["units"]=$info_units;

			$product["status"]=2;

			$created_products[]=$product;

			//error_log($count_of_products.". code=".$code." price=".$price." article=".$article." created");
		}else{

			$changed_flag=false;//Нужен в конце чтобы определить нужно ли обновить товар
			$changed_reason="";

			$product_info=json_decode($product["info"],true);

			//Проверка артикла
			if(strcmp($product["article"],$article)){
					$product["article"]=$article;
					$product["article_changed"]=true;
					$changed_flag=true;
					$changed_reason.="article, ";
			}else{
				$product["article_changed"]=false;
			}

			//Проверка цены
			if($product["price"]!=$price){

					$product["price"]=$price;
					$product_info["price"]=$price;
					$changed_flag=true;
					$changed_reason.="price, ";
			}

			//Проверка наименования
			if(strcmp($product["name"],$nomenclature)){
					$product["name"]=$nomenclature;
					$changed_flag=true;
					$changed_reason.="name, ";
			}

			//Проверка полного наименования
			if( !isset($product_info["name_full"]) || !isset($product_info["name_full"]["text"])  || strcmp($product_info["name_full"]["text"],$name_full)){

					$product_info["name_full"]=array(
						"text"=>$name_full
					);


					$changed_flag=true;
					$changed_reason.="name_full, ";
			}

			//Проверка юнитов, сравнение идет только по полям value и label
			/*$units_changed_flag=false;
			try{

				//Проверка на совпадение количества юнитов
				if(count($product_info["units"])!=count($info_units)){
					$units_changed_flag=true;
					throw new Exception("Other units count");
				}

				//Проверка label и value каждого юнита с каждым юнитом по отдельности
				for($i=0;$i<count($info_units);$i++){
					$found=false;
					for($j=0;$j<count($info_units);$j++){
						if( (strcmp($info_units[$i]["value"],$product_info["units"][$i]["value"])==0) && (strcmp($info_units[$i]["label"],$product_info["units"][$i]["label"] )==0) ){
							$found=true;
							break;
						}
					}

					if(!$found){
						$units_changed_flag=true;
						throw new Exception("Label or value of unit is other:".
							" old_value=".$product_info["units"][$i]["value"]." new_value=".$info_units[$i]["value"].
							" old_label=".$product_info["units"][$i]["label"]." new_label=".$info_units[$i]["label"]
						);
					}
				}

			}catch(Exception $e){
				if($units_changed_flag){
					$product_info["units"]=$info_units;
					$changed_flag=true;
					$changed_reason.="units(".$e->getMessage()."), ";
				}else{
					throw $e;//Значит настоящая ошибка мы выводим ее наружу
				}

			}*/

			//Продукт минимум в одном месте изменен, тогда меняем продукт на новый
			if($changed_flag){

				$product["info"]=json_encode($product_info,JSON_UNESCAPED_UNICODE);
				$product["changed_reason"]=$changed_reason;

				$changed_products[]=$product;

				//error_log($count_of_products.". code=".$code." price=".$price." article=".$article." changed");
			}else{
				//Продукт существует и не изменен
				$not_changed_products[]=$product;

				//error_log($count_of_products.". code=".$code." price=".$price." article=".$article);
			}



		}



	}
	error_log(" ");


	if($count_of_products>0){
		error_log("count_of_products=$count_of_products");
		error_log("created_products=".count($created_products));
		error_log("changed_products=".count($changed_products));
		error_log("not_changed_products=".count($not_changed_products));

		$response["count_of_products"] = $count_of_products;
		$response["created_products"] = count($created_products);
		$response["changed_products"] = count($changed_products);
		$response["not_changed_products"] = count($not_changed_products);
	}

	if(count($created_products)>0){
		error_log("------------------created_products=".count($created_products)."/$count_of_products------------------");

		$index=0;
		foreach($created_products as $product){
			$index++;
			$product["id"]=$db_fabricant->createProduct($product["contractorid"], $product["name"], $product["price"], json_encode($product["info"],JSON_UNESCAPED_UNICODE), $product["status"], $product["code1c"]);

			//Нет параметра для артикула в методе updateProduct, поэтому артикул проверяем и меняем отдельно
			if( isset($product["article"]) ){
				$db_fabricant->updateProductArticle($product["id"], $product["article"]);
			}

			error_log($index.". id=".$product['id'].". code=".$product['code1c']." price=".$product['price']." article=".$product['article']);

		}

	}

	if(count($changed_products)>0){
		error_log("------------------changed_products=".count($changed_products)."/$count_of_products------------------");

		$index=0;
		foreach($changed_products as $product){
			$index++;
			$db_fabricant->updateProduct($product["id"], $product["name"], $product["price"], $product["info"], $product["status"]);

			//Нет параметра для артикула в методе updateProduct, поэтому артикул проверяем и меняем отдельно
			if( isset($product["article_changed"]) && $product["article_changed"] ){
				$db_fabricant->updateProductArticle($product["id"], $product["article"]);
			}

			error_log($index.". id=".$product['id'].". code=".$product['code1c']." price=".$product['price']." article=".$product['article']." changed_reason:[ ".$product['changed_reason']." ]");//.(isset($product['info'])&&isset($product['info']['units']))?" units=".count($product['info']['units']));
		}

	}

	if(count($not_changed_products)>0){
		error_log("------------------not_changed_products=".count($not_changed_products)."/$count_of_products------------------");

		$index=0;
		foreach($not_changed_products as $product){
			$index++;
			if(isset($product["article"]))$product['article']="missing";
			error_log($index.". id=".$product['id'].". code=".$product['code1c']." price=".$product['price']);//." article=".$product['article']);//.(isset($product['info'])&&isset($product['info']['units']))?" units=".count($product['info']['units']));
		}

	}

	//Уведомляем по коммуникатору измененные продукты
	$merged_products=array_merge($created_products,$changed_products);
	if(count($merged_products)>0){
		consoleCommandNotifyProducts($merged_products);
	}

	$response["error"] = false;
	$response["message"] = "Success";
	$response["success"] = 1;

	echoResponse(200, $response);
});

/**
 * Получает остатки всех товаров, не полученные остатки обнуляются
 * method POST
 */
$app->post('/1c_products_rest_all_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_products_rest_all_kustuk----------------");
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

	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	// Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	// Check the file size >100MB
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
		$filename = '1c_products_rest_all_kustuk'.date(" Y-m-d H-i-s ").uniqid();
		error_log("logged in file: ".$filename.".json");		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename.".json";		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}

	$incoming_products = json_decode($data,true);

	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$db_fabricant = new DbHandlerFabricant();

	$count_of_products=0;
	$count_of_unknown_products=0;

	$success_products=array();
	$changed_products=array();
	$changed_products_for_rest=array();

	error_log("incoming rest products count=".count($incoming_products));
	
	$incoming_rests=array();
	
	foreach ($incoming_products as $incoming_product) {

		$count_of_products++;

		try{

			$code=$incoming_product["code"];
			$rest=$incoming_product["rest"];
			
			$product=$db_fabricant->getProductByCode($contractorid,$code);

			//Если код продукта не существует в контракторе, создаем новый продукт
			if(!isset($product)){
				error_log("$count_of_products. Product code=".$code." rest=".$rest." missing");
				$count_of_unknown_products++;
				continue;
			}
			
			$incoming_rests[strval($code)]=$rest;
			
		}catch(Exception $e){
			error_log($e->getMessage());
		}

	}
	
	$products=$db_fabricant->getProductsOfContractor($contractorid);
		
	$count_of_products=0;
	foreach($products as $product){
	
		$count_of_products++;
		
		try{
			
			if( !isset($product["code1c"]) )continue;
			
			$code=$product["code1c"];
			
			$found_in_incoming_rests=false;
			
			if( isset($incoming_rests[$code]) ){
				$rest=$incoming_rests[$code];	
				$found_in_incoming_rests=true;			
			}else{
				//Если остаток для этого товара не пришел, то делаем как будто пришел нулевой остаток
				$rest=0;
			}
			
			//Берем основные остатки из базы
			$rest_string=$db_fabricant->getProductRestById($product["id"]);
			$rest_array=json_decode($rest_string,true);

			if((!isset($rest_array["main"]))||($rest_array["main"]!=$rest)){

				$rest_array["main"]=$rest;
				$rest_string=json_encode($rest_array,JSON_UNESCAPED_UNICODE);
				$db_fabricant->setProductRestById($product["id"],$rest_string);

				$rest_product=array();
				$rest_product["productid"]=$product["id"];
				$rest_product["rest"]=$rest_string;
				$changed_products_for_rest[]=$rest_product;

			}

			//Находим минимальный остаток
			$min_rest=getMinRest($product);

			if( ($rest>$min_rest)&&(isProductInStock($product)==false) ){
				//Продукт только-что появился в наличии
				error_log("$count_of_products. Product code=".$code." id=".$product["id"]." reciepte rest=".$rest." min_rest=".$min_rest);
				$db_fabricant->makeProductInStock($product["id"]);
				$changed_products[]=$product;
			}else if( ($rest<=$min_rest)&&(isProductInStock($product)==true) ){
				//Продукт только-что закончился
				error_log("$count_of_products. Product code=".$code." id=".$product["id"]." not_in_stock rest=".$rest." min_rest=".$min_rest);
				$db_fabricant->makeProductNotInStock($product["id"]);
				$changed_products[]=$product;
			}else{
				error_log("$count_of_products. Product code=".$code." id=".$product["id"]." is_in_stock=".isProductInStock($product)." rest=".$rest." min_rest=".$min_rest);
			}
			
		}catch(Exception $e){
			error_log($e->getMessage());
		}
		
		if($found_in_incoming_rests){
			$success_products[]=$code;
		}

	}
	
	error_log("incoming rest products count=".count($incoming_products)." above");
	error_log("stocking changed products count=".count($changed_products)." (not_in_stock/in_stock)");
	error_log("main rest changed products count=".count($changed_products_for_rest));
	error_log("unknown products count=".$count_of_unknown_products);
	error_log("success count=".count($success_products));
	error_log("1c_products_rest_all_kustuk above");
	error_log(" ");
	
	//Уведомляем по коммуникатору измененные продукты
	if(count($changed_products)>0){
		consoleCommandNotifyProducts($changed_products); 
	}

	//Уведомляем по коммуникатору изменение остатков
	if(count($changed_products_for_rest)>0){
		consoleCommandRecalculateProductsRest($contractorid,$changed_products_for_rest); 
	}

	$response["error"] = false;
	$response["message"] = "Import of all products rest successfully done";
	$response["success"] = 1;
	$response["success_products"] = $success_products;
		
	echoResponse(200, $response);
});

/**
 * Получает остатки товаров и высталяет наличие или не-наличие
 * method POST
 */
$app->post('/1c_products_rest_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_products_rest_kustuk----------------");
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

	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	// Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	// Check the file size >100MB
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
		$filename = '1c_products_rest_kustuk'.date(" Y-m-d H-i-s ").uniqid();
		error_log("logged in file: ".$filename.".json");		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename.".json";		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}

	$incoming_products = json_decode($data,true);

	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$db_fabricant = new DbHandlerFabricant();

	$count_of_products=0;
	$count_of_unknown_products=0;

	$success_products=array();
	$changed_products=array();
	$changed_products_for_rest=array();

	error_log("incoming rest products count=".count($incoming_products));

	foreach ($incoming_products as $incoming_product) {

		$count_of_products++;

		try{

			$code=$incoming_product["code"];
			$rest=$incoming_product["rest"];

			$product=$db_fabricant->getProductByCode($contractorid,$code);

			//Если код продукта не существует в контракторе, создаем новый продукт
			if(!isset($product)){

				error_log("$count_of_products. Product code=".$code." rest=".$rest." missing");
				$count_of_unknown_products++;
				continue;
			}

			//Берем основные остатки из базы
			$rest_string=$db_fabricant->getProductRestById($product["id"]);
			$rest_array=json_decode($rest_string,true);

			if((!isset($rest_array["main"]))||($rest_array["main"]!=$rest)){

				$rest_array["main"]=$rest;
				$rest_string=json_encode($rest_array,JSON_UNESCAPED_UNICODE);
				$db_fabricant->setProductRestById($product["id"],$rest_string);

				$rest_product=array();
				$rest_product["productid"]=$product["id"];
				$rest_product["rest"]=$rest_string;
				$changed_products_for_rest[]=$rest_product;

			}

			//Находим минимальный остаток
			$min_rest=getMinRest($product);

			if( ($rest>$min_rest)&&(isProductInStock($product)==false) ){
				//Продукт только-что появился в наличии
				error_log("$count_of_products. Product code=".$code." id=".$product["id"]." reciepte rest=".$rest." min_rest=".$min_rest);
				$db_fabricant->makeProductInStock($product["id"]);
				$changed_products[]=$product;
			}else if( ($rest<=$min_rest)&&(isProductInStock($product)==true) ){
				//Продукт только-что закончился
				error_log("$count_of_products. Product code=".$code." id=".$product["id"]." not_in_stock rest=".$rest." min_rest=".$min_rest);
				$db_fabricant->makeProductNotInStock($product["id"]);
				$changed_products[]=$product;
			}else{
				error_log("$count_of_products. Product code=".$code." id=".$product["id"]." is_in_stock=".isProductInStock($product)." rest=".$rest." min_rest=".$min_rest);
			}
			
			$success_products[]=$code;

		}catch(Exception $e){
			error_log($e->getMessage());
		}

	}
	
	error_log("incoming rest products count=".count($incoming_products)." above");
	error_log("stocking changed products count=".count($changed_products)." (not_in_stock/in_stock)");
	error_log("main rest changed products count=".count($changed_products_for_rest));
	error_log("unknown products count=".$count_of_unknown_products);
	error_log("success count=".count($success_products));
	error_log("1c_products_rest_kustuk above");
	error_log(" ");
	
	//Уведомляем по коммуникатору измененные продукты
	if(count($changed_products)>0){
		consoleCommandNotifyProducts($changed_products);
	}

	//Уведомляем по коммуникатору изменение остатков
	if(count($changed_products_for_rest)>0){
		consoleCommandRecalculateProductsRest($contractorid,$changed_products_for_rest);
	}

	$response["error"] = false;
	$response["message"] = "Import of operative rests successfully done";
	$response["success"] = 1;
	$response["success_products"] = $success_products;
		
	echoResponse(200, $response);
});

/**
 * Высталяет наличие или не-наличие всем товарам, согласно остаткам
 * method GET
 */
$app->get('/rest_stocking_actualize_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	$contractorid=127;

	error_log("-------------rest_stocking_actualize_kustuk----------------");

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	//try{

		$changed_products=array();

		$products=$db_fabricant->getProductsOfContractor($contractorid);

		$count_of_products=0;

		foreach ($products as $product) {

			$count_of_products++;

			try{

				//Берем основные остатки из базы
				$rest_string=$db_fabricant->getProductRestById($product["id"]);
				$rest_array=json_decode($rest_string,true);

				$rest_main=0;

				if( isset($rest_array["main"]) ){
					$rest_main=$rest_array["main"];
				}

				//Находим минимальный остаток
				$min_rest=getMinRest($product);

				if( ($rest_main>$min_rest)&&(isProductInStock($product)==false) ){
					//Продукт только-что появился в наличии
					error_log("$count_of_products. Product id=".$product["id"]." reciepte rest_main=".$rest_main." min_rest=".$min_rest);
					$db_fabricant->makeProductInStock($product["id"]);
					$changed_products[]=$product;
				}else if( ($rest_main<=$min_rest)&&(isProductInStock($product)==true) ){
					//Продукт только-что закончился
					error_log("$count_of_products. Product id=".$product["id"]." not_in_stock rest_main=".$rest_main." min_rest=".$min_rest);
					$db_fabricant->makeProductNotInStock($product["id"]);
					$changed_products[]=$product;
				}else{
					error_log("$count_of_products. Product id=".$product["id"]." is_in_stock=".isProductInStock($product)." rest_main=".$rest_main." min_rest=".$min_rest);
				}

			}catch(Exception $e){
				error_log($e->getMessage());
			}

		}

		error_log("all products count=".count($products));
		error_log("changed products count=".count($changed_products)." (not_in_stock/in_stock)");

	//} catch (Exception $e) {
		// Exception occurred. Make error flag true
		//$response["error"] = true;
		//$response["message"] = $e->getMessage();
		//$response["success"] = 0;
		//$response = $e->getMessage();
	//}

		$response["error"] = false;
		$response["success"] = 1;
		$response["message"] = "Rest stocking actualized successfully";
		$response["all_products_count"] = count($products);
		$response["changed_products_count"] = count($changed_products);

		echoResponse(200, $response);
});

/**
 * Возвращает дельту заказов (новые, измененные)
 * method POST
 */
$app->post('/1c_orders_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'/*,"last_timestamp"*/));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');
	$dbname = $app->request->post('dbname');
	//$last_timestamp = $app->request->post('last_timestamp');

	//Формируем timestamp для последних 3-х дней
	$date = date("M-d-Y", mktime(0, 0, 0, date('m'), date('d')-3, date('Y')));
	$last_timestamp=strtotime($date)-(3*60*60*24);

	error_log("-------------1c_orders_kustuk----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."_dbname=".$dbname."_lasttimestamp=".$last_timestamp."|");
	error_log("current_date=".date("M-d-Y"));
	error_log("target_date=".$date);
	error_log("timestamp=".$last_timestamp);


	$db_profile=new DbHandlerProfile();
	$db_fabricant=new DbHandlerFabricant();

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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

	//Берем флаг запрет импорта заказов без визы
	$contractor=$db_profile->getGroupById($contractorid)[0];
	$contractor_info=(isset($contractor["info"]))?json_decode($contractor["info"],true):null;

	$synchronize_only_orders_with_visa=( isset($contractor_info["synchronize_only_orders_with_visa"]) && $contractor_info["synchronize_only_orders_with_visa"] );
	error_log("synchronize_only_orders_with_visa=".$synchronize_only_orders_with_visa);

	$orders=$db_fabricant->getOrdersDeltaOfContractor($contractorid,$last_timestamp);
	error_log("orders count: ".count($orders));

	$outgoing_orders=array();

	foreach($orders as $order){

		$record=json_decode($order["record"],true);

		/*error_log("");
		error_log("orderid: ".$order["id"]);
		error_log("status: ".$order["status"]);
		error_log("code1c: ".$order["code1c"]);

		if(isset($record["visa"]))error_log("record: ".$record["visa"]);
		*/
		//Проверка условий для импрта заказа
		if( $order["status"]!=1 || !empty($order["code1c"]) ){
			//error_log("abort: status or code1c are not correct");
			continue;
		}

		//Если стоит запрет на импорт заказа без визы
		if( $synchronize_only_orders_with_visa && ( !isset($record["visa"]) || !$record["visa"] ) ){
			//error_log("abort: visa is not set");
			continue;
		}

		$outgoing_order = array();
		$outgoing_order["orderid"]=$order["id"];
		$outgoing_order["ordercode"]=$order["code1c"];
		$outgoing_order["date"]=date('Y-m-d H:i:s',$order["created_at"]);
		$outgoing_order["status"]=$order["status"];

		$outgoing_order["customerid"]=$order["customerid"];
		if(isset($record["customercode"])){
			$outgoing_order["customercode"]=$record["customercode"];
		}else{
			$temp_customercode=$db_profile->getCustomerCodeInContractorById($order["customerid"],$order["contractorid"]);

			if(isset($temp_customercode)){
				$outgoing_order["customercode"]=$temp_customercode;
			}else{
				$outgoing_order["customercode"]=null;
				//error_log("abort: customercode is not set");
				//Заказы без кода контрагента не отправляем
				continue;
			}
		}

		$outgoing_order["customerName"]=$record["customerName"];

		$outgoing_order["customerUserId"]=$record["customerUserId"];
		if(isset($record["customerUserCode"])){
			$outgoing_order["customerUserCode"]=$record["customerUserCode"];
		}else{
			$temp_customerUserCode=$db_profile->getUserCodeInContractorById($record["customerUserId"],$order["contractorid"]);

			if(isset($temp_customerUserCode)){
				$outgoing_order["customerUserCode"]=$temp_customerUserCode;
			}else{
				$outgoing_order["customerUserCode"]=null;
			}
		}
		$outgoing_order["customerUserName"]=$record["customerUserName"];


		if( isset($record["visa"]) && $record["visa"] ){
			$outgoing_order["visaAddedUserId"]=$record["visaAddedUserId"];
			if(!empty($record["visaAddedUserCode"])){
				$outgoing_order["visaAddedUserCode"]=$record["visaAddedUserCode"];
			}else{
				$temp_visaAddedUserCode=$db_profile->getUserCodeInContractorById($record["visaAddedUserId"],$order["contractorid"]);

				if(!empty($temp_visaAddedUserCode)){
					$outgoing_order["visaAddedUserCode"]=$temp_visaAddedUserCode;
				}else{
					$outgoing_order["visaAddedUserCode"]=null;
				}
			}

			$outgoing_order["visaAddedUserName"]=$record["visaAddedUserName"];
			$outgoing_order["visa"]=true;
		}else{
			$outgoing_order["visa"]=false;
		}


		if(isset($record["address"])){
			$outgoing_order["address"]=$record["address"];
		}else{
			$outgoing_order["address"]=null;
		}

		if(isset($record["phone"])){
			$outgoing_order["phone"]=$record["phone"];
		}else{
			$outgoing_order["phone"]=null;
		}

		if(isset($record["comment"])){
			$outgoing_order["comment"]="Фабрикант: ".$record["comment"];
		}else{
			$outgoing_order["comment"]="Фабрикант";
		}
		
		//error_log("outgoing order before items : ".json_encode($outgoing_order,JSON_UNESCAPED_UNICODE));

		$outgoing_items=array();

		foreach ($record["items"] as $item) {

			//error_log("source_item index=".count($outgoing_items)." :".json_encode($item,JSON_UNESCAPED_UNICODE));
			
			$outgoing_item = array();

			$outgoing_item["id"]=$item["productid"];
			$outgoing_item["code"] = $db_fabricant->getProductCodeById($item["productid"]);
			$outgoing_item["name"] = $item["name"];
			$outgoing_item["count"] = $item["count"];
			if (isset($item["sale"]) && !empty($item["sale"]) && isset($item["sale"]["price_with_sale"]) && !empty($item["sale"]["price_with_sale"]))
				$outgoing_item["price"] = $item["sale"]["price_with_sale"];
			else
				$outgoing_item["price"] = $item["price"];

			$outgoing_item["amount"] = $item["amount"];
			
			/*error_log("outgoing_item index=".count($outgoing_items)." :".json_encode($outgoing_item,JSON_UNESCAPED_UNICODE));			
			json_encode($outgoing_items,JSON_UNESCAPED_UNICODE);
			error_log("jsonlasterror: ".json_last_error());*/
			
			$outgoing_items[]=$outgoing_item;
			
			
		}

		/*error_log("outgoing_order: ".json_encode($outgoing_order,JSON_UNESCAPED_UNICODE));		
		error_log("jsonlasterror: ".json_last_error());
		
		error_log("outgoing_items: ".json_encode($outgoing_items,JSON_UNESCAPED_UNICODE));
		error_log("jsonlasterror: ".json_last_error());		
		*/
		
		$outgoing_order["items"]=$outgoing_items;
		
		error_log((count($outgoing_orders)+1).". orderid=".$outgoing_order["orderid"]." customerid=".$outgoing_order["customerid"]." items_count=".count($outgoing_items)." date:".$outgoing_order["date"]);

		
		$outgoing_orders[]=$outgoing_order;

	}

	error_log("outgoing_orders count=".count($outgoing_orders));

	//error_log("outgoing_orders: ".json_encode($outgoing_orders,JSON_UNESCAPED_UNICODE));

	echoResponse(200, $outgoing_orders);

});

/**
 * При создании заказа получаем связки КОД и ID
 * method POST
 */
$app->post('/1c_orders_created_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_orders_created_kustuk----------------");
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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	// Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	// Check the file size >100MB
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
		$filename = '1c_orders_created_kustuk'.date(" Y-m-d H-i-s ").uniqid().".json";
		error_log("logged in file: ".$filename);		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}
	
	$incoming_orders = json_decode($data,true);

	if(!isset($incoming_orders)){
		throw new Exception('File is not json');
	}
	
	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$count_of_braches=0;

	$success_orders=array();

	foreach ($incoming_orders as $incoming_order) {

		$count_of_braches++;

		try{

			$id=$incoming_order["id"];
			$code=$incoming_order["code"];
			$date=$incoming_order["date"];

			$db_fabricant->updateOrderCode($id, $code);

			error_log($count_of_braches.". id=".$id." code=".$code." date=".$date);
			
			$success_orders[]=$code;
			
		}catch(Exception $e){
			error_log($e->getMessage());
		}
	}
	
	error_log("count_of_incoming_orders=".count($incoming_orders)." above");
	error_log("count_of_success_orders=".count($success_orders));	
	error_log(" ");

	$response["error"] = false;
	$response["message"] = "Success orders count=".count($success_orders)." from ".count($incoming_orders);
	$response["success"] = 1;
	$response["success_orders"]=$success_orders;

	echoResponse(200, $response);
});

/**
 * При присваивании заказчику в 1с кода контрагента получаем связки КОД и ID
 * method POST
 */
$app->post('/1c_contragents_created_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_contragents_created_kustuk----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."|");

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();

	$user=$db_profile->getUserByPhone($phone);
	permissionAdminInGroup($user["id"],$contractorid,$db_profile);

	global $api_key,$user_id;
	$api_key=$user["api_key"];
	$user_id=$user["id"];//Это нужно чтобы в функциях updateOrder и acceptOrder

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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

	$count_of_braches=0;

	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	// Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	// Check the file size >100MB
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
		$filename = '1c_contragents_created_kustuk'.date(" Y-m-d H-i-s ").uniqid().".json";
		error_log("logged in file: ".$filename);		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}
	
	$incoming_contragents=json_decode($data,true);

	if(!isset($incoming_contragents)){
		throw new Exception('File is not json');
	}

	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$success_contragents=array();
	$count_of_braches=0;
	
	foreach ($incoming_contragents as $incoming_contragent) {

		$count_of_braches++;

		try{

			$contragentid=$incoming_contragent["id"];
			$contragentcode=$incoming_contragent["code"];
			$contragentname=$incoming_contragent["name"];
			$contragentphone=$incoming_contragent["phone"];
			$contragentaddress=$incoming_contragent["address"];

			error_log($count_of_braches.". id=".$contragentid." code=".$contragentcode." phone=".$contragentphone);

			$temp_customerid=$db_profile->getCustomerIdInContractorByCode($contragentcode,$contractorid);

			if(empty($contragentid) || empty($contragentid) || empty($contragentid) ){
				error_log("failed. contragentid or contragentid or contragentid is not isset");
				continue;
			}

			if(isset($temp_customerid)){
				error_log("failed. already exists with customerid=".$temp_customerid);
				continue;
			}

			$id_by_code=$db_profile->getCustomerIdInContractorByCode($contragentcode,$contractorid);

			if( isset($id_by_code) ){
				error_log("failed. code already exists in contractor with id=".$id_by_code);
				continue;
			}

			$code_by_id=$db_profile->getCustomerCodeInContractorById($contragentid,$contractorid);

			if( isset($code_by_id) ){
				error_log("failed. id already exists in contractor with code=".$code_by_id);
				continue;
			}

			if( !isset($contragentid) && isset($contragentcode) ){

				//Создаем нового заказчика
				error_log("creating new customer");

				$create_customer_response=createCustomer($contragentname,$contragentaddress,$contragentphone,"{}",$user_id);

				if( isset($create_customer_response["contragentid"]) ){
					error_log("created");
					$contragentid=$create_customer_response["contragentid"];
				}else{
					error_log("failed. when createCustomer");
					continue;
				}
			}

			error_log("set customer code in contarctor");
			//Связка найденного(созданного) заказчика с контрагентом 1С
			$db_profile->setCustomerCodeInContractor($contragentid, $contragentcode,$contractorid);
			
			$success_contragents[]=$contragentcode;
			
		}catch(Exception $e){
			error_log($e->getMessage());
		}

	}

	error_log("count_of_incoming_contragents=".count($incoming_contragents)." above");
	error_log("count_of_success_contragents=".count($success_contragents));	
	error_log(" ");

	$response["error"] = false;
	$response["message"] = "Success contragent count=".count($success_contragents)." from ".count($incoming_contragents);
	$response["success"] = 1;
	$response["success_contragents"]=$success_contragents;

	echoResponse(200, $response);
});

/**
 * При присваивании пользователю в 1с кода ответственного лица получаем связки КОД и ID
 * method POST
 */
$app->post('/1c_users_created_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_users_created_kustuk----------------");
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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

	if (!isset($_FILES["json"])) {
		throw new Exception('Param json is missing');
	}
	// Check if the file is missing
	if (!isset($_FILES["json"]["name"])) {
		throw new Exception('Property name of json param is missing');
	}
	// Check the file size >100MB
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
		$filename = '1c_users_created_kustuk'.date(" Y-m-d H-i-s ").uniqid().".json";
		error_log("logged in file: ".$filename);		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}
	
	$incoming_users = json_decode($data,true);
	if(!isset($incoming_users)){
		throw new Exception('File is not json');
	}
	
	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$success_users=array();	
	$count_of_braches=0;

	foreach ($incoming_users as $incoming_user) {

		$count_of_braches++;

		try{
			$id=$incoming_user["id"];
			$code=$incoming_user["code"];
			$name=$incoming_user["name"];

			error_log("");
			error_log($count_of_braches.". id=".$id." code=".$code." name=".$name);

			if( empty($id) || empty($code) || empty($name) ){
				error_log("failed. id or code or name is not isset");
				continue;
			}

			$id_by_code=$db_profile->getUserIdInContractorByCode($code,$contractorid);

			if( isset($id_by_code) ){
				error_log("failed. code already exists in contractor with id=".$id_by_code);
				continue;
			}

			$code_by_id=$db_profile->getUserCodeInContractorById($id,$contractorid);

			if( isset($code_by_id) ){
				error_log("failed. id already exists in contractor with code=".$code_by_id);
				continue;
			}

			//Добавляем новую запись в code_in_contractor, в info пользователя
			$db_profile->setUserCodeInContractor($id, $code,$contractorid);

			error_log("success");
			
			$success_users[]=$code;

		}catch(Exception $e){
			error_log($e->getMessage());
		}

	}
		
	error_log("count_of_incoming_users=".count($incoming_users)." above");
	error_log("count_of_success_users=".count($success_users));	
	error_log(" ");

	$response["error"] = false;
	$response["message"] = "Success users count=".count($success_users)." from ".count($incoming_users);
	$response["success"] = 1;
	$response["success_users"]=$success_users;

	echoResponse(200, $response);
});

/**
 * Возвращает контрагентов без кода, у которых есть активные заказы к поставщику
 * method POST
 */
$app->post('/1c_contragents_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'/*,"last_timestamp"*/));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');
	//$last_timestamp = $app->request->post('last_timestamp');

	//Формируем timestamp для последних 3-х дней
	$date = date("M-d-Y", mktime(0, 0, 0, date('m'), date('d')-3, date('Y')));
	$last_timestamp=strtotime($date);//-(3*60*60*24);

	error_log("-------------1c_contragents_kustuk----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."_lasttimestamp=".$last_timestamp."|");
	error_log("current_date=".date("M-d-Y"));
	error_log("target_date=".$date);
	error_log("timestamp=".$last_timestamp);


	$db_profile=new DbHandlerProfile();
	$db_fabricant=new DbHandlerFabricant();

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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

	//Берем флаг запрет импорта заказов без визы
	$contractor=$db_profile->getGroupById($contractorid)[0];
	$contractor_info=(isset($contractor["info"]))?json_decode($contractor["info"],true):null;

	$synchronize_only_orders_with_visa=( isset($contractor_info["synchronize_only_orders_with_visa"]) && $contractor_info["synchronize_only_orders_with_visa"] );
	error_log("synchronize_only_orders_with_visa=".$synchronize_only_orders_with_visa);

	$orders=$db_fabricant->getOrdersDeltaOfContractor($contractorid,$last_timestamp);
	error_log("potential orders count: ".count($orders));

	$outgoing_contragents=array();

	foreach($orders as $num=>$order){

		//error_log("");
		//error_log("$num. orderid=".$order["id"]);

		$record=json_decode($order["record"],true);

		//Проверка условий для импорта заказа
		if( $order["status"]!=1 || !empty($order["code1c"]) ){
			//error_log("abort: status or code1c are not correct");
			continue;
		}

		//Если стоит запрет на импорт заказа без визы
		if( $synchronize_only_orders_with_visa && ( !isset($record["visa"]) || !$record["visa"] ) ){
			//error_log("abort: visa is not set");
			continue;
		}

		//Берем только те заказы у которых нет кода контрагента
		if(isset($record["customercode"])){
			//error_log("abort: customercode is set: ".$record["customercode"]);
			continue;
		}else{

			$temp_customercode=$db_profile->getCustomerCodeInContractorById($order["customerid"],$order["contractorid"]);

			//Проверяем на случай, если код был присвоен в базу после создания заказа
			if(isset($temp_customercode)){
				//error_log("abort: customercode was set after order created: ".$temp_customercode);
				continue;
			}else{


				error_log("status: ".$order["status"]);
				error_log("code1c: ".$order["code1c"]);

				error_log("customerid: ".$order["customerid"]);
				error_log("customerName: ".$record["customerName"]);


				$outgoing_contragent=array();
				$outgoing_contragent["customerid"]=$order["customerid"];
				$outgoing_contragent["customerName"]=$record["customerName"];

				if(isset($record["address"])){
					$outgoing_contragent["address"]=$record["address"];
					error_log("code1c: ".$record["address"]);
				}else{
					$outgoing_contragent["address"]=null;
					error_log("address: ".null);
				}

				if(isset($record["customerPhone"])){
					$outgoing_contragent["customerPhone"]=$record["phone"];
					error_log("customerPhone: ".$record["phone"]);
				}else{
					$outgoing_contragent["customerPhone"]=null;
					error_log("customerPhone: ".null);
				}

				$outgoing_contragents[]=$outgoing_contragent;
			}
		}



	}

	echoResponse(200, $outgoing_contragents);

});

/**
 * Возвращает пользователей без кода, у которых есть активные заказы к поставщику
 * method POST
 */
$app->post('/1c_users_kustuk', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'/*,"last_timestamp"*/));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');
	//$last_timestamp = $app->request->post('last_timestamp');

	//Формируем timestamp для последних 3-х дней
	$date = date("M-d-Y", mktime(0, 0, 0, date('m'), date('d')-3, date('Y')));
	$last_timestamp=strtotime($date);//-(3*60*60*24);

	error_log("-------------1c_users_kustuk----------------");
	error_log("|contractorid=".$contractorid."_phone=".$phone."_password=".$password."_lasttimestamp=".$last_timestamp."|");
	error_log("current_date=".date("M-d-Y"));
	error_log("target_date=".$date);
	error_log("timestamp=".$last_timestamp);


	$db_profile=new DbHandlerProfile();
	$db_fabricant=new DbHandlerFabricant();

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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

	//Берем флаг запрет импорта заказов без визы
	$contractor=$db_profile->getGroupById($contractorid)[0];
	$contractor_info=(isset($contractor["info"]))?json_decode($contractor["info"],true):null;

	$synchronize_only_orders_with_visa=( isset($contractor_info["synchronize_only_orders_with_visa"]) && $contractor_info["synchronize_only_orders_with_visa"] );
	error_log("synchronize_only_orders_with_visa=".$synchronize_only_orders_with_visa);

	$orders=$db_fabricant->getOrdersDeltaOfContractor($contractorid,$last_timestamp);
	error_log("potential orders count: ".count($orders));

	$outgoing_users=array();

	foreach($orders as $num=>$order){
		//error_log("");
		//error_log("$num. orderid=".$order["id"]);


		$record=json_decode($order["record"],true);

		//Проверка условий для импорта заказа
		if( $order["status"]!=1 || !empty($order["code1c"]) ){
			//error_log("abort: status or code1c are not correct");
			continue;
		}

		//Если стоит запрет на импорт заказа без визы
		if( $synchronize_only_orders_with_visa && ( !isset($record["visa"]) || !$record["visa"] ) ){
			//error_log("abort: visa is not set");
			continue;
		}

		//Отправляем только те у кого нет кода пользователя поставившего визу
		if(isset($record["visaAddedUserCode"])){
			//error_log("abort: visaAddedUserCode is set in order");
			continue;
		}else{
			$temp_visaAddedUserCode=$db_profile->getUserCodeInContractorById($record["visaAddedUserId"],$order["contractorid"]);

			//Проверяем на случай, если код был установлен после создания заказа
			if(isset($temp_visaAddedUserCode)){
				//error_log("abort: visaAddedUserCode was set after order created");
				continue;
			}else{

				error_log("status: ".$order["status"]);
				error_log("customerUserId: ".$record["customerUserId"]);
				error_log("visaAddedUserId: ".$record["visaAddedUserId"]);

				$outgoing_user=array();
				$outgoing_user["id"]=$record["visaAddedUserId"];
				$outgoing_user["name"]=$record["visaAddedUserName"];

				$outgoing_users[]=$outgoing_user;
			}
		}

	}

	echoResponse(200, $outgoing_users);

});

/**
 * Проверяет все продукты на UTC несовместимые символы. Чтобы предупредить json ошибки
 * method GET
 */
$app->get('/check_products_uts_compatable', 'authenticate',function() use ($app) {
	global $user_id;	
	$contractorid=127;//Kustuk id
	
	// array for final json response
	$response = array();

	error_log("-------------/check_products_uts_compatable----------------");
	error_log("|user_id=".$user_id." contractorid=".$contractorid."|");

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	permissionAdminInGroup($user_id,$contractorid,$db_profile);
	
	$products = $db_fabricant->getProductsOfContractor($contractorid);
	
	$products_with_defect=array();
	foreach($products as $product){
		
		$temp=array();
		$temp["name"]=$product["name"];
		$temp["info"]=$product["info"];
		
		json_encode($temp,JSON_UNESCAPED_UNICODE);
		
		if(json_last_error()!=0){
			$defect=array();
			$defect["productid"]=$product["id"];
			$defect["json_last_error"]=json_last_error();
			$products_with_defect[]=$defect;
		}
		
	}
	
	if ($products) {
		$response["error"] = false;
		$response["success"] = 1;		
		$response["message"] = "Success. Defected products count=".count($products_with_defect);		
		$response["products_with_defect"] = $products_with_defect;
		
	}
	else {
		$response["error"] = true;
		$response["success"] = 0;
		$response["message"] = "Failed to get products of contractor. Please try again";
	}
	
	

	echoResponse(200, $response);
});

//-------------------------------Kustuk Analytic-------------------------------

/**
 * Получаем список групп для аналитики агента
 * agentid - id агента по которому нужна аналитика
 * Возвращаемое значение - группы
 * method POST
 */
$app->post('/analytic_agent_kustuk/:agentid/groups/', 'authenticate', function($agentid) use ($app) {

	global $user_id;	
	$contractorid=127;//Kustuk id
	
	$tea_akb_summ=2000;
	$coffee_akb_summ=500;
	
	// array for final json response
	$response = array();
		
	error_log("-------------/analytic_agent_kustuk/".$agentid."/groups----------------");
	error_log("|agentid=".$agentid." user_id=".$user_id." contractorid=".$contractorid."|");
	
	$date_from='2017-09-29';
	$date_to='2017-11-02';

	$timestamp_from = DateTime::createFromFormat('Y-m-d', $date_from)->getTimestamp();
	$timestamp_to = DateTime::createFromFormat('Y-m-d', $date_to)->getTimestamp();
	
	error_log("|timestamp_from=".$timestamp_from." timestamp_to=".$timestamp_to."|");
	
	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	permissionInGroup($user_id,$contractorid,$db_profile);
	
	if($user_id!=$agentid){
		permissionAdminInGroup($user_id,$contractorid,$db_profile);
	}
	
	$groups = $db_fabricant->getAnalyticGroupsOfUser($agentid);	
	$extended_groups=array();
	
	$tea_ids=$db_fabricant->getAnalyticProductsIdsWithTag("kustuk_tea");
	$coffee_ids=$db_fabricant->getAnalyticProductsIdsWithTag("kustuk_coffee");
	
	foreach($groups as $group){		
		$groupid=$group["id"];
		$tea_summ=0;
		$coffee_summ=0;
		
		$orders=$db_fabricant->getAnalyticCustomerOrders($contractorid, $groupid, $timestamp_from, $timestamp_to);
		
		foreach($orders as $order){
			$record=json_decode($order["record"],true);
			$items=$record["items"];
			
			foreach($items as $item){
				$productid=$item["productid"];
				$amount=$item["productid"];
			
				if(in_array($productid,$tea_ids)){
					$tea_summ+=$item["amount"];
				}				
				if(in_array($productid,$coffee_ids)){
					$coffee_summ+=$item["amount"];
				}
				
			}
			
		}
		
		$coffe_akb_flag=$coffee_summ>$coffee_akb_summ;
		$tea_akb_flag=$tea_summ>$tea_akb_summ;
		
		$group["tea_summ"]=$tea_summ;
		$group["coffee_summ"]=$coffee_summ;
		$group["coffe_akb_flag"]=$coffe_akb_flag;
		$group["tea_akb_flag"]=$tea_akb_flag;
		
		$extended_groups[]=$group;
	}
	
	if ($groups) {
		$response["error"] = false;
		$response["success"] = 1;		
		$response["message"] = "Success. Groups count=".count($groups);		
		$response["groups"] = $extended_groups;
		
	}
	else {
		$response["error"] = true;
		$response["success"] = 0;
		$response["message"] = "Failed to get groups list. Please try again";
	}
	
	

	echoResponse(200, $response);
});

/**
 * Получаем заказы агента для аналитики
 * agentid - id агента по которому нужна аналитика
 * Возвращаемое значение - краткая информация по заказам
 * method POST
 */
$app->post('/analytic_agent_kustuk/:agentid/orders/:date_from/:date_to', 'authenticate', function($agentid,$date_from,$date_to) use ($app) {

	global $user_id;	
	$contractorid=127;//Kustuk id
	
	// array for final json response
	$response = array();

	error_log("-------------/analytic_agent_kustuk/".$agentid."/orders----------------");
	error_log("|agentid=".$agentid." user_id=".$user_id." contractorid=".$contractorid." date_from=".$date_from." date_to=".$date_to."|");

	$timestamp_from = DateTime::createFromFormat('Y-m-d', $date_from)->getTimestamp();
	$timestamp_to = DateTime::createFromFormat('Y-m-d', $date_to)->getTimestamp();
	
	error_log("|timestamp_from=".$timestamp_from." timestamp_to=".$timestamp_to."|");
	
	if(empty($timestamp_from) || empty($timestamp_to)){
		$response["error"] = true;
		$response["success"] = 0;
		$response["message"] = "Date format incorrect";
		echoResponse(200, $response);
	}
	
	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	permissionInGroup($user_id,$contractorid,$db_profile);
	
	if($user_id!=$agentid){
		permissionAdminInGroup($user_id,$contractorid,$db_profile);
	}
	
	$orders = $db_fabricant->getAnalyticAgentOrders($contractorid, $agentid, $timestamp_from, $timestamp_to);
	if ($orders) {
		$response["error"] = false;
		$response["success"] = 1;
		$response["message"] = "Success. Orders count=".count($orders);
		$response["customers"] = $orders;
		
	}else {
		$response["error"] = true;
		$response["success"] = 0;
		$response["message"] = "Failed to get orders list. Please try again";
	}
	
	

	echoResponse(200, $response);
});

/**
 * Получаем список магазинов у которые входят в базу 90
 * Обновляет только таблиуцу analytic_groups, и только те строки которые есть в excel
 * method POST
 */
$app->post('/analytic_groups_add_tag_kustuk_90', 'authenticate', function() use ($app) {
	global $user_id;	
	$contractorid=127;//Kustuk id
	
	// array for final json response
	$response = array();

	error_log("-------------analytic_groups_add_tag_kustuk_90----------------");
	error_log("|user_id=".$user_id." contractorid=".$contractorid."|");

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	permissionFabricantAdmin($user_id);
	
	//-------------------Берем Excel файл----------------------------

	if (!isset($_FILES["xls"])) {
		throw new Exception('Param xls is missing');
	}

	// Check if the file is missing
	if (!isset($_FILES["xls"]["name"])) {
		throw new Exception('Property name of xls param is missing');
	}

	// Check the file size > 100MB
	if($_FILES["xls"]["size"] > 100*1024*1024) {
		throw new Exception('File is too big');
	}

	$tmpFile = $_FILES["xls"]["tmp_name"];

	// Подключаем класс для работы с excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';

	// Подключаем класс для вывода данных в формате excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

	$objPHPExcel = PHPExcel_IOFactory::load($tmpFile);

	// Set and get active sheet
	$objPHPExcel->setActiveSheetIndex(0);
	$worksheet = $objPHPExcel->getActiveSheet();
	$worksheetTitle = $worksheet->getTitle();
	$highestRow = $worksheet->getHighestRow();
	$highestColumn = $worksheet->getHighestColumn();
	$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
	$nrColumns = ord($highestColumn) - 64;

	$db_fabricant = new DbHandlerFabricant();

	$updated_groups=array();
	for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
		$cells = array();

		for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
			$cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
			$cells[] = $cell->getValue();
		}

		$id=$cells[7];
		
		if(!empty($id)){
			$group=array();
			$group["id"]=$id;
				
			$db_fabricant->addTagToAnalyticGroup("kustuk_90",$id);
			
			//$group["info"]=$db_fabricant->getAnalyticGroupById($id)["info"];		
			//$group["hasTag"]=$db_fabricant->groupHasAnalyticTag("kustuk_90",$id);
			
			$updated_groups[]=$group;
		}
	}

	$response=array();
	$response["error"]=false;
	$response["success"]=1;
	$response["highestRow"]=$highestRow;
	$response["updated_groups_count"]=count($updated_groups);
	$response["updated_groups"]=$updated_groups;

	echoResponse(200,$response);

});

/**
 * Получаем список продуктов с отметкой чай/кофе
 * Обновляет только таблиуцу analytic_products, и только те строки которые есть в excel
 * method POST
 */
$app->post('/analytic_products_tea_coffee', 'authenticate', function() use ($app) {
	global $user_id;	
	$contractorid=127;//Kustuk id
	
	// array for final json response
	$response = array();

	error_log("-------------analytic_products_tea_coffee----------------");
	error_log("|user_id=".$user_id." contractorid=".$contractorid."|");

	$db_profile=new DbHandlerProfile();
	$db_fabricant = new DbHandlerFabricant();
	
	permissionFabricantAdmin($user_id);
	
	//-------------------Берем Excel файл----------------------------

	if (!isset($_FILES["xls"])) {
		throw new Exception('Param xls is missing');
	}

	// Check if the file is missing
	if (!isset($_FILES["xls"]["name"])) {
		throw new Exception('Property name of xls param is missing');
	}

	// Check the file size > 100MB
	if($_FILES["xls"]["size"] > 100*1024*1024) {
		throw new Exception('File is too big');
	}

	$tmpFile = $_FILES["xls"]["tmp_name"];

	// Подключаем класс для работы с excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';

	// Подключаем класс для вывода данных в формате excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

	$objPHPExcel = PHPExcel_IOFactory::load($tmpFile);

	// Set and get active sheet
	$objPHPExcel->setActiveSheetIndex(0);
	$worksheet = $objPHPExcel->getActiveSheet();
	$worksheetTitle = $worksheet->getTitle();
	$highestRow = $worksheet->getHighestRow();
	$highestColumn = $worksheet->getHighestColumn();
	$highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
	$nrColumns = ord($highestColumn) - 64;

	$db_fabricant = new DbHandlerFabricant();

	$updated_products=array();
	for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
		$cells = array();

		for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
			$cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
			$cells[] = $cell->getValue();
		}

		$id=$cells[0];
		$tea_flag=($cells[13]==1);
		$coffee_flag=($cells[14]==1);
		
		if(!empty($id)){
			$product=array();
			$product["id"]=$id;
			
			if($tea_flag){
				$db_fabricant->addTagToAnalyticProduct("kustuk_tea",$id);
			}
			
			if($coffee_flag){
				$db_fabricant->addTagToAnalyticProduct("kustuk_coffee",$id);
			}
			
			$product["info"]=$db_fabricant->getAnalyticProductById($id)["info"];		
			//$product["kustuk_tea"]=$db_fabricant->productHasAnalyticTag("kustuk_tea",$id);
			//$product["kustuk_coffee"]=$db_fabricant->productHasAnalyticTag("kustuk_coffee",$id);
			
			$updated_products[]=$product;
		}
	}
	
	
	$response=array();
	$response["error"]=false;
	$response["success"]=1;
	$response["highestRow"]=$highestRow;
	$response["updated_products_count"]=count($updated_products);
	$response["updated_products"]=$updated_products;
	
	$response["tea_products"]=$db_fabricant->getAnalyticProductsIdsWithTag("kustuk_tea");
	$response["coffee_products"]=$db_fabricant->getAnalyticProductsIdsWithTag("kustuk_coffee");
		
	echoResponse(200,$response);

});

/**
 * Отчет о доходе с кустук
 * С учетом всех поставок маазинов, независимо от пользователя сделавшего заказ
 * method POST
 */
$app->get('/analytic_profit_report/:phone/:password', function($phone,$password) use ($app) {
	
	$contractorid=127;//Kustuk id
	
	error_log("-------------analytic_profit_report----------------");
	error_log("|phone=".$phone." password=".$password."|");

	$db_profile=new DbHandlerProfile();	
	$db_fabricant = new DbHandlerFabricant();

	//Проверяем логин и пароль
	if(!$db_profile->checkLoginByPhone($phone,$password)){

		$response = array();
		$response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
		echoResponse(200,$response);
		return;
	}

	$user=$db_profile->getUserByPhone($phone);
	$user_id=$user["id"];	
	error_log("user_id=".$user_id);
	
	permissionFabricantAdmin($user_id);
	
	$orders=$db_fabricant->getAnalyticProfitOrders();
	
	$xls_out=getExcelOfProfits($orders);

	// Выводим содержимое файла
	$objWriter = new PHPExcel_Writer_Excel5($xls_out);
	$objWriter->save('php://output');
	
	/*$response=array();
	$response["error"]=false;
	$response["success"]=1;
	$response["orders_count"]=count($orders);
	$response["orders"]=$orders;
	echoResponse(200,$response);*/

});

/**
 * Отчет о всех заказах, с индикатором изменений
 * С учетом всех поставок маазинов, независимо от пользователя сделавшего заказ
 * method POST
 */
$app->get('/analytic_changed_orders_report/:phone/:password', function($phone,$password) use ($app) {
	
	$contractorid=127;//Kustuk id
	
	error_log("-------------analytic_changed_orders_report----------------");
	error_log("|phone=".$phone." password=".$password."|");

	$db_profile=new DbHandlerProfile();	
	$db_fabricant = new DbHandlerFabricant();

	//Проверяем логин и пароль
	if(!$db_profile->checkLoginByPhone($phone,$password)){

		$response = array();
		$response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
		echoResponse(200,$response);
		return;
	}

	$user=$db_profile->getUserByPhone($phone);
	$user_id=$user["id"];	
	error_log("user_id=".$user_id);
	
	permissionFabricantAdmin($user_id);
	
	$orders=$db_fabricant->getAnalyticChangedOrders();
	
	$xls_out=getExcelOfChangedOrders($orders);

	// Выводим содержимое файла
	$objWriter = new PHPExcel_Writer_Excel5($xls_out);
	$objWriter->save('php://output');
	
	/*$response=array();
	$response["error"]=false;
	$response["success"]=1;
	$response["orders_count"]=count($orders);
	$response["orders"]=$orders;
	echoResponse(200,$response);*/

});

//--------------------------------------Kustuk synch-------------------------------------


/**
 * Проверка готов ли сервер принять массив
 * method POST
 */
$app->post('/1c_synch_check_massive_state', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_synch_check_massive_state----------------");
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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);
	
	//---------------Чтение из файла-------------------------
	
	$control_file_name="1c_synch_control.json";
	
	try{
		
		$string_from_file=file_get_contents($control_file_name);
		
		$json_control=json_decode($string_from_file,true);
		
		if($json_control["one_shot"]==true){
			
			$response["error"] = false;	
			$response["success"] = 1;	
			$response["one_shot"]=true;
			$response["message"]="Successfully get one shot";
			error_log("one_shot. successfully get");
			
			$json_control["one_shot"]=false;
			file_put_contents($control_file_name, json_encode($json_control,JSON_UNESCAPED_UNICODE));
			
			error_log("one_shot. successfully get");
		}else{
			$response["error"] = false;	
			$response["success"] = 1;	
			$response["one_shot"]=false;			
			$response["message"]="One shot already got";
			
			error_log("one_shot. already used");
		}
		
	}catch(Exception $e){
		
		$response["error"] = true;	
		$response["success"] = 0;	
		$response["message"]=$e->getMessage();
		error_log($e->getMessage());
	}
	

	echoResponse(200, $response);
});

/**
 * Получаем массив для Кустук в виде расширенного Excel
 * Расширение uid:контрагент, товар, заказ
 * method POST
 */
$app->post('/1c_synch_get_massive_in_excel', function() use ($app) {
	// array for final json response
	$response = array();

	verifyRequiredParams(array('contractorid', 'phone', 'password'));

	$contractorid = $app->request->post('contractorid');
	$phone = "7".$app->request->post('phone');
	$password = $app->request->post('password');

	error_log("-------------1c_synch_get_massive_in_excel----------------");
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

	//Проверка доступна ли 1С синхронизация у этого поставщика
	check1CSynchronizingEnabledInContractor($contractorid,$db_profile);

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
	
	//Считываем закодированный файл xls в строку
	$data = file_get_contents($tmpFile);
	//Декодируем строку из base64 в нормальный вид
	$data = base64_decode($data);
	
	//Запись в /v2/reports для лога
	try{
		$filename = '1c_synch_get_massive_in_excel'.date(" Y-m-d H-i-s ").uniqid().".json";
		error_log("logged in file: ".$filename);		
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;		
		if ( !empty($data) && ($fp = @fopen($path, 'wb')) ){
			@fwrite($fp, $data);
			@fclose($fp);
		}
	}catch(Exception $e){
		error_log("error when log in file /v2/reports/: ".$e->getMessage());
	}
	
	//Освобождаем память занятую строкой (это файл, поэтому много занятой памяти)
	unset($data);

	$response["error"] = false;
	$response["message"] = "Success";
	$response["success"] = 1;

	echoResponse(200, $response);
});

//---------------------------------------------------

/*
	Формирует и возвращает файл Excel для расчета прибыли
	Поля order :id,customerName		
*/
function getExcelOfProfits($orders){

	// Подключаем класс для работы с excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
	// Подключаем класс для вывода данных в формате excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

	// New PHPExcel class
	$xls = new PHPExcel();

	$sheet = $xls->setActiveSheetIndex(0);

	$row_index=9;
	
	//Заполнение шапки
	$sheet->setCellValue("A$row_index", 'ИД');
	$sheet->setCellValue("B$row_index", 'CustomerID');
	$sheet->setCellValue("C$row_index", 'Заказчик');
	$sheet->setCellValue("D$row_index", 'Адрес');
	$sheet->setCellValue("E$row_index", 'Сумма');
	$sheet->setCellValue("F$row_index", 'Статус');
	$sheet->setCellValue("G$row_index", 'Дата создания');

	$row_index=10;

	foreach($orders as $order){

		$sheet->setCellValue("A$row_index", $order["id"]);
		$sheet->setCellValue("B$row_index", $order["customerid"]);
		$sheet->setCellValue("C$row_index", $order["customerName"]);
		$sheet->setCellValue("D$row_index", $order["address"]);
		$sheet->setCellValue("E$row_index", $order["amount"]);
		$sheet->setCellValue("F$row_index", $order["status"]);
		$sheet->setCellValue("G$row_index", $order["created_at"]);

		$row_index++;
	}
	
	//Ставим автоширину колонок
	$sheet->getColumnDimension('C')->setWidth(40);
	$sheet->getColumnDimension('D')->setWidth(40);
	$sheet->getColumnDimension('E')->setWidth(12);
	$sheet->getColumnDimension('G')->setWidth(20);

	// Выводим HTTP-заголовки
	 header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
	 header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
	 header ( "Cache-Control: no-cache, must-revalidate" );
	 header ( "Pragma: no-cache" );
	 header ( "Content-type: application/vnd.ms-excel" );
	 header ( "Content-Disposition: attachment; filename=matrix.xls" );

	return $xls;
}

/*
	Формирует и возвращает файл Excel для расчета прибыли
	Поля order :id,customerName		
*/
function getExcelOfChangedOrders($orders){

	// Подключаем класс для работы с excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';
	// Подключаем класс для вывода данных в формате excel
	require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

	// New PHPExcel class
	$xls = new PHPExcel();

	$sheet = $xls->setActiveSheetIndex(0);

	$row_index=9;
	
	//Заполнение шапки
	$sheet->setCellValue("A$row_index", 'ID');
	$sheet->setCellValue("B$row_index", 'AgentID');
	$sheet->setCellValue("C$row_index", 'CustomerID');
	$sheet->setCellValue("D$row_index", 'Заказчик');
	$sheet->setCellValue("E$row_index", 'Адрес');
	$sheet->setCellValue("F$row_index", 'Статус');
	$sheet->setCellValue("G$row_index", 'Исходная сумма');
	$sheet->setCellValue("H$row_index", 'Конечная сумма');
	$sheet->setCellValue("I$row_index", 'Разница сумм');
	$sheet->setCellValue("J$row_index", 'DiffFlag');
	$sheet->setCellValue("K$row_index", 'Уменьшение суммы');
	$sheet->setCellValue("L$row_index", 'LesionFlag');
	$sheet->setCellValue("M$row_index", 'Дата создания');

	$row_index=10;

	foreach($orders as $order){

		$sheet->setCellValue("A$row_index", $order["id"]);
		$sheet->setCellValue("B$row_index", $order["agentid"]);
		$sheet->setCellValue("C$row_index", $order["customerid"]);
		$sheet->setCellValue("D$row_index", $order["customerName"]);
		$sheet->setCellValue("E$row_index", $order["address"]);
		$sheet->setCellValue("F$row_index", $order["status"]);
		$sheet->setCellValue("G$row_index", $order["initial_amount"]);
		$sheet->setCellValue("H$row_index", $order["amount"]);
		$sheet->setCellValue("I$row_index", $order["diff"]);
		$sheet->setCellValue("J$row_index", $order["diff_flag"]);
		$sheet->setCellValue("K$row_index", $order["lesion"]);
		$sheet->setCellValue("L$row_index", $order["lesion_flag"]);
		$sheet->setCellValue("M$row_index", $order["created_at"]);

		$row_index++;
	}
	
	//Ставим автоширину колонок
	$sheet->getColumnDimension('D')->setWidth(40);
	$sheet->getColumnDimension('E')->setWidth(40);
	$sheet->getColumnDimension('M')->setWidth(20);

	// Выводим HTTP-заголовки
	 header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
	 header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
	 header ( "Cache-Control: no-cache, must-revalidate" );
	 header ( "Pragma: no-cache" );
	 header ( "Content-type: application/vnd.ms-excel" );
	 header ( "Content-Disposition: attachment; filename=matrix.xls" );

	return $xls;
}

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

	return;
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
* temp url for change home url for images
* method GET
* url /copy49to127
*/
$app->get('/gethomeurlofimages', 'authenticate', function() use ($app) {

	global $user_id;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);

	$db_fabricant = new DbHandlerFabricant();
	$db_profile = new DbHandlerProfile();

	$response = array();

	//Продукты
	$products = $db_fabricant->getAllProducts();
	$products_ids=array();
	if ($products) {
		foreach ($products as $product) {

			if(strstr($product["info"],"igorserver.ru")){
				$pair=array();
				$pair["contractorid"]=$product["contractorid"];
				$pair["id"]=$product["id"];
				$products_ids[]=$pair;
			}
		}
	}

	//Группы
	$groups = $db_profile->getAllGroups();
	$groups_ids=array();
	if ($groups) {
		foreach ($groups as $group) {

			if(strstr(json_encode($group["info"],JSON_UNESCAPED_UNICODE),"igorserver.ru")){
				$pair=array();
				$pair["id"]=$group["id"];
				$groups_ids[]=$pair;
			}
		}
	}

	//Пользователи
	$users = $db_profile->getAllUsers();
	$users_ids=array();
	if ($users) {
		foreach ($users as $user) {

			if(strstr($user["info"],"igorserver.ru")){
				$pair=array();
				$pair["id"]=$user["id"];
				$users_ids[]=$pair;
			}
		}
	}

	$response["error"] = false;
	$response["message"] = "";
	$response["products_ids"]=$products_ids;
	$response["users_ids"]=$users_ids;
	$response["groups_ids"]=$groups_ids;

	error_log($response["message"]);

	echoResponse(200, $response);

});

$app->get('/changehomeurlofimages', 'authenticate', function() use ($app) {

	global $user_id;
	// creating new contracotor
	$db = new DbHandlerProfile();

	permissionFabricantAdmin($user_id);

	$db_fabricant = new DbHandlerFabricant();
	$db_profile = new DbHandlerProfile();

	$response = array();

	//Продукты
	$products = $db_fabricant->getAllProducts();
	$products_ids=array();
	if ($products) {
		foreach ($products as $product) {

			if(strstr($product["info"],"igorserver.ru")){
				$new_info=str_replace("igorserver.ru","fabricant.pro",$product["info"]);

				$db_fabricant->updateProduct($product["id"], $product["name"], $product["price"], $new_info, $product["status"]);

				$pair=array();
				$pair["contractorid"]=$product["contractorid"];
				$pair["id"]=$product["id"];
				$products_ids[]=$pair;
			}
		}
	}

	//Группы
	$groups = $db_profile->getAllGroups();
	$groups_ids=array();
	if ($groups) {
		foreach ($groups as $group) {
			$info_string=json_encode($group["info"],JSON_UNESCAPED_UNICODE);
			if(strstr($info_string,"igorserver.ru")){
				$new_info=str_replace("igorserver.ru","fabricant.pro",$info_string);

				$db_profile->changeGroupInfo($new_info,$group["id"]);

				$pair=array();
				$pair["id"]=$group["id"];
				$groups_ids[]=$pair;
			}
		}
	}

	//Пользователи
	$users = $db_profile->getAllUsers();
	$users_ids=array();
	if ($users) {
		foreach ($users as $user) {

			if(strstr($user["info"],"igorserver.ru")){

				$new_info=str_replace("igorserver.ru","fabricant.pro",$user["info"]);
				$db_profile->updateUserInfo($user["id"],$new_info);
				$pair=array();
				$pair["id"]=$user["id"];
				$users_ids[]=$pair;
			}
		}
	}

	$response["error"] = false;
	$response["message"] = "";
	$response["products_ids"]=$products_ids;
	$response["users_ids"]=$users_ids;
	$response["groups_ids"]=$groups_ids;

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

function isProductInStock($product){

	$tag="not_in_stock";

	if(!isset($product)){
		return true;
	}

	if(!isset($product["info"])){
		return true;
	}

	$info=json_decode($product["info"],true);

	if(!isset($info["tags"])){
		return true;
	}

	if(!in_array($tag,$info["tags"])){
		return true;
	}

	return false;
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
define("M_CONSOLE_OPERATION_ORDER", 3);
define("M_CONSOLE_OPERATION_GROUP_CHANGED", 5);
define("M_CONSOLE_OPERATION_PRODUCT_CHANGED", 6);
define("M_CONSOLE_OPERATION_NOTIFY_PRODUCTS", 7);
define("M_CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST", 8);

define("M_ORDEROPERATION_ACCEPT", 2);

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
		$json_header["console"]="group_changed";
		$json_header["operation"]=M_CONSOLE_OPERATION_GROUP_CHANGED;
		$json_header["groupid"] = $groupid;
		try{
		$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение группы не пойдет по коммуникатору
		}

}

function consoleCommandProductUpdated($productid){

		$json_header=array();
		$json_header["console"]="product_changed";
		$json_header["operation"]=M_CONSOLE_OPERATION_PRODUCT_CHANGED;
		$json_header["productid"] = $productid;

		try{
		$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение продукта не пойдет по коммуникатору
		}
}

function consoleCommandNotifyProducts($products){

		$json_header=array();
		$json_header["console"]="notify_products";
		$json_header["operation"]=M_CONSOLE_OPERATION_NOTIFY_PRODUCTS;
		$json_header["products"] = $products;

		try{
		$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение ряда продуктов не пойдет по коммуникатору
		}
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

function consoleCommandUserUpdated($userid){

		$json_header=array();
		$json_header["console"]="user_changed";
		$json_header["operation"]=M_CONSOLE_OPERATION_USER_CHANGED;
		$json_header["userid"] = $userid;
		try{
			$console_response=consoleCommand($json_header);
		}catch(Exception $e){
			//Была ошибка. Изменение юзера не пойдет по коммуникатору
		}

}

$app->run();

?>
