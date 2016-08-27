<?php

require_once dirname(__FILE__).'/../include/SimpleImage.php'; 
require_once dirname(__FILE__).'/../include/DbHandlerProfile.php';
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
require_once dirname(__FILE__).'/../include/PassHash.php';

require_once dirname(__FILE__).'/../libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
 
// User id from db - Global Variable
$user_id = NULL;

/**
 * It used to Slim testing during installation the server 
 */

$app->get('/hello/:name', function ($name) {
	//Console command to notify that group has been changed
	/*$json_header=array();
	$json_header["console"]="v1/index/hello";
	$json_header["operation"]=M_CONSOLE_OPERATION_GROUP;
	$json_header["group_operationid"]=M_GROUPOPERATION_SAVE;
	$json_header["groupid"]=2;
	$json_header["senderid"]=1;
	$json=array();
	$json["name"]="$name";
	
	$json_header["json"]=json_encode($json);
	$console_response=consoleCommand($json_header);			

	echo $console_response["message"];*/
	$response["error"] = true;
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
 		// get the api key
		$api_key = $headers['Api-Key'];
		// validating api key
		if (!$db->isValidApiKey($api_key)) {
			// api key is not present in users table
			$response["error"] = true;
			$response["message"] = "Access Denied. Invalid Api key";
			$response["success"] = 0;
			echoResponse(401, $response);
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
		echoResponse(400, $response);
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

//--------------------Admin panel----------------------------
/**
 * Listing all contractor products
 * method GET
 * url /products
 */
$app->get('/products', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('contractorid'));
	// reading get params
	$contractorid = $app->request->get('contractorid');
	// listing all users
	$fdb = new DbHandlerFabricant();
	$result = $fdb->getProductsOfContractor($contractorid);
	$response = array();
	$response["error"] = false;
	$response["products"]=$result;
	echoResponse(200, $response);
});
/**
 * Creating contractor product
 * method POST
 * url /products
 */
$app->post('/products', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('contractorid'));
	// reading post params
	$contractorid = $app->request->post('contractorid');
	// creating new product
	$fdb = new DbHandlerFabricant();
	$productid = $fdb->createProduct($contractorid, "", 0, "");
	$response = array();
	if ($productid != NULL) {
		$response["error"] = false;
		$response["message"] = "Product created successfully";
		$response["id"]=$productid;
		$response["contractorid"]=$contractorid;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to create product. Please try again";
	}
	echoResponse(201, $response);
});
/**
 * Updating contractor product
 * method PUT
 * url /products/:id
 */
$app->put('/products/:id', function($id) use ($app) {
	// check for required params
	verifyRequiredParams(array('name', 'price', 'info', 'status'));
	// reading put params
	$name = $app->request->put('name');
	$price = $app->request->put('price');
	$info = $app->request->put('info');
	$status = $app->request->put('status');
	// creating new product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->updateProduct($id, $name, $price, $info, $status);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product updated successfully";
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product failed to update. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Deleting contractor product
 * method DELETE
 * url /products/:id
 */
$app->delete('/products/:id', function($id) use ($app) {
	// creating new product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->removeProduct($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["message"] = "Product deleted successfully";
	}
	else {
		$response["error"] = true;
		$response["message"] = "Product failed to delete. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Uploading product image
 * method POST
 * url /product/upload:prefix
 */
$app->post('/products/upload/:prefix', function($prefix) use ($app) {
	// array for final json respone
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

		$type = $_FILES["image"]["type"];
		$filename = uniqid($prefix).'.'.substr($type, strrpos($type,'/')+1);
		$path = $_SERVER["DOCUMENT_ROOT"].'/v2/images/products/'.$filename;

		if (move_uploaded_file($tmpFile, $path)) {
			$response["message"] = 'File uploaded successfully!';
			$response["url"] = $_SERVER["HTTP_HOST"].'/v2/images/products/'.$filename;
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

$app->run();

?>