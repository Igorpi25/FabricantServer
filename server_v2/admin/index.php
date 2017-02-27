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
 * Listing all groups of user (POST method, datatables)
 * method POST
 * url /groups/:id
 */
$app->post('/groups/dt/:id', 'authenticate', function($id) use ($app) {
	// listing all groups
	$db = new DbHandlerProfile();
	$groups = $db->getGroupsOfUser($id);
	$response = array();
	if ($groups != NULL || empty($groups)) {
		$data = array();
		foreach ($groups as $group) {
			if ($group["type"] == 1)
				$data[] = $group;
		}
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(10);
		$response["recordsFiltered"] = intval(10);
		$response["data"] = $data;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
});

/**
 * Listing all users in group
 * method POST
 * url /groupusers/:contractorid
 */
$app->post('/groupusers/:contractorid', function($contractorid) use ($app) {
	// check for required params
    verifyRequiredParams(array('shopid'));
	// reading post params
    $shopid = $app->request->post('shopid');
	// listing all users of group
	$db = new DbHandlerProfile();
	$groupsuser = $db->getUsersInGroupWeb($contractorid);
	$response = array();
	if ($groupsuser != NULL || empty($groupsuser)) {
		$data = array();
		foreach ($groupsuser as $user) {
			if (!$db->isUserInGroup($shopid,$user["id"])&&$user["id"]>3)
				$data[] = $user;
		}
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(10);
		$response["recordsFiltered"] = intval(10);
		$response["data"] = $data;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all groups of user (GET method)
 * method GET
 * url /groups/:id
 */
$app->get('/groups/:id', function($id) use ($app) {
	// listing all groups of user
	$db = new DbHandlerProfile();
	$result = $db->getGroupsOfUser($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["groups"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
});
/**
 * Listing all users in group
 * method GET
 * url /groupusers/:id
 */
$app->get('/groupusers/:id', function($id) use ($app) {
	// check for required params
    /*verifyRequiredParams(array('shopid'));
	// reading post params
    $shopid = $app->request->get('shopid');
	// listing all users of group
	$db = new DbHandlerProfile();
	$groupsuser = $db->getUsersInGroupWeb($shopid);
	$response = array();
	if ($groupsuser != NULL || empty($groupsuser)) {
		$data = array();
		foreach ($groupsuser as $user) {
			if ($user["id"]>3)
				$data[] = $user;
		}
		$response["draw"] = intval(1);
		$response["recordsTotal"] = intval(10);
		$response["recordsFiltered"] = intval(10);
		$response["data"] = $data;
		//$response["error"] = false;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);*/
	// listing all groups
	$db = new DbHandlerProfile();
	$result = $db->getUsersInGroupWeb($id);
	$response = array();
	if ($result) {
		$response["error"] = false;
		$response["users"] = $result;
	}
	else {
		$response["error"] = true;
		$response["message"] = "Failed to get groups of user. Please try again";
	}
	echoResponse(200, $response);
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
	$result = $fdb->getActiveProductsOfContractor($contractorid);
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
$app->get('/products', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('contractorid'));
	// reading get params
	$contractorid = $app->request->get('contractorid');
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
	$productid = $fdb->createProduct($contractorid, "", $price, "", $status, "");
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
		$code1c = NULL;
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
	verifyRequiredParams(array('id','name', 'price', 'info', 'status'));
	// reading put params
	$id = $app->request->post('id');
	$name = $app->request->post('name');
	$price = $app->request->post('price');
	$info = $app->request->post('info');
	$status = $app->request->post('status');
	// updating product
	$fdb = new DbHandlerFabricant();
	$result = $fdb->updateProduct($id, $name, $price, $info, $status);
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
 * Creating contractor
 * method POST
 * url /contractor
 */
$app->post('/contractors', 'authenticate', function() use ($app) {
	// creating new contracotor
	$db = new DbHandlerProfile();
	$status = 1;
	$type = 0;
	$new_id = $db->createGroupWeb("", $status, $type, "");
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Contractor created successfully";
		$response["id"] = $new_id;
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
	// check for required params
	verifyRequiredParams(array('name', 'address', 'phone', 'status', 'info'));
	// reading put params
	$name = $app->request->put('name');
	$address = $app->request->put('address');
	$phone = $app->request->put('phone');
	$status = $app->request->put('status');
	$info = $app->request->put('info');
	// updating contractor
	$db = new DbHandlerProfile();
	$result = $db->updateGroupWeb($id, $name, $address, $phone, $status, $info);
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
 * Creating customers
 * method POST
 * url /customers
 */
$app->post('/customers', function() use ($app) {
	// creating new customers
	$db = new DbHandlerProfile();
	$status = 1;
	$type = 1;
	$new_id = $db->createGroupWeb("", $status, $type, "");
	$response = array();
	if ($new_id != NULL) {
		$response["error"] = false;
		$response["message"] = "Customer created successfully";
		$response["id"] = $new_id;
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
$app->put('/customers/:id', function($id) use ($app) {
	// check for required params
	verifyRequiredParams(array('name', 'address', 'phone', 'status', 'info'));
	// reading put params
	$name = $app->request->put('name');
	$address = $app->request->put('address');
	$phone = $app->request->put('phone');
	$status = $app->request->put('status');
	$info = $app->request->put('info');
	// updating customers
	$db = new DbHandlerProfile();
	$result = $db->updateGroupWeb($id, $name, $address, $phone, $status, $info);
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
					$tmp["itemid"] = $item["productid"];
					$tmp["itemname"] = $item["name"];
					$tmp["itemcount"] = $item["count"];
					$tmp["itemprice"] = $item["price"];
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
		foreach ($data as $orderskey => $order) {
			$i=0;
			foreach ($order as $recordkey => $record) {
				$sheet->setCellValueByColumnAndRow($i++, $orderskey+2, $record);
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
	// array for final json respone
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
 * Uploading slides images
 * method POST
 * url /slides/upload/:prefix
 */
$app->post('/slides/upload/:prefix', 'authenticate', function($prefix) use ($app) {
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


//Operation numbers from WebsocketServer
define("M_CONSOLE_OPERATION_USER_CHANGED", 0);
define("M_CONSOLE_OPERATION_GROUP_CHANGED", 5);
define("M_CONSOLE_OPERATION_PRODUCT_CHANGED", 6);

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