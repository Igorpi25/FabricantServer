<?php
 
/**
 * Class to handle all db operations of Fabricant-project
 *
 * @author Igor Ivanov
 */
 
require_once dirname(__FILE__).'/DbHandler.php';
 
class DbHandlerFabricant extends DbHandler{

	const STATUS_CREATED=0;
	const STATUS_PUBLISHED=1;
	const STATUS_DELETED=4;
 
    function __construct() {
        parent::__construct();
    }
	
/* ------------- `products` ------------------ */
 
    /**
     * Creating new user
     * @param String $name User full name
     * @param String $email User login email id
     * @param String $password User login password
     */
	public function createProduct($contractorid, $name, $price, $info) {

		// insert query
		$stmt = $this->conn->prepare("INSERT INTO products(contractorid, name, price, info) values(?, ?, ?, ?)");
		$stmt->bind_param("isds", $contractorid, $name, $price, $info);
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

	public function getProductById($id) {
        
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at FROM products p WHERE p.id =?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
        
	    $stmt->store_result();
            if($stmt->num_rows==0)return NULL;
            
            $stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at);            
            
            $stmt->fetch();
            
	            $res= array();
	            $res["id"] = $id;
				//$res["contractorid"] = $contractorid;
	            $res["name"] = $name;
	            //$res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;

	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
	            
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }
	
	public function getProductsOfContractor($contractorid){
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at FROM products p WHERE p.contractorid=? AND p.status<>0");
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
		$stmt = $this->conn->prepare("SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at FROM products p");
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

	public function publishProduct($id) {
		// update query
		$stmt = $this->conn->prepare("UPDATE `products` SET `status`=2 , `changed_at`=CURRENT_TIMESTAMP() WHERE `id`=?");
		$stmt->bind_param("i", $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

//-------------------------Delta-----------------------------------------
		
    public function getProductsDelta($timestamp) {
	
        $stmt = $this->conn->prepare("
			SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.changed_at 
			FROM products p 
			WHERE ( p.changed_at > ? ) ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "s", $date_string);
		
		
		$products=array();
		
        if ($stmt->execute()) {
        			
            $stmt->bind_result($id,$contractorid,$name, $status, $price, $info, $changed_at);            
            
            while($stmt->fetch()){
            
	            $res= array();
	            $res["id"] = $id;
				$res["contractorid"] = $contractorid;
	            $res["name"] = $name;
	            $res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = $info;
				//$res["info"] = '{"name":{"text":"ФИТНЕС ХЛЕБ МИЛЕДИ"},"name_full":{"text":"ФИТНЕС ХЛЕБ МИЛЕДИ"},"price":104,"summary":{"text":"В одной упаковке входят 5 булок. 1 булка – 90 г., размером 20х6х4 см. Фитнес хлеб полезен для пищеварения, здоровья, так как не содержит жиров, дрожжей. В сочетании с кашами будет питательным и малокалорийным завтраком не только для женщин, но и для всей семьи."},"icon":{"image_url":"http://igorserver.ru/v2/images/products/41_20_icon.jpg"},"details":[{"title":{"text":"Вес"},"photo":{"visible":false},"value":{"text":"90 г. (1 штука)"},"type":1},{"title":{"text":"Состав"},"photo":{"visible":false},"value":{"text":"Мука пшеничная хлебопекарная в/с, кунжут, смесь МИЛЕДИ, вода (фильтрованная)."},"type":1},{"title":{"text":"Пищевая ценность в 100 г."},"photo":{"visible":false},"value":{"text":"Белки – 10,4 г, жиры – 9,8 г, углеводы – 37,7 г"},"type":1},{"title":{"text":"Срок годности"},"photo":{"visible":false},"value":{"text":"72 часов t° хранения 18± 3C"},"type":1}]}';

	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
								
	            $products[]=$res;
	        }			
            $stmt->close();
			
        }
		
        return $products;        
    }
		
}
 
?>