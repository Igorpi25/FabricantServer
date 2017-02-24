<?php
 
/**
 * Class to handle all db operations of Profile-project
 *
 * @author Igor Ivanov
 */
 
require_once dirname(__FILE__).'/DbHandler.php';
 
class DbHandlerProfile extends DbHandler{
 
    function __construct() {
        parent::__construct();
    }
	
	
/* ------------- `users by phone` ------------------ */
	
	public function createSMSVerificationCode($phone){
       	
        $code=rand(100000,999999);
		
		$stmt = $this->conn->prepare("UPDATE `sms_verification` SET `code` = ?, `count` = 0, `created_at` = NOW()  WHERE `phone` = ? ");
        $stmt->bind_param("is", $code,$phone);
		$stmt ->execute();
		$affected_rows = $stmt->affected_rows;
		$stmt->close();
		
		if($affected_rows==0){
			// insert query
			$stmt = $this->conn->prepare("INSERT INTO sms_verification(phone,code,created_at) values(?, ?, NOW() )");
			$stmt->bind_param("si", $phone, $code);
			$result = $stmt->execute();
			$stmt->close();

			// Check for successful insertion
			if (!$result) {				
				return null;
			}
		}
			
        return $code;
	}
	
	public function checkVerificationCode($phone,$code_sent_by_user){
       		
        $stmt = $this->conn->prepare("SELECT code, count, created_at FROM sms_verification WHERE ( phone = ? ) ");
        $stmt->bind_param("s",$phone);
        if ($stmt->execute()) {
		
			$res = array();
            $stmt->bind_result($code,$count, $created_at);               
			$success=$stmt->fetch();
			
			
			if(!$success){				
				//Если записей не найдено	
				$stmt->close();
				return false;
			}
			
			if($code_sent_by_user==$code){
				$stmt->close();
				$this->removeVerificationCodes($phone);
				return true;
			}
			
			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
			$created_timestamp = $timestamp_object->getTimestamp();
			
            if( (time()-$created_timestamp > 60*10 ) || ($count>2) ){
				$stmt->close();
				$this->removeVerificationCodes($phone);
				return false;
			}
			
		}
		$stmt->close();	
		
		$count_inc = 1+$count;
		
		$stmt = $this->conn->prepare("UPDATE `sms_verification` SET `count` = ?  WHERE `phone` = ? ");
        $stmt->bind_param("is", $count_inc, $phone);
		$stmt->execute();
		$stmt->close();
		
        return false;
	}
	
	public function removeVerificationCodes($phone){
       		
        $stmt = $this->conn->prepare("DELETE FROM `sms_verification` WHERE  `phone` = ?  ");
        $stmt->bind_param("s",$phone);
        $stmt->execute();
		$stmt->close();
	}
	
	public function createUserByPhone($name, $phone, $email, $password, $info) {
        require_once 'PassHash.php';
 
        // First check if user already existed in db
        if (!$this->isUserWithPhoneExists($phone)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);
 
            // Generating API key
            $api_key = $this->generateApiKey();
			
			$info_string=json_encode($info,JSON_UNESCAPED_UNICODE);
			
            // insert query
            $stmt = $this->conn->prepare("INSERT INTO users(name, phone, email, password_hash, api_key, status, info) values(?, ?, ?, ?, ?, 1, ?)");
            $stmt->bind_param("ssssss", $name, $phone, $email, $password_hash, $api_key, $info_string);
 
            $result = $stmt->execute();
 
            $stmt->close();
 
            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same phone already existed in the db
            return USER_ALREADY_EXISTED;
        }
 
    }
	
	public function isUserWithPhoneExists($phone) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
	
	public function checkLoginByPhone($phone, $password) {
        // fetching user by phone
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE phone = ?");
 
        $stmt->bind_param("s", $phone);
 
        $stmt->execute();
 
        $stmt->bind_result($password_hash);
 
        $stmt->store_result();
 
        if ($stmt->num_rows > 0) {
            // Found user with the phone
            // Now verify the password
 
            $stmt->fetch();
 
            $stmt->close();
 
            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();
 
            // user not existed with the phone
            return FALSE;
        }
    }
	
	public function getUserByPhone($_phone) {
        $stmt = $this->conn->prepare("SELECT `id`, `name`, `phone`, `email`, `info`, `api_key`, `status`, `changed_at` FROM `users` WHERE `phone` = ? ");
        $stmt->bind_param("s", $_phone);
        if ($stmt->execute()) {
        
            
            $stmt->bind_result($id,$name, $phone, $email, $info, $api_key, $status, $changed_at);            
            
            if(!$stmt->fetch())return NULL;
			
            
			$res = array();
			$res["id"] = $id;
			$res["name"] = $name;
			$res["phone"] = $phone;
			$res["email"] = $email;
			
			$info_array=json_decode($info,true);
			
			$res["info"] = $info_array;
			$res["api_key"] = $api_key;
			$res["status"] = $status;
			
			$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
			$res["changed_at"] = $timestamp_object->getTimestamp();
	        
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }
	
	public function checkVerifiedPassword($userid, $password) {
        // fetching user by phone
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE id = ?");
 
        $stmt->bind_param("i", $userid);
 
        $stmt->execute();
 
        $stmt->bind_result($password_hash);
 
        $stmt->store_result();
 
        if ($stmt->num_rows > 0) {
            // Found user with the phone
            // Now verify the password
 
            $stmt->fetch();
 
            $stmt->close();
 
            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();
 
            // user not existed with the phone
            return FALSE;
        }
    }

	public function changeUserPassword($userid, $password) {
        require_once 'PassHash.php';
 
		 // Generating password hash
		$password_hash = PassHash::hash($password);

		// insert query
		$stmt = $this->conn->prepare("UPDATE `users` SET `password_hash` = ? WHERE `id` = ? ");
		$stmt->bind_param("si", $password_hash,$userid);

		$result = $stmt->execute();

		$stmt->close();
 
		// Check for successful insertion
		if ($result) {
			$response["success"]=1;
			$response["error"]=false;
			$response["message"]="User password is changed";
		} else {
			$response["success"]=0;
			$response["error"]=true;
			$response["message"]="Update query to DB failed";
		}
        
 
        return $response;
    }

	/* ------------- `users` ------------------ */
	
	public function updateUserInfo($id, $info) {
        require_once 'PassHash.php';
        
        $response = array();
		
		$info_string=json_encode($info,JSON_UNESCAPED_UNICODE);
		        
		// updatequery
		$stmt = $this->conn->prepare("UPDATE `users` SET `info` = ? , `changed_at` = CURRENT_TIMESTAMP() WHERE `id` = ? ");
		$stmt->bind_param("si", $info_string, $id);

		$result = $stmt->execute();

		$stmt->close();

		// Check for successful insertion
		if ($result) {
			$response["success"]=1;
			$response["error"]=false;
			$response["message"]="User is successfully updated";
		} else {
			$response["success"]=0;
			$response["error"]=true;
			$response["message"]="Update query to DB failed";
		}
        
 
        return $response;
    }
	
	public function updateUserName($id, $name) {
        require_once 'PassHash.php';
        
        $response = array();
        
		// updatequery
		$stmt = $this->conn->prepare("UPDATE `users` SET `name` = ? , `changed_at` = CURRENT_TIMESTAMP() WHERE `id` = ? ");
		$stmt->bind_param("si", $name, $id);

		$result = $stmt->execute();

		$stmt->close();

		// Check for successful insertion
		if ($result) {
			$response["success"]=1;
			$response["error"]=false;
			$response["message"]="User is successfully updated";
		} else {
			$response["success"]=0;
			$response["error"]=true;
			$response["message"]="Update query to DB failed";
		}
        
 
        return $response;
    }
	
    public function updateUserRegdata($id, $name, $info) {
        require_once 'PassHash.php';
        
        $response = array();
         
            // updatequery
			
			$info_string=json_encode($info);
			
            $stmt = $this->conn->prepare("UPDATE `users` SET `name` = ? , `info` = ? , `changed_at` = CURRENT_TIMESTAMP() WHERE `id` = ? ");
            $stmt->bind_param("ssi", $name, $info_string,$id);
 
            $result = $stmt->execute();
 
            $stmt->close();
 
            // Check for successful insertion
            if ($result) {
                $response["success"]=1;
                $response["error"]=false;
                $response["message"]="User is successfully updated";
            } else {
                $response["success"]=0;
                $response["error"]=true;
                $response["message"]="Update query to DB failed";
            }
        
 
        return $response;
    }    
 
    public function checkLogin($phone, $password) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE phone = ?");
 
        $stmt->bind_param("s", $phone);
 
        $stmt->execute();
 
        $stmt->bind_result($password_hash);
 
        $stmt->store_result();
 
        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password
 
            $stmt->fetch();
 
            $stmt->close();
 
            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();
 
            // user not existed with the email
            return FALSE;
        }
    }
 
    /**
     * Fetching user by id
     * @param int $id User id
     * returns public columns of 'users' table
     */
    public function getUserById($id) {
        $stmt = $this->conn->prepare("SELECT u.id, u.name, u.phone, u.email, u.info, u.status, u.changed_at, a.filename_icon, a.filename_avatar, a.filename_full FROM users u LEFT OUTER JOIN avatars a ON u.avatar = a.id WHERE u.id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
        
	    $stmt->store_result();
            if($stmt->num_rows==0)return NULL;
            
            $stmt->bind_result($id,$name, $phone, $email, $info, $status, $changed_at, $icon, $avatar,$full);            
            
            $stmt->fetch();
            
	            $res= array();
	            $res["id"] = $id;
	            $res["name"] = $name;
				$res["phone"] = $phone;
				$res["email"] = $email;
				
				
				$info_array=json_decode($info,true);				
				$res["info"] = $info_array;
				
	            $res["status"] = $status;

	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
	            
	            $avatars=array();
	            
	            if($full) $avatars['full']=URL_HOME.path_fulls.$full;
	            if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
	            if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
	            
				if(count($avatars))
					$res['avatars']=$avatars;
	            
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }
    
    /**
     * Get all users
     * returns public columns of 'users' table
     */
    public function getAllUsers() {
        $stmt = $this->conn->prepare("SELECT u.id, u.name, u.status, u.changed_at, a.filename_icon, a.filename_avatar, a.filename_full FROM users u LEFT OUTER JOIN avatars a ON u.avatar = a.id ");
        
        if ($stmt->execute()) {
        
        
            $stmt->bind_result($id,$name, $status, $changed_at, $icon, $avatar,$full);            
            
            $users=array();
            while($stmt->fetch()){
            	    
            	    $res= array();
	            $res["id"] = $id;
	            $res["name"] = $name;
	            $res["status"] = $status;
	            
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
	            
				$avatars=array();
	            
	            if($full) $avatars['full']=URL_HOME.path_fulls.$full;
	            if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
	            if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
	            
	            if(count($avatars))
					$res['avatars']=$avatars;          
	            
	            $users[]=$res;
	    }            
            $stmt->close();
            
            return $users;
        } else {
            return NULL;
        }
    }
 
    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($api_key);            
            $stmt->fetch();
            $res["api_key"] = $api_key;
            
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }
 
    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id);            
            $stmt->fetch();
            $res["id"] = $id;
            
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }
 
    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
 
    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
 	
