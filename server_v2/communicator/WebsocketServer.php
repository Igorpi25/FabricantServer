<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
config: array
pid: string - file's name where will be saved pid(process id)
websocket: string - tcp://[ip]:[port] ,example: tcp://127.0.0.1:8666
log: string - file's name to save logs
*/

//------------------Transport constants----------------------------

define("TRANSPORT_NOTIFICATION",100);
define("TRANSPORT_TEXT",1);
define("TRANSPORT_MAP",2);
define("TRANSPORT_PROFILE",3);
define("TRANSPORT_FABRICANT",4);
define("TRANSPORT_MONITOR",5);
define("TRANSPORT_SESSION",6);

//------------------Map message constants ------------------------
define("OUTGOING_START_BROADCAST", 1);
define("OUTGOING_STOP_BROADCAST", 2);
define("OUTGOING_COORS", 3);
define("OUTGOING_CONFIRM_START_RECIEVE", 4);

define("INCOMING_START_RECIEVE", 1);
define("INCOMING_STOP_RECIEVE", 2);
define("INCOMING_COORS", 3);

define("RECIEVER_TYPE_FRIENDS",1);
define("RECIEVER_TYPE_ONE_USER",2);
define("RECIEVER_TYPE_GROUP",3);
define("RECIEVER_TYPE_ALL",4);

define("RECIEVER_RADIUS_MAX",300000);//In 300000 km radius

//-------------------Monitor------------------------------

define("OUTGOING_STATE", 1);
define("OUTGOING_LOG", 2);
define("OUTGOING_PONG", 3);

define("INCOMING_STATE_REQUEST", 1);
define("INCOMING_PING", 2);

//-------------------Session------------------------------

define("INCOMING_LOGIN", 1);
define("INCOMING_INFO", 2);

define("OUTGOING_LOGIN_DENIED", 1);
define("OUTGOING_INFO_REQUEST", 2);
define("OUTGOING_LAST_ANDROID_VERSION", 3);
define("OUTGOING_YOU_ARE_INCOGNITO", 4);

//-----------------Profile message constants (надо снизу перевести вот сюда )-----------------
define("OUTGOING_USERS_DELTA", 1);
define("OUTGOING_GROUPS_DELTA", 2);
define("OUTGOING_GROUP_USERS_DELTA", 3);

define("INCOMING_FRIEND_OPERATION", 1);
define("INCOMING_GROUP_OPERATION", 2);
define("INCOMING_ME_OPERATION", 3);

define("GROUPOPERATION_ADD_USERS", 0);
define("GROUPOPERATION_SAVE", 1);
define("GROUPOPERATION_CREATE", 2);
define("GROUPOPERATION_USER_STATUS", 4);

define("GROUPSTATUS_COMMON_USER", 0);
define("GROUPSTATUS_ADMIN_CREATER", 1);
define("GROUPSTATUS_ADMIN", 2);
define("GROUPSTATUS_BANNED", 3);
define("GROUPSTATUS_MISSING", 4);
define("GROUPSTATUS_LEAVE", 5);
define("GROUPSTATUS_REMOVED", 6);
define("GROUPSTATUS_NOT_IN_GROUP", 7);
define("GROUPSTATUS_AGENT", 8);

//Это на самом деле типы, надо потом исправить
define("GROUP_STATUS_DEFAULT", 0);
define("GROUP_STATUS_CONTRACTOR", 1);
define("GROUP_STATUS_CUSTOMER", 2);

//-----------------Fabricant message constants -----------------

define("OUTGOING_CHECK_CONNECTION", 0);
define("OUTGOING_PRODUCTS_DELTA", 1);
define("OUTGOING_ORDERS_DELTA", 2);
define("OUTGOING_PRODUCTS_REST_DELTA", 3);

define("INCOMING_CHECK_CONNECTION", 0);

define("ORDEROPERATION_CREATE", 0);
define("ORDEROPERATION_UPDATE", 1);
define("ORDEROPERATION_ACCEPT", 2);
define("ORDEROPERATION_REMOVE", 3);
define("ORDEROPERATION_TRANSFER", 4);
define("ORDEROPERATION_MAKE_PAID", 5);
define("ORDEROPERATION_HIDE", 6);

define("SALE_OPERATION_CREATE", 0);
define("SALE_OPERATION_REMOVE", 1);
define("SALE_OPERATION_UPDATE", 2);
define("SALE_OPERATION_ADD_TO_CUSTOMER", 3);
define("SALE_OPERATION_REMOVE_FROM_CUSTOMER", 4);
define("SALE_OPERATION_SET_DEFAULT_INSTALLMENT", 5);
define("SALE_OPERATION_CLEAR_DEFAULT_INSTALLMENTS", 6);

define("SALE_TYPE_SALE", 4);
define("SALE_TYPE_DISCOUNT", 5);
define("SALE_TYPE_INSTALLMENT", 6);

//-------------------Console-----------------------------------------

define("CONSOLE_OPERATION_USER_CHANGED", 0);
define("CONSOLE_OPERATION_GROUP", 1);
define("CONSOLE_OPERATION_CHECK_SERVER", 2);
define("CONSOLE_OPERATION_ORDER", 3);
define("CONSOLE_OPERATION_SALE", 4);
define("CONSOLE_OPERATION_GROUP_CHANGED", 5);
define("CONSOLE_OPERATION_PRODUCT_CHANGED", 6);
define("CONSOLE_OPERATION_NOTIFY_PRODUCTS", 7);
define("CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST", 8);

class WebsocketServer {

public $map_userid_connect=array();//HashMap key : userid, value: connect
public $map_connectid_userid=array();//HashMap key : connectid, value: userid ($connectid=getIdByConnect($connect))
public $map_connectid_framecount=array();//HashMap key : connectid, sent frames count
public $connects=array();

public $connects_incognito=array();
public $map_connectid_connect_incognito=array();
public $map_incognito_connectid_userid=array();
public $map_incognito_connectid_created_at=array();
public $map_incognito_connectid_last_timestamp=array();
public $session_ping_connectid=array();
public $session_ping_sent_at=array();

public $recievers=array();

public $db_chat,$db_profile,$db_map, $db_fabricant;

public function __construct($config) {
        $this->config = $config;

		require_once dirname(__FILE__)."/../include/DbHandlerChat.php";
		require_once dirname(__FILE__)."/../include/DbHandlerProfile.php";
		require_once dirname(__FILE__)."/../include/DbHandlerMap.php";
		require_once dirname(__FILE__)."/../include/DbHandlerFabricant.php";

		require_once dirname(__FILE__)."/../include/Config.php";
		require_once dirname(__FILE__)."/../include/DbConnect.php";


		$mysqli = mysqli_init();
		if (!$mysqli) {
			die('mysqli_init failed');
		}

		if (!$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 31536000)) {
			die('mysqli->options MYSQLI_OPT_CONNECT_TIMEOUT failed');
		}

		if (!$mysqli->real_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME)) {
			die('mysqli->real_connect failed (' . mysqli_connect_errno() . ') '. mysqli_connect_error());
		}

		//Set timezone
		DbConnect::setTimezone($mysqli);

		$this->db_chat = new DbHandlerChat($mysqli);
		$this->db_profile = new DbHandlerProfile($mysqli);
		$this->db_map = new DbHandlerMap($mysqli);
		$this->db_fabricant = new DbHandlerFabricant($mysqli);
}

