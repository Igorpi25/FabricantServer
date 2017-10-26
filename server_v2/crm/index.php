<?php

require_once dirname(__FILE__).'/../include/SimpleImage.php';
require_once dirname(__FILE__).'/../include/DbHandlerProfile.php';
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
require_once dirname(__FILE__).'/../include/DbHandlerCRM.php';
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
$app->get('/hello/:name', 'allowAccessOrigin', function ($name) {
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
/**
 * Adding Middle Layer to allow origin header every request
 * Checking if the request has valid origin header
 */
function allowAccessOrigin(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $allowOrigins = [
        "http://192.168.1.3:8080",
        "https://adm.fabricant.pro",
        "https://crm.fabricant.pro"
    ];
    if (isset($headers["Origin"]) && in_array($headers["Origin"], $allowOrigins)) {
        $app = \Slim\Slim::getInstance();
        $app->response->headers->set("Access-Control-Allow-Origin", $headers["Origin"]);
        $app->response->headers->set("Access-Control-Allow-Credentials", "true");
    }
}
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

//--------------------CRM panel----------------------------
/**
 * Get groups events
 * method GET
 * url /groups/events
 */
 $app->get('/groups/events', 'authenticate', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('rangeStart', 'rangeEnd'));
    // reading get params
    $range_start = $app->request->get('rangeStart');
    $range_end = $app->request->get('rangeEnd');

    $db_profile = new DbHandlerProfile();
    $db_crm = new DbHandlerCRM();

    validateDateFromString($range_start);
    validateDateFromString($range_end);

    $result = $db_crm->getGroupsEvents($range_start, $range_end);
    
    $response = array();
    $groups = array();

    if (count($result) > 0) {
        $group = $result[0];
        $index = $group["id"];
        $events = array();

        foreach ($result as $key => $value) {
            if ($value["id"] != $index) {
                if (!empty($events)) {
                    $group["events"] = $events;
                }
                $groups[] = $group;

                $events = array();
                $group = $value;
                $index = $value["id"];
            }
            if (isset($value["events"])) {
                $events[] = $value["events"];
            }
            if ($key == count($result) - 1) {
                if (!empty($events)) {
                    $group["events"] = $events;
                }
                $groups[] = $group;
            }
        }
    }
    
    if ($result) {
        $response["error"] = false;
        $response["result"] = $groups;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to get groups events. Please try again";
    }
    echoResponse(200, $response);
});
/**
 * Get groups
 * method GET
 * url /groups
 */
 $app->get('/groups', 'authenticate', function() use ($app) {
    $db_profile = new DbHandlerProfile();
    $db_crm = new DbHandlerCRM();

    $result = $db_crm->getGroups();
    
    $response = array();
    
    if (isset($result)) {
        $response["error"] = false;
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to get groups. Please try again";
    }
    echoResponse(200, $response);
});
 /**
 * Get contractors
 * method GET
 * url /groups/contractors
 */
 $app->get('/groups/contractors', 'authenticate', function() use ($app) {
    $db_profile = new DbHandlerProfile();
    $db_crm = new DbHandlerCRM();

    $result = $db_crm->getContractors();
    
    $response = array();
    
    if (isset($result)) {
        $response["error"] = false;
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to get contractors. Please try again";
    }
    echoResponse(200, $response);
});
 /**
 * Get customers
 * method GET
 * url /groups/customers
 */
 $app->get('/groups/customers', 'authenticate', function() use ($app) {
    $db_profile = new DbHandlerProfile();
    $db_crm = new DbHandlerCRM();

    $result = $db_crm->getCustomers();
    
    $response = array();
    
    if (isset($result)) {
        $response["error"] = false;
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to get customers. Please try again";
    }
    echoResponse(200, $response);
});
/**
 * Get events
 * method GET
 * url /events
 */
 $app->get('/events', 'authenticate', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('rangeStart', 'rangeEnd'));
    // reading get params
    $range_start = $app->request->get('rangeStart');
    $range_end = $app->request->get('rangeEnd');

    $db_profile = new DbHandlerProfile();
    $db_crm = new DbHandlerCRM();

    validateDateFromString($range_start);
    validateDateFromString($range_end);

    $result = $db_crm->getEvents($range_start, $range_end);
    
    $response = array();
    
    if (isset($result)) {
        $response["error"] = false;
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to get events. Please try again";
    }
    echoResponse(200, $response);
});
 /**
 * Get events
 * method GET
 * url /events/:groupid
 */
 $app->get('/events/:id', 'authenticate', function($groupid) use ($app) {
    // check for required params
    verifyRequiredParams(array('rangeStart', 'rangeEnd'));
    // reading get params
    $range_start = $app->request->get('rangeStart');
    $range_end = $app->request->get('rangeEnd');

    $db_profile = new DbHandlerProfile();
    $db_crm = new DbHandlerCRM();

    validateDateFromString($range_start);
    validateDateFromString($range_end);

    $result = $db_crm->getGroupEvents($range_start, $range_end, $groupid);
    
    $response = array();
    
    if (isset($result)) {
        $response["error"] = false;
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to get events. Please try again";
    }
    echoResponse(200, $response);
});
/**
 * Creating event
 * method POST
 * url /events/create
 */
