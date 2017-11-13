<?php
namespace CRM\Controller;

require_once dirname(__FILE__) . '/../services/GroupService.php';

class GroupController extends BaseController
{
    public function __construct($app)
    {
        parent::__construct($app);
        $app->get('', [$this, 'getAllGroups']);
        $app->get('/contractors', [$this, 'getContractors']);
        $app->get('/customers', [$this, 'getCustomers']);

        $app->get('/agents/:agentid', [$this, 'getAgentGroups']);
    }
    /**
     * Все группы
     * url - /groups
     * @method GET
     * @return Json response
     */
    public function getAllGroups()
    {
        $db = new \CRM\Service\GroupService();
        $result = $db->fetchAllGroups();
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        }
        else {
            $this->echoResponse(404, ['error' => true, 'message' => self::ERROR_MESSAGE_FETCHING_DATA]);
        }
    }
    /**
     * Поставщики
     * url - /groups/contractors
     * @method GET
     * @return Json response
     */
    public function getContractors()
    {
        $groupType = 0;
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
            $db = new \CRM\Service\GroupService();
            if ($rows_per_page == -1) {
                $result = $db->fetchGroupsByType($groupType);
            }
            else {
                $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
                $result = $db->fetchGroupsByTypeAndFilters($groupType, $sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
            }
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            }
            else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
            // var_dump($result);

        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Заказчики
     * url - /groups/customers
     * @method GET
     * @return Json response
     */
    public function getCustomers()
    {
        $groupType = 1;
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
            $db = new \CRM\Service\GroupService();
            if ($rows_per_page == -1) {
                $result = $db->fetchGroupsByType($groupType);
            }
            else {
                $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
                $result = $db->fetchGroupsByTypeAndFilters($groupType, $sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
            }
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            }
            else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
            // var_dump($result);

        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Получаем список групп для аналитики агента
     * url - /groups/customers
     * @method GET
     * @param int $agentid ид агента
     * @return Json response
     */
    public function getAgentGroups($agentid)
    {
        $contractorid = 127;
        $user_id = $this->app->auth_user_id;

        error_log("-------------/analytic_agent_kustuk/" . $agentid . "/groups----------------");
        error_log("|agentid=" . $agentid . " user_id=" . $user_id . " contractorid=" . $contractorid . "|");

        $db_fabricant = new \DbHandlerFabricant();

        try {
            if (!$this->isUserInGroup($user_id, $contractorid)) {
                throw new \Exception(self::ERROR_MESSAGE_PERMISSION_IN_GROUP, 403);
            }
            if ($user_id != $agentid) {
                if (!$this->isUserAdminInGroup($user_id, $contractorid)) {
                    throw new \Exception(self::ERROR_MESSAGE_PERMISSION_ADMIN_IN_GROUP, 403);
                }
            }
            $result = $db_fabricant->getAnalyticGroupsOfUser($agentid);
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'success' => 1, 'result' => ["total" => count($result), "items" => $result]]);
            } else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA, 404);
            }
        } catch(\Exception $e) {
            $this->echoResponse($e->getCode(), ['error' => true, 'success' => 0, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Проверка сортируемого столбца на корректность
     * @return bool
     */
    function isSortableCol($colName)
    {
        if ($colName == 'id' || $colName == 'name' || $colName == 'phone' ||
            $colName == 'address' || $colName == 'status' || $colName == 'created_at' || $colName == 'changed_at') {
            return TRUE;
        }
        return FALSE;
    }
}
?>