public function Start(){

	/*$pid = @file_get_contents($this->config['pid']);
    if ($pid) {
        $this->log("Start. Failed. Another pid-file found pid=".$pid);
        die("Start. Failed. Another pid-file found pid=".$pid);
    }*/
	

	$socket = stream_socket_server($this->config['websocket'], $errno, $errstr);
	if (!$socket) {
	    die("e1 $errstr ($errno)\n");
	}
	file_put_contents($this->config['pid'], posix_getpid());
	
	error_log("Restarting server PID=".posix_getpid());

	$this->log("Start. Success. config=(".$this->config['websocket'].") pid=".posix_getpid());

	while (true) {
	    //формируем массив прослушиваемых сокетов:
	    $read = array_merge($this->connects,$this->connects_incognito);
	    $read []= $socket;
	    $write = $except = null;

	    if (!stream_select($read, $write, $except, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
	        break;
	    }

	    if (in_array($socket, $read)) {//есть новое соединение

	        //принимаем новое соединение и производим рукопожатие:
	        if (($connect = stream_socket_accept($socket, -1)) && $info = $this->handshake($connect)) {

				if( isset($info["console"]) ){//Локальная консольная команда

					$this->ProcessConsoleOperation($connect,$info);

				}else if(isset($info["userid"]) && isset($info["last_timestamp"])){//Обычный пользователь

					$userid=intval($info["userid"]);
					$last_timestamp=$info["last_timestamp"];

					//Если есть другой connect с таким же userid
					if($this->isUserIdHasConnect($userid)){

						$this->log("Connect. Accepted. connectid=".$this->getIdByConnect($connect).", userid=".$userid." already exists with connectid=".$this->getIdByConnect($this->getConnectByUserId($userid)).", last_timestamp=".$last_timestamp);
						$this->putConnectIncognito($connect,$userid,$last_timestamp);

						//Отправляем проверочный пинг
						$this->sendPing($this->getConnectByUserId($userid),strval($this->getIdByConnect($connect)));

					}else{

						$this->log("Connect. Accepted. connectid=".$this->getIdByConnect($connect).", userid=".$userid.", last_timestamp=".$last_timestamp);
						$this->putConnect($connect,$userid);
						$this->onOpen($connect, $info);//вызываем пользовательский сценарий
					}

				}else{ //Инкогнито


					$this->log("Connect. Accepted. connectid=".$this->getIdByConnect($connect).", userid = incognito");
					$this->putConnectIncognito($connect,0,0);

				}
	        }
	        unset($read[ array_search($socket, $read) ]);
	    }

	    foreach($read as $connect) {//обрабатываем все соединения
	        $data = fread($connect, 100000);

	        if (!$data) { //соединение было закрыто

				$this->log("Connect. Closed. connectid=".$this->getIdByConnect($connect).", userid=".$this->getUserIdByConnect($connect));

				$this->onClose($connect);//вызываем пользовательский сценарий

				if($this->isConnectIncognito($connect)){
					$this->removeConnectIncognito($connect);
				}else if(in_array($connect,$this->connects)){
					$this->removeConnect($connect);
				}

				fclose($connect);

	            continue;
	        }

			$this->log("DataRead. connectid=".$this->getIdByConnect($connect));

	        $this->OnMessage($connect,array_merge($this->connects,$this->connects_incognito), $data);

	    }
	}

	$this->log("close server");
	fclose($server);

}

public function Stop(){
	$pid = @file_get_contents($this->config['pid']);
        if ($pid) {
        	posix_kill($pid, 15);//SIGTERM=15
        	unlink($this->config['pid']);

        	$this->log("Stop. Success. pid=".$pid." Pid-file has been unlinked");
        } else {
        	$this->log("Stop. Pid-file not found. pid=".$pid);
        }
}

//--------------------Функции протокола Session ---------------------

protected function putConnectIncognito($connect,$userid,$last_timestamp) {
	$connectid=$this->getIdByConnect($connect);
	$this->log("putConnectIncognito. connectid=".$connectid." userid=".$userid." last_timestamp=".$last_timestamp);
	array_push($this->connects_incognito,$connect);
	$this->map_connectid_connect_incognito[strval($connectid)]=$connect;
	$this->map_incognito_connectid_userid[strval($connectid)]=$userid;
	$this->map_incognito_connectid_created_at[strval($connectid)]=date('Y-m-d H:i:s',time());
	$this->map_incognito_connectid_last_timestamp[strval($connectid)]=$last_timestamp;

	$this->sendFrame($connect, json_decode('{"transport":"100","value":"Connected to fabricant-server incognito"}',true));

	//Сообщаем ему, что он инкогнито
	$this->outgoingYouAreIncognito($connect);

	//Так как состояние изменилось отправляем сообщение монитору
	$this->outgoingStateToMonitors();
}

protected function removeConnectIncognito($connect) {

	$connectid=$this->getIdByConnect($connect);
	$this->log("removeConnectIncognito. connectid=".$connectid);
	unset($this->map_connectid_connect_incognito[strval($connectid)]);
	unset($this->map_incognito_connectid_userid[strval($connectid)]);
	unset($this->map_incognito_connectid_created_at[strval($connectid)]);
	unset($this->map_incognito_connectid_last_timestamp[strval($connectid)]);
	unset($this->connects_incognito[array_search($connect, $this->connects_incognito)]);

	//Так как состояние изменилось отправляем сообщение монитору
	$this->outgoingStateToMonitors();
}

protected function getConnectIncognitoById($connectid) {
	return $this->map_connectid_connect_incognito[strval($connectid)];
}

protected function isConnectIncognito($connect) {
	return in_array($connect,$this->connects_incognito);
}

protected function isConnectIdIncognito($connectid) {
	if(!array_key_exists(strval($connectid),$this->map_connectid_connect_incognito))return false;

	$connect=$this->getConnectIncognitoById($connectid);
	return in_array($connect,$this->connects_incognito);
}

protected function outgoingLoginDenied($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_SESSION;
	$json["type"]=OUTGOING_LOGIN_DENIED;
	$json["title"]="Обнаружено другое активное соединение";
	$json["text"]="Другое устройство использует этот аккаунт для входа в Фабрикант. Одновременно с одного аккаунта может быть только одно активное устройство. \n Если у вас есть другое устройство с этим аккаунтом, то вам нужно его закрыть, прежде чем использовать на этом устройстве. \n Если у вас нет других устройств с этим аккаунтом, то сообщите в техподдержку Фабриканта";

	$this->sendFrame($connect, $json);
}

protected function outgoingYouAreIncognito($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_SESSION;
	$json["type"]=OUTGOING_YOU_ARE_INCOGNITO;

	$this->sendFrame($connect, $json);
}

protected function outgoingInfoRequest($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_SESSION;
	$json["type"]=OUTGOING_INFO_REQUEST;

	$this->sendFrame($connect, $json);
}

protected function outgoingLastAndroidVersion($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_SESSION;
	$json["type"]=OUTGOING_LAST_ANDROID_VERSION;
	$json["versionName"]=1.8;
	$json["versionCode"]=9;
	$json["message"]="Вышла новая версия ".$json["versionName"].".\nИзменения:\n- Добавлена новая метка: \"Нет в наличии\", в списке товаров\n- По умолчанию \"Недавно заказанные\" не отображать \n- По умолчанию свернуть \"Историю заказов\"\n- Исправления в дизайне\n* ВНИМАНИЕ! После обновления рекомендуем сделать \"Сброс данных\" (Для этого: зайдите в Настройки -> Нажмите \"Сброс данных\")";

	$json["title"]="Обновление Фабриканта";

	$this->sendFrame($connect, $json);
}

protected function ProcessMessageSession($sender,$connects,$json) {

	$this->log("ProcessMessageSession. Sender.connectId=".$this->getIdByConnect($sender)." incognito, json=".json_encode($json,JSON_UNESCAPED_UNICODE));

	switch($json["type"]){
		case INCOMING_LOGIN :

			//Запрещаем прямую отправку userid
			if(isset($json["userid"])){
				$this->log("ProcessMessageSession INCOMING_LOGIN direct userid=".$json["userid"]." catched");
				unset($json["userid"]);
			}

			if(isset($json["phone"]) && isset($json["password"])){

				$phone=filter_var($json["phone"],FILTER_VALIDATE_INT);
				$password=$json["password"];

				try{

					if ($this->db_profile->checkLoginByPhone($phone, $password)) {
						$user = $this->db_profile->getUserByPhone($phone);
						$json["userid"]=$user["id"];
					}else{
						$this->log("ProcessMessageSession INCOMING_LOGIN incorrect phone or password");
					}

				}catch(Exception $e){
					$this->log("ProcessMessageSession INCOMING_LOGIN exception when process phone and password");
				}
			}

			if(!isset($json["userid"])){
				$this->log("ProcessMessageSession INCOMING_LOGIN breaked cause userid unset");
				return;
			}

			if(!isset($json["last_timestamp"])){
				$this->log("ProcessMessageSession INCOMING_LOGIN breaked cause last_timestamp missing");
				return;
			}

			$requested_userid=$json["userid"];
			$last_timestamp=$json["last_timestamp"];

			//Если user балуется, пытаясь залогиниться будучи залогиненным
			if($this->isConnectHasUserId($sender)){
				return;
			}

			//Если connect инкогнито
			if($this->isConnectIncognito($sender)){

				//Если есть другой connect с таким же userid
				if( $this->isUserIdHasConnect($requested_userid) ){

					if( isset($this->session_ping_connectid[strval($this->getIdByConnect($sender))]) &&
						( $this->session_ping_connectid[strval($this->getIdByConnect($sender))]==$this->getIdByConnect($this->getConnectByUserId($requested_userid)) )
						){

						//Если слишком быстро повторил запрос
						if( time()-$this->session_ping_sent_at[strval($this->getIdByConnect($sender))]<=5 ){
							$this->log("Connect. Failed SESSION_LOGIN. Too often requests. connectid=".$this->getIdByConnect($sender).", userid=".$requested_userid.", last_timestamp=".$last_timestamp." old_connectid=".$this->getIdByConnect($sender));
							return;
						}

						//Удаляем записи пинга
						unset($this->session_ping_connectid[strval($this->getIdByConnect($sender))]);
						unset($this->session_ping_sent_at[strval($this->getIdByConnect($sender))]);

						$this->log("Connect. Accepted by SESSION_LOGIN. connectid=".$this->getIdByConnect($sender).", userid=".$requested_userid.", last_timestamp=".$last_timestamp." old_connectid=".$this->getIdByConnect($sender));

						$json["last_timestamp"]=$this->map_incognito_connectid_last_timestamp[strval($this->getIdByConnect($sender))];

						$this->removeConnectIncognito($sender);
						$this->removeConnect($this->getConnectByUserId($requested_userid));
						$this->putConnect($sender,$requested_userid);
						$this->onOpen($sender, $json);//вызываем пользовательский сценарий

						return;

					}else{
						//Отправляем проверочный пинг
						$this->sendPing($this->getConnectByUserId($requested_userid),strval($this->getIdByConnect($sender)));
					}
					return;
				}else{

					$this->log("Connect. Accepted by SESSION_LOGIN. connectid=".$this->getIdByConnect($sender).", userid=".$requested_userid.", last_timestamp=".$last_timestamp);

					$json["last_timestamp"]=$this->map_incognito_connectid_last_timestamp[strval($this->getIdByConnect($sender))];

					$this->removeConnectIncognito($sender);
					$this->putConnect($sender,$requested_userid);
					$this->onOpen($sender, $json);//вызываем пользовательский сценарий
					return;
				}

			}


		break;

		case INCOMING_INFO:
			$this->log("ProcessMessageSession INCOMING_INFO connectid=".$this->getIdByConnect($sender)." userid=".$this->getUserIdByConnect($sender)." versionName=".$json["versionName"]." versionCode=".$json["versionCode"]);

		break;

	}

}

//--------------------Функции протокола Monitor ---------------------

protected function getMonitors(){
	$ids=array();
	$ids[]=strval(2);
	$ids[]=strval(1);
	$ids[]=strval(3);

	return $ids;
}

protected function isUserMonitor($userid){
	$monitors=$this->getMonitors();
	return in_array(strval($userid), $monitors);
}

protected function getState(){

	//-----------------Connects-----------------------

	$connects = array();
	foreach($this->connects as $connect){
		$item=array();
		$item["connect"]=intval($connect);
		$connects[]=$item;
	}

	$map_userid_connect = array();
	foreach($this->map_userid_connect as $userid => $connect){
		$item=array();
		$item["userid"]=$userid;
		$item["connect"]=intval($connect);
		$map_userid_connect[]=$item;
	}

	$map_connectid_userid = array();
	foreach($this->map_connectid_userid as $connectid => $userid){
		$item=array();
		$item["connectid"]=$connectid;
		$item["userid"]=$userid;
		$map_connectid_userid[]=$item;
	}

	$map_connectid_framecount = array();
	foreach($this->map_connectid_framecount as $connectid => $framecount){
		$item=array();
		$item["connectid"]=$connectid;
		$item["framecount"]=$framecount;
		$map_connectid_framecount[]=$item;
	}

	//---------------Connects Incognito---------------------

	$connects_incognito = array();
	foreach($this->connects_incognito as $connect_incognito){
		$item=array();
		$item["connect_incognito"]=intval($connect_incognito);
		$connects_incognito[]=$item;
	}

	$map_connectid_connect_incognito = array();
	foreach($this->map_connectid_connect_incognito as $connectid => $connect_incognito){
		$item=array();
		$item["connectid"]=$connectid;
		$item["connect_incognito"]=intval($connect_incognito);
		$map_connectid_connect_incognito[]=$item;
	}

	$map_incognito_connectid_userid = array();
	foreach($this->map_incognito_connectid_userid as $incognito_connectid => $userid){
		$item=array();
		$item["incognito_connectid"]=$incognito_connectid;
		$item["userid"]=$userid;
		$map_incognito_connectid_userid[]=$item;
	}

	$map_incognito_connectid_created_at = array();
	foreach($this->map_incognito_connectid_created_at as $incognito_connectid => $created_at){
		$item=array();
		$item["incognito_connectid"]=$incognito_connectid;
		$item["created_at"]=$created_at;
		$map_incognito_connectid_created_at[]=$item;
	}

	//---------------------Session------------------------

	$session_ping_connectid = array();
	foreach($this->session_ping_connectid as $ping_payload => $connectid){
		$item=array();
		$item["ping_payload"]=$ping_payload;
		$item["connectid"]=$connectid;
		$session_ping_connectid[]=$item;
	}

	$state=array();
	$state["PID"]=posix_getpid();
	$state["server_date"]=date("Y-m-d H:i:s");
	$state["connects"]=$connects;
	$state["map_userid_connect"]=$map_userid_connect;
	$state["map_connectid_userid"]=$map_connectid_userid;
	$state["map_connectid_framecount"]=$map_connectid_framecount;
	$state["connects_incognito"]=$connects_incognito;
	$state["map_connectid_connect_incognito"]=$map_connectid_connect_incognito;
	$state["map_incognito_connectid_userid"]=$map_incognito_connectid_userid;
	$state["map_incognito_connectid_created_at"]=$map_incognito_connectid_created_at;
	$state["session_ping_connectid"]=$session_ping_connectid;

	return $state;
}

protected function outgoingState($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_MONITOR;
	$json["type"]=OUTGOING_STATE;
	$json["state"]= $this->getState();

	$this->sendFrame($connect, $json);
}

protected function outgoingPong($connect,$payload){

 	$json = array();
    $json["transport"]=TRANSPORT_MONITOR;
	$json["type"]=OUTGOING_PONG;
	$json["payload"]= $payload;

	$this->sendFrame($connect, $json);
}

protected function outgoingStateToMonitors(){

	foreach($this->getMonitors() as $userid){
		if($this->isUserIdHasConnect($userid)){
			$this->outgoingState($this->getConnectByUserId($userid));
		}
	}

}

protected function ProcessMessageMonitor($sender,$connects,$json) {

	$this->log("ProcessMessageMonitor. Sender.connectId=".$this->getIdByConnect($sender).", Sender.userid=".$this->getUserIdByConnect($sender).", json=".json_encode($json,JSON_UNESCAPED_UNICODE));

	switch($json["type"]){
		case INCOMING_STATE_REQUEST :

			$userid=$this->getUserIdByConnect($sender);

			if($this->isUserMonitor($userid)){
				$this->outgoingState($sender);
			}

		break;

		case INCOMING_PING :

			$userid=$this->getUserIdByConnect($sender);

			if($this->isUserMonitor($userid)){

				if(!isset($json["payload"]))return;

				$this->outgoingPong($sender,$json["payload"]);
			}

		break;

	}

}


//--------------------Функции протокола Fabricant ---------------------

protected function outgoingOrder($connect,$order){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_ORDERS_DELTA;
    $json["orders"]= array();
	$json["orders"][]=$order;

	$this->sendFrame($connect, $json);
}

protected function outgoingOrdersDelta($connect,$timestamp){

	//Получаем только за три-дня
	if($timestamp==0){
		$timestamp=time()-(60*60*24*3);
	}

	$userid=$this->getUserIdByConnect($connect);
	$groups=$this->db_profile->getGroupsOfUser($userid);

	foreach($groups as $group){

		$orders=null;


		if( ($group["type"]==0) ){
			//Контрактор

			if( ($group["status_in_group"]==1)||($group["status_in_group"]==2) ){
				//Если статус в группе админ или супер-админ
				$orders=$this->db_fabricant->getOrdersDeltaOfContractor($group["id"],$timestamp);
			}else if( $group["status_in_group"]==8) {
				//Если статус в группе агент, получаем только свои заказы
				$orders=$this->db_fabricant->getOrdersDeltaOfContractorAgent($group["id"],$userid,$timestamp);
			}

		}else if($group["type"]==1){
			//Заказчик

			//Только обычный, админ, супер админ могут получать все заказы группы
			//Агенты не получают, потому-что получат этот же заказ в дельте контрактора куда они входят
			if( ($group["status_in_group"]==0)||($group["status_in_group"]==1)||($group["status_in_group"]==2) ){
				$orders=$this->db_fabricant->getOrdersDeltaOfCustomer($group["id"],$timestamp);
			}
		}

		if(isset($orders)&&(count($orders)>0)){
			$json = array();
			$json["transport"]=TRANSPORT_FABRICANT;
			$json["type"]=OUTGOING_ORDERS_DELTA;
			$json["orders"]=$orders;

			$this->sendFrame($connect, $json);
		}
	}

}

protected function outgoingNotifyOrderToCustomer($order,$groupid){

	//Get users list of group
    $users = $this->db_profile->getUsersInGroup($groupid);

	$this->log("<<outgoingNotifyOrderToCustomer orderid=".$order["id"]." groupid=".$groupid." :");

	foreach($users as $user) {
		$user_id=$user["userid"];
		$status_in_customer = $user["status_in_group"];

		//Если пользователь более не состоит в группе, то пропускаем его
		if(($status_in_customer!=0)&&($status_in_customer!=1)&&($status_in_customer!=2)&&($status_in_customer!=8))continue;

		//Агенты должны получать только заказы своих поставщиков
		if(($status_in_customer==8)){
			$status_in_contractor=$this->db_profile->getUserStatusInGroup($order["contractorid"],$user_id);

			//Если агент не состоит в группе поставщика для этого заказа, то пропускаем его
			if(($status_in_contractor!=1)&&($status_in_contractor!=2)&&($status_in_contractor!=8))continue;
		}

		//If user connected then notify him
		if( array_key_exists(strval($user_id), $this->map_userid_connect) ){
			$connect=$this->getConnectByUserId($user_id);

			$this->outgoingOrder($connect,$order);
		}

	}
	$this->log(">>");

}

protected function outgoingNotifyOrderToCustomerById($orderid,$groupid){

	$order=$this->db_fabricant->getOrderById($orderid);

	$this->outgoingNotifyOrderToCustomer($order,$groupid);
}

protected function outgoingNotifyOrderToContractor($order,$groupid){

	//Get users list of group
    $users = $this->db_profile->getUsersInGroup($groupid);

	$this->log("<<outgoingNotifyOrderToContractor orderid=".$order["id"]." groupid=".$groupid." :");

	foreach($users as $user) {
		$user_id=$user["userid"];

		$status_in_contractor=$user["status_in_group"];

		//Если пользователь не состоит в группе поставщика, то пропускаем его
		if(($status_in_contractor!=1)&&($status_in_contractor!=2)&&($status_in_contractor!=0))continue;


		//If user connected then notify him
		if( array_key_exists(strval($user_id), $this->map_userid_connect) ){
			$connect=$this->getConnectByUserId($user_id);

			$this->outgoingOrder($connect,$order);
		}

	}
	$this->log(">>");

}

protected function outgoingNotifyOrderToContractorById($orderid,$groupid){

	$order=$this->db_fabricant->getOrderById($orderid);

	$this->outgoingNotifyOrderToContractor($order,$groupid);
}

protected function outgoingNotifyContractorProductsDelta($contractorid,$timestamp){

    //Prepare json with changed products
    $result = $this->db_fabricant->getContractorProductsDelta($contractorid,$timestamp);
	$json = array();
	$json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_PRODUCTS_DELTA;
	$json["products"]=$result;

	$this->log("<<outgoingNotifyContractorProductsDelta contractorid=".$contractorid." timestamp=".$timestamp." :");

	foreach($this->connects as $connect) {

		$this->sendFrame($connect, $json);

	}
	$this->log(">>");


}

protected function outgoingProductsDelta($connect,$timestamp){

    // Listing users have changed since $timestamp

    $result = $this->db_fabricant->getProductsDelta($timestamp);

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_PRODUCTS_DELTA;
    $json["products"]=$result;

	$this->sendFrame($connect, $json);
}

protected function outgoingCheckConnection($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_CHECK_CONNECTION;
    $json["message"]="Hello, Igor. Не печалься, скоро будет все!";

	$this->sendFrame($connect, $json);
}

protected function outgoingNotifyProductChanged($productid){

	$product=$this->db_fabricant->getProductById($productid);
	$contractorid=$product["contractorid"];

	$this->log("<<outgoingNotifyProductChanged productid=".$productid." contractorid=".$contractorid." :");

	foreach($this->connects as $connect) {

		$this->outgoingProduct($connect,$product);

	}
	$this->log(">>");

}

protected function outgoingNotifyProducts($products){


	$this->log("<<outgoingNotifyProducts products count=".count($products));

	foreach($this->connects as $connect) {

		$json = array();
		$json["transport"]=TRANSPORT_FABRICANT;
		$json["type"]=OUTGOING_PRODUCTS_DELTA;
		$json["products"]=$products;

		$this->sendFrame($connect, $json);

	}
	$this->log(">>");

}

protected function outgoingProductById($connect,$productid){

	$product = $this->db_fabricant->getProductById($productid);

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_PRODUCTS_DELTA;
    $json["products"]= array();
	$json["products"][]=$product;

	$this->sendFrame($connect, $json);
}

protected function outgoingProduct($connect,$product){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_PRODUCTS_DELTA;
    $json["products"]= array();
	$json["products"][]=$product;

	$this->sendFrame($connect, $json);
}

protected function outgoingRemoteToast($connect,$text){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["remote"]="toast";
	$json["text"]=$text;

	$this->sendFrame($connect, $json);
}

protected function outgoingRemoteInfoDialog($connect,$title,$text){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["remote"]="dialog";
	$json["type"]="info";
    $json["title"]=$title;
	$json["text"]=$text;

	$this->sendFrame($connect, $json);
}

protected function outgoingRemoteUpdateDialog($connect,$title,$text){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["remote"]="dialog";
	$json["type"]="update";
    $json["title"]=$title;
	$json["text"]=$text;

	$this->sendFrame($connect, $json);
}

protected function outgoingRemoteReset($connect){

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["remote"]="reset";

	$this->sendFrame($connect, $json);
}

protected function outgoingProductsRestDelta($connect,$contractorid,$timestamp){

    // Listing users have changed since $timestamp

	$this->log("<<outgoingProductsRestDelta");

    $result = $this->db_fabricant->getProductsRestDelta($contractorid,$timestamp);

 	$json = array();
    $json["transport"]=TRANSPORT_FABRICANT;
	$json["type"]=OUTGOING_PRODUCTS_REST_DELTA;
    $json["products"]=$result;

	$this->sendFrame($connect, $json);

	$this->log(">>");
}

protected function outgoingNotifyContractorProductsRest($contractorid,$products){

	/*$users = $this->db_profile->getUsersInGroup($contractorid);
	$this->log("<<outgoingNotifyContractorProductsRest contractorid=".$contractorid." :");
	foreach($users as $user) {

		//Только админы и агенты получают остатки
		if(($user["status_in_group"]!=0)&&($user["status_in_group"]!=1)&&($user["status_in_group"]!=2)&&($user["status_in_group"]!=8)){
			continue;
		}

		$user_id=$user["userid"];
		//If user connected then notify him
		if( array_key_exists(strval($user_id), $this->map_userid_connect) ){
			$connect=$this->getConnectByUserId($user_id);

			$json = array();
			$json["transport"]=TRANSPORT_FABRICANT;
			$json["type"]=OUTGOING_PRODUCTS_REST_DELTA;
			$json["products"]=$products;

			$this->sendFrame($connect, $json);
		}
	}
	$this->log(">>");*/

}

protected function ProcessMessageFabricant($sender,$connects,$json) {

	$this->log("ProcessMessageFabricant. Sender.connectId=".$this->getIdByConnect($sender).", Sender.userid=".$this->getUserIdByConnect($sender).", json=".json_encode($json,JSON_UNESCAPED_UNICODE));

	switch($json["type"]){
		case INCOMING_CHECK_CONNECTION :
			$this->outgoingCheckConnection($sender);
		break;

	}

}

//--------------------Функции протокола Profile (profile protocol methods)---------------------

protected function outgoingUsersDelta($connect,$timestamp){

    // Listing users have changed since $timestamp

    $result = $this->db_profile->getUsersDelta($this->getUserIdByConnect($connect),$timestamp);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_USERS_DELTA;
    $json["users"]=$result;

	$this->sendFrame($connect, $json);
}

protected function outgoingOneUser($connect,$userid){

    // Send one user info to $connect

    $one_user = $this->db_profile->getFriendById($this->getUserIdByConnect($connect),$userid);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_USERS_DELTA;
    $json["users"]=array();
	$json["users"][]=$one_user;

	$this->sendFrame($connect, $json);
}

protected function outgoingNotifyFriends($userid){

    // Notify about user info changed

    $friends = $this->db_profile->getAllFriends($userid);

	$this->log("<<outgoingNotifyFriends:");

	foreach($friends as $friend) {
		$friend_id=$friend["id"];
		if( array_key_exists(strval($friend_id), $this->map_userid_connect) ){
			$friend_connect=$this->getConnectByUserId($friend_id);

			$this->outgoingOneUser($friend_connect,$userid);
		}

	}
	$this->log(">>");

}

protected function outgoingNotifyGroupmates($groupid,$userid){

    // Notify about user info changed

	$this->log("<outgoingNotifyGroupmates groupid=$groupid userid=$userid:");

	$groupmates=$this->db_profile->getUsersInGroup($groupid);

	foreach($groupmates as $groupmate) {
		$groupmate_id=$groupmate["userid"];
		$status_in_group=$groupmate["status_in_group"];

		//Уведомляем только тех кто состоит в группе, удаленных не уведомляем
		if(($status_in_group!=0)&&($status_in_group!=1)&&($status_in_group!=2)&&($status_in_group!=8))continue;

		if( array_key_exists(strval($groupmate_id), $this->map_userid_connect) ){
			$groupmate_connect=$this->getConnectByUserId($groupmate_id);
			$this->outgoingOneUser($groupmate_connect,$userid);
		}
	}


	$this->log(">");
}

protected function outgoingNotifyAllGroupmates($userid){

    // Notify about user info changed

    $groups = $this->db_profile->getGroupsOfUser($userid);

	$this->log("<<outgoingNotifyAllGroupmates. userid=$userid:");

	foreach($groups as $group) {
		$group_id=$group["id"];

		$this->outgoingNotifyGroupmates($group_id,$userid);

	}
	$this->log(">>");

}

protected function outgoingCustomersDelta($connect,$timestamp){

    // Listing group have changed since $timestamp

    $result = $this->db_profile->getCustomersDelta($this->getUserIdByConnect($connect),$timestamp);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_GROUPS_DELTA;
    $json["groups"]=$result;

	$this->sendFrame($connect, $json);
}

protected function outgoingContractorsDelta($connect,$timestamp){

    // Listing group have changed since $timestamp

    $result = $this->db_profile->getContractorsDelta($timestamp);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_GROUPS_DELTA;
    $json["groups"]=$result;

	$this->sendFrame($connect, $json);
}

protected function outgoingGroupUsersDelta($connect,$timestamp){

    // Listing group users have changed since $timestamp

    $result = $this->db_profile->getGroupUsersDelta($this->getUserIdByConnect($connect),$timestamp);

	// Check users in result before operation
	$this->outgoingUsersIfUnknown($connect,$result);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_GROUP_USERS_DELTA;
    $json["group_users"]=$result;

	$this->sendFrame($connect, $json);
}

protected function outgoingGroupmatesDelta($connect,$timestamp){

    // Listing groupmates info have changed since $timestamp

    $result = $this->db_profile->getGroupmatesDelta($this->getUserIdByConnect($connect),$timestamp);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_USERS_DELTA;
    $json["users"]=$result;

	$this->sendFrame($connect, $json);
}

protected function outgoingGroup($connect,$groupid){

    // Send one group

	$groups = $this->db_profile->getGroupById($groupid);

 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_GROUPS_DELTA;
    $json["groups"]= $groups;

	$this->sendFrame($connect, $json);
}

protected function outgoingGroupUsers($connect,$changed_users){

	// Check changed_users before operation
	$this->outgoingUsersIfUnknown($connect,$changed_users);

    // Send changed users of group
 	$json = array();
    $json["transport"]=TRANSPORT_PROFILE;
	$json["type"]=OUTGOING_GROUP_USERS_DELTA;
    $json["group_users"]=$changed_users;

	$this->sendFrame($connect, $json);
}

protected function outgoingUsersIfUnknown($connect,$users){
	// Send users which friend-status STATUS_DEFAULT:0

	$connect_userid=$this->getUserIdByConnect($connect);

	foreach($users as $user){
		$status=$this->db_profile->getFriendStatus($connect_userid,$user["userid"]);
		if(($status!=3)||($status!=1)||($status!=2)){
			$this->outgoingOneUser($connect,$user["userid"]);
		}
	}
}

protected function outgoingNotifyGroupChanged($groupid){

	$groups = $this->db_profile->getGroupById($groupid);
	if(!isset($groups) || count($groups)!=1 ){

		$this->log("outgoingNotifyGroupChanged groupid=".$groupid." not found, or has problem");
		return;
	}

	$group=$groups[0];

	// Notify all users if group is contractor
	if($group["type"]==0){
		$this->log("<<outgoingNotifyGroupChanged groupid=".$groupid." :");
		foreach($this->connects as $connect) {
			$this->outgoingGroup($connect,$groupid);
		}
		$this->log(">>");
		return;
	}

	// Notify only groupmates if customer
	if($group["type"]==1){
		$users = $this->db_profile->getUsersInGroup($groupid);
		$this->log("<<outgoingNotifyGroupChanged groupid=".$groupid." :");
		foreach($users as $user) {
			$user_id=$user["userid"];
			$status_in_group=$user["status_in_group"];

			//Уведомляем только тех кто состоит в группе, удаленных не уведомляем
			if(($status_in_group!=0)&&($status_in_group!=1)&&($status_in_group!=2)&&($status_in_group!=8))continue;

			//If user connected then notify him
			if( array_key_exists(strval($user_id), $this->map_userid_connect) ){
				$connect=$this->getConnectByUserId($user_id);

				$this->outgoingGroup($connect,$groupid);
			}

		}
		$this->log(">>");
	}
}

protected function outgoingNotifyGroupsChanged($groups){

	if(!isset($groups)){
		return;
	}

	foreach($groups as $group){
		$this->outgoingNotifyGroupChanged($group["id"]);
	}
}

protected function outgoingNotifyGroupUsersChanged($groupid,$changed_users){
    // Notify all users of group(groupid) about some users has been changed

    $users = $this->db_profile->getUsersInGroup($groupid);

	$this->log("<<outgoingNotifyGroupUsers groupid=".$groupid." :");

	foreach($users as $user) {
		$user_id=$user["userid"];

		//If user connected then notify him
		if( array_key_exists(strval($user_id), $this->map_userid_connect) ){
			$connect=$this->getConnectByUserId($user_id);

			$this->outgoingGroupUsers($connect,$changed_users);
		}

	}

	$this->log(">>");

}

protected function groupOperationAddUsers($senderid,$groupid,$users){

	$this->log("groupOperationAddUsers. senderid=".$senderid." groupid=".$groupid." users=".json_encode($users,JSON_UNESCAPED_UNICODE));

    //Presently all user consists in group can do that operation. Status: 0,1,2
	$current_user_status=$this->db_profile->getUserStatusInGroup($groupid,$senderid);
	if( !( ($current_user_status==1)||($current_user_status==2) ) ){
		$this->log("groupOperationAddUsers. No permission. Sender not in group. senderid=".$senderid." groupid=".$groupid);
		return;
	}

	$status = 0;//Common user status
	$changed_at = time();

	$changed_users = array();

	foreach($users as $user){
		$userid=$user["id"];

		//You can add user to group only if this user is your friend
		/*$friend_status=$this->db_profile->getFriendStatus($senderid,$userid);
		if($friend_status!=3){
			$this->log("groupOperationAddUsers. No permission. User not friend of sender. senderid=".$senderid." userid=".$userid);
			continue;
		}*/

		$this->db_profile->addUserToGroup($groupid,$userid,$status);

		//Send group-info and group-users to new user
		if(array_key_exists(strval($userid), $this->map_userid_connect)) {

			$user_connect=$this->getConnectByUserId($userid);

			$this->outgoingGroup($user_connect, $groupid);
			$group_users=$this->db_profile->getUsersInGroup($groupid);

			//Send values to groupusers-table
			$this->outgoingGroupUsers($user_connect, $group_users);

			//Send info about users in group
			$this->outgoingUsersIfUnknown($user_connect,$group_users);

		}
		//Send new user-info to groupmates
		$this->outgoingNotifyGroupmates($groupid,$userid);

		$this->log("groupOperationAddUsers. User added. userid=".$userid." groupid=".$groupid);

		$new_user = array();
		$new_user["groupid"]=$groupid;
		$new_user["userid"]=$userid;
		$new_user["status_in_group"]=$status;
		$new_user["changed_at"]=$changed_at;

		$changed_users[]=$new_user;

		//$this->sendSMS("Group".$groupid."Sender".$senderid."AddUser".$userid);
	}

	$this->log("groupOperationAddUsers. Users added. added_users=".json_encode($changed_users,JSON_UNESCAPED_UNICODE));

	$this->outgoingNotifyGroupUsersChanged($groupid,$changed_users);

	foreach($changed_users as $user){

	}

}

protected function groupOperationSave($senderid,$groupid,$json){

    //Presently all user consists in group can do that operation. Status: 0,1,2
	$current_user_status=$this->db_profile->getUserStatusInGroup($groupid,$senderid);
	if( !( ($current_user_status==1)||($current_user_status==2) ) ){
		$this->log("INCOMING_GROUP_OPERATION. No permission. senderid=".$senderid." groupid=".$groupid." operationid=".$operationid);
		return;
	}

	if(isset($json["name"])){
		$name = $json["name"];
		$this->db_profile->changeGroupName($name,$groupid);

		$groups=$this->db_profile->getGroupById($groupid);
		if(sizeof($groups)!=1)return;
		$groups=$this->db_profile->getGroupById($groupid);
		$json_info=json_decode($groups[0]["info"],true);
		$json_info["name"]["text"]=$name;
		$this->db_profile->changeGroupInfo(json_encode($json_info,JSON_UNESCAPED_UNICODE),$groupid);
	}

	if(isset($json["address"])){
		$address = $json["address"];
		$this->db_profile->changeGroupAddress($address,$groupid);
	}

	if(isset($json["phone"])){
		$phone = $json["phone"];
		$this->db_profile->changeGroupPhone($phone,$groupid);
	}

	if(isset($json["name_full"])){
		$name_full = $json["name_full"];

		$groups=$this->db_profile->getGroupById($groupid);
		if(sizeof($groups)!=1)return;
		$groups=$this->db_profile->getGroupById($groupid);
		$json_info=json_decode($groups[0]["info"],true);
		$json_info["name_full"]["text"]=$name_full;

		$this->db_profile->changeGroupInfo(json_encode($json_info,JSON_UNESCAPED_UNICODE),$groupid);

	}

	if(isset($json["quadrature"])){
		$quadrature = $json["quadrature"];

		$groups=$this->db_profile->getGroupById($groupid);
		if(sizeof($groups)!=1)return;
		$groups=$this->db_profile->getGroupById($groupid);
		$json_info=json_decode($groups[0]["info"],true);
		$json_info["quadrature"]=$quadrature;
		$this->db_profile->changeGroupInfo(json_encode($json_info,JSON_UNESCAPED_UNICODE),$groupid);

	}

	$this->log("groupOperationSave. Group saved. groupid=".$groupid);

	$this->outgoingNotifyGroupChanged($groupid);

}

protected function groupOperationCreate($senderid){

	$groupid=$this->db_profile->createGroup($senderid);

	$this->log("groupOperationCreate. Group created. senderid=".$senderid." groupid=".$groupid);

	$changed_users= json_decode('[{"userid":'.$senderid.', "groupid":'.$groupid.', "status_in_group":1, "changed_at": '.time().'}]',true);

	$this->outgoingNotifyGroupChanged($groupid);
	$this->outgoingNotifyGroupUsersChanged($groupid,$changed_users);

	return $groupid;

}

protected function groupOperationUserStatus($senderid,$userid,$groupid,$status){


	$sender_status=$this->db_profile->getUserStatusInGroup($groupid,$senderid);
	$user_status=$this->db_profile->getUserStatusInGroup($groupid,$userid);

	$count=0;

	switch($status){
		case GROUPSTATUS_LEAVE :
			if( (($sender_status==0)||($sender_status==1)||($sender_status==2)||($sender_status==8)) &&($senderid==$userid) ){
				$count=$this->db_profile->changeUserStatusInGroup($groupid,$userid,$status);
			}
		break;

		case GROUPSTATUS_REMOVED :
			if( (($sender_status==1)||($sender_status==2)) && ($senderid!=$userid) && (($user_status==0)||($user_status==2)||($user_status==8)) ){
				$count=$this->db_profile->changeUserStatusInGroup($groupid,$userid,$status);
			}
		break;

		//Передача статуса суперадмин. При этом сам становится обычным админом
		case GROUPSTATUS_ADMIN_CREATER :
			if( ($sender_status==1) && ($senderid!=$userid) && (($user_status==0)||($user_status==2)||($user_status==8)) ){
				$this->db_profile->changeUserStatusInGroup($groupid,$userid,GROUPSTATUS_ADMIN_CREATER);
				$this->db_profile->changeUserStatusInGroup($groupid,$senderid,GROUPSTATUS_ADMIN);

				$this->log("groupOperationUserStatus. transfer_superadmin. senderid=".$senderid." userid=".$userid." groupid=".$groupid);

				$changed_users= json_decode('
					[
						{"userid":'.$userid.', "groupid":'.$groupid.', "status_in_group":'.GROUPSTATUS_ADMIN_CREATER.', "changed_at": '.time().'},
						{"userid":'.$senderid.', "groupid":'.$groupid.', "status_in_group":'.GROUPSTATUS_ADMIN.', "changed_at": '.time().'}
					]
				',true);

				$this->outgoingNotifyGroupUsersChanged($groupid,$changed_users);

			}
		break;

		case GROUPSTATUS_ADMIN :
			if( (($sender_status==1)||($sender_status==2)) && ($senderid!=$userid) && (($user_status==0)||($user_status==8)) ){
				$count=$this->db_profile->changeUserStatusInGroup($groupid,$userid,$status);
			}
		break;

		case GROUPSTATUS_COMMON_USER :
			if(
				( (($sender_status==1)||($sender_status==2)) && ($senderid!=$userid) && (($user_status==2)||($user_status==8)) ) ||
				( (($sender_status==1)||($sender_status==2)||($sender_status==8)) && ($senderid==$userid) )
			){
				$count=$this->db_profile->changeUserStatusInGroup($groupid,$userid,$status);
			}
		break;

		case GROUPSTATUS_AGENT :

			//Если отправитель супер-админ, то он не может стать агентом
			if(
				( (($sender_status==1)||($sender_status==2)) && ($senderid!=$userid) && (($user_status==0)||($user_status==2)) ) ||
				( ($sender_status==1) && ($senderid==$userid) )
			) {
				$count=$this->db_profile->changeUserStatusInGroup($groupid,$userid,$status);
			}
		break;

	}

	$this->log("groupOperationUserStatus. count=".$count.". senderid=".$senderid." userid=".$userid." groupid=".$groupid);

	if($count>0){
		$changed_users= json_decode('[{"userid":'.$userid.', "groupid":'.$groupid.', "status_in_group":'.$status.', "changed_at": '.time().'}]',true);
		$this->outgoingNotifyGroupUsersChanged($groupid,$changed_users);
	}

	return $count;
}

protected function ProcessMessageProfile($sender,$connects,$json) {

	$this->log("ProcessMessageProfile. Sender.connectId=".$this->getIdByConnect($sender).", Sender.userid=".$this->getUserIdByConnect($sender).", json=".json_encode($json,JSON_UNESCAPED_UNICODE));

	switch($json["type"]){
		case INCOMING_FRIEND_OPERATION :

			// reading post params
			$userid = $this->getUserIdByConnect($sender);
			$friendid = $json["friendid"];
			$operationid = $json["operationid"];

			$result=$this->db_profile->friendOperation($userid,$friendid,$operationid);

			if($result!=NULL){

				$delivered=false;

				$this->outgoingOneUser($sender,$friendid);

				foreach($connects as $connect){
					if ($this->getUserIdByConnect($connect)==$friendid) {
						$this->outgoingOneUser($connect,$userid);
						$delivered=true;
						break;
					}
				}
				if(!$delivered)	$this->log("outgoingOneUser. Offline. friendid=".$friendid." Message not delivered");
			}

		break;

		case INCOMING_GROUP_OPERATION :

			// reading post params
			$senderid = $this->getUserIdByConnect($sender);
			$operationid = $json["operationid"];

			switch($operationid){
				case GROUPOPERATION_ADD_USERS :
					$users=$json["users"];
					$groupid = $json["groupid"];
					$this->groupOperationAddUsers($senderid,$groupid,$users);
				break;

				case GROUPOPERATION_SAVE :
					$groupid = $json["groupid"];
					$this->groupOperationSave($senderid,$groupid,$json);
					//$this->sendSMS("Group".$groupid."SavedSender".$senderid);
				break;

				case GROUPOPERATION_CREATE :
					//$this->groupOperationCreate($senderid);
				break;

				case GROUPOPERATION_USER_STATUS :
					$groupid = $json["groupid"];
					$userid = $json["userid"];
					$status = $json["status"];
					$this->groupOperationUserStatus($senderid,$userid,$groupid,$status);
					//$this->sendSMS("Group".$groupid."User".$userid."Status".$status);
				break;
			}

		break;

		case INCOMING_ME_OPERATION :

			// reading post params
			$user_id = $this->getUserIdByConnect($sender);

			//Сохранение name
			if(isset($json["name"])){

				$name=$json["name"];

				$user_json = $this->db_profile->getUserById($user_id);
				$status=$user_json["status"];

				$this->db_profile->updateUserName($user_id,$name);

				$this->log("Sender:");
				//Уведомляем отправителя об изменении name
				$this->outgoingOneUser($sender,$user_id);

				//Уведомляем друзей
				$this->outgoingNotifyFriends($user_id);
				$this->outgoingNotifyAllGroupmates($user_id);

			}

		break;

	}



}

//--------------------Console operations-----------------------------------

//Используется чтобы пересчитать оперативные остатки
protected function recalculateInOrderRest($contractorid){

	error_log("----------------recalculateInOrderRest---------------------");

	/*$orders=$this->db_fabricant->getAllProcessingOrdersOfContractor($contractorid);

	error_log("orders count=".count($orders));

	$rests=array();

	foreach($orders as $order){
		 $record=json_decode($order["record"],true);

		 $items=$record["items"];

		 foreach($items as $item){
			if(isset($rests[strval($item["productid"])])){
				$rests[strval($item["productid"])]+=$item["count"];
			}else{
				$rests[strval($item["productid"])]=$item["count"];
			}
		 }
	}

	foreach($rests as $productid=>$rest){

		$product_rest=json_decode($this->db_fabricant->getProductRestById($productid),true);

		if((!isset($product_rest["in_order"]))||($product_rest["in_order"]!=$rest)){
			//Просто присвоение, т.к. уже пересчитаны все заказы
			$product_rest["in_order"]=0;//$rest;

			$product_rest_string=json_encode($product_rest,JSON_UNESCAPED_UNICODE);
			$this->db_fabricant->setProductRestById($productid,$product_rest_string);

			error_log("productid=$productid rest: ".json_encode($product_rest,JSON_UNESCAPED_UNICODE));

			$product=array();
			$product["productid"]=$productid;
			$product["rest"]=$product_rest_string;

			$products[]=$product;
		}
	}*/

  $rests=$this->db_fabricant->getAllProductsRestLikeObject();

	foreach($rests as $productid=>$rest){

		$product_rest=json_decode($this->db_fabricant->getProductRestById($productid),true);

		if((isset($product_rest["in_order"]))&&($product_rest["in_order"]!=0)){
			//Просто присвоение, т.к. уже пересчитаны все заказы
			$product_rest["in_order"]=0;//$rest;

			$product_rest_string=json_encode($product_rest,JSON_UNESCAPED_UNICODE);
			$this->db_fabricant->setProductRestById($productid,$product_rest_string);

			error_log("productid=$productid rest: ".json_encode($product_rest,JSON_UNESCAPED_UNICODE));

			$product=array();
			$product["productid"]=$productid;
			$product["rest"]=$product_rest_string;

			$products[]=$product;
		}
	}

	error_log("recalculated products rest count=".count($products));

	return $products;
}

//Используется чтобы пересчитать оперативные остатки в итоге создания одного заказа
protected function increaseInOrderRestWhenOrderCreate($orderid){

	$rests=array();
	return $rests;

	error_log("----------------increaseInOrderRestWhenOrderCreate---------------------");

	error_log("orderid=".$orderid);

	$order=$this->db_fabricant->getOrderById($orderid);
	$record=json_decode($order["record"],true);
	$contractorid=$order["contractorid"];
	$items=$record["items"];

	$rests=array();

	foreach($items as $item){
		if(isset($rests[strval($item["productid"])])){
			$rests[strval($item["productid"])]+=$item["count"];
		}else{
			$rests[strval($item["productid"])]=$item["count"];
		}
	}

	$products=array();

	foreach($rests as $productid=>$rest){

		$product_rest=json_decode($this->db_fabricant->getProductRestById($productid),true);

		//Пересчитываем остаток
		$product_rest["in_order"]=(isset($product_rest["in_order"]))?($product_rest["in_order"]+$rest):$rest;

		$product_rest_string=json_encode($product_rest,JSON_UNESCAPED_UNICODE);
		$this->db_fabricant->setProductRestById($productid,$product_rest_string);

		error_log("productid=$productid rest: ".json_encode($product_rest,JSON_UNESCAPED_UNICODE));

		$product=array();
		$product["productid"]=$productid;
		$product["rest"]=$product_rest_string;

		$products[]=$product;
	}

	error_log("products_rest count=".count($products));

	error_log(" ");

	return $products;
}

protected function ProcessConsoleOperation($connect,$info) {

	$this->log("ProcessConsoleOperation. info = ".json_encode($info,JSON_UNESCAPED_UNICODE));

	switch($info["operation"]){

		case CONSOLE_OPERATION_USER_CHANGED:
			$userid=$info["userid"];

			$this->log("<<<CONSOLE_OPERATION_USER_CHANGED:");

			if( array_key_exists(strval($userid), $this->map_userid_connect) ){
				$this->outgoingOneUser($this->getConnectByUserId($userid),$userid);
			}

			$this->outgoingNotifyFriends($userid);
			$this->outgoingNotifyAllGroupmates($userid);

			$this->log(">>>");

			//Response to console client
			$response = array();
			$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_USER_CHANGED success userid=".$userid;
			$this->sendFrame($connect, $response);

		break;

		case CONSOLE_OPERATION_GROUP_CHANGED:
			$groupid=$info["groupid"];

			$this->log("<<<CONSOLE_OPERATION_GROUP_CHANGED:");

			$this->outgoingNotifyGroupChanged($groupid);

			$group_users=$this->db_profile->getUsersInGroup($groupid);
			$this->outgoingNotifyGroupUsersChanged($groupid,$group_users);

			$this->log(">>>");

			//Response to console client
			$response = array();
			$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_GROUP_CHANGED success groupid=".$groupid;
			$this->sendFrame($connect, $response);

		break;

		case CONSOLE_OPERATION_PRODUCT_CHANGED:
			$productid=$info["productid"];

			$this->log("<<<CONSOLE_OPERATION_PRODUCT_CHANGED:");

			$this->outgoingNotifyProductChanged($productid);

			$this->log(">>>");

			//Response to console client
			$response = array();
			$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_PRODUCT_CHANGED success productid=".$productid;
			$this->sendFrame($connect, $response);

		break;

		case CONSOLE_OPERATION_NOTIFY_PRODUCTS:
			$products=$info["products"];

			$this->log("<<<CONSOLE_OPERATION_NOTIFY_PRODUCTS:");

			$this->outgoingNotifyProducts($products);

			$this->log(">>>");

			//Response to console client
			$response = array();
			$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_NOTIFY_PRODUCTS success products count=".count($products);
			$this->sendFrame($connect, $response);

		break;

		case CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST:
			$contractorid=$info["contractorid"];
			$products_main=$info["products"];

			error_log("products_main: ".json_encode($products_main,JSON_UNESCAPED_UNICODE));

			$this->log("<<<CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST:");

			$products_in_order=$this->recalculateInOrderRest($contractorid);


			error_log("products_in_order: ".json_encode($products_in_order,JSON_UNESCAPED_UNICODE));

			foreach($products_main as $product_main){
				$found=false;
        if(!empty($products_in_order)){
  				foreach($products_in_order as $product_in_order){
  					$id_main=$product_main->productid;
  					$id_in_order=$product_in_order["productid"];
  					if($id_main==$id_in_order){
  						$found=true;
  						break;
  					}
  				}
				}

				if(!$found){
					$products_in_order[]=$product_main;
				}
			}

			//$this->outgoingNotifyContractorProductsRest($contractorid,$products_in_order);

			$this->log(">>>");

			//Response to console client
			$response = array();
			$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_RECALCULATE_PRODUCTS_REST success products count=".count($products_in_order);
			$this->sendFrame($connect, $response);

		break;

		case CONSOLE_OPERATION_GROUP:

			$group_operationid=$info["group_operationid"];
			$senderid=$info["senderid"];

			switch($group_operationid){
				case GROUPOPERATION_ADD_USERS :
					$users=json_decode(stripslashes($info["users"]),true);
					$groupid=$info["groupid"];
					$this->groupOperationAddUsers($senderid,$groupid,$users);

					//Response to console client
					$response = array();
					$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_GROUP.GROUPOPERATION_ADD_USERS success groupid=".$groupid;
					$this->sendFrame($connect, $response);

				break;

				case GROUPOPERATION_SAVE :
					$json=json_decode($info["json"],true);
					$groupid=$info["groupid"];
					$this->groupOperationSave($senderid,$groupid,$json);

					//Response to console client
					$response = array();
					$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_GROUP.GROUPOPERATION_SAVE success groupid=".$groupid;
					$this->sendFrame($connect, $response);

				break;

				case GROUPOPERATION_CREATE :
					$address=$info["json"];
					$groupid=$this->groupOperationCreate($senderid);

					//Response to console client
					$response = array();
					$response["groupid"]=$groupid;
					$response["message"]="WebsocketServer. ProcessConsoleOperation CONSOLE_OPERATION_GROUP.GROUPOPERATION_CREATE success groupid=".$groupid;
					$this->sendFrame($connect, $response);

				break;
			}

		break;

		case CONSOLE_OPERATION_CHECK_SERVER:
			$userid=$info["userid"];

			$this->log("<<<CONSOLE_OPERATION_CHECK_SERVER:");
			$this->log("user_id=".$userid);
			$this->log(">>>");

			//Response to console client
			$response = array();
			$response["message"]="WebsocketServer. Server is running";
			$response["status"]=1;
			$this->sendFrame($connect, $response);

		break;

		case CONSOLE_OPERATION_ORDER:

			$order_operationid=$info["order_operationid"];
			$senderid=$info["senderid"];

			$record=json_decode($info["record"],true);
			$this->log("order_operationid=".$order_operationid);
			switch($order_operationid){

				case ORDEROPERATION_CREATE :
					$orderid=$this->db_fabricant->createOrder($record);
					$this->outgoingNotifyOrderToCustomerById($orderid,$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($orderid,$record["contractorid"]);

					//Пересчет оперативных остатков
					//$changed_products=$this->increaseInOrderRestWhenOrderCreate($orderid);
					//$this->outgoingNotifyContractorProductsRest($record["contractorid"],$changed_products);

					//Response to console client
					$response = array();
					$response["orderid"]=$orderid;
					$response["message"]="Order created";
					$this->sendFrame($connect, $response);

				break;

				case ORDEROPERATION_UPDATE :

					$this->db_fabricant->updateOrder($record);
					$this->outgoingNotifyOrderToCustomerById($record["id"],$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($record["id"],$record["contractorid"]);

					//Response to console client
					$response = array();
					$response["message"]="Order updated";
					$this->sendFrame($connect, $response);

				break;

				case ORDEROPERATION_ACCEPT :
					$this->db_fabricant->acceptOrder($record);
					$this->outgoingNotifyOrderToCustomerById($record["id"],$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($record["id"],$record["contractorid"]);

					//Response to console client
					$response = array();
					$response["message"]="Order accepted";
					$this->sendFrame($connect, $response);

				break;

				case ORDEROPERATION_REMOVE :
					$this->db_fabricant->removeOrder($record);
					$this->outgoingNotifyOrderToCustomerById($record["id"],$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($record["id"],$record["contractorid"]);

					//Response to console client
					$response = array();
					$response["message"]="Order removed";
					$this->sendFrame($connect, $response);

				break;

				case ORDEROPERATION_TRANSFER :

					$this->db_fabricant->transferOrder($record);
					$this->outgoingNotifyOrderToCustomerById($record["id"],$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($record["id"],$record["contractorid"]);

					//Response to console client
					$response = array();
					$response["message"]="Order transferred";
					$this->sendFrame($connect, $response);

				break;

				case ORDEROPERATION_MAKE_PAID :
					$this->db_fabricant->makeOrderPaid($record);
					$this->outgoingNotifyOrderToCustomerById($record["id"],$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($record["id"],$record["contractorid"]);

					//Response to console client
					$response = array();
					$response["message"]="Order paid";
					$this->sendFrame($connect, $response);

				break;

				case ORDEROPERATION_HIDE :
					$this->db_fabricant->hideOrder($record);
					$this->outgoingNotifyOrderToCustomerById($record["id"],$record["customerid"]);
					$this->outgoingNotifyOrderToContractorById($record["id"],$record["contractorid"]);

					//Response to console client
					$response = array();
					$response["message"]="Order hidden";
					$this->sendFrame($connect, $response);

				break;

			}

		break;

		case CONSOLE_OPERATION_SALE:

			$sale_operationid=$info["sale_operationid"];
			$senderid=$info["senderid"];

			switch($sale_operationid){

				case SALE_OPERATION_CREATE :

					$contractorid=$info["contractorid"];
					$type=$info["type"];
					$label=$info["label"];
					$name=$info["name"];
					$name_full=$info["name_full"];
					$summary=$info["summary"];
					$alias=$info["alias"];
					$for_all_customers=$info["for_all_customers"];

					if(!$this->db_profile->isUserInGroup($contractorid,$senderid)){
						$this->consoleResponse($connect,true,200,"User is not in contractor group. contractorid=".$contractorid." userid=".$senderid);
						return;
					}

					//create new sale
					switch($type){
						case SALE_TYPE_SALE:
							$rate=$info["rate"];
							$cash_only=$info["cash_only"];
							$saleid=$this->db_fabricant->createSaleRate($senderid,$contractorid,$label,$name,$name_full,$summary,$alias,$for_all_customers,$rate,$cash_only);
							break;

						case SALE_TYPE_DISCOUNT:
							$rate=$info["rate"];
							$min_summ=$info["min_summ"];
							$max_summ=$info["max_summ"];
							$cash_only=$info["cash_only"];
							$for_all_products=$info["for_all_products"];
							$saleid=$this->db_fabricant->createDiscount($senderid,$contractorid,$label,$name,$name_full,$summary,$alias,$for_all_customers,$for_all_products,$rate,$min_summ,$max_summ,$cash_only);
							break;

						case SALE_TYPE_INSTALLMENT:
							$time_notification=$info["time_notification"];
							$saleid=$this->db_fabricant->createInstallment($senderid,$contractorid,$label,$name,$name_full,$summary,$alias,$for_all_customers,$time_notification);
							break;
					}

					//add created sale into contractor
					if(!isset($saleid)){
						$this->consoleResponse($connect,true,200,"Sale id is not set");
						return;
					}

					$sale=$this->db_fabricant->getSaleById($saleid);
					$condition=json_decode($sale["condition"],true);
					$this->db_profile->addSaleToContractorInfo($contractorid,$condition);

					//notify delta changes
					$this->log("Sale created. type=".$type." contractorid=".$contractorid." saleid=".$saleid );
					$this->outgoingNotifyGroupChanged($contractorid);

					$this->consoleResponse($connect,false,200,"Sale created");

				break;

				case SALE_OPERATION_REMOVE :
					$saleid=$info["saleid"];

					$sale=$this->db_fabricant->getSaleById($saleid);
					$condition=json_decode($sale["condition"],true);

					//remove sale in sales table
					if(!$this->db_fabricant->removeSale($senderid,$saleid)){
						$this->consoleResponse($connect,true,200,"Sale saleid=".$saleid." is not found");
						return;
					}

					$contractorid=$sale["contractorid"];
					$type=$condition["type"];

					//remove sale from contractor's info
					$this->db_profile->removeSaleFromContractorInfo($sale["contractorid"],$condition);

					//notify delta changes
					$this->log("Sale updated. type=".$type." contractorid=".$contractorid." saleid=".$saleid );
					$this->outgoingNotifyGroupChanged($contractorid);

					//make actions specified for type
					switch($type){
						case SALE_TYPE_SALE:
						case SALE_TYPE_DISCOUNT:

							$before_change_products=time()-1;

							$tag=$condition["tag_product"];
							$this->db_fabricant->removeTagFromContractorProducts($contractorid,$tag);

							//notify delta of products after removing of the installment price
							$this->outgoingNotifyContractorProductsDelta($contractorid,$before_change_products);

							break;

						case SALE_TYPE_INSTALLMENT:
							$before_change_products=time()-1;

							$tag=$condition["tag_product"];
							$this->db_fabricant->removeTagFromContractorProducts($contractorid,$tag);

							$price_name=$condition["price_name"];
							$this->db_fabricant->removePriceInstallmentFromContractorProducts($contractorid,$price_name);

							//notify delta of products after removing of the installment price
							$this->outgoingNotifyContractorProductsDelta($contractorid,$before_change_products);

							break;
					}

					//Removing tag_customer	from customers
					$tag=$condition["tag_customer"];
					$customers=$this->db_profile->removeTagFromCustomers($tag);

					//Notify changed customers
					$this->outgoingNotifyGroupsChanged($customers);

					//Console response
					$this->consoleResponse($connect,false,200,"Sale removed");

				break;

				case SALE_OPERATION_UPDATE :

					$saleid=$info["saleid"];

					$sale=$this->db_fabricant->getSaleById($saleid);

					if(!isset($sale)){
						$this->consoleResponse($connect,true,200,"Sale id is not set");
						return;
					}

					$contractorid=$sale["contractorid"];

					if(!$this->db_profile->isUserInGroup($contractorid,$senderid)){
						$this->consoleResponse($connect,true,200,"User is not in contractor group. contractorid=".$contractorid." userid=".$senderid);
						return;
					}

					//set updated params to condition
					$condition=json_decode($sale["condition"],true);

					$text_object=array();
					$text_object["text"]=$info["label"];
					$condition["label"]=$text_object;

					$text_object=array();
					$text_object["text"]=$info["name"];
					$condition["name"]=$text_object;

					$text_object=array();
					$text_object["text"]=$info["name_full"];
					$condition["name_full"]=$text_object;

					$text_object=array();
					$text_object["text"]=$info["summary"];
					$condition["summary"]=$text_object;

					$condition["alias"]=$info["alias"];

					$condition["for_all_customers"]=$info["for_all_customers"];

					//set specific condition params
					$type=$condition["type"];
					switch($type){
						case SALE_TYPE_SALE:
							$condition["rate"]=$info["rate"];
							$condition["cash_only"]=$info["cash_only"];
							break;

						case SALE_TYPE_DISCOUNT:
							$condition["rate"]=$info["rate"];
							$condition["min_summ"]=$info["min_summ"];
							$condition["max_summ"]=$info["max_summ"];
							$condition["cash_only"]=$info["cash_only"];
							$condition["for_all_products"]=$info["for_all_products"];
							break;

						case SALE_TYPE_INSTALLMENT:
							$this->log("SALE_TYPE_INSTALLMENT info=".json_encode($info,JSON_UNESCAPED_UNICODE));
							$this->log("SALE_TYPE_INSTALLMENT condition=".json_encode($condition,JSON_UNESCAPED_UNICODE));
							$condition["time_notification"]=$info["time_notification"];
							break;
					}

					//update sale in sales table
					$this->db_fabricant->updateSale($senderid,$saleid,$condition);

					//replace sale in contractor info
					$this->db_profile->updateSaleInContractorInfo($contractorid,$condition);

					//notify delta changes
					$this->log("Sale updated. type=".$type." contractorid=".$contractorid." saleid=".$saleid );
					$this->outgoingNotifyGroupChanged($contractorid);

					$this->consoleResponse($connect,false,200,"Sale updated");

				break;

				case SALE_OPERATION_ADD_TO_CUSTOMER :
					$saleid=$info["saleid"];
					$sale=$this->db_fabricant->getSaleById($saleid);

					if(!isset($sale)){
						$this->consoleResponse($connect,true,403,"Sale with saleid=".$saleid." not found");
						return;
					}

					if($sale["status"]==2){
						$this->consoleResponse($connect,true,403,"Sale with saleid=".$saleid." was removed");
						return;
					}

					$condition=json_decode($sale["condition"],true);

					$customerid=$info["customerid"];
					$groups=$this->db_profile->getGroupById($customerid);
					if((!isset($groups))||(!isset($groups[0]))){
						$this->consoleResponse($connect,true,403,"Customer with customer id not found");
						return;
					}
					$customer=$groups[0];

					if($customer["type"]==0){
						$this->consoleResponse($connect,true,403,"Group with customerid is not customer. type=".$customer["type"]);
						return;
					}

					$user_status_in_contractor=$this->db_profile->getUserStatusInGroup($sale["contractorid"],$senderid);
					if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
						$this->consoleResponse($connect,true,403,"Only contractor user can add tag to customer. user_status_in_contractor=".$user_status_in_contractor);
						return;
					}



					//Add tag_customer to customer
					$this->db_profile->addTagToGroup($condition["tag_customer"],$customerid);

					//Notify changed customer
					$this->outgoingNotifyGroupChanged($customerid);

					//Console response
					$this->consoleResponse($connect,false,200,"Sale added to customer");

				break;

				case SALE_OPERATION_REMOVE_FROM_CUSTOMER :
					$saleid=$info["saleid"];
					$sale=$this->db_fabricant->getSaleById($saleid);

					if(!isset($sale)){
						$this->consoleResponse($connect,true,403,"Sale with saleid not found");
						return;
					}

					$condition=json_decode($sale["condition"],true);

					$customerid=$info["customerid"];
					$groups=$this->db_profile->getGroupById($customerid);
					if((!isset($groups))||(!isset($groups[0]))){
						$this->consoleResponse($connect,true,403,"Customer with customer id not found");
						return;
					}
					$customer=$groups[0];

					$user_status_in_contractor=$this->db_profile->getUserStatusInGroup($sale["contractorid"],$senderid);
					if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
						$this->consoleResponse($connect,true,403,"Only contractor user can remove tag from customer. user_status_in_contractor=".$user_status_in_contractor);
						return;
					}

					//Remove tag_customer from customer's default_installments
					$this->db_profile->removeDefaultInstallment($condition["tag_customer"],$customerid);

					//Remove tag_customer from customer
					$this->db_profile->removeTagFromGroup($condition["tag_customer"],$customerid);

					//Notify changed customer
					$this->outgoingNotifyGroupChanged($customerid);

					//Console response
					$this->consoleResponse($connect,false,200,"Sale removed from customer");

				break;

				case SALE_OPERATION_SET_DEFAULT_INSTALLMENT :
					$saleid=$info["saleid"];
					$sale=$this->db_fabricant->getSaleById($saleid);

					if(!isset($sale)){
						$this->consoleResponse($connect,true,403,"Sale with saleid=".$saleid." not found");
						return;
					}

					if($sale["status"]==2){
						$this->consoleResponse($connect,true,403,"Sale with saleid=".$saleid." was removed");
						return;
					}

					$condition=json_decode($sale["condition"],true);

					$customerid=$info["customerid"];
					$groups=$this->db_profile->getGroupById($customerid);
					if((!isset($groups))||(!isset($groups[0]))){
						$this->consoleResponse($connect,true,403,"Customer with customer id not found");
						return;
					}
					$customer=$groups[0];

					$groups=$this->db_profile->getGroupById($sale["contractorid"]);
					if((!isset($groups))||(!isset($groups[0]))){
						$this->consoleResponse($connect,true,403,"Contractor with contractorid not found");
						return;
					}
					$contractor=$groups[0];

					if($customer["type"]==0){
						$this->consoleResponse($connect,true,403,"Group with customerid is not customer. type=".$customer["type"]);
						return;
					}

					$user_status_in_contractor=$this->db_profile->getUserStatusInGroup($sale["contractorid"],$senderid);
					if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
						$this->consoleResponse($connect,true,403,"Only contractor user can add tag to customer. user_status_in_contractor=".$user_status_in_contractor);
						return;
					}

					if(!$this->db_profile->groupHasTag($condition["tag_customer"],$customerid)){
						$this->consoleResponse($connect,true,403,"Customer has not installment with saleid=".$saleid);
						return;
					}

					$contractor_info=json_decode($contractor["info"],true);
					if(isset($contractor_info) && isset($contractor_info["sales"])){

						$contractor_sales=$contractor_info["sales"];

						//Clear all tag_customers from customer	before add new
						$this->db_profile->clearDefaultInstallments($customerid,$contractor_sales);

						//Add new one
						$this->db_profile->addDefaultInstallment($condition["tag_customer"],$customerid);

						//Notify changed customer
						$this->outgoingNotifyGroupChanged($customerid);

						//Console response
						$this->consoleResponse($connect,false,200,"Default installment set");
					}else{
						//Console response
						$this->consoleResponse($connect,false,200,"No sales found in contractor");
					}

				break;

				case SALE_OPERATION_CLEAR_DEFAULT_INSTALLMENTS :
					$contractorid=$info["contractorid"];
					$customerid=$info["customerid"];

					$groups=$this->db_profile->getGroupById($customerid);
					if((!isset($groups))||(!isset($groups[0]))){
						$this->consoleResponse($connect,true,403,"Customer with customerid not found");
						return;
					}
					$customer=$groups[0];

					$groups=$this->db_profile->getGroupById($contractorid);
					if((!isset($groups))||(!isset($groups[0]))){
						$this->consoleResponse($connect,true,403,"Contractor with contractorid not found");
						return;
					}
					$contractor=$groups[0];

					$user_status_in_contractor=$this->db_profile->getUserStatusInGroup($contractorid,$senderid);
					if(($user_status_in_contractor!=1)&&($user_status_in_contractor!=2)){
						$this->consoleResponse($connect,true,403,"Only contractor user can remove tag from customer. user_status_in_contractor=".$user_status_in_contractor);
						return;
					}


					$contractor_info=json_decode($contractor["info"],true);
					if(isset($contractor_info) && isset($contractor_info["sales"])){

						$contractor_sales=$contractor_info["sales"];

						//Remove tag_customer from customer
						$this->db_profile->clearDefaultInstallments($customerid,$contractor_sales);

						//Notify changed customer
						$this->outgoingNotifyGroupChanged($customerid);

						//Console response
						$this->consoleResponse($connect,false,200,"Default installments removed from customer");
					}else{
						//Console response
						$this->consoleResponse($connect,false,200,"No sales found in contractor");
					}


				break;
			}

		break;
	}



}

protected function consoleResponse($connect,$error,$status,$message){
	$response=array();
	$response["error"]=$error;
	$response["message"]=$message;
	$response["success"]=($error?0:1);
	$response["status"]=$status;

	$this->log("consoleResponse. status=".$status." message=".$message);

	$this->sendFrame($connect, $response);
}

//--------------------Функции протокола чата (chat protocol methods)-----------------------

protected function ConfirmToSender($connect, $json) {

	$transport=TRANSPORT_TEXT;
	$message_id=$json["message_id"];
	$date=$json["date"];

	if(isset($json["interlocutor_id"])) {

		$interlocutor_id=$json["interlocutor_id"];
		$string_data='{"transport":"'.$transport.'", "message_id":"'.$message_id.'", "interlocutor_id":"'.$interlocutor_id.'", "date":"'.$date.'", "last_timestamp":"'.time().'"}';
		$this->sendFrame($connect, json_decode($string_data,true));
		$this->log("ConfirmToSender. Private. Sender.connectId=".$this->getIdByConnect($connect).", Sender.userid=".$this->getUserIdByConnect($connect).", client.message_id=".$message_id);
	}else
	if(isset($json["group_id"])) {

		$group_id=$json["group_id"];
		$id=$json["id"];//server id of group-message
		$string_data='{"transport":"'.$transport.'", "message_id":"'.$message_id.'", "id":"'.$id.'", "group_id":"'.$group_id.'", "date":"'.$date.'", "last_timestamp":"'.time().'"}';
		$this->sendFrame($connect, json_decode($string_data,true));
		$this->log("ConfirmToSender. Group. Sender.connectId=".$this->getIdByConnect($connect).", Sender.userid=".$this->getUserIdByConnect($connect).", group_id=".$group_id.", client.message_id=".$message_id);
	}

}

protected function SendToDestination($connect, $json) {

	$transport=TRANSPORT_TEXT;
	$value=$json["value"];
	$date=$json["date"];

	if(isset($json["interlocutor_id"])) {

		$interlocutor_id=$json["interlocutor_id"];

		$data_string='{"transport":"'.$transport.'","value":"'.$value.'","date":"'.$date.'","interlocutor_id":"'.$interlocutor_id.'", "last_timestamp":"'.time().'"}';

		$this->sendFrame($connect, json_decode($data_string,true));
		$this->log("SendToDestination. Private. Destination.connectid=".$this->getIdByConnect($connect).", Destination.userid=".$this->getUserIdByConnect($connect)." Message-data: ".$data_string);
	}else
	if(isset($json["group_id"])) {


		$group_id=$json["group_id"];
		$id=$json["id"];//server id of group-message
		$sender=$json["sender"];

		$data_string='{"transport":"'.$transport.'","value":"'.$value.'","date":"'.$date.'","group_id":"'.$group_id.'","id":"'.$id.'","sender":"'.$sender.'", "last_timestamp":"'.time().'"}';

		$this->sendFrame($connect, json_decode($data_string,true));
		$this->log("SendToDestination. Group. Destination.connectid=".$this->getIdByConnect($connect).", Destination.userid=".$this->getUserIdByConnect($connect)." Message-data: ".$data_string);
	}

}

protected function ProcessMessageChat($sender,$connects,$json) {

	$json["date"]=time();

	if(array_key_exists("interlocutor_id",$json)){
		//Private message

		$destination_id=$json["interlocutor_id"];
		$json["interlocutor_id"]=$this->getUserIdByConnect($sender);

		$this->log("<<ChatMessage. Private. sender=".$this->getUserIdByConnect($sender)." destination=".$destination_id." transport=".$json["transport"]." value=".$json["value"]);

		//$delivered=false;

		foreach($connects as $connect){
			if($sender==$connect){
				$this->ConfirmToSender($connect,$json);
			}else if ($this->getUserIdByConnect($connect)==$destination_id) {
				$this->SendToDestination($connect,$json);
				//$delivered=true;
			}
		}

		$this->log("Accumulate. sender=".$this->getUserIdByConnect($sender)." destination_id=".$destination_id." transport=".$json["transport"]." value=".$json["value"]);
		$this->db_chat->addMessagePrivate($this->getUserIdByConnect($sender),$destination_id,intval($json["transport"]),$json["value"]);

		/*//Если destination не найден, то сохраняем сообщение в БД, чтобы отправить потом
		if(!$delivered){

			$this->log("Accumulate. sender=".$this->getUserIdByConnect($sender)." destination_id=".$destination_id." transport=".$json["transport"]." value=".$json["value"]);
			$this->db_chat->addMessagePrivate($this->getUserIdByConnect($sender),$destination_id,intval($json["transport"]),$json["value"]);
		}*/

		$this->log(">>");

	}else
	if(array_key_exists("group_id",$json)){
		//Group message

		//Проверяем состоит ли отправитель в группе
		if(!$this->db_profile->isUserInGroup($json["group_id"],$this->getUserIdByConnect($sender)))return;

		$group_id=$json["group_id"];
		$json["sender"]=$this->getUserIdByConnect($sender);

		//Все групповые сообщения сохраняются в БД
		$id=$this->db_chat->addMessageGroup($this->getUserIdByConnect($sender),$group_id,intval($json["transport"]),$json["value"]);
		$this->log("<<ChatMessage. Group. sender=".$this->getUserIdByConnect($sender)." group_id=".$group_id." transport=".$json["transport"]." value=".$json["value"]." id=".$id);

		//Это нужно для SendToDestination и ConfirmToSender
		$json["id"]=$id;

		//Готовим массив user-ов одногрупников
		$users_array=$this->db_profile->getUsersInGroup($group_id);

		foreach($users_array as $user){
			$status=$user["status_in_group"];
			$userid=$user["userid"];
			//Если одногрупник статус 0 или 1 или 2, и сейчас подключен
			if( (($status==0)||($status==1)||($status==2)) && (array_key_exists(strval($userid), $this->map_userid_connect)) ){

				$connect=$this->getConnectByUserId($userid);

				//Одногрупник - есть отправитель?
				if($connect==$sender){
					//Подтверждаем
					$this->ConfirmToSender($connect,$json);
				}else {
					//Отправляем сообщение другому одногрупнику
					$this->SendToDestination($connect,$json);
				}
			}
		}

		$this->log(">>");


	}
}

//--------------------Функции протокола карты (map protocol methods)-----------------------

protected function outgoingConfirmStartRecieve($connect) {

	$this->log("outgoingStopBroadcast. connectId=".$this->getIdByConnect($connect));

	$string_data='{"transport":"'.TRANSPORT_MAP.'", "type":"'.OUTGOING_CONFIRM_START_RECIEVE.'", "last_timestamp":"'.time().'"}';
	$this->sendFrame($connect, json_decode($string_data,true));


}

protected function outgoingStartBroadcast($connect) {

	$this->log("outgoingStartBroadcast. connectId=".$this->getIdByConnect($connect));

	$string_data='{"transport":"'.TRANSPORT_MAP.'", "type":"'.OUTGOING_START_BROADCAST.'", "last_timestamp":"'.time().'"}';
	$this->sendFrame($connect, json_decode($string_data,true));


}

protected function outgoingStopBroadcast($connect) {

	$this->log("outgoingStopBroadcast. connectId=".$this->getIdByConnect($connect));

	$string_data='{"transport":"'.TRANSPORT_MAP.'", "type":"'.OUTGOING_STOP_BROADCAST.'", "last_timestamp":"'.time().'"}';
	$this->sendFrame($connect, json_decode($string_data,true));


}

protected function outgoingCoors($connect, $json) {
	$this->log("outgoingCoors. connectId=".$this->getIdByConnect($connect).", json=".json_encode($json,JSON_UNESCAPED_UNICODE));

	$json["transport"]=TRANSPORT_MAP;
	$json["type"]=OUTGOING_COORS;

	$this->sendFrame($connect, $json);


}

protected function putReciever($userid,$connect,$type,$latitude,$longitude,$radius,$clientid) {

	$this->log("putReciever. connectId=".$this->getIdByConnect($connect).", userid=".$userid.", type=".$type.", latitude=".$latitude.", longitude=".$longitude.", radius=".$radius," clientid=".$clientid);

	//Add to Recievers Array
	$reciever=array();

	$reciever["connect"]=$connect;
	$reciever["type"]=$type;
	$reciever["latitude"]=$latitude;
	$reciever["longitude"]=$longitude;
	$reciever["radius"]=$radius;
	$reciever["clientid"]=$clientid;//Used if type=RECIEVER_TYPE_ONE_USER and RECIEVER_TYPE_GROUP

	$this->recievers[strval($userid)]=$reciever;

}

protected function notifyTargetedUsers($userid,$type,$clientid){

	$this->log("notifyTargetedUsers. userid=".$userid.", type=".$type." clientid=".$clientid);

	//!!!!!!!!!!!!!Нужно добавить радиус действия!!!!!!!!!!!!!!!!!

	//Notify targeted users
	switch($type){
		case RECIEVER_TYPE_ALL :
			foreach($this->connects as $conn){
				if($this->getUserIdByConnect($conn)!=$userid)
					$this->outgoingStartBroadcast($conn);
			}
		break;

		case RECIEVER_TYPE_FRIENDS :

			$friends=$this->db_profile->getAllFriends($userid);

			foreach($friends as $friend){
				if( isset( $this->map_userid_connect[strval($friend["id"])] ) ){
					$conn=$this->getConnectByUserId($friend["id"]);
					$this->outgoingStartBroadcast($conn);
				}
			}
		break;

		case RECIEVER_TYPE_ONE_USER :
				if( isset( $this->map_userid_connect[strval($clientid)] ) ){
					$conn=$this->getConnectByUserId($clientid);
					$this->outgoingStartBroadcast($conn);
				}
		break;

		case RECIEVER_TYPE_GROUP :
			$groupid=$clientid;

			$group_users=$this->db_profile->getUsersInGroup($groupid);

			foreach($group_users as $user){
				if( isset( $this->map_userid_connect[strval($user["userid"])] ) ){
					$conn=$this->getConnectByUserId($user["userid"]);
					$this->outgoingStartBroadcast($conn);
				}
			}
		break;
	}
}

protected function removeReciever($userid) {
	$this->log("removeReciever. userid=".$userid);

	if(isset($this->recievers[strval($userid)])){
		unset($this->recievers[strval($userid)]);
	}
}

protected function resendCoorsToRecievers($sender,$sender_userid,$coors){
	$this->log("resendCoorsToRecievers. sender.connectId=".$this->getIdByConnect($sender).", sender_userid=".$sender_userid.", coors=".json_encode($coors,JSON_UNESCAPED_UNICODE));
	$resent_count=0;//Счетчик количества принявших ресиверов

	foreach($this->recievers as $reciever_userid=>$reciever){

		$this->log("resendCoorsToRecievers. reciever_userid=".$reciever_userid.", reciever.connectid=".$this->getIdByConnect($reciever["connect"]));

		//Предупреждаем чтобы ресивер сам себе не отправлял координаты
		if($reciever_userid==$sender_userid){
			$this->log("resendCoorsToRecievers. reciever_userid=sender_userid");
			$reciever["latitude"]=$coors["latitude"];
			$reciever["longitude"]=$coors["longitude"];
			$reciever["accuracy"]=$coors["accuracy"];
			$reciever["provider"]=$coors["provider"];
			$resent_count++;
			continue;
		}

		//Если у ресивера установлен радиус действия и если отправитель находится вне радиуса, то пропускаем
		if( (isset($reciever["radius"]))&&($reciever["radius"]>0)&&(isset($reciever["latitude"]))&&(isset($reciever["longitude"])) ){
			if($this->distanceBetween($coors["latitude"],$coors["longitude"],$reciever["latitude"],$reciever["longitude"])>$reciever["radius"]){
				$this->log("resendCoorsToRecievers. Out of Radius");
				continue;
			}
		}

		//Notify targeted users
		switch($reciever["type"]){
			case RECIEVER_TYPE_ALL :
				$this->log("resendCoorsToRecievers. RECIEVER_TYPE_ALL");
				$this->outgoingCoors($reciever["connect"],$coors);
				$resent_count++;
			break;

			case RECIEVER_TYPE_FRIENDS :
				$this->log("resendCoorsToRecievers. RECIEVER_TYPE_FRIENDS");
				if($this->db_profile->getFriendStatus($sender_userid,$reciever_userid)==3){
					$this->outgoingCoors($reciever["connect"],$coors);
					$resent_count++;
				}
			break;

			case RECIEVER_TYPE_ONE_USER :
				$this->log("resendCoorsToRecievers. RECIEVER_TYPE_ONE_USER");
				if($reciever["clientid"]==$sender_userid){
					$this->outgoingCoors($reciever["connect"],$coors);
					$resent_count++;
				}
			break;

			case RECIEVER_TYPE_GROUP :
				$this->log("resendCoorsToRecievers. RECIEVER_TYPE_GROUP");
				$groupid=$reciever["clientid"];

				if($this->db_profile->isUserInGroup($groupid,$sender_userid)){
					$this->outgoingCoors($reciever["connect"],$coors);
					$resent_count++;
				}

			break;
		}
	}

	$this->log("resendCoorsToRecievers. resent_count=".$resent_count);

	return $resent_count;
}

private function distanceBetween($ax,$ay,$bx,$by){
	return sqrt( pow($ax-$bx,2)+pow($ay-$by,2) );
}

protected function ProcessMessageMap($sender,$connects,$json) {

	$this->log("ProcessMessageMap. Sender.connectId=".$this->getIdByConnect($sender).", Sender.userid=".$this->getUserIdByConnect($sender).", json=".json_encode($json,JSON_UNESCAPED_UNICODE));

	switch($json["type"]){
		case INCOMING_START_RECIEVE :

			$user_location=$this->db_map->getUserLocation($this->getUserIdByConnect($sender));

			$radius=( isset($json["radius"]) )? $json["radius"] : 0;
			$clientid=( isset($json["clientid"]) )? $json["clientid"] : 0;


			if($user_location!=NULL){
				$this->putReciever($this->getUserIdByConnect($sender),$sender,$json["reciever_type"],$user_location["latitude"],$user_location["longitude"],$radius,$clientid);
			}else{
				$this->putReciever($this->getUserIdByConnect($sender),$sender,$json["reciever_type"],null,null,$radius,$clientid);
			}

			$this->outgoingConfirmStartRecieve($sender);

			$this->notifyTargetedUsers($this->getUserIdByConnect($sender),$json["reciever_type"],$clientid);

			/*//Отправляем последнее положение друзей
			$friends=$this->db_map->getFriendsLocation($this->getUserIdByConnect($sender));
			foreach($friends as $friend){
				$coors=array();
				$coors["transport"]=TRANSPORT_MAP;
				$coors["type"]=OUTGOING_COORS;
				$coors["userid"]=$friend["userid"];
				$coors["timestamp"]=$friend["timestamp"];
				$coors["latitude"]=$friend["latitude"];
				$coors["longitude"]=$friend["longitude"];
				$coors["accuracy"]=$friend["accuracy"];
				$coors["provider"]=$friend["provider"];

				$this->outgoingCoors($this->getConnectByUserId($friend["userid"]), $coors);
			}*/
		break;

		case INCOMING_STOP_RECIEVE :

			$senderid=$this->getUserIdByConnect($sender);

			$recievertype=0;

			if( isset( $this->recievers[$senderid] ) ){
				$reciever=$this->recievers[$senderid];
				$recievertype=$reciever["type"];
				$clientid=$reciever["clientid"];

				$this->removeReciever($this->getUserIdByConnect($sender));
				$this->outgoingStartBroadcast($sender);
				$this->notifyTargetedUsers($this->getUserIdByConnect($sender),$recievertype,$clientid);
			}

		break;

		case INCOMING_COORS :

			//$this->db_map->setUserLocation($this->getUserIdByConnect($sender),$json["latitude"],$json["longitude"],$json["accuracy"],$json["provider"]);

			$coors=array();
			$coors["transport"]=TRANSPORT_MAP;
			$coors["type"]=OUTGOING_COORS;
			$coors["userid"]=$this->getUserIdByConnect($sender);
			$coors["timestamp"]=time();
			$coors["latitude"]=$json["latitude"];
			$coors["longitude"]=$json["longitude"];
			$coors["accuracy"]=$json["accuracy"];
			$coors["provider"]=$json["provider"];

			$resend_count=$this->resendCoorsToRecievers($sender,$this->getUserIdByConnect($sender),$coors);
			if($resend_count==0){
				$this->outgoingStopBroadcast($sender);
			}

		break;
	}



}

//-----------Функции обеспечения связи между UserId и Connection----

protected function getIdByConnect($connect) {
        return intval($connect);
}

protected function getConnectByUserId($userid) {
    return $this->map_userid_connect[strval($userid)];
}

protected function getUserIdByConnect($connect) {
	$connectid=$this->getIdByConnect($connect);
	try{
		return $this->map_connectid_userid[strval($connectid)];
	}catch(Exception $e){
		return 0;
	}
}

protected function putConnect($connect,$userid) {
	$connectid=$this->getIdByConnect($connect);

	$this->map_connectid_userid[strval($connectid)]=$userid;
	$this->map_userid_connect[strval($userid)]=$connect;

	//Counting every sent frame to guarantee delievering
	$this->map_connectid_framecount[strval($connectid)]=(1*2)-2;

	array_push($this->connects,$connect);

	//Так как состояние изменилось отправляем сообщение монитору
	$this->outgoingStateToMonitors();
}

protected function removeConnect($connect) {

	$connectid=$this->getIdByConnect($connect);


	unset($this->map_userid_connect[strval($this->getUserIdByConnect($connect))]);
	unset($this->map_connectid_userid[strval($connectid)]);
	unset($this->map_connectid_framecount[strval($connectid)]);

	unset($this->connects[array_search($connect, $this->connects)]);

	//Так как состояние изменилось отправляем сообщение монитору
	$this->outgoingStateToMonitors();
}

protected function isConnectHasUserId($connect) {
	$connectid=$this->getIdByConnect($connect);
	return array_key_exists(strval($connectid),$this->map_connectid_userid);
}

protected function isUserIdHasConnect($userid) {
	return array_key_exists(strval($userid),$this->map_userid_connect);
}

//---------------------Служебные-------------------------

public function log($message){
	//Лог в укзанный в config файл

	if($this->config['log']){
		//file_put_contents($this->config['log'], "pid:".posix_getpid()." ".date("Y-m-d H:i:s")." ".$message."\n",FILE_APPEND);
	}
}

//-------Стандартные функции протокола WebSocket----------

protected function onOpen($connect, $info) {


	//Начало транзакции дельты
	$this->sendFrame($connect, json_decode('{"transaction":"begin"}',true));

	//-------------Notification---------------------------

	//Отправляем уведомление об удачном подключении
	$this->sendFrame($connect, json_decode('{"transport":"100","value":"Connected to fabricant-server","last_timestamp":"'.time().'"}',true));

	$userid=$info["userid"];
	$last_timestamp=$info["last_timestamp"];

	//-------Private-----------------------------

	if($last_timestamp!=0){

		$messages=$this->db_chat->getMessagesPrivate($userid,$last_timestamp);
		//Если есть сохраненные в БД private-сообщения, для только что подключившегося User, от текущего Interlocutor, то отправляем
		if($messages){
			$this->log("<<ChatMessage. De-accumulate. Private. connectid=".$this->getIdByConnect($connect)." userid=".$userid);
			foreach($messages as $message){


					$json=array();
					$json["interlocutor_id"]=$message["sender"];
					$json["transport"]=$message["message"];
					$json["value"]=$message["value"];
					$json["date"]=$message["date"];

					$this->SendToDestination($connect,$json);
					//После отправления удаляем из БД, таким образом исключая повторное отправление
					//$this->db_chat->deleteMessagePrivate(intval($message["id"]));

			}
			$this->log(">>");
		}
	} else {//do nothing
		//$messages=$this->db_chat->getLast20GroupMessagesOfUser($userid);
	}

	//-------Group--------------------------

	$messages=null;

	if($last_timestamp!=0){

		$messages=$this->db_chat->getGroupMessagesOfUser($userid,$last_timestamp);
		//Если за время отсуствия были групповые сообщения для User
		if($messages){
			$this->log("<<ChatMessage. De-accumulate. Group. connectid=".$this->getIdByConnect($connect)." userid=".$userid);
			foreach($messages as $message){
				$this->SendToDestination($connect,$message);
			}
			$this->log(">>");
		}

	} else {//do nothing
		//$messages=$this->db_chat->getLast20GroupMessagesOfUser($userid);
	}

	//------------Map------------------

	//$this->outgoingStartBroadcast($connect);

	//----------Profile--------------------------

	$this->outgoingUsersDelta($connect,$info["last_timestamp"]);

	$this->outgoingCustomersDelta($connect,$info["last_timestamp"]);
	$this->outgoingContractorsDelta($connect,$info["last_timestamp"]);

	$this->outgoingGroupUsersDelta($connect,$info["last_timestamp"]);
	$this->outgoingGroupmatesDelta($connect,$info["last_timestamp"]);

	//----------Fabricant--------------------------

	$this->outgoingProductsDelta($connect,$info["last_timestamp"]);

	$this->outgoingOrdersDelta($connect,$info["last_timestamp"]);

	//Пока только Кустук отправляет остатки
	$user_status_in_group=$this->db_profile->getUserStatusInGroup(127,$userid);
	if(($user_status_in_group==0)||($user_status_in_group==1)||($user_status_in_group==2)||($user_status_in_group==8)){
		try{
			error_log("products_rest sending in onOpen userid=".$userid." phone=".$this->db_profile->getUserById($userid)["phone"]);
		}catch(Exception $e){
			error_log("error when log userphone in products_rest");
		}
		
		$this->outgoingProductsRestDelta($connect,127,$info["last_timestamp"]);
	}

	//Завершение транзакции дельты
	$this->sendFrame($connect, json_decode('{"transaction":"end"}',true));

	//---------Moniotor------------------------

	if($this->isUserMonitor($userid)){
		$this->outgoingState($connect);
	}

	$this->outgoingLastAndroidVersion($connect);

	$this->outgoingInfoRequest($connect);

}

protected function onClose($connect) {
	//Пользовательский сценарий. Обратный вызов после закрытия соединения

	//-----------Map-------------------

	$this->removeReciever($this->getUserIdByConnect($connect));
}

protected function onMessage($sender,$connects,$data) {

	//$this->log("onMessage. senderid=".$this->getIdByConnect($sender));

	$decoded_data=$this->decode($data);

	$this->log("onMessage. senderid=".$this->getIdByConnect($sender)." decoded_data=".json_encode($decoded_data,JSON_UNESCAPED_UNICODE));

	if($this->isConnectHasUserId($sender)){
		$userid=$this->getUserIdByConnect($sender);
	}else if($this->isConnectIncognito($sender)){
		$userid="incognito";
	}else{
		$userid="unknown";
	}

	if($decoded_data['type']=='ping'){
		$this->log("incomingPing. payload=".$decoded_data['payload']." connectId=".$this->getIdByConnect($sender)." userId=".$userid." timestamp=".time());

		$this->log("outgoingPong. payload=".$decoded_data['payload']." connectId=".$this->getIdByConnect($sender)." userId=".$userid." timestamp=".time());
		fwrite($sender, $this->encode($decoded_data['payload'],'pong'));


		return;
	}

	if($decoded_data['type']=='pong'){

		$this->log("incomingPong. payload=".$decoded_data['payload']." connectId=".$this->getIdByConnect($sender)." userId=".$userid." timestamp=".time());

		//Если pong получен от правильного connect-а
		if(isset($this->session_ping_connectid[$decoded_data['payload']])&&($this->session_ping_connectid[$decoded_data['payload']]==$this->getIdByConnect($sender))){


			unset($this->session_ping_connectid[$decoded_data['payload']]);
			unset($this->session_ping_sent_at[$decoded_data['payload']]);


			//Получен проверочный понг SESSION_LOGIN, т.е. уже существующий user активен
			if( $this->isConnectIdIncognito($decoded_data['payload']) && (!$this->isConnectIncognito($sender)) && ($this->map_incognito_connectid_userid[$decoded_data['payload']]==$this->getUserIdByConnect($sender)) ){

				$this->outgoingLoginDenied($this->getConnectIncognitoById($decoded_data['payload']));

				//отключаем нового инкогнито user, который отправил запрос на логин
				//$this->removeConnectIncognito($this->getConnectIncognitoById($decoded_data['payload']));
			}
		}

		return;
	}

	//Далее принимаем только текстоввые сообщения
	if($decoded_data['type']!='text')return;

	//Пользовательский сценарий. Обратный вызов при получении сообщения
	$message_string= $decoded_data['payload'] . "\n";
	$json=json_decode($message_string,true);

	//Только регистрированные не инкогнито соединения
	if( array_key_exists("transport",$json) && !$this->isConnectIncognito($sender) ){

		switch($json["transport"]){

			case TRANSPORT_TEXT :{
				$this->ProcessMessageChat($sender,$connects,$json);
				break;
			}

			case TRANSPORT_MAP:{
				$this->ProcessMessageMap($sender,$connects,$json);
				break;
			}

			case TRANSPORT_PROFILE:{
				$this->ProcessMessageProfile($sender,$connects,$json);
				break;
			}

			case TRANSPORT_FABRICANT:{
				$this->ProcessMessageFabricant($sender,$connects,$json);
				break;
			}

			case TRANSPORT_MONITOR:{
				$this->ProcessMessageMonitor($sender,$connects,$json);
				break;
			}

			case TRANSPORT_SESSION :{
					$this->ProcessMessageSession($sender,$connects,$json);
					break;
			}

		}

	}else{

		switch($json["transport"]){

			case TRANSPORT_SESSION :{
					$this->ProcessMessageSession($sender,$connects,$json);
					break;
			}

		}
	}

}

protected function sendSMS($text){
	$phone="79142966292";
	try{
		$body=file_get_contents("http://sms.ru/sms/send?api_id=A73F3F48-2F27-8D8D-D7A2-6AFF64E4F744&to=".$phone."&from=fabricant&text=".$text);
		return $body;
	}catch(Exception $e){
		return null;
	}
}

protected function sendFrame($connect,$json) {

	$connectid=$this->getIdByConnect($connect);

	if( array_key_exists(strval($connectid), $this->map_connectid_framecount) ){
		$this->map_connectid_framecount[strval($connectid)]=1+2-2+$this->map_connectid_framecount[strval($connectid)];
		$json["frame_count"]=$this->map_connectid_framecount[strval($connectid)];
	}

	$json["last_timestamp"]=time();

	if($this->isConnectHasUserId($connect)){
		$userid=$this->getUserIdByConnect($connect);
	}else if($this->isConnectIncognito($connect)){
		$userid="incognito";
	}else{
		$userid="unknown";
	}

	$data_string=json_encode($json,JSON_UNESCAPED_UNICODE);
	$this->log("sendFrame. userid=".$userid." connectid=".$connectid." json=".$data_string);

	fwrite($connect, $this->encode($data_string));
}

protected function sendPing($connect,$payload) {

	$connectid=$this->getIdByConnect($connect);

	if($this->isConnectHasUserId($connect)){
		$userid=$this->getUserIdByConnect($connect);
	}else if($this->isConnectIncognito($connect)){
		$userid="incognito";
	}else{
		$userid="unknown";
	}

	$this->log("sendPing. userid=".$userid." connectid=".$connectid." payload=".$payload);

	$this->session_ping_connectid[$payload]=$connectid;
	$this->session_ping_sent_at[$payload]=time();

	fwrite($connect, $this->encode($payload,"ping"));
}

function handshake($connect){
    $info = array();

    //$this->log("handshake begin");

    $line = fgets($connect);
    $header = explode(' ', $line);

    try{
		$info['method'] = $header[0];
		$info['uri'] = $header[1];
	}catch(Exception $e){

	}
	//$this->log("handshake header-method : ".$info['method']);

    //считываем заголовки из соединения
    while ($line = rtrim(fgets($connect))) {
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
            $info[$matches[1]] = $matches[2];
        } else {

            break;
        }
    }


    $address = explode(':', stream_socket_get_name($connect, true)); //получаем адрес клиента
    $info['ip'] = $address[0];
    $info['port'] = $address[1];

    if (empty($info['Sec-WebSocket-Key'])) {
		$this->log("handshake is failed 'Sec-WebSocket-Key' is missing");
        return false;
    }

	//$this->log('Sec-WebSocket-Key'.$info['Sec-WebSocket-Key']);

    //отправляем заголовок согласно протоколу вебсокета
    $SecWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

    $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: $SecWebSocketAccept\r\n\r\n";

	fwrite($connect, $upgrade);

    //$this->log("handshake info : ".implode('  ',$info));
	//$this->log("handshake end");


	//Удаляем последствие json_encode ковычек из WebsocketClient
	if( isset($info["console"]) ){
		foreach($info as $key => $value)
		{
			$info[$key]=json_decode($value);
		}
	}

	//Пресекаем прямую передачу userid и senderid
	if(isset($info["userid"])){
		unset($info["userid"]);
	}
	if(isset($info["senderid"])){
		unset($info["senderid"]);
	}

	if(isset($info["phone"]) && isset($info["password"])){

		$phone=filter_var($info["phone"],FILTER_VALIDATE_INT);
		$password=$info["password"];

		try{

			if ($this->db_profile->checkLoginByPhone($phone, $password)) {
				$user = $this->db_profile->getUserByPhone($phone);
				$info["userid"]=$user["id"];
			}else{
				$this->log("handshake incorrect phone or password");
			}

		}catch(Exception $e){
			$this->log("handshake exception when process phone and password");
		}
	}else if(isset($info["Api-Key"])){

		try{
			$api_key=$info['Api-Key'];

			if ($this->db_profile->isValidApiKey($api_key)) {
				$info["userid"]=$this->db_profile->getUserId($api_key)["id"];
				$info["senderid"]=$info["userid"];
				$this->log("handshake Api-Key=".$api_key." accepted userid=".$info["userid"]);
			}else{
				$this->log("handshake incorrect Api-Key=".$api_key);
			}

		}catch(Exception $e){
			$this->log("handshake exception when process Api-Key=");
		}
	}


    return $info;
}

function encode($payload, $type = 'text', $masked = false){
    $frameHead = array();
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            // first byte indicates FIN, Text-Frame (10000001):
            $frameHead[0] = 129;
            break;

        case 'close':
            // first byte indicates FIN, Close Frame(10001000):
            $frameHead[0] = 136;
            break;

        case 'ping':
            // first byte indicates FIN, Ping frame (10001001):
            $frameHead[0] = 137;
            break;

        case 'pong':
            // first byte indicates FIN, Pong frame (10001010):
            $frameHead[0] = 138;
            break;
    }

    // set mask and payload length (using 1, 3 or 9 bytes)
    if ($payloadLength > 65535) {
        $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 255 : 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
        }
        // most significant bit MUST be 0
        if ($frameHead[2] > 127) {
            return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
        }
    } elseif ($payloadLength > 125) {
        $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 254 : 126;
        $frameHead[2] = bindec($payloadLengthBin[0]);
        $frameHead[3] = bindec($payloadLengthBin[1]);
    } else {
        $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
    }

    // convert frame-head to string:
    foreach (array_keys($frameHead) as $i) {
        $frameHead[$i] = chr($frameHead[$i]);
    }
    if ($masked === true) {
        // generate a random mask:
        $mask = array();
        for ($i = 0; $i < 4; $i++) {
            $mask[$i] = chr(rand(0, 255));
        }

        $frameHead = array_merge($frameHead, $mask);
    }
    $frame = implode('', $frameHead);

    // append payload to frame:
    for ($i = 0; $i < $payloadLength; $i++) {
        $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }

    return $frame;
}

function decode($data){
    $unmaskedPayload = '';
    $decodedData = array();

    // estimate frame type:
    $firstByteBinary = sprintf('%08b', ord($data[0]));
    $secondByteBinary = sprintf('%08b', ord($data[1]));
    $opcode = bindec(substr($firstByteBinary, 4, 4));
    $isMasked = ($secondByteBinary[0] == '1') ? true : false;
    $payloadLength = ord($data[1]) & 127;

	//$this->log("decode. opcode=".$opcode." isMasked=".$isMasked);



    // unmasked frame is received:
    //if (!$isMasked) {
    //    return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
    //}

	$decodedData['masked']=$isMasked;

    switch ($opcode) {
        // text frame:
        case 1:
            $decodedData['type'] = 'text';
            break;

        case 2:
            $decodedData['type'] = 'binary';
            break;

        // connection close frame:
        case 8:
            $decodedData['type'] = 'close';
            break;

        // ping frame:
        case 9:
            $decodedData['type'] = 'ping';
            break;

        // pong frame:
        case 10:
            $decodedData['type'] = 'pong';
            break;

        default:
            return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
    }

    if ($payloadLength === 126) {
        $mask = substr($data, 4, 4);
        $payloadOffset = 8;
        $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
    } elseif ($payloadLength === 127) {
        $mask = substr($data, 10, 4);
        $payloadOffset = 14;
        $tmp = '';
        for ($i = 0; $i < 8; $i++) {
            $tmp .= sprintf('%08b', ord($data[$i + 2]));
        }
        $dataLength = bindec($tmp) + $payloadOffset;
        unset($tmp);
    } else {
        $mask = substr($data, 2, 4);
        $payloadOffset = 6;
        $dataLength = $payloadLength + $payloadOffset;
    }

    /**
     * We have to check for large frames here. socket_recv cuts at 1024 bytes
     * so if websocket-frame is > 1024 bytes we have to wait until whole
     * data is transferd.
     */
    if (strlen($data) < $dataLength) {
        return false;
    }

    if ($isMasked) {
        for ($i = $payloadOffset; $i < $dataLength; $i++) {
            $j = $i - $payloadOffset;
            if (isset($data[$i])) {
                $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
            }
        }
        $decodedData['payload'] = $unmaskedPayload;
    } else {
        $payloadOffset = $payloadOffset - 4;
        $decodedData['payload'] = substr($data, $payloadOffset);
    }

    return $decodedData;
}

}