$app->post('/events/create', 'authenticate', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('groupid', 'noticeDate', 'message', 'priority'));
    // reading post params
    $group_id = $app->request->post('groupid');
    $notice_date = $app->request->post('noticeDate');
    $message = $app->request->post('message');
    $priority = $app->request->post('priority');
    global $user_id;
    // creating new event
    $db = new DbHandlerCRM();
    $result = $db->createEvent($group_id, $notice_date, $message, $priority, $user_id);

    $response = array();
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Event created successfully";
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to create event. Please try again";
    }
    echoResponse(201, $response);
});
/**
 * Updating event
 * method POST
 * url /groups/events/update
 */
$app->post('/events/update', 'authenticate', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('id', 'noticeDate', 'message', 'priority'));
    // reading put params
    $id = $app->request->post('id');
    $notice_date = $app->request->post('noticeDate');
    $message = $app->request->post('message');
    $priority = $app->request->post('priority');
    global $user_id;
    // updating event
    $db = new DbHandlerCRM();
    $result = $db->updateEvent($id, $notice_date, $message, $priority, $user_id);

    $response = array();
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Event updated successfully";
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to update event. Please try again";
    }
    echoResponse(200, $response);
});
/**
 * Removing event
 * method POST
 * url /groups/events/remove/:id
 */
$app->post('/events/remove/:id', 'authenticate', function($id) use ($app) {
    $db = new DbHandlerCRM();
    global $user_id;
    $result = $db->removeEvent($id, $user_id);
    $response = array();
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Event removed successfully";
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to remove event. Please try again";
    }
    echoResponse(200, $response);
});
/**
 * Accepting event
 * method POST
 * url /groups/events/accept/:id
 */
$app->post('/events/accept/:id', 'authenticate', function($id) use ($app) {
    $db = new DbHandlerCRM();
    global $user_id;
    $result = $db->acceptEvent($id, $user_id);
    $response = array();
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Event accepted successfully";
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to accept event. Please try again";
    }
    echoResponse(200, $response);
});
/**
 * Restore event operation
 * method POST
 * url /groups/events/restore/:id
 */
 $app->post('/events/restore/:id', 'authenticate', function($id) use ($app) {
    $db = new DbHandlerCRM();
    global $user_id;
    $result = $db->restoreEvent($id, $user_id);
    $response = array();
    if ($result) {
        $response["error"] = false;
        $response["message"] = "Event restored successfully";
        $response["result"] = $result;
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to restore event. Please try again";
    }
    echoResponse(200, $response);
});

/** Insert Orimi customers*/

