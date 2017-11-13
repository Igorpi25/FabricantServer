<?php
namespace CRM\Middleware;

class CORSAccessControl extends \Slim\Middleware {
    public function __construct() {
        //Разрешенные домены
        $this->allowOrigins = [
            "http://localhost:8080",
            "http://192.168.1.3:8080",
            "https://admin.fabricant.pro",
            "http://admin.fabricant.pro",
            "https://crm.fabricant.pro",
            "http://crm.fabricant.pro",
            "https://agent.fabricant.pro",
            "http://agent.fabricant.pro"
        ];
    }
    /**
     * Call
     * @todo выполнение для всех входящих запросов
     */
    public function call() {
        $app = $this->app;
        $origin = $app->request->headers->get('Origin');
        // Проверка вхождения в разрешенные домены
        if (in_array($origin, $this->allowOrigins)) {
            // При CORS option отправляем ответ
            if ($app->request->isOptions()) {
                $app->response->headers->set("Access-Control-Allow-Origin", $origin);
                $app->response->headers->set("Access-Control-Allow-Credentials", "true");
                $app->response->headers->set("Access-Control-Allow-Headers", "X-Requested-With, Content-Type, Accept, Origin, Api-Key");
                //$app->response->headers->set("Access-Control-Allow-Credentials", "GET, POST, PUT, DELETE, OPTIONS");
            } else {
                $app->response->headers->set("Access-Control-Allow-Origin", $origin);
                $app->response->headers->set("Access-Control-Allow-Credentials", "true");
                // Продолжить выполнение
                $this->next->call();
            }
        } else {
            // На одном и том же домене будет разрешение, иначе apache отправит доступ запрещен
            $this->next->call();
        }
    }
}
?>