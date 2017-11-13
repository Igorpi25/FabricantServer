<?php
namespace CRM\Controller;

abstract class BaseController
{
    const ERROR_MESSAGE_FETCHING_DATA = "Ошибка запроса данных.";
    const ERROR_MESSAGE_INCORRECT_INPUTS = "Не корректные входные данные.";
    const ERROR_MESSAGE_PERMISSION_FABRICANT_ADMIN = "У Вас нет прав доступа. Доступ разрешен только для администраторов.";
    const ERROR_MESSAGE_PERMISSION_IN_GROUP = "У Вас нет прав доступа. Доступ разрешен только для членов группы.";
    const ERROR_MESSAGE_PERMISSION_ADMIN_IN_GROUP = "У Вас нет прав доступа. Доступ разрешен только для администраторов группы.";
    const ERROR_MESSAGE_PERMISSION_SUPER_ADMIN_IN_GROUP = "У Вас нет прав доступа. Доступ разрешен только для создателей группы.";
    
    /**
     * @var $app \Slim\App
     */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }
    /**
     * Отправка ответа
     * @param int $status_code Http код ответа
     * @param string $response Json ответ
     * @return Json response
     */
    public function echoResponse($status_code, $response)
    {
        $this->app->status($status_code);
        $this->app->contentType('application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    //--------------------Permission--------------------------------

    /**
     * Доступ только для администраторов фабриканта
     * @param int $userid ид пользователя запроса
     * @param string $response Json ответ
     * @return Json response
     */
    function permissionFabricantAdmin($userid)
    {
        if (($userid == 1) || ($userid == 3)) return;

        $response["error"] = true;
        $response["message"] = self::ERROR_MESSAGE_PERMISSION_FABRICANT_ADMIN;
        $response["success"] = 0;

        $this->echoResponse(403, $response);
        $this->$app->stop();
    }
    /**
     * Доступ только для членов группы
     * @param int $userid ид пользователя запроса
     * @param int $userid ид группы, к которой делается запрос
     * @param string $response Json ответ
     * @return Json response
     */
    function permissionInGroup($userid, $groupid)
    {
        $db_profile = new \DbHandlerProfile();
        $status = $db_profile->getUserStatusInGroup($groupid, $userid);

        if ($userid == 1 || $userid == 3) return;
        if (($status == 0) || ($status == 2) || ($status == 1)) return;

        $response["error"] = true;
        $response["message"] = self::ERROR_MESSAGE_PERMISSION_IN_GROUP;
        $response["success"] = 0;

        $this->echoResponse(403, $response);
        $this->$app->stop();
    }
    /**
     * Доступ только для админов группы
     * @param int $userid ид пользователя запроса
     * @param int $userid ид группы, к которой делается запрос
     * @param string $response Json ответ
     * @return Json response
     */
    function permissionAdminInGroup($userid, $groupid)
    {
        $db_profile = new \DbHandlerProfile();
        $status = $db_profile->getUserStatusInGroup($groupid, $userid);

        if ($userid == 1 || $userid == 3) return;
        if (($status == 2) || ($status == 1)) return;

        $response["error"] = true;
        $response["message"] = self::ERROR_MESSAGE_PERMISSION_ADMIN_IN_GROUP;
        $response["success"] = 0;

        $this->echoResponse(403, $response);
        $this->$app->stop();
    }
    /**
     * Доступ только для создателей группы
     * @param int $userid ид пользователя запроса
     * @param int $userid ид группы, к которой делается запрос
     * @param string $response Json ответ
     * @return Json response
     */
    function permissionSuperAdminInGroup($userid, $groupid)
    {
        $db_profile = new \DbHandlerProfile();
        $status = $db_profile->getUserStatusInGroup($groupid, $userid);

        if ($userid == 1 || $userid == 3) return;
        if ($status == 1) return;

        $response["error"] = true;
        $response["message"] = "";
        $response["success"] = 0;

        $this->echoResponse(403, $response);
        $this->$app->stop();
    }
    /**
     * Доступ только для администраторов фабриканта
     * @param int $userid ид пользователя запроса
     * @param string $response Json ответ
     * @return bool
     */
    function isUserFabricantAdmin($userid)
    {
        if (($userid == 1) || ($userid == 3)) return TRUE;
        return FALSE;
    }
    /**
     * Доступ только для членов группы
     * @param int $userid ид пользователя запроса
     * @param int $userid ид группы, к которой делается запрос
     * @param string $response Json ответ
     * @return bool
     */
    function isUserInGroup($userid, $groupid)
    {
        $db_profile = new \DbHandlerProfile();
        $status = $db_profile->getUserStatusInGroup($groupid, $userid);
        if ($userid == 1 || $userid == 3) return TRUE;
        if (($status == 0) || ($status == 2) || ($status == 1)) return TRUE;
        return FALSE;
    }
    /**
     * Доступ только для админов группы
     * @param int $userid ид пользователя запроса
     * @param int $userid ид группы, к которой делается запрос
     * @param string $response Json ответ
     * @return Json response
     */
    function isUserAdminInGroup($userid, $groupid)
    {
        $db_profile = new \DbHandlerProfile();
        $status = $db_profile->getUserStatusInGroup($groupid, $userid);
        if ($userid == 1 || $userid == 3) return TRUE;
        if (($status == 2) || ($status == 1)) return TRUE;
        return FALSE;
    }
    /**
     * Доступ только для создателей группы
     * @param int $userid ид пользователя запроса
     * @param int $userid ид группы, к которой делается запрос
     * @param string $response Json ответ
     * @return Json response
     */
    function isUserSuperAdminInGroup($userid, $groupid)
    {
        $db_profile = new \DbHandlerProfile();
        $status = $db_profile->getUserStatusInGroup($groupid, $userid);
        if ($userid == 1 || $userid == 3) return TRUE;
        if ($status == 1) return TRUE;
        return FALSE;
    }

    //--------------------Validation--------------------------------

    /**
     * Проверка обязательных параметров
     * @param array $required_fields обязательные поля
     */
    function verifyRequiredParams($required_fields)
    {
        $error = false;
        $error_fields = "";
        $request_params = array();
        $request_params = $_REQUEST;
        // PUT запросы
        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            parse_str($this->app->request()->getBody(), $request_params);
        }
        foreach ($required_fields as $field) {
            if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
                $error = true;
                $error_fields .= $field . ', ';
            }
        }
        if ($error) {
            // При отсутствии обязательного поля отправляет ответ, и останавливает
            $response = array();
            $response["error"] = true;
            $response["message"] = 'Обязательное поле(я) ' . substr($error_fields, 0, -2) . ' отсутствуют или пустые.';
            $this->echoResponse(400, $response);
            $this->app->stop();
        }
    }
    /**
     * Проверка адреса электронной почты на корректность
     * @param string $email адрес электронной почты
     */
    function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response["error"] = true;
            $response["message"] = 'Адрес электронной почты не действителен.';
            $this->echoResponse(400, $response);
            $this->app->stop();
        }
    }
    /**
     * Проверка номера телефона на корректность
     * @param string $phone номер телефона
     */
    function validatePhone($phone)
    {
        if ( (!preg_match("/^[0-9]{11}$/", $phone)) || ($phone[0] != 7)) {
            $response["error"] = true;
            $response["message"] = 'Номер телефона не действителен.';
            $this->echoResponse(400, $response);
            $this->app->stop();
        }
    }
    /**
     * Проверка строки даты на корректность
     * @param string $date_string строка даты
     */
    function validateDateFromString($date_string)
    {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $date_string);
        if ($d && $d->format('Y-m-d H:i:s') !== $date_string) {
            $response["error"] = true;
            $response["message"] = 'Дата не действительна.';
            $this->echoResponse(400, $response);
            $this->app->stop();
        }
    }
    /**
     * Проверка кода 1C на корректность
     * @param string $uidString строка кода
     * @return bool
     */
    function isUID($uidString)
    {
        if (preg_match("#^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$#", $uidString) !== 1) {
            return FALSE;
        }
        else {
            return TRUE;
        }
    }
    /**
     * Создание из строки запроса регулярное выражение
     * очистка строки от спец. символов
     * замена пробелов на ИЛИ
     * знак * на И
     * @param string $query строка запрса
     * @return string регулярное выражение
     */
    function genQueryString($query)
    {
        $query = preg_replace("/[\^\[\]=$%&<>{}]/u", '', $query);
        if (empty($query)) {
            return "";
        }
        else {
            $query = str_replace(' ', '|', $query);
            $query = str_replace('""', '\"', $query);
            $query = str_replace('*', '.*', $query);
            // $query = mb_strtolower($query,'UTF-8');
            return $query;
        }
    }
    /**
     * Проверка на наличие спец. символов в строке запроса
     * @param string $queryString строка запроса
     * @return bool
     */
    function isQueryString($queryString)
    {
        if (preg_match("/^[_*\+\-0-9A-Za-zА-Яа-пр-яЁё]+$/u", $queryString) === 1) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }
}
?>