$app->post('/import_customers_from_file', 'authenticate', function() use ($app) {

    global $user_id;
    permissionFabricantAdmin($user_id);

    //-------------------Берем Excel файл----------------------------

    if (!isset($_FILES["file"])) {
        throw new Exception('Param file is missing');
    }

    // Check if the file is missing
    if (!isset($_FILES["file"]["name"])) {
        throw new Exception('Property name of file param is missing');
    }

    // Check the file size > 100MB
    if($_FILES["file"]["size"] > 100*1024*1024) {
        throw new Exception('File is too big');
    }

    $tmpFile = $_FILES["file"]["tmp_name"];

    $ext = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
    $filename = date('dmY').'-'.uniqid('excel_for_import_customers-').$ext;

    /** NEED DELETE /FB **/

    $filepath = $_SERVER["DOCUMENT_ROOT"].'/fb/v2/reports/'.$filename;

    // Подключаем класс для работы с excel
    require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';

    // Подключаем класс для вывода данных в формате excel
    require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

    //$objPHPExcel = PHPExcel_IOFactory::load($path);

    $inputFileType = PHPExcel_IOFactory::identify($tmpFile);  // узнаем тип файла, excel может хранить файлы в разных форматах, xls, xlsx и другие
    $objReader = PHPExcel_IOFactory::createReader($inputFileType); // создаем объект для чтения файла
    $objPHPExcel = $objReader->load($tmpFile); // загружаем данные файла в объект
    
    // Set and get active sheet
    $objPHPExcel->setActiveSheetIndex(0);
    $worksheet = $objPHPExcel->getActiveSheet();
    $worksheetTitle = $worksheet->getTitle();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
    $nrColumns = ord($highestColumn) - 64;

    $db_profile = new DbHandlerProfile();
    $db_fabricant = new DbHandlerFabricant();

    $logs = array();
    for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
        $customer = array();

        $cells = array();

        for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
            $cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
            $cells[] = $cell->getValue();
        }

        $uid = $cells[0];
        $name = $cells[1];
        $name_full = $cells[2];
        $phone = $cells[3];
        $address = $cells[4];
        $contractorid = intval($cells[5]);
        $userid = intval($cells[6]);
        $status = 1;
        $type = 1;
        $log = array();
        $log[] = $rowIndex - 1;

        if (trim($name) === '' || trim($address) === '') {
            $log[] = "Name or address of customer not setted.";
        } else {
            $infoInit = '{"name":{"text":""},"name_full":{"text":""},"summary":{"text":""},"icon":{"image_url":""},"tags":[],"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]}';
            $info = json_decode($infoInit, true);
            $info["name"]["text"] = $name;
            $info["name_full"]["text"] = $name_full;

            $new_id = $db_profile->createGroupWeb($name, $address, $phone, $status, $type, json_encode($info, JSON_UNESCAPED_UNICODE));
    
            if($new_id != NULL) {
                $log[] = "Customer created with id=".$new_id.".";
                if ($userid > 0) {
                    $db_profile->addUserToGroup($new_id, $userid, $status);
                    $log[] = "User with id=".$userid." added to created group.";
                }
                if ($contractorid > 0 && isUID($uid)) {
                    $db_crm = new DbHandlerCRM();
                    $customerCodeSetResult = $db_crm->setCustomerCodeInContractor($new_id, $uid, $contractorid);
                    if ($customerCodeSetResult) {
                        $log[] = "Customer code setted.";
                    } else {
                        $log[] = "Error on set customer code.";
                    }
                }
                
            } else {
                $log[] = "Customer not created.";
            }
        }
        $logs[] = $log;
    }

    $response = array();
    $response["error"] = false;
    $response["success"] = 1;
    $response["log"] = $logs;

    echoResponse(200,$response);
});

