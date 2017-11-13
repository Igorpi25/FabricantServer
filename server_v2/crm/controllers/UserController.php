<?php
namespace CRM\Controller;

require_once dirname(__FILE__) . '/../services/UserService.php';

class UserController extends BaseController
{
    const USER_STATUS_IN_GROUP_COMMON = 0;
    const USER_STATUS_IN_GROUP_CREATER = 1;
    const USER_STATUS_IN_GROUP_ADMIN = 2;
    const USER_STATUS_IN_GROUP_BANNED = 3;
    const USER_STATUS_IN_GROUP_AGENT = 8;

    public function __construct($app)
    {
        parent::__construct($app);
        $app->post('/auth', [$this, 'userAuth']);
        $app->get('/profile', [$this, 'getProfile']);
        $app->get('', [$this, 'getAllUsers']);
        $app->get('/:id', [$this, 'getUser'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->put('/:id', [$this, 'updateUser'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->delete('/:id', [$this, 'removeUser'])->conditions(array('id' => '[0-9]{1,11}'));
    }
    /**
     * Авторизация для адмнки
     * url - /users/auth
     * @method POST
     * @param string phone required
     * @param string password required
     * @return Json response
     */
    public function userAuth()
    {
        $this->verifyRequiredParams(['login', 'password']);
        $phone = $this->app->request()->post('login');
        $password = $this->app->request()->post('password');
        $origin = $this->app->request->headers->get('Origin');
        $response = array();
        $db_profile = new \DbHandlerProfile();
        try {
            // Проверка пользователя по номеру телефона
            if ($db_profile->checkLoginByPhone($phone, $password)) {
                $user = $db_profile->getUserByPhone($phone);
                if (isset($user)) {
                    // $response['token'] = $user['api_key'];
                    if ($origin == 'https://admin.fabricant.pro' || $origin == 'http://admin.fabricant.pro' || 'http://192.168.1.3:8080' || 'http://localhost:8080') {
                        if ($this->isUserFabricantAdmin($user["id"])) {
                            return $this->echoResponse(200, ['error' => false, 'token' => $user['api_key']]);
                        } else {
                            throw new \Exception(self::ERROR_MESSAGE_PERMISSION_IN_GROUP, 403);
                        }
                    } else if ($origin == 'https://crm.fabricant.pro' || $origin == 'http://crm.fabricant.pro') {
                        if ($this->isUserAdminInSomeGroup($user["id"])) {
                            return $this->echoResponse(200, ['error' => false, 'token' => $user['api_key']]);
                        } else {
                            throw new \Exception(self::ERROR_MESSAGE_PERMISSION_IN_GROUP, 403);
                        }
                    } else if ($origin == 'https://agent.fabricant.pro' || $origin == 'http://agent.fabricant.pro') {
                        if ($this->isUserAgentInSomeGroup($user["id"])) {
                            return $this->echoResponse(200, ['error' => false, 'token' => $user['api_key']]);
                        } else {
                            throw new \Exception(self::ERROR_MESSAGE_PERMISSION_IN_GROUP, 403);
                        }
                    }
                }
                else {
                    // Неизвестная ошибка
                    throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA, 400);
                }
            }
            else {
                // Не правильные данные пользователя
                throw new \Exception('Авторизация не пройдена. Не правильный логин или пароль.', 404);
            }
        } catch(\Exception $e) {
            $this->echoResponse($e->getCode(), ['error' => true, 'success' => 0, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Профиль пользователя
     * url - /users/profile
     * @method GET
     * @return Json response
     */
    public function getProfile()
    {
        $userGroup = $this->app->request()->post('group');
        $db_profile = new \DbHandlerProfile();
        $response = array();
        try {
            if (!isset($this->app->auth_user_id)) {
                throw new \Exception('Ошибка авторизации, попробуйте еще раз.');
            }
            $user = $db_profile->getUserById($this->app->auth_user_id);
            if (!isset($user)) {
                throw new \Exception('Пользователь не найден.');
            }

            $groups = $db_profile->getGroupsOfUser($this->app->auth_user_id);

            $contractors = array();
            foreach ($groups as $group) {
                if ($group["type"] == 0) {
                    // Доступ только для создателя, админа или агента
                    if ( ($group['status_in_group'] == 1) || ($group['status_in_group'] == 2) || ($group['status_in_group'] == 8)) {
                        $contractors[] = $group;
                    }
                }
            }
            // Временное решение
            if (count($contractors) == 0) {
                throw new \Exception('Нет прав доступа.');
            }
            if (count($contractors) == 1) {
                $user['contractor'] = $contractors[0];
            }
            if ($this->isUserFabricantAdmin($user['id'])) {
                foreach ($contractors as $group) {
                    if ($group["id"] == 127) {
                        $user['contractor'] = $group;
                        break;
                    }
                }
            }
            $this->echoResponse(200, ['error' => false, 'result' => $user]);
        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Данные пользователя
     * url - /users/:id
     * @method GET
     * @return Json response
     */
    public function getUser($id)
    {
        $db_profile = new \DbHandlerProfile();
        $response = array();
        $result = $db_profile->getUserById($id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        }
        else {
            $this->echoResponse(404, ['error' => true, 'message' => self::ERROR_MESSAGE_FETCHING_DATA]);
        }
    }
    /**
     * Обновление данных пользователя
     * url - /users/:id
     * @method PUT
     * @return Json response
     */
    public function updateUser($id)
    {
        $this->echoResponse(200, ['update user' => $id]);
    }
    /**
     * Удаление данных пользователя
     * url - /users/:id
     * @method DELETE
     * @return Json response
     */
    public function removeUser($id)
    {
        $this->echoResponse(200, ['delete user' => $id]);
    }
    /**
     * Все пользователи
     * url - /users
     * @method GET
     * @return Json response
     */
    public function getAllUsers()
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
            } else {
                $order = "ASC";
            }
            $db = new \CRM\Service\UserService();
            if ($rows_per_page == -1) {
                $result = $db->fetchAllUsers();
            } else {
                $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
                $result = $db->fetchUsersWithFilters($sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
            }
            if ($result) {
                $this->echoResponse(200, ['error' => false, 'result' => $result]);
            } else {
                throw new \Exception(self::ERROR_MESSAGE_FETCHING_DATA);
            }
        } catch (\Exception $e) {
            $this->echoResponse(404, ['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Проверка сортируемого столбца на корректность
     * @return bool
     */
    function isSortableCol($colName) {
        if ($colName == 'id' || $colName == 'name' || $colName == 'phone' || 
            $colName == 'email' || $colName == 'status' || $colName == 'changed_at') {
            return TRUE;
        }
        return FALSE;
    }

    function isUserAdminInSomeGroup($user_id) {
        $db_profile = new \DbHandlerProfile();
        $groups = $db_profile->getGroupsOfUser($this->app->auth_user_id);
        $contractors = array();
        $result = FALSE;
        foreach ($groups as $group) {
            if ($group['type'] == 0 && $group['status_in_group'] == 1) {
                $result = TRUE;
                break;
            }
        }
        return FALSE;
    }
    function isUserAgentInSomeGroup($user_id) {
        $db_profile = new \DbHandlerProfile();
        $groups = $db_profile->getGroupsOfUser($this->app->auth_user_id);
        $contractors = array();
        $result = FALSE;
        foreach ($groups as $group) {
            if ($group['type'] == 0 && $group['status_in_group'] == 8) {
                $result = TRUE;
                break;
            }
        }
        return FALSE;
    }
}
?>