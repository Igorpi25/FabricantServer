<?php
 
/**
 * Class to handle all db operations of Fabricant-project
 *
 * @author Igor Ivanov
 */
 
require_once dirname(__FILE__).'/DbHandler.php';
 
class DbHandlerFabricant extends DbHandler{

	const STATUS_CREATED=1;
	const STATUS_PUBLISHED=2;
	const STATUS_UNPUBLISHED=3;
	const STATUS_DELETED=4;
	
	const STATUS_ORDER_PROCESSING=1;
	const STATUS_ORDER_CONFIRMED=2;
	const STATUS_ORDER_ONWAY=3;			
	const STATUS_ORDER_CANCELED=4;
	const STATUS_ORDER_TRANSFERRED=5;//Only on client side
	const STATUS_ORDER_HIDDEN=6;//Становится не отображаемым в панели поставщика и приложении заказчика
	const STATUS_ORDER_DELIVERED=8;
	
	
	const STATUS_SALE_CREATED=1;
	const STATUS_SALE_REMOVED=2;
		 
	const ORDER_OPERATION_TYPE_CREATE=1;
	const ORDER_OPERATION_TYPE_ACCEPT=2;
	const ORDER_OPERATION_TYPE_REMOVE=3;
	const ORDER_OPERATION_TYPE_UPDATE=4;
	const ORDER_OPERATION_TYPE_TRANSFER=5;
	const ORDER_OPERATION_TYPE_MAKE_PAID=6;
	const ORDER_OPERATION_TYPE_HIDE=7;
	
	
	const CONTRACTOR_CONDITION_TYPE_SALERATE=4;
	const CONTRACTOR_CONDITION_TYPE_DISCOUNT=5;
	const CONTRACTOR_CONDITION_TYPE_INSTALLMENT=6;
	
	//status column in sales_operations table
	const SALE_OPERATION_TYPE_CREATE=1;
	const SALE_OPERATION_TYPE_REMOVE=2;
	const SALE_OPERATION_TYPE_UPDATE=3;
	
    function __construct() {
        parent::__construct();
    }
	
	/*------------- `products` ------------------ */
 
	/**
	 * Creating new user
	 */
	public function createProduct($contractorid, $name, $price, $info, $status, $code1c) {
		
		if(empty($info)){
			$info="{}";
		}
		
		// insert query
		$stmt = $this->conn->prepare("INSERT INTO products(contractorid, name, price, info, status, code1c) values(?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("isdsis", $contractorid, $name, $price, $info, $status, $code1c);
		$stmt->execute();
		$result = $this->conn->insert_id;
		$stmt->close();
		return $result;
	}
	
	/**
	 * Creating new user with specified id
	 */
	public function createProductWithId($id,$contractorid, $name, $price, $info, $status, $code1c) {
		
		if(empty($info)){
			$info="{}";
		}
		
		// insert query
		$stmt = $this->conn->prepare("INSERT INTO products(id,contractorid, name, price, info, status, code1c) values(?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("iisdsis", $id, $contractorid, $name, $price, $info, $status, $code1c);
		$stmt->execute();
		$result = $this->conn->insert_id;
		$stmt->close();
		return $result;
	}
	
	public function updateProduct($id, $name, $price, $info, $status) {

		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `name`=? , `price`=? , `info`=? , `status`=? , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("sdsii", $name, $price, $info, $status, $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}
	
	public function removeProduct($id) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `status`=4 , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("i", $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}
	
	public function removeAllProductsOfContractor($contractorid) {
		// update query
		$stmt = $this->conn->prepare("DELETE FROM `products` WHERE `contractorid`=?");
		$stmt->bind_param("i", $contractorid);
		$result = $stmt->execute();
		
		if($result){
			$affected_rows=$stmt->affected_rows;
			$stmt->close();
			return $affected_rows;
		}else{			
			$stmt->close();
			return 0;
		}
	}

	public function getProductById($id) {

		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c FROM products p WHERE p.id =?");
		$stmt->bind_param("i", $id);
		if ($stmt->execute()) {

			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at,$code1c);            

			$stmt->fetch();

			$res= array();
			$res["id"] = $id;
			$res["contractorid"] = $contractorid;
			$res["name"] = $name;
			$res["status"] = $status;
			$res["price"] = $price;
			$res["info"] = $info;
			$res["code1c"] = $code1c;

			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
			$res["changed_at"] = $timestamp_object->getTimestamp();	

			$stmt->close();
			return $res;
		} else {
			return NULL;
		}
	}
	
	public function getProductsOfContractor($contractorid){
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c FROM products p WHERE p.contractorid=? AND p.status<>0");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id, $contractorid, $name, $status, $price, $info, $changed_at, $code1c);
			$result=array();
			while($stmt->fetch()){
				$res=array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["name"] = $name;
				$res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				$res["code1c"] = $code1c;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
				$result[]=$res;
			}
			$stmt->close();
			return $result;
		} else {
			return NULL;
		}
	}

	public function getActiveProductsOfContractor($contractorid){
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c FROM products p WHERE p.contractorid=? AND p.status<>0 AND p.status<>4");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id, $contractorid, $name, $status, $price, $info, $changed_at, $code1c);
			$result=array();
			while($stmt->fetch()){
				$res=array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["name"] = $name;
				$res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				$res["code1c"] = $code1c;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
				$result[]=$res;
			}
			$stmt->close();
			return $result;
		} else {
			return NULL;
		}
	}