/*----------------`avatars`-----------------------------*/
    
    /**
     * Get user's avatars by user_id
     * @param int $user_id
     * returns user's avatars filenames
     */
    public function getUserAvatar($user_id) {
    
        $stmt = $this->conn->prepare("SELECT a.filename_full, a.filename_avatar, a.filename_icon, a.created_at FROM users u LEFT OUTER JOIN avatars a ON u.avatar = a.id WHERE u.id = ? AND u.avatar != 0 AND u.avatar != NULL ");
        
        
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
        
	    $stmt->store_result();
            if($stmt->num_rows==0){
            	throw new Exception("User's avatars not exist user_id=".$user_id);
            }
            
            $stmt->bind_result($filename_full, $filename_avatar,$filename_icon,$changed_at);            
            
            $stmt->fetch();
            
	            $res= array();
	            $res['full'] = $filename_full;
	            $res['avatar'] = $filename_avatar;
	            $res['icon'] = $filename_icon;
	            
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
	            
            $stmt->close();
            
            return $res;
        } else {
            throw new Exception("BD can't execute: $stmt->execute()==NULL ");
        }
    }
    
    /**
     * Create new user's photos(full,avatar,icon)     	
	 * @param String $user_id
	 * @param String $filename_full
	 * @param String $filename_avatar
	 * @param String $filename_icon
	 *return true if success, false otherwise;
     */
    public function createUserAvatar($user_id, $filename_full, $filename_avatar, $filename_icon) {  
    	
		if(!$this->getUserById($user_id))return NULL;
		
		//If user have avatar already, delete row in avatars table and unlink files
    	$this->deleteUserAvatar($user_id);
    	  
		//Create new avatar row 
        $stmt = $this->conn->prepare("INSERT INTO avatars (filename_full, filename_avatar, filename_icon) VALUES(?,?,?)");
        $stmt->bind_param("sss", $filename_full, $filename_avatar, $filename_icon);
        $result = $stmt->execute();
		//Save avatar id
		$new_avatar_id = $this->conn->insert_id;
        $stmt->close();
 
		//Update avatar column on users table		
		$stmt = $this->conn->prepare("UPDATE `users` SET `avatar` = ? WHERE `id` = ? ");
        $stmt->bind_param("ii", $new_avatar_id,$user_id);
		$result = $stmt->execute();
		$stmt->close();
		
		if($result){
			return true;
		}else{
			return false;
		}        
    }
    
    /**
     * Delete(unlink) files from directory, and delete rows from datebase    	
	 * @param String $user_id
	 *return deleted rows count;
     */
    public function deleteUserAvatar($user_id) {  
    	
    	$stmt = $this->conn->prepare("SELECT a.filename_full, a.filename_avatar, a.filename_icon, a.id FROM users u LEFT OUTER JOIN avatars a ON u.avatar = a.id WHERE u.id = ? ");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
        
			$stmt->store_result();
            if($stmt->num_rows==0){
            	return 0;
            }            
            $stmt->bind_result($filename_full, $filename_avatar,$filename_icon,$avatar_id);            
            $stmt->close();
			
            //Unlinking files
            if( file_exists($_SERVER['DOCUMENT_ROOT'].path_fulls.$filename_full)&& ($filename_full!=NULL) ){
				unlink($_SERVER['DOCUMENT_ROOT'].path_fulls.$filename_full);}            						
			if(file_exists($_SERVER['DOCUMENT_ROOT'].path_avatars.$filename_avatar)&& ($filename_full!=NULL) ){
				unlink($_SERVER['DOCUMENT_ROOT'].path_avatars.$filename_avatar);}    						
			if(file_exists($_SERVER['DOCUMENT_ROOT'].path_icons.$filename_icon) && ($filename_full!=NULL) ){
				unlink($_SERVER['DOCUMENT_ROOT'].path_icons.$filename_icon);}    		
				
			//Update avatar column on users table
			$new_avatar_id = $this->conn->insert_id;
			$stmt = $this->conn->prepare("UPDATE `users` SET `avatar` = NULL WHERE `id` = ? ");
			$stmt->bind_param("i", $user_id);
			$stmt->execute();
			$stmt->close();
			
			//Delete row in avatars table
			$stmt = $this->conn->prepare("DELETE FROM avatars WHERE id = ? ");
			$stmt->bind_param("i", $avatar_id);			
			$stmt->execute();
			$count=$stmt->affected_rows;
			$stmt->close();
			
        } else {
            throw new Exception("BD can't execute: $stmt->execute()==NULL ");
        }
		
        return $count;
    }
 
    /**
     * Get group's avatars by group_id
     * @param int $group_id
     * returns group's avatars filenames
     */
    public function getGroupAvatar($group_id) {
    
        $stmt = $this->conn->prepare("SELECT a.filename_full, a.filename_avatar, a.filename_icon, a.created_at FROM groups g LEFT OUTER JOIN avatars a ON g.avatar = a.id WHERE g.id = ? AND g.avatar != 0 AND g.avatar != NULL ");
        
        
        $stmt->bind_param("i", $group_id);
        
        if ($stmt->execute()) {
        
	    $stmt->store_result();
            if($stmt->num_rows==0){
            	throw new Exception("Group's avatars not exist group_id=".$group_id);
            }
            
            $stmt->bind_result($filename_full, $filename_avatar,$filename_icon,$changed_at);            
            
            $stmt->fetch();
            
	            $res= array();
	            $res['full'] = $filename_full;
	            $res['avatar'] = $filename_avatar;
	            $res['icon'] = $filename_icon;
	            
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();	
	            
            $stmt->close();
            
            return $res;
        } else {
            throw new Exception("BD can't execute: $stmt->execute()==NULL ");
        }
    }
    
    /**
     * Create groups photos(full,avatar,icon)     	
	 * @param String $group_id
	 * @param String $filename_full
	 * @param String $filename_avatar
	 * @param String $filename_icon
	 *return true if success, false otherwise;
     */
    public function createGroupAvatar($group_id, $filename_full, $filename_avatar, $filename_icon) {  
    	
		if(!$this->getGroupById($group_id))return NULL;
		
		//If group have avatar already, delete row in avatars table and unlink files
    	$this->deleteGroupAvatar($group_id);
    	  
		//Create new avatar row 
        $stmt = $this->conn->prepare("INSERT INTO avatars (filename_full, filename_avatar, filename_icon) VALUES(?,?,?)");
        $stmt->bind_param("sss", $filename_full, $filename_avatar, $filename_icon);
        $result = $stmt->execute();
		//Save avatar id
		$new_avatar_id = $this->conn->insert_id;
        $stmt->close();
 
		//Update avatar column on users table		
		$stmt = $this->conn->prepare("UPDATE `groups` SET `avatar` = ? WHERE `id` = ? ");
        $stmt->bind_param("ii", $new_avatar_id,$group_id);
		$result = $stmt->execute();
		$stmt->close();
		
		if($result){
			return true;
		}else{
			return false;
		}        
    }
    
    /**
     * Delete(unlink) files from directory, and delete rows from datebase    	
	 * @param String $group_id
	 *return deleted rows count;
     */
    public function deleteGroupAvatar($group_id) {  
    	
    	$stmt = $this->conn->prepare("SELECT a.filename_full, a.filename_avatar, a.filename_icon, a.id FROM groups g LEFT OUTER JOIN avatars a ON g.avatar = a.id WHERE g.id = ? ");
        $stmt->bind_param("i", $group_id);
        
        if ($stmt->execute()) {
        
			$stmt->store_result();
            if($stmt->num_rows==0){
            	return 0;
            }            
            $stmt->bind_result($filename_full, $filename_avatar,$filename_icon,$avatar_id);            
            $stmt->close();
			
            //Unlinking files
            if( file_exists($_SERVER['DOCUMENT_ROOT'].path_fulls.$filename_full)&& ($filename_full!=NULL) ){
				unlink($_SERVER['DOCUMENT_ROOT'].path_fulls.$filename_full);}            						
			if(file_exists($_SERVER['DOCUMENT_ROOT'].path_avatars.$filename_avatar)&& ($filename_full!=NULL) ){
				unlink($_SERVER['DOCUMENT_ROOT'].path_avatars.$filename_avatar);}    						
			if(file_exists($_SERVER['DOCUMENT_ROOT'].path_icons.$filename_icon) && ($filename_full!=NULL) ){
				unlink($_SERVER['DOCUMENT_ROOT'].path_icons.$filename_icon);}    		
				
			//Update avatar column on users table
			$new_avatar_id = $this->conn->insert_id;
			$stmt = $this->conn->prepare("UPDATE `groups` SET `avatar` = NULL WHERE `id` = ? ");
			$stmt->bind_param("i", $group_id);
			$stmt->execute();
			$stmt->close();
			
			//Delete row in avatars table
			$stmt = $this->conn->prepare("DELETE FROM avatars WHERE id = ? ");
			$stmt->bind_param("i", $avatar_id);			
			$stmt->execute();
			$count=$stmt->affected_rows;
			$stmt->close();
			
        } else {
            throw new Exception("BD can't execute: $stmt->execute()==NULL ");
        }
		
        return $count;
    }
 

