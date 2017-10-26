<?php
 
/**
 * Class to handle db operations of Fabricant CRM project
 *
 * @author Stepan Sotnikov
 */
 
require_once dirname(__FILE__).'/DbHandler.php';
 
class DbHandlerCRM extends DbHandler {

    const EVENT_STATUS_CREATE =1;
    const EVENT_STATUS_ACCEPT = 2;
    const EVENT_STATUS_REMOVE = 4;

    const EVENT_OPERATION_CREATE = 1;
    const EVENT_OPERATION_UPDATE = 2;
    const EVENT_OPERATION_DELETE = 3;
    const EVENT_OPERATION_RESTORE = 9;

    const ERROR_EVENT_OPERATION = "Can't create log of Event operation.";
    const ERROR_EVENT_GET_RESULT = "Can't get Event by id.";
    
    function __construct() {
        parent::__construct();
    }

    /*------------- `groups` ------------------ */
    /**
     * Fetching groups events
     * returns public columns of 'groups' table
     * @param String $range_start start of events range
     * @param String $range_end end of events range
     */
    public function getGroupsEvents($range_start, $range_end) {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.phone, g.address, g.info, g.status, g.type, g.created_at, g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full, 
                e.id as e_id, e.group_id, e.notice_date, e.status as e_status, e.priority, e.message, e.changed_at as e_changed_at 
            FROM groups g 
            LEFT JOIN avatars a 
                ON g.avatar = a.id 
            LEFT JOIN crm_events e 
                ON g.id = e.group_id AND e.status <> 4 AND e.notice_date 
                    BETWEEN STR_TO_DATE('$range_start', '%Y-%m-%d %H:%i:%s') 
                        AND STR_TO_DATE('$range_end', '%Y-%m-%d %H:%i:%s') 
            WHERE g.status <> 4 
            ORDER BY g.id
        ");

        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $info, $status, $type, $created_at, $changed_at, $icon, $avatar, $full, $e_id, $group_id, $e_notice_date, $e_status, $e_priority, $e_message, $e_changed_at);

            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["created"] = $created_at;
                $res["changed"] = $changed_at;
                
                $avatars = array();
                if($full) $avatars['full']=URL_HOME.path_fulls.$full;
                if($avatar) $avatars['avatar']=URL_HOME.path_avatars.$avatar;
                if($icon) $avatars['icon']=URL_HOME.path_icons.$icon;
                if(count($avatars)) {
                    $res['avatars']=$avatars;
                }