	public function getPublishedProductsOfContractor($contractorid){
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at FROM products p WHERE p.contractorid=? AND p.status=2");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at);
			$result=array();
			while($stmt->fetch()){
				$res=array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["name"] = $name;
				$res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
				$result[]=$res;
			}
			$stmt->close();
			return $result;
		} else {
			return NULL;
		}
	}

	public function getAllProducts(){
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c FROM products p");
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at, $code1c);
			$result=array();
			while($stmt->fetch()){
				$res=array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["name"] = $name;
				$res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
				$res["code1c"] = $code1c;
				$result[]=$res;
			}
			$stmt->close();
			return $result;
		} else {
			return NULL;
		}
	}

	public function publishProduct($id) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `status`=2 , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("i", $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	public function unpublishProduct($id) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `status`=3 , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("i", $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	public function updateProductCode($id, $code) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `code1c`= ? , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("si", $code, $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	public function getProductCodeById($id) {

		$stmt = $this->conn->prepare("SELECT `code1c` FROM `products` WHERE `id`=?");
		$stmt->bind_param("i", $id);
		if ($stmt->execute()) {
			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($code1c);
			$stmt->fetch();
			$res=$code1c;
			$stmt->close();
			return $res;
		} else {
			return NULL;
		}
	}

	public function getProductByCode($contractorid,$code) {

		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c FROM products p WHERE p.contractorid=? AND p.code1c =?");
		$stmt->bind_param("is", $contractorid,$code);
		if ($stmt->execute()) {

			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at,$code1c);            

			$stmt->fetch();

			$res= array();
			$res["id"] = $id;
			$res["contractorid"] = $contractorid;
			$res["name"] = $name;
			$res["status"] = $status;
			$res["price"] = $price;
			$res["info"] = $info;
			

			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
			$res["changed_at"] = $timestamp_object->getTimestamp();	

			$res["code1c"] = $code1c;
			
			$stmt->close();
			return $res;
		} else {
			return NULL;
		}
	}
	
	//-----------------Order--------------------
	
	public function createOrder($record){
		
		// Add date to record
		$date_string=date('Y-m-d H:i:s',time());		
		$record["created_at"]=$date_string;		
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);
		
		//status is created
		$status=self::STATUS_ORDER_PROCESSING;
		
		// insert query
		$stmt = $this->conn->prepare("INSERT INTO orders(contractorid, customerid, status, record, created_at) values(?, ?, ?, ?, ? )");
		$stmt->bind_param("iiiss", $record["contractorid"], $record["customerid"], $status, $json_info, $date_string);
		$stmt->execute();
		$orderid = $this->conn->insert_id;
		$stmt->close();
		
		// Add id to record
		$record["id"]=$orderid;		
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);
		
		$stmt = $this->conn->prepare("UPDATE `orders` SET `record`=? , `changed_at`=? WHERE `id`=?");
		$stmt->bind_param("ssi", $json_info, $date_string, $orderid);
		$stmt->execute();
		$stmt->close();
		
		//write log in orders_operations table
		$this->addOrderOperation($orderid,$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_CREATE,$json_info,"Order create operation");
		
		return $orderid;
	}
	
	public function getOrderById($id) {
	
		$stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
			FROM orders o 
			WHERE ( o.id = ? ) ");
		
		$stmt->bind_param( "i", $id);
		
		$orders=array();
		
		if ($stmt->execute()) {
			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;
			
			$stmt->bind_result($id,$contractorid,$customerid, $status, $record, $code1c, $created_at, $changed_at);            

			$stmt->fetch();

			$res= array();
			$res["id"] = $id;
			$res["contractorid"] = $contractorid;
			$res["customerid"] = $customerid;
			$res["status"] = $status;
			$res["record"] = $record;
			$res["code1c"] = $code1c;
			
			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
			$res["created_at"] = $timestamp_object->getTimestamp();	
			
			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
			$res["changed_at"] = $timestamp_object->getTimestamp();	
			
			$orders[]=$res;

			$stmt->close();
			
			return $res;			
		}else {
			return NULL;
		}
	}
	
	public function getAllOrdersOfContractor($contractorid) {
	
		$stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
			FROM orders o 
			WHERE ( o.contractorid = ? ) 
			ORDER BY o.created_at DESC ");

		$stmt->bind_param( "i", $contractorid);

		$orders=array();

        if ($stmt->execute()) {
			$stmt->bind_result($id,$contractorid,$customerid, $status, $record, $code1c, $created_at, $changed_at);            

            while($stmt->fetch()){
				$res= array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["customerid"] = $customerid;
				$res["status"] = $status;
				$res["record"] = $record;
				$res["code1c"] = $code1c;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();

				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();

				$orders[]=$res;
			}
			$stmt->close();
		}
		return $orders;
	}

	public function getAllOrdersOfContractorWeb($contractorid) {
		$stmt=$this->conn->prepare("SELECT status, record FROM orders WHERE contractorid=?");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()) {
			$result=array();
			$stmt->bind_result($status, $records);
			while($stmt->fetch()) {
				$record = json_decode($records, true);
				$record["status"] = $status;
				$result[] = $record;
			}
			$stmt->close();
			return $result;
		}
		else
			return NULL;
	}

	public function getAllOrdersOfContractorIntervalWeb($contractorid, $interval) {
		$stmt=$this->conn->prepare("
			SELECT status, record FROM orders 
			WHERE contractorid=? AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND NOW()
		");
		$stmt->bind_param("ii", $contractorid,$interval);
		if ($stmt->execute()) {
			$result=array();
			$stmt->bind_result($status, $records);
			while($stmt->fetch()) {
				$record = json_decode($records, true);
				$record["status"] = $status;
				$result[] = $record;
			}
			$stmt->close();
			return $result;
		}
		else
			return NULL;
	}

	public function getAllOrdersOfCustomerWeb($customerid) {
		$stmt=$this->conn->prepare("SELECT status, record FROM orders WHERE customerid=?");
		$stmt->bind_param("i", $customerid);
		if ($stmt->execute()) {
			$result=array();
			$stmt->bind_result($status, $records);
			while($stmt->fetch()) {
				$record = json_decode($records, true);
				$record["status"] = $status;
				$result[] = $record;
			}
			$stmt->close();
			return $result;
		}
		else
			return NULL;
	}

	public function getAllOrdersWeb($interval) {
		$stmt=$this->conn->prepare("
			SELECT `status`, `record` FROM `orders` 
			WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND NOW()
		");
		$stmt->bind_param("i", $interval);
		if ($stmt->execute()) {
			$result=array();
			$stmt->bind_result($status, $records);
			while($stmt->fetch()) {
				$record = json_decode($records, true);
				$record["status"] = $status;
				$result[] = $record;
			}
			$stmt->close();
			return $result;
		}
		else
			return NULL;
	}

	public function newOrderNotify($contractorid) {
		$stmt = $this->conn->prepare("SELECT count(*) AS count FROM orders WHERE contractorid=?");
		$stmt->bind_param("i", $contractorid);
		$result = 0;
		if ($stmt->execute()) {
			$stmt->bind_result($count);
			while($stmt->fetch()) {
				$result=$count;
			}
			$stmt->close();
		}
		return $result;
	}

	public function newOrderNotifyAll() {
		$stmt = $this->conn->prepare("SELECT count(*) AS count FROM orders");
		$result = 0;
		if ($stmt->execute()) {
			$stmt->bind_result($count);
			while($stmt->fetch()) {
				$result=$count;
			}
			$stmt->close();
		}
		return $result;
	}

	public function acceptOrder($record) {	
	
		$date_string=date('Y-m-d H:i:s',time());
		$record["accepted_at"]=$date_string;
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);
		
		$status=self::STATUS_ORDER_CONFIRMED;
		
		// update query
		$stmt = $this->conn->prepare("UPDATE orders SET status=? , record=?, changed_at=? WHERE id=?");
		$stmt->bind_param("issi", $status,$json_info,$date_string,$record["id"]);
		$result = $stmt->execute();
		$stmt->close();
		
		//write log in orders_operations table
		$this->addOrderOperation($record["id"],$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_ACCEPT,$json_info,"Order accept operation");
		
		return $result;
	}

	public function removeOrder($record) {	
		
		$date_string=date('Y-m-d H:i:s',time());
		$record["removed_at"]=$date_string;
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);
		
		$status=self::STATUS_ORDER_CANCELED;
		
		// update query
		$stmt = $this->conn->prepare("UPDATE orders SET status=? , record=?, changed_at=? WHERE id=?");
		$stmt->bind_param("issi", $status,$json_info,$date_string,$record["id"]);
		$result = $stmt->execute();
		$stmt->close();
		
		//write log in orders_operations table
		$this->addOrderOperation($record["id"],$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_REMOVE,$json_info,"Order remove operation. comment=".$record["removeComment"]);
		
		return $result;
	}
	
	public function hideOrder($record) {	
		
		$date_string=date('Y-m-d H:i:s',time());
		$record["hidden_at"]=$date_string;
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);
		
		$status=self::STATUS_ORDER_HIDDEN;
		
		// update query
		$stmt = $this->conn->prepare("UPDATE orders SET status=? , record=?, changed_at=? WHERE id=?");
		$stmt->bind_param("issi", $status,$json_info,$date_string,$record["id"]);
		$result = $stmt->execute();
		$stmt->close();
		
		//write log in orders_operations table
		$this->addOrderOperation($record["id"],$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_HIDE,$json_info,"Order hide operation. comment=".$record["hideComment"]);
		
		return $result;
	}
	
	public function updateOrder($record) {
	
		$date_string=date('Y-m-d H:i:s',time());
		$record["updated_at"]=$date_string;
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);		
		$status=self::STATUS_ORDER_PROCESSING;
		
		// update query
		$stmt = $this->conn->prepare("UPDATE orders SET status=?, customerid=?, record=?, changed_at=? WHERE id=?");
		$stmt->bind_param("iissi", $status,$record["customerid"],$json_info,$date_string,$record["id"]);
		$result = $stmt->execute();
		$stmt->close();
		
		
		$this->addOrderOperation($record["id"],$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_UPDATE,$json_info,"Order update operation");
		
		return $result;
	}
	
	public function transferOrder($record) {
	
		$date_string=date('Y-m-d H:i:s',time());
		$record["updated_at"]=$date_string;
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);		
		$status=self::STATUS_ORDER_TRANSFERRED;
		
		// update query
		$stmt = $this->conn->prepare("UPDATE orders SET status=?, customerid=?, record=?, changed_at=? WHERE id=?");
		$stmt->bind_param("iissi", $status,$record["customerid"],$json_info,$date_string,$record["id"]);
		$result = $stmt->execute();
		$stmt->close();
		
		
		$this->addOrderOperation($record["id"],$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_TRANSFER,$json_info,"Order tranfer operation");
		
		return $result;
	}

	public function makeOrderPaid($record) {	
		
		$date_string=date('Y-m-d H:i:s',time());
		$record["paid_at"]=$date_string;
		$json_info=json_encode($record,JSON_UNESCAPED_UNICODE);
		
		// update query
		$stmt = $this->conn->prepare("UPDATE orders SET record=?, changed_at=? WHERE id=?");
		$stmt->bind_param("ssi", $json_info,$date_string,$record["id"]);
		$result = $stmt->execute();
		$stmt->close();
		
		//write log in orders_operations table
		$this->addOrderOperation($record["id"],$record["contractorid"],$record["customerid"],self::ORDER_OPERATION_TYPE_MAKE_PAID,$json_info,"Order make paid operation");
		
		return $result;
	}
	
	public function updateOrderCode($id, $code) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `orders` SET `code1c`= ? , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("si", $code, $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	public function getOrderCodeById($id) {

		$stmt = $this->conn->prepare("SELECT `code1c` FROM `orders` WHERE `id`=?");
		$stmt->bind_param("i", $id);
		if ($stmt->execute()) {
			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($code1c);
			$stmt->fetch();
			$res=$code1c;
			$stmt->close();
			return $res;
		} else {
			return NULL;
		}
	}

	public function getOrderById($code) {
	
		$stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
			FROM orders o 
			WHERE ( o.code1c = ? ) ");
		
		$stmt->bind_param( "s", $code);
		
		$orders=array();
		
		if ($stmt->execute()) {
			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;
			
			$stmt->bind_result($id,$contractorid,$customerid, $status, $record, $code1c, $created_at, $changed_at);            

			$stmt->fetch();

			$res= array();
			$res["id"] = $id;
			$res["contractorid"] = $contractorid;
			$res["customerid"] = $customerid;
			$res["status"] = $status;
			$res["record"] = $record;
			$res["code1c"] = $code1c;
			
			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
			$res["created_at"] = $timestamp_object->getTimestamp();	
			
			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
			$res["changed_at"] = $timestamp_object->getTimestamp();	
			
			$orders[]=$res;

			$stmt->close();
			
			return $res;			
		}else {
			return NULL;
		}
	}
		
	//-------------------OrderOperations-----------------------
	
	public function addOrderOperation($orderid,$contractorid,$customerid,$type,$record,$comment){
		
		// insert query
		$stmt = $this->conn->prepare("INSERT INTO orders_operations(orderid, contractorid, customerid, type, record, comment) values(?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("iiiiss", $orderid, $contractorid, $customerid, $type, $record, $comment);
		$stmt->execute();
		$order_operation_id = $this->conn->insert_id;
		$stmt->close();
		
		return $order_operation_id;
	}

	//---------------------Sales Utils---------------------------
	
	protected function createSale($contractorid,$created_at,$condition){
		
		$json_info=json_encode($condition,JSON_UNESCAPED_UNICODE);
		
		//Status is created
		$status=self::STATUS_SALE_CREATED;
		
		//Insert query
		$stmt = $this->conn->prepare("INSERT INTO sales(contractorid, status, `condition`, created_at) values(?, ?, ?, ? )");
		$stmt->bind_param("iiss", $contractorid, $status, $json_info, $created_at);
		$stmt->execute();
		$saleid = $this->conn->insert_id;
		$stmt->close();
		
		return $saleid;
	}
	
	//Utility used to don't change the created_at column in sales table when creating the new sale, 
	protected function updateSaleCondition($saleid,$created_at,$condition){
		$json_info=json_encode($condition,JSON_UNESCAPED_UNICODE);
		
		$stmt = $this->conn->prepare("UPDATE `sales` SET `condition`=? , `changed_at`=? WHERE `id`=?");
		$stmt->bind_param("ssi", $json_info, $created_at, $saleid);
		$stmt->execute();
		$stmt->close();
	}
	
	//-----------------------Sales methods------------------
	
	public function createSaleRate($userid,$contractorid,$label,$name,$name_full,$summary,$alias,$for_all_customers,$rate,$cash_only){
		
		$created_at=date('Y-m-d H:i:s',time());
		
		$condition=array();
		
		$condition["type"]=self::CONTRACTOR_CONDITION_TYPE_SALERATE;
		
		$text_object=array();
		$text_object["text"]=$label;
		$condition["label"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$name;
		$condition["name"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$name_full;
		$condition["name_full"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$summary;
		$condition["summary"]=$text_object;
		
		$condition["alias"]=$alias;
		
		$condition["rate"]=$rate;
		
		$condition["cash_only"]=$cash_only;
		
		$condition["created_at"]=$created_at;
		
		$saleid=$this->createSale($contractorid,$created_at,$condition);	
		$tag="sale_".$saleid;
		
		$condition["id"]=$saleid;
		$condition["tag_product"]=$tag;
		$condition["tag_customer"]=$tag;
		$condition["for_all_customers"]=$for_all_customers;
		
		$this->updateSaleCondition($saleid,$created_at,$condition);
		
		//write log in sales_operations table
		$this->addSaleOperation($saleid,self::SALE_OPERATION_TYPE_CREATE,$condition,"Sale create operation, alias=".$alias." userid=".$userid." rate=".$rate);
		
		return $saleid;		
	}
	
	public function createDiscount($userid,$contractorid,$label,$name,$name_full,$summary,$alias,$for_all_customers,$for_all_products,$rate,$min_summ,$max_summ,$cash_only){
		$created_at=date('Y-m-d H:i:s',time());
		
		$condition=array();
		
		$condition["type"]=self::CONTRACTOR_CONDITION_TYPE_DISCOUNT;
		
		$text_object=array();
		$text_object["text"]=$label;
		$condition["label"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$name;
		$condition["name"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$name_full;
		$condition["name_full"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$summary;
		$condition["summary"]=$text_object;
		
		$condition["alias"]=$alias;
		
		$condition["rate"]=$rate;
		$condition["min_summ"]=$min_summ;
		$condition["max_summ"]=$max_summ;
		$condition["cash_only"]=$cash_only;
		
		$condition["created_at"]=$created_at;
		
		$saleid=$this->createSale($contractorid,$created_at,$condition);	
		$tag="discount_".$saleid;
		
		$condition["id"]=$saleid;		
		$condition["tag_product"]=$tag;
		$condition["tag_customer"]=$tag;
		$condition["for_all_customers"]=$for_all_customers;
		$condition["for_all_products"]=$for_all_products;
		
		$this->updateSaleCondition($saleid,$created_at,$condition);
		
		//write log in sales_operations table
		$this->addSaleOperation($saleid,self::SALE_OPERATION_TYPE_CREATE,$condition,"Discount create operation, alias=".$alias." userid=".$userid." rate=".$rate." min_summ=".$min_summ." max_summ=".$max_summ);
		
		return $saleid;	
	}
	
	public function createInstallment($userid,$contractorid,$label,$name,$name_full,$summary,$alias,$for_all_customers,$time_notification){
		
		$created_at=date('Y-m-d H:i:s',time());
		
		$condition=array();
		
		$condition["type"]=self::CONTRACTOR_CONDITION_TYPE_INSTALLMENT;
		
		$text_object=array();
		$text_object["text"]=$label;
		$condition["label"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$name;
		$condition["name"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$name_full;
		$condition["name_full"]=$text_object;
		
		$text_object=array();
		$text_object["text"]=$summary;
		$condition["summary"]=$text_object;
		
		$condition["alias"]=$alias;
		
		$condition["time_notification"]=$time_notification;
		
		$condition["created_at"]=$created_at;
		
		$saleid=$this->createSale($contractorid,$created_at,$condition);	
		$tag="installment_".$saleid;
		
		$condition["id"]=$saleid;		
		$condition["tag_product"]=$tag;
		$condition["tag_customer"]=$tag;
		$condition["for_all_customers"]=$for_all_customers;
		$condition["price_name"]="installment_".$saleid;
		
		$this->updateSaleCondition($saleid,$created_at,$condition);
		
		//write log in sales_operations table
		$this->addSaleOperation($saleid,self::SALE_OPERATION_TYPE_CREATE,$condition,"Installment create operation, alias=".$alias." userid=".$userid." time_notification=".$time_notification);
		
		return $saleid;			
	}
	
	public function removeSale($userid,$saleid){
		
		// update query
		$stmt = $this->conn->prepare("UPDATE `sales` SET `status`=4 , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("i", $saleid);
		$stmt->execute();
		$affected_rows=$stmt->affected_rows;
		$stmt->close();
		
		//write log in sales_operations table
		if($affected_rows!=0){		
			$this->addSaleOperation($saleid,self::SALE_OPERATION_TYPE_REMOVE,'{"info":"Sale removed"}',"Sale remove operation, userid=".$userid);
			return true;
		}
		
		return false;
	}
	
	public function getSaleById($saleid){
		
		$stmt = $this->conn->prepare("SELECT id, contractorid, `condition`, status, created_at, changed_at FROM sales WHERE id = ? ");
		$stmt->bind_param("i", $saleid);
		if ($stmt->execute()) {

			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($id, $contractorid, $condition, $status, $created_at, $changed_at);            

			if($stmt->fetch()){

				$res= array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["condition"] = $condition;
				$res["status"] = $status;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();	

				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	

				$stmt->close();
				return $res;
			}
			
		} else {
			return NULL;
		}
	}
	
	public function updateSale($userid,$saleid,$condition){
	
		$json_info=json_encode($condition,JSON_UNESCAPED_UNICODE);
		
		$stmt = $this->conn->prepare("UPDATE `sales` SET `condition`=? WHERE `id`=?");
		$stmt->bind_param("si", $json_info, $saleid);
		$stmt->execute();
		$stmt->close();
		
		//write log in sales_operations table
		$this->addSaleOperation($saleid,self::SALE_OPERATION_TYPE_UPDATE,$condition,"Sale update operation. alias=".$condition["alias"]." userid=".$userid);
		
	}
	
	//-------------------SalesLogs(sales_operation table)-----------------------
	
	public function addSaleOperation($saleid,$type,$condition,$comment){
		
		$json_info=json_encode($condition,JSON_UNESCAPED_UNICODE);
		
		// insert query
		$stmt = $this->conn->prepare("INSERT INTO sales_operations(saleid, type, `condition`, comment) values(?, ?, ?, ?)");
		$stmt->bind_param("iiss", $saleid, $type, $json_info, $comment);
		$stmt->execute();
		$sale_operation_id = $this->conn->insert_id;
		$stmt->close();
		
		return $sale_operation_id;
	}
		
	//----------------------Tags & Prices------------------------------
	
	protected function addTagToProduct($productid,$tag){
		
		$product=$this->getProductById($productid);
		
		if(!isset($product["info"])){
			$info=array();
			$info["tags"]=array();
			$product["info"]=json_encode($info,JSON_UNESCAPED_UNICODE);
		}		
		
		$info=json_decode($product["info"],true);
		
		if(!isset($info["tags"])){			
			$info["tags"]=array();
		}
		
		//Add tag if not already exists
		if(!in_array($tag,$info["tags"])){
			$info["tags"][]=$tag;			
			$this->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
		}
	}
	
	protected function removeTagFromProduct($productid,$tag){
		
		$product=$this->getProductById($productid);
		
		if(!isset($product["info"]))
				return;
				
		$info=json_decode($product["info"],true);
			
		if(!isset($info["tags"]))
				return;
		
		$tags=$info["tags"];			
		$tag_found=false;
		while(($key = array_search($tag, $tags)) !== false) {
			unset($tags[$key]);		
			
			$tags=array_values($tags);
			
			$tag_found=true;
		}		
		$info["tags"]=$tags;
		
		if($tag_found){
			$this->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
		}
	}
	
	public function removeTagFromContractorProducts($contractorid,$tag){
		
		$products=$this->getProductsOfContractor($contractorid);
		
		foreach($products as $product){
		
			if(!isset($product["info"]))
				return;
				
			$info=json_decode($product["info"],true);
			
			if(!isset($info["tags"]))
				return;
			
			$tags=$info["tags"];
			
			$tags=$info["tags"];			
			$tag_found=false;
			while(($key = array_search($tag, $tags)) !== false) {
				unset($tags[$key]);						
				$tags=array_values($tags);				
				$tag_found=true;
			}		
			$info["tags"]=$tags;
			
			if($tag_found){
				$this->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
			}
		
		}
	}
	
	public function removePriceInstallmentFromContractorProducts($contractorid,$price_name){
		
		$products=$this->getProductsOfContractor($contractorid);
		
		foreach($products as $product){
		
			if(!isset($product["info"]))
				return;
				
			$info=json_decode($product["info"],true);
			
			if(!isset($info["prices"]))
				return;
			
			$prices=$info["prices"];
			
			$prices_changed=false;
			for($i=count($prices)-1;$i>=0;$i--){
				if($prices[$i]["name"]==$price_name){
					unset($info["prices"][$i]);
					$prices_changed=true;
				}
			}
			
			$info["prices"]=array_values($info["prices"]);
			
			if($prices_changed){				
				$this->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);				
			}
		
		}
	}
	
	//--------------------------1c----------------------------------
	
	public function makeProductNotInStock($id){
		
		$tag="not_in_stock";
		
		$product=$this->getProductById($id);

		if(!isset($product)){
			return false;
		}
		
		if(!isset($product["info"])){
			$info=array();
			$info["tags"]=array();
			$product["info"]=json_encode($info,JSON_UNESCAPED_UNICODE);
		}		
		
		$info=json_decode($product["info"],true);
		
		if(!isset($info["tags"])){			
			$info["tags"]=array();
		}
		
		//Add tag if not already exists
		if(!in_array($tag,$info["tags"])){
			$info["tags"][]=$tag;			
			$this->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
		}
		
		return true;
	}
	
	public function makeProductInStock($id){
		
		$tag="not_in_stock";
		
		$product=$this->getProductById($id);
		
		if(!isset($product))
			return false;
			
		if(!isset($product["info"]))
			return true;
				
		$info=json_decode($product["info"],true);
			
		if(!isset($info["prices"]))
				return true;
		
		$tags=$info["tags"];			
		$tag_found=false;
		while(($key = array_search($tag, $tags)) !== false) {
			unset($tags[$key]);		
			
			$tags=array_values($tags);
			
			$tag_found=true;
		}		
		$info["tags"]=$tags;
		
		if($tag_found){
			$this->updateProduct($product["id"], $product["name"], $product["price"], json_encode($info,JSON_UNESCAPED_UNICODE), $product["status"]);
		}
		
		return true;
	}
	
	//----------------------Delta-----------------------------------------
	public function getProductsDelta($timestamp) {
	
        $stmt = $this->conn->prepare("
			SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.code1c, p.changed_at 
			FROM products p 
			WHERE ( p.changed_at > ? )  ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "s", $date_string);
		
		
		$products=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $code1c, $changed_at);            
            
            while($stmt->fetch()) {
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["name"] = $name;
	            $res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				$res["code1c"] = $code1c;
				
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
								
	            $products[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $products;        
    }
	
    public function getContractorProductsDelta($contractorid,$timestamp) {
	
        $stmt = $this->conn->prepare("
			SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.code1c, p.changed_at 
			FROM products p 
			WHERE ( p.changed_at > ? ) AND (p.contractorid = ?) ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "si", $date_string,$contractorid);
		
		
		$products=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $code1c, $changed_at);            
            
            while($stmt->fetch()) {
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["name"] = $name;
	            $res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				$res["code1c"] = $code1c;
				
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
								
	            $products[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $products;        
    }
	
	public function getOrdersDelta($timestamp) {
	
        $stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
			FROM orders o 
			WHERE ( o.changed_at > ? ) ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "s", $date_string);
		
		
		$orders=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$customerid, $status, $record, $created_at, $changed_at);            
            
            while($stmt->fetch()){
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["customerid"] = $customerid;
	            $res["status"] = $status;
				$res["record"] = $record;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();	
				
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
								
	            $orders[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $orders;        
    }
	
	public function getOrdersDeltaOfCustomer($customerid, $timestamp) {
	
		//Здесь используется order_operation, а не просто order из-за того, что заказы могут быть переданы от одного заказчика другому (насколько я помню так было, я давно это сделал)
        $stmt = $this->conn->prepare("
			SELECT p.orderid AS id, p.contractorid, p.customerid, CASE p.type WHEN 5 THEN 5 ELSE o.status END AS status, p.record, o.code1c AS code1c, o.created_at AS created_at, p.created_at AS changed_at 
			FROM orders_operations p 
			INNER JOIN ( 
				SELECT orderid, max(created_at) AS created_at 
				FROM orders_operations 
				WHERE ( (customerid = ?) AND ( created_at > ? ) ) 
				GROUP BY orderid 
			) AS pmax USING (orderid,created_at) 
			LEFT JOIN orders o ON p.orderid=o.id 
			");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "is", $customerid,$date_string);
		
		
		$orders=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$customerid, $status, $record, $code1c, $created_at, $changed_at);            
            
            while($stmt->fetch()){
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["customerid"] = $customerid;
	            $res["status"] = $status;
				$res["record"] = $record;
				$res["code1c"] = $code1c;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();	
				
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
								
	            $orders[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $orders;        
    }
	
	public function getOrdersDeltaOfContractor($contractorid, $timestamp) {
	
        $stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
			FROM orders o 
			WHERE ( (o.contractorid = ?) AND ( o.changed_at > ? ) ) ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "is", $contractorid,$date_string);
		
		
		$orders=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$customerid, $status, $record, $code1c, $created_at, $changed_at);            
            
            while($stmt->fetch()){
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["customerid"] = $customerid;
	            $res["status"] = $status;
				$res["record"] = $record;
				$res["code1c"] = $code1c;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();	
				
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
								
	            $orders[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $orders;        
    }
	
	public function getOrdersDeltaOfContractorAgent($contractorid, $userid, $timestamp) {
	
        $stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
			FROM orders o 
			INNER JOIN ( 
				SELECT groupid 
				FROM group_users 
				WHERE ( ( userid = ? ) AND ( status = 8 ) ) 
			) AS gu ON  ( o.customerid = gu.groupid ) 
			WHERE ( (o.contractorid = ?) AND ( o.changed_at > ? ) ) ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "iis", $userid,$contractorid,$date_string);
		
		
		$orders=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$customerid, $status, $record, $code1c, $created_at, $changed_at);            
            
            while($stmt->fetch()){
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["customerid"] = $customerid;
	            $res["status"] = $status;
				$res["record"] = $record;
				$res["code1c"] = $code1c;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();	
				
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
								
	            $orders[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $orders;        
    }
	
		
}
 
?>