function saveLogToXLSFile($filename, $titles, $data) {

    /** ON SERVER NEED DELETE /FB **/
    $filepath = $_SERVER["DOCUMENT_ROOT"].'/fb/v2/reports/'.$filename;

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

    /*foreach ($data as $key => $item) {
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
    }*/
    $objWriter = new PHPExcel_Writer_Excel5($xls);
    $objWriter->save($filepath);
}

//--------------------Validation--------------------------------

/** Validating date from string */

function validateDateFromString($date_string) {
    $app = \Slim\Slim::getInstance();
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $date_string);
    if ($d && $d->format('Y-m-d H:i:s') !== $date_string) {
        $response["error"] = true;
        $response["message"] = 'Date string is not valid.';
        echoResponse(400, $response);
        $app->stop();
    }
}

/** Validating uid string */

function isUID($uidString) {
    if(preg_match("#^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$#", $uidString) !== 1 ) {
        return FALSE;
    } else {
        return TRUE;
    }
}

//--------------------Permission--------------------------------

function permissionFabricantAdmin($userid) {
    if(($userid == 1) || ($userid == 3))return;
    $response["error"] = true;
    $response["message"] = "You have no permission. Only fabricant admin has permission";
    $response["success"] = 0;
    echoResponse(200, $response);

    global $app;
    $app->stop();
}

function permissionInGroup($userid, $groupid, $db_profile) {
    $status = $db_profile->getUserStatusInGroup($groupid, $userid);

    if ($userid == 1 || $userid == 3) return;

    if (($status == 0)||($status == 2) || ($status == 1)) return;

    $response["error"] = true;
    $response["message"] = "You have no permission. Only user in group has permission";
    $response["success"] = 0;
    echoResponse(200, $response);

    global $app;
    $app->stop();
}

function permissionAdminInGroup($userid, $groupid, $db_profile) {
    $status = $db_profile->getUserStatusInGroup($groupid, $userid);

    if ($userid == 1 || $userid == 3) return;
    if (($status == 2) || ($status == 1)) return;

    $response["error"] = true;
    $response["message"] = "You have no permission. Only group admin has permission";
    $response["success"] = 0;
    echoResponse(200, $response);

    global $app;
    $app->stop();
}

function permissionSuperAdminInGroup($userid, $groupid, $db_profile) {
    $status = $db_profile->getUserStatusInGroup($groupid, $userid);

    if ($userid == 1 || $userid == 3) return;
    if ($status == 1) return;

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

function consoleCommand($header_json) {
    global $api_key;
    $header_json["Api-Key"] = $api_key;

    $client = new WebsocketClient;
    $response = "{'message': 'ConsoleCommand. begin', 'status':'0'}";

    if ($client->connect($header_json, '127.0.0.1', WEBSOCKET_SERVER_PORT, "/")) {
        $data = fread($client->_Socket, 1024);
        $message_array = $client->_hybi10Decode($data); //implode(",",);
        $response = $message_array["payload"];

    } else {
        $response = "{'message':'ConsoleCommand. Connecting failed', 'status':'0'}";
    }

    $client->disconnect();
    $json = (array)json_decode($response);

    return $json;
}

function consoleCommandGroupUpdated($groupid) {
    $json_header = array();
    $json_header["console"] = "v2/index/create_installment";
    $json_header["operation"] = M_CONSOLE_OPERATION_GROUP_CHANGED;
    $json_header["groupid"] = $groupid;
    try {
        $console_response=consoleCommand($json_header);
    } catch(Exception $e) {
        //Была ошибка. Изменение продукта не пойдет по коммуникатору
    }
}

function consoleCommandProductUpdated($productid) {
    $json_header = array();
    $json_header["console"] = "v2/index/create_installment";
    $json_header["operation"] = M_CONSOLE_OPERATION_PRODUCT_CHANGED;
    $json_header["productid"] = $productid;

    try {
        $console_response=consoleCommand($json_header);
    } catch (Exception $e) {
        //Была ошибка. Изменение продукта не пойдет по коммуникатору
    }
}

$app->run();

?>
