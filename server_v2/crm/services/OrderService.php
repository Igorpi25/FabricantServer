<?php
namespace CRM\Service;

require_once dirname(__FILE__) . '/../../include/DbHandler.php';

class OrderService extends \DbHandler
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Все заказы для CRM
     * @param string $sort_by название колонки сортировки
     * @param string $order направление сортировки ASC или DESC
     * @param int $rows_per_page количество строк в странице
     * @param int $offset смещение, номер страницы умноженная на $rows_per_page
     * @param string $query строка запроса фильтрации
     * @return mixed
     */
    public function fetchAllOrders($sort_by, $order, $rows_per_page, $offset, $query)
    {
        $stmt = $this->conn->prepare("
            SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
            FROM orders o 
            WHERE 
                ( CONCAT_WS('|', o.id, o.contractorid, o.customerid, o.status, o.code1c, o.created_at, o.changed_at) REGEXP ? ) 
            ORDER BY $sort_by $order 
            LIMIT ? OFFSET ? 
        ;");
        $stmt->bind_param("sii", $query, $rows_per_page, $offset);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $contractorid, $customerid, $status, $record, $code1c, $created_at, $changed_at);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["contractorid"] = $contractorid;
                $res["customerid"] = $customerid;
                $res["status"] = $status;
                $res["record"] = json_decode($record, TRUE);
                $res["code1c"] = $code1c;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $result[] = $res;
            }
            $stmt->close();
            $ordersCount = $this->ordersCount();
            return ["total" => $ordersCount, "items" => $result];
        }
        else {
            return NULL;
        }
    }
    /**
     * Все заказы поставщика для CRM
     * @param int $groupid ид группы
     * @param string $sort_by название колонки сортировки
     * @param string $order направление сортировки ASC или DESC
     * @param int $rows_per_page количество строк в странице
     * @param int $offset смещение, номер страницы умноженная на $rows_per_page
     * @param string $query строка запроса фильтрации
     */
    public function fetchOrdersOfContrator($groupid, $sort_by, $order, $rows_per_page, $offset, $query)
    {
        $stmt = $this->conn->prepare("
            SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
            FROM orders o 
            WHERE ( o.contractorid = ? ) AND 
                ( CONCAT_WS('|', o.id, o.contractorid, o.customerid, o.status, o.code1c, o.created_at, o.changed_at) REGEXP ? ) 
            ORDER BY $sort_by $order 
            LIMIT ? OFFSET ? 
        ;");
        $stmt->bind_param("isii", $groupid, $query, $rows_per_page, $offset);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $contractorid, $customerid, $status, $record, $code1c, $created_at, $changed_at);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["contractorid"] = $contractorid;
                $res["customerid"] = $customerid;
                $res["status"] = $status;
                $res["record"] = json_decode($record, TRUE);
                $res["code1c"] = $code1c;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $result[] = $res;
            }
            $stmt->close();
            $ordersCount = $this->ordersCountOfContractor($groupid);
            return ["total" => $ordersCount, "items" => $result];
        }
        else {
            return NULL;
        }
    }
    /**
     * Все заказы заказчика для CRM
     * @param int $groupid ид группы
     * @param string $sort_by название колонки сортировки
     * @param string $order направление сортировки ASC или DESC
     * @param int $rows_per_page количество строк в странице
     * @param int $offset смещение, номер страницы умноженная на $rows_per_page
     * @param string $query строка запроса фильтрации
     */
    public function fetchOrdersOfCustomer($groupid, $sort_by, $order, $rows_per_page, $offset, $query)
    {
        $stmt = $this->conn->prepare("
            SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
            FROM orders o 
            WHERE ( o.customerid = ? ) AND 
                ( CONCAT_WS('|', o.id, o.contractorid, o.customerid, o.status, o.code1c, o.created_at, o.changed_at) REGEXP ? ) 
            ORDER BY $sort_by $order 
            LIMIT ? OFFSET ? 
        ;");
        $stmt->bind_param("isii", $groupid, $query, $rows_per_page, $offset);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $contractorid, $customerid, $status, $record, $code1c, $created_at, $changed_at);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["contractorid"] = $contractorid;
                $res["customerid"] = $customerid;
                $res["status"] = $status;
                $res["record"] = json_decode($record, TRUE);
                $res["code1c"] = $code1c;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $result[] = $res;
            }
            $stmt->close();
            $ordersCount = $this->ordersCountOfCustomer($groupid);
            return ["total" => $ordersCount, "items" => $result];
        }
        else {
            return NULL;
        }
    }
    /**
     * Заказ по ИД для CRM
     * @param int $id ид заказа
     */
    public function fetchOrderById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT o.id, o.contractorid, o.customerid, o.status, o.record, o.code1c, o.created_at, o.changed_at 
            FROM orders o 
            WHERE o.id = ?
        ;");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $contractorid, $customerid, $status, $record, $code1c, $created_at, $changed_at);
            $result = array();
            if ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["contractorid"] = $contractorid;
                $res["customerid"] = $customerid;
                $res["status"] = $status;
                $res["record"] = json_decode($record, TRUE);
                $res["code1c"] = $code1c;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $result[] = $res;
            }
            $stmt->close();
            return ["total" => count($result), "items" => $result];
        }
        else {
            return NULL;
        }
    }
    /**
     * Количество всех заказов
     * @return int count
     */
    function ordersCount()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders;");
        $result = 0;
        if ($stmt->execute()) {
            $stmt->bind_result($count);
            if ($stmt->fetch()) {
                $result = $count;
            }
            else {
                $stmt->close();
                return FALSE;
            }
            $stmt->close();
        }
        else {
            return FALSE;
        }
        return $result;
    }
    /**
     * Количество заказов поставщика
     * @param int $groupid ид поставщика
     * @return int count
     */
    function ordersCountOfContractor($groupid)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE contractorid = ?;");
        $stmt->bind_param("i", $groupid);
        $result = 0;
        if ($stmt->execute()) {
            $stmt->bind_result($count);
            if ($stmt->fetch()) {
                $result = $count;
            }
            else {
                $stmt->close();
                return FALSE;
            }
            $stmt->close();
        }
        else {
            return FALSE;
        }
        return $result;
    }
    /**
     * Количество заказов заказчика
     * @param int $groupid ид заказчика
     * @return int count
     */
    function ordersCountOfCustomer($groupid)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE customerid = ?;");
        $stmt->bind_param("i", $groupid);
        $result = 0;
        if ($stmt->execute()) {
            $stmt->bind_result($count);
            if ($stmt->fetch()) {
                $result = $count;
            }
            else {
                $stmt->close();
                return FALSE;
            }
            $stmt->close();
        }
        else {
            return FALSE;
        }
        return $result;
    }
}
?>