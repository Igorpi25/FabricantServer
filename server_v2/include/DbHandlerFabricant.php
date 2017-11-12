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

		$stmt = $this->conn->prepare("SELECT id, contractorid, name, status, price, info, changed_at, code1c, article FROM products WHERE id = ? "); 
		$stmt->bind_param("i", $id);
		
		if ($stmt->execute()) {

			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at,$code1c,$article);

			$stmt->fetch();

			$res= array();
			$res["id"] = $id;
			$res["contractorid"] = $contractorid;
			$res["name"] = $name;
			$res["status"] = $status;
			$res["price"] = $price;
			$res["info"] = $info;
			$res["code1c"] = $code1c;
			$res["article"] = $article;

			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
			$res["changed_at"] = $timestamp_object->getTimestamp();

			$stmt->close();
			return $res;
		} else {
			return NULL;
		}
	}

	public function getProductsOfContractor($contractorid){
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c, p.article,r.rest FROM products p LEFT OUTER JOIN products_rest r ON p.id=r.productid WHERE p.contractorid=? AND p.status<>0");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id, $contractorid, $name, $status, $price, $info, $changed_at, $code1c,$article,$rest);
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
				$res["article"] = $article;
				$res["rest"] = $rest;
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
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c, p.article FROM products p WHERE p.contractorid=? AND p.status<>0 AND p.status<>4");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id, $contractorid, $name, $status, $price, $info, $changed_at, $code1c, $article);
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
				$res["article"] = $article;
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
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c, p.article FROM products p WHERE p.contractorid=? AND p.status=2");
		$stmt->bind_param("i", $contractorid);
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at, $code1c, $article);
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
				$res["article"] = $article;
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
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c, p.article FROM products p");
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return NULL;
			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at, $code1c, $article);
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
				$res["article"] = $article;
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

	public function updateProductArticle($id, $article) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `article`= ? , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("si", $article, $id);
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

		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at, p.code1c, p.article FROM products p WHERE p.contractorid=? AND p.code1c =?");
		$stmt->bind_param("is", $contractorid,$code);
		if ($stmt->execute()) {

			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at,$code1c,$article);


			if($stmt->fetch()){

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
				$res["article"] = $article;

				$stmt->close();
				return $res;
			}else{
				return NULL;
			}
		} else {
			return NULL;
		}
	}

	public function setProductRestById($id, $rest) {

		// update query
		$stmt = $this->conn->prepare("UPDATE `products_rest` SET `rest`=? WHERE `productid`=?");
		$stmt->bind_param("si", $rest, $id);
		$result = $stmt->execute();
		if($result)
			$affected_rows = $stmt->affected_rows;
		else
			$affected_rows=0;
		$stmt->close();

		if(empty($affected_rows)){
			$stmt = $this->conn->prepare("INSERT INTO `products_rest` (`productid`,`rest`) values(?, ?)");
			$stmt->bind_param("is", $id,$rest);
			$result=$stmt->execute();
			$stmt->close();
		}

		return $result;
	}

	public function getProductRestById($id) {

		$stmt = $this->conn->prepare("SELECT `rest` FROM `products_rest` WHERE `productid`=?");
		$stmt->bind_param("i", $id);
		if ($stmt->execute()) {
			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($rest);
			$stmt->fetch();
			$res=$rest;
			$stmt->close();
			return $res;
		} else {
			return NULL;
		}
	}

	public function getAllProductsRestLikeObject() {

		$stmt = $this->conn->prepare("SELECT `productid`,`rest` FROM `products_rest`");
		if ($stmt->execute()) {
			$stmt->store_result();
			if($stmt->num_rows==0)return NULL;

			$stmt->bind_result($productid,$rest);

      $result=array();

      while($stmt->fetch()){
        $result[strval($productid)]=$rest;
      }

      $stmt->close();

			return $result;
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

	public function getAllProcessingOrdersOfContractor($contractorid) {

		$stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at
			FROM orders o
			WHERE ( ( o.contractorid = ? ) AND ( o.status = 1 ) )
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
			
			$extended_results=array();
			foreach($result as $record){
				
				$items=$record["items"];				
				$extended_items=array();
				foreach($items as $item){	
				
					$productid=intval($item["productid"]);
					
					$product=$this->getProductById($productid);
					$item["article"]=$product["article"];					
					$extended_items[]=$item;
				}
				$record["items"]=$extended_items;	
				$extended_results[]=$record;
			}
			
			return $extended_results;
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
			
			$extended_results=array();
			foreach($result as $record){
				
				$items=$record["items"];				
				$extended_items=array();
				foreach($items as $item){	
				
					$productid=intval($item["productid"]);
					
					$product=$this->getProductById($productid);
					$item["article"]=$product["article"];					
					$extended_items[]=$item;
				}
				$record["items"]=$extended_items;	
				$extended_results[]=$record;
			}
			
			return $extended_results;
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

	public function getOrderByCode($code) {

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

		if(!isset($info["tags"]))
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

	//---------------------Analytic-------------------------------------
	
	//CRM
	
	public function getAnalyticAgentOrders($contractorid, $userid, $timestamp_from, $timestamp_to) {

        $stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, SUBSTRING_INDEX( SUBSTRING_INDEX(o.record, '\"customerUserId\":', -1),',',1 ) as `customerUserId`, SUBSTRING_INDEX( SUBSTRING_INDEX(o.record, '\"customerUserName\":\"', -1),'\",',1 ) as `customerUserName`, SUBSTRING_INDEX( SUBSTRING_INDEX(o.record, '{\"itemsAmount\":', -1),'}',1 ) as `amount`, o.code1c IS NOT NULL AS `imported`, o.status, o.created_at, o.changed_at 
			FROM orders o 
			INNER JOIN ( 
				SELECT groupid 
				FROM group_users 
				WHERE ( ( userid = ? ) AND ( status IN (0,1,2,8) ))  
			) AS gu ON  ( o.customerid = gu.groupid ) 
			LEFT OUTER JOIN groups AS g ON g.id = o.customerid 
			WHERE ( (o.contractorid = ?)  AND (g.type = 1) AND ( g.status IN (1,2) ) AND ( o.changed_at >= ? ) AND ( o.changed_at <= ? ) ) ");

		$date_from_string=date('Y-m-d H:i:s',$timestamp_from);
		$date_to_string=date('Y-m-d H:i:s',$timestamp_to);

        $stmt->bind_param( "iiss", $userid,$contractorid,$date_from_string,$date_to_string);

		$orders=array();

        if ($stmt->execute()) {

            $stmt->bind_result($id,$contractorid,$customerid,$customerUserId,$customerUserName,$amount,$imported, $status, $created_at, $changed_at);

            while($stmt->fetch()){

	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["customerid"] = $customerid;
				$res["customerUserId"] = $customerUserId;
				$res["customerUserName"] = $customerUserName;
				$res["amount"] = $amount;
				$res["imported"] = $imported;
	            $res["status"] = $status;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();

	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();

	            $orders[]=$res;
	        }
            $stmt->close();
			
			return $orders;

        }else{
			return NULL;
		}
		
    }
	
	public function getAnalyticCustomerOrders($contractorid, $customerid, $timestamp_from, $timestamp_to) {

        $stmt = $this->conn->prepare("
			SELECT o.id, o.contractorid, o.customerid, o.record, o.status, o.created_at, o.changed_at 
			FROM orders o
			WHERE ( (o.contractorid = ?)  AND (o.customerid = ?) AND ( o.status IN (1,2) ) AND ( o.changed_at >= ? ) AND ( o.changed_at <= ? ) ) ");

		$date_from_string=date('Y-m-d H:i:s',$timestamp_from);
		$date_to_string=date('Y-m-d H:i:s',$timestamp_to);

        $stmt->bind_param( "iiss", $contractorid,$customerid,$date_from_string,$date_to_string);

		$orders=array();

        if ($stmt->execute()) {

            $stmt->bind_result($id,$contractorid,$customerid,$record, $status, $created_at, $changed_at);

            while($stmt->fetch()){

	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["customerid"] = $customerid;
				$res["record"] = $record;
	            $res["status"] = $status;
				
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"] = $timestamp_object->getTimestamp();

	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();

	            $orders[]=$res;
	        }
            $stmt->close();
			
			return $orders;

        }else{
			return $orders;
		}
		
    }
	
	//Group 
		
    public function getAnalyticGroupsOfUser($userid) {

            $stmt = $this->conn->prepare("
				SELECT `g`.id, `g`.name, `g`.address, `g`.status, `gu`.`status` AS `status_in_group`, IF (`ag`.info REGEXP '\"kustuk_90\"',TRUE,FALSE) AS `kustuk_90`, `g`.created_at, `g`.changed_at  
				FROM `group_users` AS `gu` 
				LEFT JOIN `groups` AS `g` ON `gu`.groupid=`g`.id 
				LEFT OUTER JOIN `analytic_groups` AS `ag` ON `gu`.groupid=`ag`.groupid 
				WHERE ( 
						`gu`.`userid`= ? 
					AND 
						`gu`.`status` IN ( 1, 2, 8, 0 ) 
					AND 
						`g`.`type`=1 
				) 
				");

			$stmt->bind_param("i", $userid);

			if($stmt->execute()){

				$stmt->bind_result($id,$name,$address,$status,$status_in_group,$kustuk_90,$created_at,$changed_at);

				$result=array();

				while($stmt->fetch()){
					$res=array();

					$res["id"]=$id;
					$res["name"]=$name;
					$res["address"]=$address;
					$res["status"]=$status;
					$res["status_in_group"]=$status_in_group;
					$res["kustuk_90"]=$kustuk_90;

					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"]=$timestamp_object->getTimestamp();

					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();

					$result[]=$res;
				}
				$stmt->close();

				return $result;
			}else{
				return NULL;
			}

    }
	
	public function getAnalyticGroupById($groupid) {

            $stmt = $this->conn->prepare("
				SELECT g.id,g.groupid,g.info,g.changed_at 
				FROM analytic_groups g 
				WHERE ( g.groupid  = ? ) ");
            $stmt->bind_param("i", $groupid);

			if($stmt->execute()){

				$stmt->bind_result($id,$groupid,$info,$changed_at);

				if($stmt->fetch()){
					$res=array();

					$res["id"]=$id;
					$res["groupid"]=$groupid;
					$res["info"]=$info;

					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();

					$stmt->close();
					return $res;
				}
				$stmt->close();

			}
			
			return NULL;
    }
	
	protected function setAnalyticGroupInfo($info,$groupid) {
		$stmt = $this->conn->prepare("SELECT `groupid` FROM `analytic_groups` WHERE ( `groupid` = ? )");
		$stmt->bind_param("i",$groupid);
        $result = $stmt->execute();
        $stmt->store_result();
        $numrows = $stmt->num_rows;

        if ($numrows > 0) {
            $stmt = $this->conn->prepare("UPDATE `analytic_groups` SET `info` = ? WHERE ( `groupid` = ? )");
			$stmt->bind_param("si",$info,$groupid);
            $stmt->execute();
            $stmt->close();
        }
        else {
            $stmt = $this->conn->prepare("INSERT INTO analytic_groups(groupid,info,changed_at) values( ? , ? , CURRENT_TIMESTAMP() )");
            $stmt->bind_param("is", $groupid, $info);
            $stmt->execute();
            $stmt->close();
        }
	
	}
	
	public function addTagToAnalyticGroup($tag,$groupid){

		$group=$this->getAnalyticGroupById($groupid);

		if(!isset($group))
			$group=array();

		if(!isset($group["info"])){
			$group["info"]="{}";
		}

		$info=json_decode($group["info"],true);

		if(!isset($info["tags"])){
			$info["tags"]=array();
		}

		$tags=$info["tags"];

		if(($key = array_search($tag, $tags)) !== false) {
			return;
		}

		$tags[]=$tag;

		$info["tags"]=$tags;

		$this->setAnalyticGroupInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $groupid);

	}

	public function removeTagFromAnalyticGroup($tag,$groupid){

		$group=$this->getAnalyticGroupById($groupid);

		if(!isset($group))
			return;

		if(!isset($group["info"])){
			return;
		}

		$info=json_decode($group["info"],true);

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
			$this->setAnalyticGroupInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $groupid);
		}
	}

	public function groupHasAnalyticTag($tag,$groupid){

		$group=$this->getAnalyticGroupById($groupid);

		if(!isset($group))
			return false;

		if(!isset($group["info"])){			
			return false;
		}

		$info=json_decode($group["info"],true);

		if(!isset($info["tags"]))
			return false;

		$tags=$info["tags"];

		if(($key = array_search($tag, $tags)) !== false) {
			return true;
		}
		
		return false;

	}
	
	//Product
	
	/*
	* Возвращает множество id продуктов с заданным тэгом
	*/
	public function getAnalyticProductsIdsWithTag($tag){
		
		$stmt = $this->conn->prepare("SELECT productid FROM analytic_products WHERE SUBSTRING_INDEX( SUBSTRING_INDEX(`info`, '\"tags\":[', -1),']',1 ) REGEXP ? ");
		$stmt->bind_param("s", $tag);
		
		$result=array();
		
		if ($stmt->execute()){
			$stmt->store_result();
			if($stmt->num_rows==0) return $result;
			$stmt->bind_result($productid);
			while($stmt->fetch()){
				$result[]=$productid;
			}
			$stmt->close();
			return $result;
		} else {
			return $result;
		}
	}
	
	public function getAnalyticProductById($productid) {

            $stmt = $this->conn->prepare("
				SELECT p.id,p.productid,p.info,p.changed_at 
				FROM analytic_products p 
				WHERE ( p.productid  = ? ) ");
            $stmt->bind_param("i", $productid);

			if($stmt->execute()){

				$stmt->bind_result($id,$productid,$info,$changed_at);

				if($stmt->fetch()){
					$res=array();

					$res["id"]=$id;
					$res["groupid"]=$productid;
					$res["info"]=$info;

					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();

					$stmt->close();
					return $res;
				}
				$stmt->close();

			}
			
			return NULL;
    }
	
	protected function setAnalyticProductInfo($info,$productid) {
		$stmt = $this->conn->prepare("SELECT `productid` FROM `analytic_products` WHERE ( `productid` = ? )");
		$stmt->bind_param("i",$productid);
        $result = $stmt->execute();
        $stmt->store_result();
        $numrows = $stmt->num_rows;

        if ($numrows > 0) {
            $stmt = $this->conn->prepare("UPDATE `analytic_products` SET `info` = ? WHERE ( `productid` = ? )");
			$stmt->bind_param("si",$info,$productid);
            $stmt->execute();
            $stmt->close();
        }
        else {
            $stmt = $this->conn->prepare("INSERT INTO analytic_products(productid, info, changed_at) values( ? , ? , CURRENT_TIMESTAMP() )");
            $stmt->bind_param("is", $productid, $info);
            $stmt->execute();
            $stmt->close();
        }
	
	}
	
	public function addTagToAnalyticProduct($tag,$productid){

		$product=$this->getAnalyticProductById($productid);

		if(!isset($product))
			$product=array();

		if(!isset($product["info"])){
			$product["info"]="{}";
		}

		$info=json_decode($product["info"],true);

		if(!isset($info["tags"])){
			$info["tags"]=array();
		}

		$tags=$info["tags"];

		if(($key = array_search($tag, $tags)) !== false) {
			return;
		}

		$tags[]=$tag;

		$info["tags"]=$tags;

		$this->setAnalyticProductInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $productid);

	}

	public function removeTagFromAnalyticProduct($tag,$productid){

		$product=$this->getAnalyticProductById($productid);

		if(!isset($product))
			return;

		if(!isset($product["info"])){
			return;
		}

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
			$this->setAnalyticProductInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $productid);
		}
	}

	public function productHasAnalyticTag($tag,$productid){

		$product=$this->getAnalyticProductById($productid);

		if(!isset($product))
			return false;

		if(!isset($product["info"])){			
			return false;
		}

		$info=json_decode($product["info"],true);

		if(!isset($info["tags"]))
			return false;

		$tags=$info["tags"];

		if(($key = array_search($tag, $tags)) !== false) {
			return true;
		}
		
		return false;
	}

	//Reports
	
	/**
     * Get analytic groups of user
     */
    public function getAnalyticProfitOrders() {

            $stmt = $this->conn->prepare("
				SELECT `o`.id,`o`.customerid,`g`.name,`g`.address, SUBSTRING_INDEX( SUBSTRING_INDEX(`record`, '{\"itemsAmount\":', -1),'}',1 ) as `amount`, `o`.status, `o`.created_at
				FROM `orders` AS `o`
				LEFT OUTER JOIN `group_users` AS `gu_252` ON ((`gu_252`.groupid = `o`.customerid) AND (`gu_252`.userid = 252 ))
				LEFT OUTER JOIN `group_users` AS `gu_253` ON ((`gu_253`.groupid = `o`.customerid) AND (`gu_253`.userid = 253 ))
				LEFT OUTER JOIN `group_users` AS `gu_254` ON ((`gu_254`.groupid = `o`.customerid) AND (`gu_254`.userid = 254 ))
				LEFT OUTER JOIN `group_users` AS `gu_256` ON ((`gu_256`.groupid = `o`.customerid) AND (`gu_256`.userid = 256 ))
				LEFT OUTER JOIN `groups` AS `g` ON (`g`.id = `o`.customerid)
				WHERE `o`.`contractorid` = 127
				AND `o`.`status` IN ( 1, 2 )
				AND (
					( `gu_252`.`status` IN (0,1,2,8) ) OR
					( `gu_253`.`status` IN (0,1,2,8) ) OR
					( `gu_254`.`status` IN (0,1,2,8) ) OR
					( `gu_256`.`status` IN (0,1,2,8) )
				)
				AND `o`.`code1c` IS NOT NULL
				AND (`o`.`created_at`>='2017-09-29')
				AND (`o`.`created_at`<'2017-11-02')
				");

			//$stmt->bind_param("i", $userid);

			if($stmt->execute()){

				$stmt->bind_result($id,$customerid,$customerName,$address,$amount,$status,$created_at);

				$result=array();

				while($stmt->fetch()){
					$res=array();

					$res["id"]=$id;
					$res["customerid"]=$customerid;
					$res["customerName"]=$customerName;
					$res["address"]=$address;
					$res["amount"]=$amount;
					$res["status"]=$status;
					$res["created_at"]=$created_at;

					$result[]=$res;
				}
				$stmt->close();

				return $result;
			}else{
				return NULL;
			}

    }
	
	/*
	* Аналитика измененные заказы, чтобы знать сколько потеряли при изменении заказа
	*/
	public function getAnalyticChangedOrders() {
		
        $stmt = $this->conn->prepare("
			SELECT s.id, s.agentid, s.customerid, s.customerName, s.address, s.status, s.initial_amount, s.amount, s.diff, (s.diff<>0) AS diff_flag, IF (s.diff<0,s.diff,0) AS lesion, IF (s.diff<0,1,0) AS lesion_flag, s.created_at
			FROM(
				SELECT s.*, (amount-initial_amount) AS diff
				FROM(
					SELECT p.*, g.agentid, o.status, SUBSTRING_INDEX( SUBSTRING_INDEX( SUBSTRING_INDEX(p.`record`, '\"customerUserId\":', -1),',',1 ),'}',1 ) AS customerUserId, g.customerName,g.address, CAST( SUBSTRING_INDEX( SUBSTRING_INDEX(p.`record`, '{\"itemsAmount\":', -1),'}',1 ) AS DECIMAL(10,2) ) as `initial_amount`, CAST( SUBSTRING_INDEX( SUBSTRING_INDEX(o.`record`, '{\"itemsAmount\":', -1),'}',1 ) AS DECIMAL(10,2) ) as `amount`
					FROM `orders_operations` AS `p`
					INNER JOIN `orders` AS o ON o.id = p.orderid
					INNER JOIN ( 
							SELECT `g`.id, name AS customerName, address, gu.userid AS agentid
							FROM `groups` AS `g`
							INNER JOIN `group_users` AS `gu` ON ((`gu`.groupid = `g`.id) AND (`gu`.userid IN (252,253,254,256) ) AND (`gu`.`status` IN (0,1,2,8))) 
							WHERE `g`.type=1
							GROUP BY id 
						) AS `g` ON (`g`.id = `o`.customerid)
					WHERE ( 	
						(p.type = 1) AND
						(p.contractorid = ?) AND 
						(p.`created_at`>=?) AND 
						(p.`created_at`<?) AND
						(`o`.`status` IN ( 1, 2 ) ) AND
						(`o`.`code1c` IS NOT NULL )
					) 
				) AS s
			) AS s
			");

		$contractorid=127; 
		$date_from_string='2017-09-29'; 
		$date_to_string='2017-11-2';
		
        $stmt->bind_param( "iss", $contractorid,$date_from_string,$date_to_string);

		$orders=array();

        if ($stmt->execute()) {

            $stmt->bind_result($id,$agentid,$customerid,$customerName,$address,$status,$initial_amount,$amount,$diff,$diff_flag,$lesion,$lesion_flag,$created_at);

            while($stmt->fetch()){

	            $res= array();
	            $res["id"] = $id;
				$res["agentid"] = $agentid;
	            $res["customerid"] = $customerid;
				$res["customerName"] = $customerName;
				$res["address"] = $address;
				$res["status"] = $status;
				$res["initial_amount"] = $initial_amount;
				$res["amount"] = $amount;
				$res["diff"] = $diff;
	            $res["diff_flag"] = $diff_flag;
				$res["lesion"] = $lesion;
	            $res["lesion_flag"] = $lesion_flag;
				
				$res["created_at"] = $created_at;

	            $orders[]=$res;
	        }
            $stmt->close();
			
			return $orders;

        }else{
			return NULL;
		}
		
    }
	
	//----------------------Delta-----------------------------------------
	public function getProductsDelta($timestamp) {

        $stmt = $this->conn->prepare("
			SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.code1c, p.changed_at
			FROM products p
			LEFT JOIN groups g ON p.contractorid = g.id
			WHERE ( p.changed_at > ? ) AND ( ( g.status = 0) OR (g.status = 1))  ");

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

	public function getProductsRestDelta($contractorid,$timestamp) {

        $stmt = $this->conn->prepare("
			SELECT p.productid, p.rest
			FROM products_rest p
			WHERE ( p.changed_at > ? )  ");

		$date_string=date('Y-m-d H:i:s',$timestamp);

        $stmt->bind_param( "s", $date_string);


		$products=array();

        if ($stmt->execute()) {

            $stmt->bind_result($productid,$rest);

            while($stmt->fetch()) {

				if(!isset($rest))continue;

	            $res= array();
	            $res["productid"] = $productid;
				$res["rest"] = $rest;

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


				try{
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"] = $timestamp_object->getTimestamp();
				}catch(Exception $e){
					$res["created_at"]= 0;
				}
				try{
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();
				}catch(Exception $e){
					$res["changed_at"]= 0;
				}
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