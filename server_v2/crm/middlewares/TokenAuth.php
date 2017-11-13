<?php
namespace CRM\Middleware;

class TokenAuth extends \Slim\Middleware {
    public function __construct() {
        // Разрешенные пути
        $this->whiteList = array('\/users\/auth');
    }
    /**
     * Отклонить запрос
     */
    public function deny_access() {
        $res = $this->app->response();
        $res->status(401);
    }
    /**
     * Проверка пути на вхождение в разрешенные
     * @param string $url
     * @return bool
     */
    public function isPublic($url) {
        $patterns_flattened = implode('|', $this->whiteList);
        $matches = null;
        preg_match('/' . $patterns_flattened . '/', $url, $matches);
        return (count($matches) > 0);
    }
    /**
     * Call
     * @todo выполнение для всех входящих запросов
     */
    public function call() {
        // Продолжить при вхождения в разрешенные пути, иначе проверить токен
        if ($this->isPublic($this->app->request->getResourceUri())) {
            // Продолжить выполнение
            $this->next->call();
        } else {
            $headers = apache_request_headers();
            $db_profile = new \DbHandlerProfile();
            if (isset($headers["Api-Key"]) && $db_profile->isValidApiKey($headers["Api-Key"])) {
                $result = $db_profile->getUserId($headers["Api-Key"]);
                if ($result && $result["id"]["status"] != 4) {
                    $this->app->auth_user_token = $headers["Api-Key"];
                    $this->app->auth_user_id = $result["id"];
                    // Продолжить выполнение
                    $this->next->call();
                } else {
                    $this->deny_access();
                }
            } else {
                $this->deny_access();
            }
        }
    }
}
?>