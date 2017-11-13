<?php
namespace CRM\Controller;

require_once dirname(__FILE__) . '/../services/OrderService.php';

class OrderController extends BaseController
{
    public function __construct($app)
    {
        parent::__construct($app);
        $app->get('', [$this, 'getAllOrders']);
        $app->get('/contractors/:id', [$this, 'getContractorOrders']);
        $app->get('/customers/:id', [$this, 'getCustomersOrders']);
        $app->get('/:id', [$this, 'getOrderById']);

        $app->get('/agents/:agentid/:range_start/:range_end', [$this, 'getAgentOrders']);
    }
    /**
     * Все заказы
     * url - /orders
     * @method GET
     * @return Json response
     */
    public function getAllOrders()
    {
        $this->verifyRequiredParams(['descending', 'sortBy', 'page', 'rowsPerPage']);
        $desc = $this->app->request()->get('descending');
        $sort_by = $this->app->request()->get('sortBy');
        $page = $this->app->request()->get('page');
        $rows_per_page = $this->app->request()->get('rowsPerPage');
        $query = $this->app->request()->get('q');
        if (isset($query) && !empty($query)) {
            $queryString = $this->genQueryString($query);
        }
        else {
            $queryString = "";
        }
        try {
            if (!$this->isSortableCol($sort_by)) {
                throw new \Exception(self::ERROR_MESSAGE_INCORRECT_INPUTS);
            }
            if ($desc == "true") {
                $order = "DESC";
            }
            else {
                $order = "ASC";
            }
            $db = new \CRM\Service\OrderService();
            $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
            $result = $db->fetchAllOrders($sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            }
            else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Все заказы поставщика
     * url - /orders/contractors/:id
     * @method GET
     * @return Json response
     */
    public function getContractorOrders($groupid)
    {
        $this->verifyRequiredParams(['descending', 'sortBy', 'page', 'rowsPerPage']);
        $desc = $this->app->request()->get('descending');
        $sort_by = $this->app->request()->get('sortBy');
        $page = $this->app->request()->get('page');
        $rows_per_page = $this->app->request()->get('rowsPerPage');
        $query = $this->app->request()->get('q');
        if (isset($query) && !empty($query)) {
            $queryString = $this->genQueryString($query);
        }
        else {
            $queryString = "";
        }
        try {
            if (!$this->isSortableCol($sort_by)) {
                throw new \Exception(self::ERROR_MESSAGE_INCORRECT_INPUTS);
            }
            if ($desc == "true") {
                $order = "DESC";
            }
            else {
                $order = "ASC";
            }
            $db = new \CRM\Service\OrderService();
            $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
            $result = $db->fetchOrdersOfContrator($groupid, $sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            }
            else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Все заказы заказчика
     * url - /orders/customers/:id
     * @method GET
     * @return Json response
     */
    public function getCustomersOrders($groupid)
    {
        $this->verifyRequiredParams(['descending', 'sortBy', 'page', 'rowsPerPage']);
        $desc = $this->app->request()->get('descending');
        $sort_by = $this->app->request()->get('sortBy');
        $page = $this->app->request()->get('page');
        $rows_per_page = $this->app->request()->get('rowsPerPage');
        $query = $this->app->request()->get('q');
        if (isset($query) && !empty($query)) {
            $queryString = $this->genQueryString($query);
        }
        else {
            $queryString = "";
        }
        try {
            if (!$this->isSortableCol($sort_by)) {
                throw new \Exception(self::ERROR_MESSAGE_INCORRECT_INPUTS);
            }
            if ($desc == "true") {
                $order = "DESC";
            }
            else {
                $order = "ASC";
            }
            $db = new \CRM\Service\OrderService();
            $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
            $result = $db->fetchOrdersOfCustomer($groupid, $sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            }
            else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Заказ по id
     * url - /orders/:id
     * @method GET
     * @return Json response
     */
    public function getOrderById($id)
    {
        try {
            if (!isset($id) || empty($id)) {
                throw new \Exception(self::ERROR_MESSAGE_INCORRECT_INPUTS);
            }
            $db = new \CRM\Service\OrderService();
            $result = $db->fetchOrderById($id);
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            }
            else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Получаем список групп для аналитики агента
     * url - /orders/:id
     * @method GET
     * @param int $agentid ид агента
     * @param string $range_start начало периода
     * @param string $range_end конец периода
     * @return Json response
     */
    public function getAgentOrders($agentid, $range_start, $range_end)
    {
        $contractorid = 127;
        $user_id = $this->app->auth_user_id;
        error_log("-------------/analytic_agent_kustuk/" . $agentid . "/orders----------------");
        error_log("|agentid=" . $agentid . " user_id=" . $user_id . " contractorid=" . $contractorid . " date_from=" . $range_start . " date_to=" . $range_end . "|");

        try {
            $timestamp_from = \DateTime::createFromFormat('Y-m-d', $range_start)->getTimestamp();
            $timestamp_to = \DateTime::createFromFormat('Y-m-d', $range_end)->getTimestamp();
    
            error_log("|timestamp_from=" . $timestamp_from . " timestamp_to=" . $timestamp_to . "|");
    
            if (empty($timestamp_from) || empty($timestamp_to)) {
                throw new \Exception(self::ERROR_MESSAGE_INCORRECT_INPUTS, 406);
            }
            if (!$this->isUserInGroup($user_id, $contractorid)) {
                throw new \Exception(self::ERROR_MESSAGE_PERMISSION_IN_GROUP, 403);
            }
            if ($user_id != $agentid) {
                if (!$this->isUserAdminInGroup($user_id, $contractorid)) {
                    throw new \Exception(self::ERROR_MESSAGE_PERMISSION_ADMIN_IN_GROUP, 403);
                }
            }

            $db_fabricant = new \DbHandlerFabricant();
            $result = $db_fabricant->getAnalyticAgentOrders($contractorid, $agentid, $timestamp_from, $timestamp_to);
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'success' => 1, 'result' => ["total" => count($result), "items" => $result]]);
            } else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA, 404);
            }
        } catch (\Exception $e) {
            $this->echoResponse($e->getCode(), ['error' => true, 'success' => 0, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Проверка сортируемого столбца на корректность
     * @return bool
     */
    function isSortableCol($colName)
    {
        if ($colName == 'id' || $colName == 'contractorid' || $colName == 'customerid' ||
            $colName == 'status' || $colName == 'code1c' || $colName == 'created_at' || $colName == 'changed_at') {
            return TRUE;
        }
        return FALSE;
    }
}
?>