                if (isset($e_id)) {
                    $res['events'] = array(
                        "id" => $e_id,
                        "groupid" => $group_id,
                        "noticeDate" => $e_notice_date,
                        "status" => $e_status,
                        "priority" => $e_priority,
                        "message" => $e_message,
                        "changed" => $e_changed_at
                    );
                }
                $result[] = $res;
            }
            $stmt->close();
            
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get group by id for crm
     * @param int $id id of group
     */
    public function getGroupById($id) {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT OUTER JOIN avatars a ON g.avatar = a.id 
            WHERE (g.id = ?) 
        ");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 0) return NULL;
            
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            
            $result = array();
            if ($stmt->fetch()) {
                $result["id"] = $id;
                $result["name"] = $name;
                $result["phone"] = $phone;
                $result["address"] = $address;
                $result["status"] = $status;
                $result["type"] = $type;
                $result["info"] = $info;
                $result["created"] = $created_at;
                $result["changed"] = $changed_at;
                
                $avatars = array();
                if ($full) $avatars['full'] = URL_HOME.path_fulls.$full;
                if ($avatar) $avatars['avatar'] = URL_HOME.path_avatars.$avatar;
                if ($icon) $avatars['icon'] = URL_HOME.path_icons.$icon;
                if (count($avatars)) {
                    $result['avatars'] = $avatars;
                }
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get all groups for crm
     */
    public function getGroups() {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
        ");
        
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = $info;
                $res["created"] = $created_at;
                $res["changed"] = $changed_at;
                
                $avatars = array();
                if ($full) $avatars['full'] = URL_HOME.path_fulls.$full;
                if ($avatar) $avatars['avatar'] = URL_HOME.path_avatars.$avatar;
                if ($icon) $avatars['icon'] = URL_HOME.path_icons.$icon;
                if (count($avatars)) {
                    $res['avatars'] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get contractors
     */
    public function getContractors() {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
            WHERE g.type = 0 AND g.status <> 4
        ");
        
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = $info;
                $res["created"] = $created_at;
                $res["changed"] = $changed_at;
                
                $avatars = array();
                if ($full) $avatars['full'] = URL_HOME.path_fulls.$full;
                if ($avatar) $avatars['avatar'] = URL_HOME.path_avatars.$avatar;
                if ($icon) $avatars['icon'] = URL_HOME.path_icons.$icon;
                if (count($avatars)) {
                    $res['avatars'] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get customers
     */
    public function getCustomers() {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.address, g.phone, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
            WHERE g.type = 1 AND g.status <> 4
        ");
        
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = $info;
                $res["created"] = $created_at;
                $res["changed"] = $changed_at;
                
                $avatars = array();
                if ($full) $avatars['full'] = URL_HOME.path_fulls.$full;
                if ($avatar) $avatars['avatar'] = URL_HOME.path_avatars.$avatar;
                if ($icon) $avatars['icon'] = URL_HOME.path_icons.$icon;
                if (count($avatars)) {
                    $res['avatars'] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }

    /*------------- `events` ------------------ */
    /**
     * Creating new event
     * @param int $group_id id of group to creating event
     * @param Timestamp $notice_date notification date
     * @param String $message notification message
     * @param Int $priority priority of event
     * @param int $user_id id of revelant user to event operation
     */
    public function createEvent($group_id, $notice_date, $message, $priority, $user_id) {
        $status = self::EVENT_STATUS_CREATE;
        $stmt = $this->conn->prepare("INSERT INTO crm_events(group_id, notice_date, message, status, priority) values(?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $group_id, $notice_date, $message, $status, $priority);

        if ($stmt->execute()) {
            $event_id = $this->conn->insert_id;
            $stmt->close();
            $op = self::EVENT_OPERATION_CREATE;
            $result = $this->logEventOperation($op, $user_id, $event_id);
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Updating event
     * @param int $event_id id of event
     * @param Timestamp $notice_date notification date
     * @param String $message notification message
     * @param Int $priority priority of event
     * @param int $user_id id of revelant user to event operation
     */
    public function updateEvent($event_id, $notice_date, $message, $priority, $user_id) {
        // update query
        $stmt = $this->conn->prepare("UPDATE `crm_events` SET `notice_date` = ?, `message` = ?, `priority` = ? WHERE `id` = ?");
        $stmt->bind_param("sssi", $notice_date, $message, $priority, $event_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $op = self::EVENT_OPERATION_UPDATE;
            $result = $this->logEventOperation($op, $user_id, $event_id);
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Removing event
     * @param int $event_id id of event
     * @param int $user_id id of revelant user to event operation
     */
    public function removeEvent($event_id, $user_id) {
        $status = self::EVENT_STATUS_REMOVE;
        // update query
        $stmt = $this->conn->prepare("UPDATE `crm_events` SET `status` = ? WHERE `id` = ?");
        $stmt->bind_param("ii", $status, $event_id);
        if ($stmt->execute()) {
            $stmt->close();
            $op = self::EVENT_OPERATION_UPDATE;
            $result = $this->logEventOperation($op, $user_id, $event_id);
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Accepting event
     * @param int $id id of event
     * @param int $user_id id of revelant user to event operation
     */
    public function acceptEvent($event_id, $user_id) {
        $status = self::EVENT_STATUS_ACCEPT;
        // update query
        $stmt = $this->conn->prepare("UPDATE `crm_events` SET `status` = ? WHERE `id` = ?");
        $stmt->bind_param("ii", $status, $event_id);
        if ($stmt->execute()) {
            $stmt->close();
            $op = self::EVENT_OPERATION_UPDATE;
            $result = $this->logEventOperation($op, $user_id, $event_id);
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get event
     * @param int $id id of event
     */
    public function getEventById($id) {
        // update query
        $stmt = $this->conn->prepare("
            SELECT id, group_id, notice_date, message, status, priority, changed_at 
            FROM crm_events WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 0) return NULL;

            $stmt->bind_result($id, $group_id, $notice_date, $message, $status, $priority, $changed_at); 

            $result= array();
            if ($stmt->fetch()) {
                $result["id"] = $id;
                $result["groupid"] = $group_id;
                $result["noticeDate"] = $notice_date;
                $result["message"] = $message;
                $result["status"] = $status;
                $result["priority"] = $priority;
                $result["changed"] = $changed_at;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get event log
     * @param int $id id of event
     */
    public function getEventLog($id) {
        // update query
        $stmt = $this->conn->prepare("
            SELECT id, operation, user_id, event_id, notice_date, message, status, priority, created_at 
            FROM crm_events_operations WHERE event_id = ? 
            ORDER BY created_at
        ");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $operation, $user_id, $event_id, $notice_date, $message, $status, $priority, $created_at); 

            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["operation"] = $operation;
                $res["userid"] = $user_id;
                $res["eventid"] = $event_id;
                $res["noticeDate"] = $notice_date;
                $res["message"] = $message;
                $res["status"] = $status;
                $res["priority"] = $priority;
                $res["created"] = $created_at;
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get events
     * @param String $range_start start of events range
     * @param String $range_end end of events range
     */
    public function getEvents($range_start, $range_end) {
        $stmt = $this->conn->prepare("
            SELECT id, group_id, notice_date, message, status, priority, changed_at 
                FROM crm_events 
                WHERE notice_date 
                    BETWEEN STR_TO_DATE('$range_start', '%Y-%m-%d %H:%i:%s') 
                        AND STR_TO_DATE('$range_end', '%Y-%m-%d %H:%i:%s') 
                    AND status <> 4 
                    ORDER BY id
        ");

        if ($stmt->execute()) {
            $stmt->bind_result($id, $group_id, $notice_date, $message, $status, $priority, $changed_at);

            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["groupid"] = $group_id;
                $res["noticeDate"] = $notice_date;
                $res["message"] = $message;
                $res["status"] = $status;
                $res["priority"] = $priority;
                $res["changed"] = $changed_at;
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Get group events
     * @param String $range_start start of events range
     * @param String $range_end end of events range
     */
    public function getGroupEvents($range_start, $range_end, $groupid) {
        $stmt = $this->conn->prepare("
            SELECT id, group_id, notice_date, message, status, priority, changed_at 
                FROM crm_events 
                WHERE notice_date 
                    BETWEEN STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s') 
                        AND STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s') 
                    AND status <> 4 
                    AND group_id = ? 
                    ORDER BY id
        ");

        $stmt->bind_param("ssi", $range_start, $range_end, $groupid);

        if ($stmt->execute()) {
            $stmt->bind_result($id, $group_id, $notice_date, $message, $status, $priority, $changed_at);

            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["groupid"] = $group_id;
                $res["noticeDate"] = $notice_date;
                $res["message"] = $message;
                $res["status"] = $status;
                $res["priority"] = $priority;
                $res["changed"] = $changed_at;
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Restore event operatation from log
     * @param int $event_id id of event
     * @param int $user_id id of revelant user to event operation
     */
    public function restoreEvent($event_id, $user_id) {
        $log = $this->getEventLog($event_id);

        $restore = array();
        if (count($log) < 1) {
            return NULL;
        } else if (count($log) == 1 && $log[0]["operation"] == self::EVENT_OPERATION_CREATE) {
            $restore["noticeDate"] = $log[0]["noticeDate"];
            $restore["message"] = $log[0]["message"];
            $restore["status"] = self::EVENT_STATUS_REMOVE;
            $restore["priority"] = $log[0]["priority"];
        } else {
            $index = count($log) - 2;
            $restore["noticeDate"] = $log[$index]["noticeDate"];
            $restore["message"] = $log[$index]["message"];
            $restore["status"] = $log[$index]["status"];
            $restore["priority"] = $log[$index]["priority"];
        }

        $stmt = $this->conn->prepare("
            UPDATE `crm_events` SET `notice_date` = ?, `message` = ?, `status` = ?, `priority` = ? WHERE `id` = ?
        ");
        $stmt->bind_param("ssisi", $restore["noticeDate"], $restore["message"], $restore["status"], $restore["priority"], $event_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $op = self::EVENT_OPERATION_RESTORE;
            $result = $this->logEventOperation($op, $user_id, $event_id);
            return $result;
        } else {
            return NULL;
        }
    }
    /**
     * Logging event operatations
     * @param int $operation kind of operation
     * @param int $user_id id of revelant user to event operation
     * @param int $event_id id of event
     */
    function logEventOperation($operation, $user_id, $event_id) {
        $event = $this->getEventById($event_id);

        $stmt = $this->conn->prepare("
            INSERT INTO crm_events_operations(operation, user_id, event_id, notice_date, message, status, priority, created_at) 
                values(?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiississ", $operation, $user_id, $event_id, $event["noticeDate"], $event["message"], $event["status"], $event["priority"], $event["changed"]);

        if ($stmt->execute()) {
            $stmt->close();
            return $event;
        } else {
            return NULL;
        }
    }

    //---------------------Fabricant----------------------------

    public function setCustomerCodeInContractor($customerid, $customercode, $contractorid) {
        //Update status column on customer_code_in_contractor table
        $stmt = $this->conn->prepare("UPDATE `customer_code_in_contractor` SET `customercode` = ? WHERE ((`customerid` = ?) AND (`contractorid` = ?))");
        $stmt->bind_param("sii", $customercode, $customerid, $contractorid);
        $result = $stmt->execute();
        $count = $stmt->affected_rows;
        $stmt->close();

        if ($result) {
            if ($count == 0) {
                $today = date("Y-m-d H:i:s");
                $stmt = $this->conn->prepare("INSERT INTO customer_code_in_contractor (customerid, customercode, contractorid, changed_at) values( ? , ? , ? , ?)");
                $stmt->bind_param("isis", $customerid, $customercode, $contractorid, $today);
                
                $result = $stmt->execute();
                $stmt->close();
                if ($result) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                return TRUE;
            }
        } else {
            return FALSE;
        }

    }
}
 
?>