//------------------`groups`----------------------	
	
	/**
     * Create new group 
	 * @param Integer $name Name of group
     * @param Integer $userid Creater
	 * @return Integer Id of new group
     */
    public function createGroup($userid) {
        
			$name="";
			$date_string=date('Y-m-d H:i:s',time());
			
            //Insert to 'groups' table
            $stmt = $this->conn->prepare("INSERT INTO groups(name,created_at) values(?,?)");
            $stmt->bind_param("ss", $name, $date_string); 
            $result = $stmt->execute(); 
			$new_groupid = $this->conn->insert_id;
            $stmt->close();
			
			//Insert to 'group_users' table as creater(status=1)
            $this->addUserToGroup($new_groupid,$userid,1);
             
            if ($result) {
                // Group successfully created
                return $new_groupid;
            } else {           
                return NULL;
            };
			
    }
	
	public function createCustomer($userid,$name,$address) {
        
			$date_string=date('Y-m-d H:i:s',time());
			
			$info_string='{"name":{"text":"'.$name.'"},"name_full":{"text":"'.$name.'"},"summary":{"text":""},"icon":{"image_url":""},"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]}';
			
            //Insert to 'groups' table
            $stmt = $this->conn->prepare("INSERT INTO groups(name,address,type,status,info,created_at) values(?,?,1,1,?,?)");
            $stmt->bind_param("ssss", $name, $address,$info_string,$date_string); 
            $result = $stmt->execute();
			$new_groupid = $this->conn->insert_id;
            $stmt->close();
			
			//Insert to 'group_users' table as creater(status=1)
            $this->addUserToGroup($new_groupid,$userid,1);
             
            if ($result) {
                // Group successfully created
                return $new_groupid;
            } else {           
                return NULL;
            };
			
    }
	
	/**
     * Get group by id
     * @param Integer $groupid
     */
    public function getGroupById($groupid) {
        
            $stmt = $this->conn->prepare("
				SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full  
				FROM groups g 
				LEFT OUTER JOIN avatars a ON g.avatar = a.id 
				WHERE ( g.id  = ? ) ");
            $stmt->bind_param("i", $groupid); 
             
			if($stmt->execute()){
			
				$stmt->bind_result($id,$name,$address,$phone,$status,$type,$info,$created_at,$changed_at,$icon,$avatar,$full);
				
				$result=array();
				
				if($stmt->fetch()){
					$res=array();
					
					$res["id"]=$id;
					$res["name"]=$name;
					$res["address"]=$address;
					$res["phone"]=$phone;
					$res["status"]=$status;
					$res["type"]=$type;
					$res["info"]=$info;
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"]=$timestamp_object->getTimestamp();
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();
					
					$avatars=array();
	            
					if($full) $avatars['full']=URL_HOME.path_fulls.$full;
					if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
					if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
					
					if(count($avatars))
						$res['avatars']=$avatars;
					
					$result[]=$res;
				}
				$stmt->close();			
				
				return $result;
			}else{
				return NULL;
			}
			
    }
	
	/**
     * Get all groups
     */
    public function getAllGroups() {
        
            $stmt = $this->conn->prepare("
				SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full  
				FROM groups g 
				LEFT OUTER JOIN avatars a ON g.avatar = a.id 
			");
             
			if($stmt->execute()){
			
				$stmt->bind_result($id,$name,$address,$phone,$status,$type,$info,$created_at,$changed_at,$icon,$avatar,$full);
				
				$result=array();
				
				while($stmt->fetch()){
					$res=array();
					
					$res["id"]=$id;
					$res["name"]=$name;
					$res["address"]=$address;
					$res["phone"]=$phone;
					$res["status"]=$status;
					$res["type"]=$type;
					$res["info"]=$info;
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"]=$timestamp_object->getTimestamp();
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();
					
					$avatars=array();
	            
					if($full) $avatars['full']=URL_HOME.path_fulls.$full;
					if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
					if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
					
					if(count($avatars))
						$res['avatars']=$avatars;
					
					$result[]=$res;
				}
				$stmt->close();			
				
				return $result;
			}else{
				return NULL;
			}
			
    }
	
	/**
     * Get groups of user
     */
    public function getGroupsOfUser($userid) {
        
            $stmt = $this->conn->prepare("
				SELECT 
					g.id AS id, g.name AS name, g.address AS address, g.phone AS phone, g.status AS status, u.status AS status_in_group, g.type AS type, g.created_at AS created_at, g.changed_at AS changed_at, a.filename_icon AS filename_icon, a.filename_avatar AS filename_avatar, a.filename_full AS filename_full 
				FROM 
					group_users AS u 
				INNER JOIN groups g ON u.groupid = g.id 
				LEFT OUTER JOIN avatars a ON g.avatar = a.id 
				WHERE ( ( u.userid = ? ) AND ((u.status=0)||(u.status=1)||(u.status=2)) ) 
				GROUP BY u.groupid 
				");
            
			$stmt->bind_param("i", $userid);
			
			if($stmt->execute()){
			
				$stmt->bind_result($id,$name,$address,$phone,$status,$status_in_group,$type,$created_at,$changed_at,$icon,$avatar,$full);
				
				$result=array();
				
				while($stmt->fetch()){
					$res=array();
					
					$res["id"]=$id;
					$res["name"]=$name;
					$res["address"]=$address;
					$res["phone"]=$phone;
					$res["status"]=$status;
					$res["status_in_group"]=$status_in_group;
					$res["type"]=$type;
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"]=$timestamp_object->getTimestamp();
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();
					
					$avatars=array();
	            
					if($full) $avatars['full']=URL_HOME.path_fulls.$full;
					if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
					if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
					
					if(count($avatars))
						$res['avatars']=$avatars;
					
					$result[]=$res;
				}
				$stmt->close();			
				
				return $result;
			}else{
				return NULL;
			}
			
    }
	
	/**
     * Add user to group 
     * @param Integer $userid
	 * @return Integer Id of new group
     */
    public function addUserToGroup($groupid,$userid,$status) {
        
		$stmt = $this->conn->prepare("UPDATE group_users SET status = $status WHERE ( ( groupid = $groupid ) AND ( userid = $userid ) )");        
        $result = $stmt->execute();
		$count=$stmt->affected_rows;
				
		if($count==0){
			//Insert to 'group_users' table
			$stmt = $this->conn->prepare("INSERT INTO group_users(groupid,userid,status) values( ? , ? , ? )");
			$stmt->bind_param("iii", $groupid,$userid,$status); 
			$stmt->execute(); 
			$stmt->close();
		}
            
    }
		
	/**
     * Get users in group
	 * @param Integer $groupid
	 * @return Array Users list 
     */
	public function getUsersInGroup($groupid) {
                    		
            $stmt = $this->conn->prepare("
			SELECT userid, status, changed_at FROM group_users WHERE  groupid = ? ");
			
            $stmt->bind_param("i", $groupid); 
             
			if($stmt->execute()){
			
				$stmt->bind_result($userid,$status_in_group,$changed_at);
				
				$result=array();
				
				while($stmt->fetch()){
					$res=array();
					
					$res["groupid"]=$groupid;
					$res["userid"]=$userid;
					$res["status_in_group"]=$status_in_group;
					
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

    /**
     * Get users in group Web
     * @param Integer $groupid
     * @return Array Users list 
     */
    public function getUsersInGroupWeb($groupid) {
        $stmt = $this->conn->prepare("SELECT u.id, u.name, u.email, u.phone FROM users u, group_users gu WHERE  u.id = gu.userid AND gu.groupid = ? AND gu.status <> 4");
        $stmt->bind_param("i", $groupid); 
        if($stmt->execute()) {
            $stmt->bind_result($id, $name, $email, $phone);
            $result=array();
            while($stmt->fetch()) {
                $res=array();
                $res["id"]=$id;
                $res["name"]=$name;
                $res["email"]=$email;
                $res["phone"]=$phone;
                $result[]=$res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }

    /**
     * Get contractor customers Web
     * @param Integer $contractorid
     * @return Array customers list
     */
    public function getContractorCustomers($contractorid) {
        $stmt = $this->conn->prepare("
		
		SELECT g.id,g.name,g.address,g.phone,g.status,g.type,g.info,g.created_at,g.changed_at, 
			sum(
				CASE ((record LIKE '%accepted%') AND (record LIKE '%installmentid%') AND (record NOT LIKE '%paid%') AND (record NOT LIKE '%canceled%') ) 
					WHEN 1 THEN CONVERT(substring(record, locate('\"totalCost\":',record)+12, locate(',', record, locate('\"totalCost\":',record))-locate('\"totalCost\":',record)-12),DECIMAL(10,2)) 
					ELSE 0.00 
				END 
			) AS debt, 
			sum(
				CASE ((record LIKE '%accepted%') AND (record LIKE '%installmentid%') AND (record NOT LIKE '%paid%') AND (record NOT LIKE '%canceled%') AND (record LIKE '%installment_time_notification%')) 
					WHEN 1 THEN 
						(
							CONVERT(substring(record, locate('\"totalCost\":',record)+12, locate(',', record, locate('\"totalCost\":',record))-locate('\"totalCost\":',record)-12),DECIMAL(10,2))*
							(
								(TO_DAYS(NOW())-TO_DAYS(o.created_at))>CONVERT(substring(record, locate('\"installment_time_notification\":',record)+32, locate(',', record, locate('\"installment_time_notification\":',record))-locate('\"installment_time_notification\":',record)-32),SIGNED)
							)
						)
					ELSE 0.00 
				END 
			) AS debt_expired 
		FROM 
		  orders o 
		INNER JOIN groups g ON g.id=o.customerid 
		WHERE o.contractorid = ? 
		GROUP BY g.id 
		
		");
			
        $stmt->bind_param("i", $contractorid);
        if($stmt->execute()) {
            $stmt->bind_result($id,$name,$address,$phone,$status,$type,$info,$created_at,$changed_at,$debt,$debt_expired);
            $result=array();
            while($stmt->fetch()){
                $res=array();
                $res["id"]=$id;
                $res["name"]=$name;
                $res["address"]=$address;
                $res["phone"]=$phone;
                $res["status"]=$status;
                $res["type"]=$type;
                $res["info"]=$info;
				$res["debt"]=$debt;
				$res["debt_expired"]=$debt_expired;
                $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
                $res["created_at"]=$timestamp_object->getTimestamp();
                $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
                $res["changed_at"] = $timestamp_object->getTimestamp();
                $result[]=$res;
            }
            $stmt->close();
            return $result;
        }
        else{
            return NULL;
        }
    }

    /**
     * Get deleted users in group Web
     * @param Integer $groupid
     * @return Array Users list 
     */
    public function getDeletedUsersInGroupWeb($groupid) {
        $stmt = $this->conn->prepare("SELECT u.id, u.name, u.email, u.phone FROM users u, group_users gu WHERE  u.id = gu.userid AND gu.groupid = ? AND gu.status == 4");
        $stmt->bind_param("i", $groupid); 
        if($stmt->execute()) {
            $stmt->bind_result($id, $name, $email, $phone);
            $result=array();
            while($stmt->fetch()) {
                $res=array();
                $res["id"]=$id;
                $res["name"]=$name;
                $res["email"]=$email;
                $res["phone"]=$phone;
                $result[]=$res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }

    /**
     * Update user name in shop
     * @param Integer $id of user, $value of new name
     * @return result 
     */
    public function updateShopUserName($id, $value) {
        $stmt = $this->conn->prepare("UPDATE users SET name=? WHERE id=?");
        $stmt->bind_param("si",$value,$id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Update user phone in shop
     * @param Integer $id of user, $value of new phone
     * @return result 
     */
    public function updateShopUserPhone($id, $value) {
        $stmt = $this->conn->prepare("UPDATE users SET phone=? WHERE id=?");
        $stmt->bind_param("si",$value,$id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
	/**
     * Update name of shop
     * @param Integer $id of shop, $value of shop name
     * @return result 
     */
    public function updateShopName($id, $value) {
        $stmt = $this->conn->prepare("UPDATE groups SET name=? WHERE id=?");
        $stmt->bind_param("si",$value,$id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
	/**
     * Update address of shop
     * @param Integer $id of shop, $value of shop address
     * @return result 
     */
    public function updateShopAddress($id, $value) {
        $stmt = $this->conn->prepare("UPDATE groups SET address=? WHERE id=?");
        $stmt->bind_param("si",$value,$id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
	/**
     * Update phone of shop
     * @param Integer $id of shop, $value of shop phone
     * @return result 
     */
    public function updateShopPhone($id, $value) {
        $stmt = $this->conn->prepare("UPDATE groups SET phone=? WHERE id=?");
        $stmt->bind_param("si",$value,$id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
		
	/**
     * Check is user in group
	 * @param Integer $groupid
     * @param Integer $userid
	 * @return boolean
     */
	public function isUserInGroup($groupid,$userid) {
                    		
            $stmt = $this->conn->prepare("SELECT r.id FROM group_users r WHERE ( r.groupid  = ? ) AND ( r.userid = ? ) AND ( (r.status=0) OR (r.status=1) OR (r.status=2) ) ");
            $stmt->bind_param("ii", $groupid,$userid);
            $stmt->execute();
            $stmt->store_result();
			$num_rows = $stmt->num_rows;
			$stmt->close();
			
			return $num_rows > 0;
    }
	
	/**
     * Get user status in group
	 * @param Integer $groupid
     * @param Integer $userid
	 * @return Integer Status 1-creater,2-admin,3-banned,0-common,'-1'-missing
     */
	public function getUserStatusInGroup($groupid,$userid) {
                    		
            $stmt = $this->conn->prepare("SELECT `status` FROM `group_users` WHERE ( `groupid`  = ? ) AND ( `userid` = ? ) ");
            $stmt->bind_param("ii", $groupid,$userid); 
            $result=$stmt->execute(); 
			
			$stmt->bind_result($status);
			
			if($stmt->fetch()){			
				$stmt->close();			
				return $status;
			}else{
				return 7;
			}
    }
	
	/**
     * Change user status in group
	 * @param Integer $groupid
     * @param Integer $userid
	 * @param Integer $status 1-creater,2-admin,3-banned,0-common,'-1'-missing
     */
	public function changeUserStatusInGroup($groupid,$userid,$status) {
        //Update status column on users table
		$stmt = $this->conn->prepare("UPDATE group_users SET status = $status WHERE ((groupid = $groupid) AND (userid = $userid))");
		$result = $stmt->execute();
		
		$count = $stmt->affected_rows;
		$stmt->close();
		
		return $count;
    }
	
	/**
     * Change group name
	 * @param String $name
	 * @param Integer $groupid	
     */
	public function changeGroupName($name,$groupid) {
                    		
        //Update status column on users table		
		$stmt = $this->conn->prepare("UPDATE `groups` SET `name` = ? WHERE ( `id` = ? )");
        $stmt->bind_param("si", $name,$groupid);
		$result = $stmt->execute();
		$stmt->close();
    }
	
	public function changeGroupAddress($address,$groupid) {
		$stmt = $this->conn->prepare("UPDATE `groups` SET `address` = ? WHERE ( `id` = ? )");
        $stmt->bind_param("si", $address,$groupid);
		$result = $stmt->execute();
		$stmt->close();
	}
	
	public function changeGroupPhone($phone,$groupid) {
		$stmt = $this->conn->prepare("UPDATE `groups` SET `phone` = ? WHERE ( `id` = ? )");
        $stmt->bind_param("si", $phone,$groupid);
		$result = $stmt->execute();
		$stmt->close();
	}
	
	public function changeGroupInfo($info,$groupid) {
		//Update status column on users table		
		$stmt = $this->conn->prepare("UPDATE `groups` SET `info` = ? WHERE ( `id` = ? )");
        $stmt->bind_param("si", $info,$groupid);
		$result = $stmt->execute();
		$stmt->close();
	}

//---------------------Fabricant----------------------------
	
	/**
	* Get all groups from web (adminpanel)
	*/
	public function getAllGroupsWeb($type) {
		$stmt = $this->conn->prepare("SELECT g.id, g.name, g.address, g.phone, g.status, g.info, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full FROM groups g LEFT OUTER JOIN avatars a ON g.avatar=a.id WHERE g.type=?");
		$stmt->bind_param("i", $type);
		if($stmt->execute()) {
			$stmt->bind_result($id,$name,$address,$phone,$status,$info,$created_at,$changed_at,$icon,$avatar,$full);
			$result=array();
			while($stmt->fetch()) {
				$res=array();
				$res["id"]=$id;
				$res["name"]=$name;
				$res["address"]=$address;
				$res["phone"]=$phone;
				$res["status"]=$status;
				$res["info"]=$info;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"]=$timestamp_object->getTimestamp();
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
				$result[]=$res;
			}
			$stmt->close();
			return $result;
		}
		else {
			return NULL;
		}
	}
	
	/**
	* Create new group from web (adminpanel)
	* @param $name string Name of new group
	* @param $status integer Status of group
	* @param $info json Information of new group
	* @return result
	*/
	public function createGroupWeb($name,$status,$type,$info) {
		$date_string=date('Y-m-d H:i:s',time());
		//Insert to 'groups' table
		$stmt = $this->conn->prepare("INSERT INTO groups(name,status,type,info,created_at) values(?,?,?,?,?)");
		$stmt->bind_param("siiss", $name,$status,$type,$info,$date_string);
		$stmt->execute();
		$result = $this->conn->insert_id;
		$stmt->close();
		return $result;
	}

	/**
	* Update group from web (adminpanel)
	* @param $id integer Id of updated group
	* @param $name string Name of group
	* @param $status integer Status of group
	* @param $info json Information of group
	* @return result
	*/
	public function updateGroupWeb($id,$name,$address,$phone,$status,$info) {
		//Update to 'groups' table
		$stmt = $this->conn->prepare("UPDATE `groups` SET `name`=?, `address`=?, `phone`=?, `status`=?, `info`=? WHERE `id`=?");
		$stmt->bind_param("sssisi", $name,$address,$phone,$status,$info,$id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	/**
	* Remove group from web (adminpanel)
	* @param $id integer Id of updated group
	* @return result
	*/
	public function removeGroupWeb($id) {
		//Update to 'groups' table
		$stmt = $this->conn->prepare("UPDATE `groups` SET `status`=4 WHERE `id`=?");
		$stmt->bind_param("i", $id);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

    /**
    * Create new group from web (temp solve)
    * @param $name string Name of new group
    * @param $status integer Status of group
    * @param $info json Information of new group
    * @return result
    */
    public function createShopWeb($name,$address,$phone,$status,$type,$info) {
        $date_string=date('Y-m-d H:i:s',time());
        //Insert to 'groups' table
        $stmt = $this->conn->prepare("INSERT INTO groups(name,address,phone,status,type,info,created_at) values(?,?,?,?,?,?,?)");
        $stmt->bind_param("sssiiss", $name,$address,$phone,$status,$type,$info,$date_string);
        $stmt->execute();
        $result = $this->conn->insert_id;
        $stmt->close();
        return $result;
    }

//-------------------------Fabricant Sales---------------------
	
	public function addSaleToContractorInfo($contractorid,$condition){
		$groups=$this->getGroupById($contractorid);
		$contractor=$groups[0];
		
		//Check if group is contractor
		if($contractor["type"]!=0)return;
		
		if(!isset($contractor["info"])){
			$info=array();
			$info["sales"]=array();
			$contractor["info"]=$info;
		}		
		
		$info=json_decode($contractor["info"],true);
		
		if(!isset($info["sales"])){
			
			$info["sales"]=array();
		}else{
			//Check if condition already in sales
			
			$sales=$info["sales"];			
			foreach($sales as $item){
				
				if(($item["type"]==$condition["type"])&&($item["id"]==$condition["id"])){
					return;
				}
			}
			
		}
		
		$info["sales"][]=$condition;
		$json_info=json_encode($info,JSON_UNESCAPED_UNICODE);
		$this->changeGroupInfo($json_info,$contractorid);
		
	}
	
	public function removeSaleFromContractorInfo($contractorid,$condition){
		$groups=$this->getGroupById($contractorid);
		$contractor=$groups[0];
		
		//Check if group is contractor
		if($contractor["type"]!=0)return;
		
		if (!isset($contractor["info"])){
			return;
		}else{
		
			$contractor["info"]=json_decode($contractor["info"],true);
		
			if(!isset($contractor["info"]["sales"]))return;
				
			$sales=$contractor["info"]["sales"];			
			for($i=0;$i<count($sales); $i++){
				$item=$sales[$i];
				if(($item["type"]==$condition["type"])&&($item["id"]==$condition["id"])){					
					
					//Remove
					unset($sales[$i]);	
					
					$contractor["info"]["sales"]=array_values($sales);					
					$this->changeGroupInfo(json_encode($contractor["info"],JSON_UNESCAPED_UNICODE),$contractorid);
					return;
				}
			}
			
		}
		
	}
	
	public function updateSaleInContractorInfo($contractorid,$condition){
		$groups=$this->getGroupById($contractorid);
		$contractor=$groups[0];
		
		//Check if group is contractor
		if($contractor["type"]!=0)return;
		
		if (!isset($contractor["info"])){
			return;
		}else{
		
			$contractor["info"]=json_decode($contractor["info"],true);
		
			if(!isset($contractor["info"]["sales"]))return;
				
			$sales=$contractor["info"]["sales"];			
			for($i=0;$i<count($sales); $i++){
				$item=$sales[$i];
				if(($item["type"]==$condition["type"])&&($item["id"]==$condition["id"])){					
					
					//Update
					$sales[$i]=$condition;		
					
					$contractor["info"]["sales"]=$sales;					
					$this->changeGroupInfo(json_encode($contractor["info"],JSON_UNESCAPED_UNICODE),$contractorid);
					return;
				}
			}
			
		}
		
	}
	
	//Get sale's condition from contractor like array-object
	public function getSaleFromContractorInfo($contractorid,$saleid){
		$groups=$this->getGroupById($contractorid);
		$contractor=$groups[0];
		
		//Check if group is contractor
		if($contractor["type"]!=0)return null;
		
		//Check if contractor has info
		if (!isset($contractor["info"])){
			return null;
		}else{
		
			$contractor["info"]=json_decode($contractor["info"],true);
		
			//Check if info has sales
			if(!isset($contractor["info"]["sales"]))return null;
				
			//Find sale's condition by id	
			$sales=$contractor["info"]["sales"];			
			for($i=0;$i<count($sales); $i++){
				$item=$sales[$i];
				if($item["id"]==$saleid){					
					return $item;	
				}
			}
			return null;
		}
		
	}
	
//--------------------------Tags----------------------------
	
	public function addTagToGroup($tag,$groupid){
		
		$groups=$this->getGroupById($groupid);
		
		
		if((!isset($groups))||(!isset($groups[0])))
			return;
		
		$group=$groups[0];
		
		if(!isset($group["info"])){
			$group["info"]=array();
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
		
		$this->changeGroupInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $groupid);
		
	}
	
	public function removeTagFromGroup($tag,$groupid){
		
		$groups=$this->getGroupById($groupid);
		
		
		if((!isset($groups))||(!isset($groups[0])))
			return;
		
		$group=$groups[0];
		
		if(!isset($group["info"]))
			return;
				
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
			$this->changeGroupInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $groupid);
		}
	}
	
	public function removeTagFromCustomers($tag){
		
		$customers=$this->getCustomersWithTag($tag);
		
		foreach($customers as $customer){
		
			if(!isset($customer["info"]))
				continue;
				
			$info=json_decode($customer["info"],true);
			
			if(!isset($info["tags"]))
				continue;
			
			$tags=$info["tags"];
			
			$tag_found=false;
			while(($key = array_search($tag, $tags)) !== false) {
				unset($tags[$key]);		
				
				$tags=array_values($tags);
				
				$tag_found=true;
			}
			
			$info["tags"]=$tags;
			
			if($tag_found){
				$this->changeGroupInfo(json_encode($info,JSON_UNESCAPED_UNICODE), $customer["id"]);
			}
		
		}
	}
	
	public function getCustomersWithTag($tag) {
		
		$stmt = $this->conn->prepare("SELECT g.id, g.name, g.address, g.phone, g.status, g.info, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full FROM groups g LEFT OUTER JOIN avatars a ON g.avatar=a.id WHERE ( ( g.type = 1 ) AND ( g.info LIKE '%$tag%' ) ) ");
		
		if($stmt->execute()) {
			$stmt->bind_result($id,$name,$address,$phone,$status,$info,$created_at,$changed_at,$icon,$avatar,$full);
			$result=array();
			while($stmt->fetch()) {
				$res=array();
				$res["id"]=$id;
				$res["name"]=$name;
				$res["address"]=$address;
				$res["phone"]=$phone;
				$res["status"]=$status;
				$res["info"]=$info;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
				$res["created_at"]=$timestamp_object->getTimestamp();
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
				$res["changed_at"] = $timestamp_object->getTimestamp();
				$result[]=$res;
			}
			$stmt->close();
			return $result;
		}
		else {
			return NULL;
		}
	}
	
	public function groupHasTag($tag,$groupid){
		
		$groups=$this->getGroupById($groupid);
		
		if((!isset($groups))||(!isset($groups[0])))
			return false;
		
		$group=$groups[0];
		
		if(!isset($group["info"]))
			return false;
				
		$info=json_decode($group["info"],true);
		
		if(!isset($info["tags"]))
			return false;
		
		$tags=$info["tags"];
		
		if(($key = array_search($tag, $tags)) !== false) {
			return true;
		}
		
	}
	
//----------------Friend Operations-----------------------
	
	const OPERATION_ADD=0;
	const OPERATION_CANCEL=1;
	const OPERATION_CONFIRM=2;
	const OPERATION_DECLINE=3;
	const OPERATION_BLOCK=4;
	const OPERATION_UNLOCK=5;
	const OPERATION_DELETE=6;
	
	const STATUS_DEFAULT=0;
	const STATUS_INVITE_OUTGOING=1;
	const STATUS_INVITE_INCOMING=2;
	const STATUS_FRIEND=3;
	const STATUS_BLOCK_OUTGOING=4;
	const STATUS_BLOCK_INCOMING=5;
	
	public function friendOperation($userid,$friendid,$operationid) {
                    		
		$status=$this->getFriendStatus($userid,$friendid);
		
		$status_user=self::STATUS_DEFAULT;
		$status_friend=self::STATUS_DEFAULT;
		
		switch( $status ){
			case self::STATUS_DEFAULT :				
				switch($operationid){
					case self::OPERATION_ADD :
						$status_user=self::STATUS_INVITE_OUTGOING;
						$status_friend=self::STATUS_INVITE_INCOMING;
					break;
				}
				break;
				
			case self::STATUS_INVITE_OUTGOING :				
				switch($operationid){
					case self::OPERATION_CANCEL :
						$status_user=self::STATUS_DEFAULT;
						$status_friend=self::STATUS_DEFAULT;
					break;
				}
				break;
				
			case self::STATUS_INVITE_INCOMING :				
				switch($operationid){
					case self::OPERATION_CONFIRM :
						$status_user=self::STATUS_FRIEND;
						$status_friend=self::STATUS_FRIEND;
					break;					
					case self::OPERATION_DECLINE :
						$status_user=self::STATUS_DEFAULT;
						$status_friend=self::STATUS_DEFAULT;
					break;
					case self::OPERATION_BLOCK :
						$status_user=self::STATUS_BLOCK_OUTGOING;
						$status_friend=self::STATUS_BLOCK_INCOMING;
					break;
				}
				break;
			
			case self::STATUS_FRIEND :				
				switch($operationid){
					case self::OPERATION_DELETE :
						$status_user=self::STATUS_DEFAULT;
						$status_friend=self::STATUS_DEFAULT;
					break;
					case self::OPERATION_BLOCK :
						$status_user=self::STATUS_BLOCK_OUTGOING;
						$status_friend=self::STATUS_BLOCK_INCOMING;
					break;					
				}
				break;
				
			case self::STATUS_BLOCK_OUTGOING :				
				switch($operationid){
					case self::OPERATION_UNLOCK :
						$status_user=self::STATUS_DEFAULT;
						$status_friend=self::STATUS_DEFAULT;
					break;								
				}
				break;
			case self::STATUS_BLOCK_INCOMING :				
				switch($operationid){
												
				}
				break;
			
		}

		if($status!=$status_user){
			$this->updateFriendStatus($userid,$friendid,$status_user);
			$this->updateFriendStatus($friendid,$userid,$status_friend);
			
			$result=array();
			$result["status_user"]=$status_user;
			$result["status_friend"]=$status_friend;
			$result["userid"]=$userid;
			$result["friendid"]=$friendid;	

			return $result;
		}
		
		return NULL;
    }
	
	/*
	*Sets status of one row.
	*return num of affected rows
	*WARNING! Use only in pair, to prevent one-directed friend relation
	*/
	private function updateFriendStatus($userid,$friendid,$status){
		
		//Delete from DB if default status
				
		$stmt = $this->conn->prepare("DELETE FROM friends WHERE ( `userid` = ? ) AND ( `friendid` = ? ) ");
		$stmt->bind_param("ii", $userid,$friendid);			
		$stmt->execute();
		$count=$stmt->affected_rows;
		$stmt->close();
					
		//if($status!=self::STATUS_DEFAULT){
			//Update status in friends-table		
			$stmt = $this->conn->prepare("INSERT INTO friends(userid,friendid,status) VALUES ( ? , ? , ? ) ");
			$stmt->bind_param("iii", $userid,$friendid,$status);
			$result = $stmt->execute();		
			$stmt->close();			
		//}
		
		return $count;
	}
	
	public function getFriendStatus($userid,$friendid) {
                   
		if($userid==$friendid)return self::STATUS_FRIEND;
		
        $stmt = $this->conn->prepare("SELECT f.status FROM friends f WHERE ( f.userid  = ? ) AND ( f.friendid = ? ) ");
        $stmt->bind_param("ii", $userid,$friendid); 
        $result=$stmt->execute(); 
			
		$stmt->bind_result($status);
			
		if($stmt->fetch()){			
			$stmt->close();		
			return $status;
		}else{
			return self::STATUS_DEFAULT;
		}
    }
     
	/**
     * Get all friends of user
	 * param - userid
     * returns public columns of 'users' table
     */
    public function getAllFriends($userid) {
        $stmt = $this->conn->prepare("
			SELECT u.id, u.name, u.status, u.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
			FROM 
			(
				SELECT f.friendid AS id, s.name AS name, f.status AS status, s.changed_at AS changed_at, s.avatar AS avatar 
				FROM friends f 
				LEFT OUTER JOIN users s ON f.friendid = s.id 
				WHERE ( f.userid = ? ) AND ( ( f.status = ".self::STATUS_INVITE_OUTGOING." ) OR ( f.status = ".self::STATUS_INVITE_INCOMING." ) OR ( f.status = ".self::STATUS_FRIEND." ) ) 
			) u 
			LEFT OUTER JOIN avatars a ON u.avatar = a.id ");
			
        $stmt->bind_param("i", $userid);
        
		$users=array();
		
		if ($stmt->execute()) {
        
        
            $stmt->bind_result($id,$name, $status, $changed_at, $icon, $avatar,$full);            
            
            while($stmt->fetch()){
            	    
            	$res= array();
	            $res["id"] = $id;
	            $res["name"] = $name;
	            $res["status"] = $status;
	            $timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
	            $res["changed_at"] = $timestamp_object->getTimestamp();	
	            
	            $avatars=array();
	            
	            if($full) $avatars['full']=URL_HOME.path_fulls.$full;
	            if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
	            if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
	            
	            if(count($avatars) )
					$res['avatars']=$avatars;          
	            
	            $users[]=$res;
			}            
            $stmt->close();
			
        }
		
        return $users;        
    }
	
	public function getFriendById($userid,$friendid){
		$result=$this->getUserById($friendid);
		if($result!=NULL){
			$result["status"]=$this->getFriendStatus($userid,$friendid);
		}
		return $result;
	}
	
//------------------Delta------------------------------------
	
	/**
     * Get delta of friend and user tables since timestamp
	 * param - userid
	 * param - timestamp
     * returns public columns of 'users' table
     */
    public function getUsersDelta($userid,$timestamp) {
        $stmt = $this->conn->prepare("
			SELECT u.id, u.name, u.status, IF( u.user_changed_at > u.friend_changed_at, u.user_changed_at, u.friend_changed_at ) AS changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
			FROM 
			(
				SELECT f.friendid AS id, s.name AS name, f.status AS status, s.changed_at AS user_changed_at, f.changed_at AS friend_changed_at, s.avatar AS avatar 
				FROM friends f 
				LEFT OUTER JOIN users s ON f.friendid = s.id 
				WHERE ( f.userid = ? )
			) u 
			LEFT OUTER JOIN avatars a ON u.avatar = a.id 
			WHERE ( ( u.user_changed_at > ? ) OR (u.friend_changed_at > ?) ) ");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		
        $stmt->bind_param( "iss", $userid,$date_string,$date_string );
        
		$users=array();
		
		if ($stmt->execute()) {
        
        
            $stmt->bind_result($id,$name, $status, $changed_at, $icon, $avatar,$full);            
            
            while($stmt->fetch()){
            	    
            	$res= array();
	            $res["id"] = $id;
	            $res["name"] = $name;
	            $res["status"] = $status;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
	            $res["changed_at"] = $timestamp_object->getTimestamp();	
	            
	            $avatars=array();
	            
	            if($full) $avatars['full']=URL_HOME.path_fulls.$full;
	            if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
	            if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
	            
	            if(count($avatars) )
					$res['avatars']=$avatars;          
	            
	            $users[]=$res;
			}            
            $stmt->close();
			
			//Requested user
			$user=$this->getUserById($userid);
			if($user["changed_at"]>$timestamp){
				$users[]=$user;
			}
        }
		
		
		
        return $users;        
    }
		
	
    public function getCustomersDelta($userid,$timestamp) {
        
            $stmt = $this->conn->prepare("
				SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full  
				FROM group_users u 
				LEFT OUTER JOIN groups g ON u.groupid = g.id 
				LEFT OUTER JOIN avatars a ON g.avatar = a.id  
				WHERE ( ( u.userid = ? ) AND ( g.type = 1 ) AND ( g.changed_at > ? ) ) 
			");
			$date_string=date('Y-m-d H:i:s',$timestamp);
			$stmt->bind_param( "is", $userid, $date_string );
             
			if($stmt->execute()){
			
				$stmt->bind_result($id,$name,$address,$phone,$status,$type,$info,$created_at,$changed_at,$icon,$avatar,$full);
				
				$result=array();
				
				while($stmt->fetch()){
					$res=array();
					
					$res["id"]=$id;
					$res["name"]=$name;
					$res["address"]=$address;
					$res["phone"]=$phone;
					$res["status"]=$status;
					$res["type"]=$type;
					$res["info"]=$info;
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"]=$timestamp_object->getTimestamp();
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();
					
					$avatars=array();
	            
					if($full) $avatars['full']=URL_HOME.path_fulls.$full;
					if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
					if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
					
					if(count($avatars))
						$res['avatars']=$avatars;
					
					$result[]=$res;
				}
				$stmt->close();			
				
				return $result;
			}else{
				return NULL;
			}
			
    }
	
	public function getContractorsDelta($timestamp) {
        
            $stmt = $this->conn->prepare("
				SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
				FROM groups g 
				LEFT OUTER JOIN avatars a ON g.avatar = a.id 
				WHERE ( g.type = 0 ) AND ( g.changed_at > ? ) 
			");
			$date_string=date('Y-m-d H:i:s',$timestamp);
			$stmt->bind_param( "s", $date_string );
             
			if($stmt->execute()){
			
				$stmt->bind_result($id,$name,$address,$phone,$status,$type,$info,$created_at,$changed_at,$icon,$avatar,$full);
				
				$result=array();
				
				while($stmt->fetch()){
					$res=array();
					
					$res["id"]=$id;
					$res["name"]=$name;
					$res["address"]=$address;
					$res["phone"]=$phone;
					$res["status"]=$status;
					$res["type"]=$type;
					$res["info"]=$info;
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $created_at);
					$res["created_at"]=$timestamp_object->getTimestamp();
					
					$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
					$res["changed_at"] = $timestamp_object->getTimestamp();
					
					$avatars=array();
	            
					if($full) $avatars['full']=URL_HOME.path_fulls.$full;
					if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
					if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
					
					if(count($avatars))
						$res['avatars']=$avatars;
					
					$result[]=$res;
				}
				$stmt->close();			
				
				return $result;
			}else{
				return NULL;
			}
			
    }
	
		
	public function getGroupUsersDelta($userid,$timestamp) {
        
			
		$stmt = $this->conn->prepare("
			SELECT u.groupid, u.userid, u.status as status_in_group, u.changed_at
			FROM 
			(
				SELECT groupid
				FROM group_users
				WHERE ( ( userid = ? ) )
			) g
			CROSS JOIN group_users u
			WHERE ( g.groupid = u.groupid ) AND ( u.changed_at > ? )
		");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		$stmt->bind_param( "is", $userid, $date_string );
		 
		if($stmt->execute()){
		
			$stmt->bind_result($groupid,$userid,$status_in_group,$changed_at);
			
			$result=array();
			
			while($stmt->fetch()){
				$res=array();
				
				$res["groupid"]=$groupid;
				$res["userid"]=$userid;
				$res["status_in_group"]=$status_in_group;
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
	
	public function getGroupmatesDelta($userid,$timestamp) {
        
			
		$stmt = $this->conn->prepare("
			SELECT u.id, u.name, u.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
			FROM 
			(
				SELECT groupid
				FROM group_users
				WHERE ( ( userid = ? ) )
			) g
			CROSS JOIN group_users gu 
			LEFT OUTER JOIN users u ON u.id = gu.userid 
			LEFT OUTER JOIN avatars a ON u.avatar = a.id 
			WHERE ( ( g.groupid = gu.groupid ) AND (u.changed_at > ?) ) 
			GROUP BY gu.userid				
		");
		
		$date_string=date('Y-m-d H:i:s',$timestamp);
		$stmt->bind_param( "is", $userid, $date_string );
		 
		$users=array();
		
		if ($stmt->execute()) {        
        
            $stmt->bind_result($id,$name, $changed_at, $icon, $avatar,$full);            
            
            while($stmt->fetch()){
            	    
            	$res= array();
	            $res["id"] = $id;
	            $res["name"] = $name;
				$timestamp_object = DateTime::createFromFormat('Y-m-d H:i:s', $changed_at);
	            $res["changed_at"] = $timestamp_object->getTimestamp();	
	            
	            $avatars=array();
	            
	            if($full) $avatars['full']=URL_HOME.path_fulls.$full;
	            if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
	            if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
	            
	            if(count($avatars) )
					$res['avatars']=$avatars;
	            
	            $users[]=$res;
			}            
            $stmt->close();
			
        }
		
        return $users;  			
    }

}
 
?>