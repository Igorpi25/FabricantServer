<?php
namespace CRM\Service;
require_once dirname(__FILE__).'/../../include/DbHandler.php';

class EventService extends \DbHandler {
    const EVENT_STATUS_CREATE = 1;
    const EVENT_STATUS_ACCEPT = 2;
    const EVENT_STATUS_REMOVE = 4;

    const EVENT_OPERATION_CREATE = 1;
    const EVENT_OPERATION_UPDATE = 2;
    const EVENT_OPERATION_DELETE = 3;
    const EVENT_OPERATION_RESTORE = 9;

    public function __construct() {
        parent::__construct();
    }
    /**
     * Создания нового события
     * @param int $group_id ид группы для которого создается событие
     * @param timestamp $notice_date дата оповещения
     * @param string $message текст события
     * @param string $priority приоритет события, вида 'grey'
     * @param int $user_id ид пользователя совершающего операцию
     */
    public function newEvent($group_id, $notice_date, $message, $priority, $user_id) {
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
     * Обновление события
     * @param int $event_id ид события
     * @param timestamp $notice_date дата оповещения
     * @param string $message текст события
     * @param int $priority приоритет события, вида 'grey'
     * @param int $user_id ид пользователя совершающего операцию
     */
    public function saveEvent($event_id, $notice_date, $message, $priority, $user_id) {
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
     * Удаление события
     * @param int $event_id ид сыбытия
     * @param int $user_id ид пользователя совершающего операцию
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
     * Отметить выполнение события
     * @param int $id ид события
     * @param int $user_id ид пользователя совершающего операцию
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
     * Событие по ид
     * @param int $id ид события
     */
    public function fetchEventById($id) {
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
     * Лог события
     * @param int $id ид события
     */
    public function fetchEventLog($id) {
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
     * Все события за период
     * @param string $range_start начало периода
     * @param string $range_end конец периода
     */
    public function fetchEvents($range_start, $range_end) {
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
     * События группы за период
     * @param String $range_start начало периода
     * @param String $range_end конец периода
     * @param string $groupid ид группы
     */
    public function fetchGroupEvents($range_start, $range_end, $groupid) {
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
     * Отменить последние изменение события
     * @param int $event_id ид события
     * @param int $user_id ид пользователя совершающего операцию
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
     * Создание лога события
     * @param int $operation тип операции
     * @param int $user_id ид пользователя совершающего операцию
     * @param int $event_id ид события
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
}
?>