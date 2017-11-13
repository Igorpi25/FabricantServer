<?php
namespace CRM;

require_once dirname(__FILE__) . '/../include/DbHandlerProfile.php';
require_once dirname(__FILE__) . '/../include/DbHandlerFabricant.php';
require_once dirname(__FILE__) . '/../include/DbHandlerCRM.php';
require_once dirname(__FILE__) . '/../include/PassHash.php';

require_once dirname(__FILE__) . '/../libs/Slim/Slim.php';
require_once dirname(__FILE__) . '/../libs/Slim/Middleware.php';

require_once dirname(__FILE__) . '/../communicator/WebsocketClient.php';

require_once dirname(__FILE__) . '/../libs/PHPExcel/PHPExcel.php';
require_once dirname(__FILE__) . '/../libs/PHPExcel/PHPExcel/IOFactory.php';

// Глобальные слои, проверки авторизации, и доступа домена
require_once './middlewares/TokenAuth.php';
require_once './middlewares/CORSAccessControl.php';

define('WEBSOCKET_SERVER_PORT', 8666);

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->add(new \CRM\Middleware\TokenAuth());
$app->add(new \CRM\Middleware\CORSAccessControl());

// Импорт контроллеров
require_once './controllers/BaseController.php';
require_once './controllers/UserController.php';
require_once './controllers/GroupController.php';
require_once './controllers/OrderController.php';
require_once './controllers/ProductController.php';

$app->group('/users', function () use ($app) {
    new \CRM\Controller\UserController($app);
});

$app->group('/groups', function () use ($app) {
    new \CRM\Controller\GroupController($app);
});

$app->group('/orders', function () use ($app) {
    new \CRM\Controller\OrderController($app);
});

$app->group('/products', function () use ($app) {
    new \CRM\Controller\ProductController($app);
});

//--------------------Permission--------------------------------

function permissionFabricantAdmin($userid)
{
    if ( ($userid == 1) || ($userid == 3)) return;
    $response["error"] = true;
    $response["message"] = "You have no permission. Only fabricant admin has permission";
    $response["success"] = 0;
    echoResponse(200, $response);

    global $app;
    $app->stop();
}

function permissionInGroup($userid, $groupid, $db_profile)
{
    $status = $db_profile->getUserStatusInGroup($groupid, $userid);

    if ($userid == 1 || $userid == 3) return;

    if ( ($status == 0) || ($status == 2) || ($status == 1)) return;

    $response["error"] = true;
    $response["message"] = "You have no permission. Only user in group has permission";
    $response["success"] = 0;
    echoResponse(200, $response);

    global $app;
    $app->stop();
}

function permissionAdminInGroup($userid, $groupid, $db_profile)
{
    $status = $db_profile->getUserStatusInGroup($groupid, $userid);

    if ($userid == 1 || $userid == 3) return;
    if ( ($status == 2) || ($status == 1)) return;

    $response["error"] = true;
    $response["message"] = "You have no permission. Only group admin has permission";
    $response["success"] = 0;
    echoResponse(200, $response);

    global $app;
    $app->stop();
}

function permissionSuperAdminInGroup($userid, $groupid, $db_profile)
{
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

function consoleCommand($header_json)
{
    global $api_key;
    $header_json["Api-Key"] = $api_key;

    $client = new WebsocketClient;
    $response = "{'message': 'ConsoleCommand. begin', 'status':'0'}";

    if ($client->connect($header_json, '127.0.0.1', WEBSOCKET_SERVER_PORT, "/")) {
        $data = fread($client->_Socket, 1024);
        $message_array = $client->_hybi10Decode($data); //implode(",",);
        $response = $message_array["payload"];

    }
    else {
        $response = "{'message':'ConsoleCommand. Connecting failed', 'status':'0'}";
    }

    $client->disconnect();
    $json = (array)json_decode($response);

    return $json;
}

function consoleCommandGroupUpdated($groupid)
{
    $json_header = array();
    $json_header["console"] = "v2/index/create_installment";
    $json_header["operation"] = M_CONSOLE_OPERATION_GROUP_CHANGED;
    $json_header["groupid"] = $groupid;
    try {
        $console_response = consoleCommand($json_header);
    } catch (Exception $e) {
        //Была ошибка. Изменение продукта не пойдет по коммуникатору


    }
}

function consoleCommandProductUpdated($productid)
{
    $json_header = array();
    $json_header["console"] = "v2/index/create_installment";
    $json_header["operation"] = M_CONSOLE_OPERATION_PRODUCT_CHANGED;
    $json_header["productid"] = $productid;

    try {
        $console_response = consoleCommand($json_header);
    } catch (Exception $e) {
        //Была ошибка. Изменение продукта не пойдет по коммуникатору


    }
}

$app->run();

?>
