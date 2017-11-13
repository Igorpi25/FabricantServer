<?php
namespace CRM\Controller;
require_once dirname(__FILE__).'/../services/EventService.php';

class EventController extends BaseController {
    public function __construct($app) {
        parent::__construct($app);
        $app->get('', [$this, 'getEventsInRange']);
        $app->get('/group/:id', [$this, 'getEventsInRangeOfGroup']);
        $app->get('/log/:id', [$this, 'getEventLogs'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->get('/:id', [$this, 'getEventById'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->post('', [$this, 'createEvent']);
        $app->put('/:id', [$this, 'updateEvent'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->delete('/:id', [$this, 'removeEvent'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->put('/accept/:id', [$this, 'acceptEvent'])->conditions(array('id' => '[0-9]{1,11}'));
        $app->put('/restore/:id', [$this, 'restoreEvent'])->conditions(array('id' => '[0-9]{1,11}'));
    }
    /**
     * События в определенный период
     * url - /events
     * @method GET
     * @param string rangeStart начало периода
     * @param string rangeEnd конец периода
     * @return Json response
     */
    public function getEventsInRange() {
        $this->verifyRequiredParams(['rangeStart', 'rangeEnd']);
        $rangeStart = $this->app->request()->get('rangeStart');
        $rangeEnd = $this->app->request()->get('rangeEnd');
        $db = new \CRM\Service\EventService();
        $result = $db->fetchEvents($rangeStart, $rangeEnd);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * События группы в определенный период
     * url - /events/group/:id
     * @method GET
     * @param string rangeStart начало периода
     * @param string rangeEnd конец периода
     * @return Json response
     */
    public function getEventsInRangeOfGroup($id) {
        $this->verifyRequiredParams(['rangeStart', 'rangeEnd']);
        $rangeStart = $this->app->request()->get('rangeStart');
        $rangeEnd = $this->app->request()->get('rangeEnd');
        $db = new \CRM\Service\EventService();
        $result = $db->fetchGroupEvents($rangeStart, $rangeEnd, $id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Событие по ид
     * url - /events/:id
     * @method GET
     * @return Json response
     */
    public function getEventById($id) {
        $db = new \CRM\Service\EventService();
        $result = $db->fetchEventById($id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Лог события
     * url - /events/log/:id
     * @method GET
     * @return Json response
     */
    public function getEventLogs($id) {
        $db = new \CRM\Service\EventService();
        $result = $db->fetchEventLog($id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Создание события
     * url - /events
     * @method POST
     * @param int groupid ид группы для которого создается событие
     * @param string noticeDate дата оповещения
     * @param string message текст события
     * @param string priority приоритет события, вида 'grey'
     * @return Json response
     */
    public function createEvent() {
        $this->verifyRequiredParams(['groupid', 'noticeDate', 'message', 'priority']);
        $group_id = $this->app->request()->post('groupid');
        $notice_date = $this->app->request()->post('noticeDate');
        $message = $this->app->request()->post('message');
        $priority = $this->app->request()->post('priority');
        $user_id = $this->app->auth_user_id;
        $db = new \CRM\Service\EventService();
        $result = $db->createEvent($group_id, $notice_date, $message, $priority, $user_id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Обновление события
     * url - /events/:id
     * @method PUT
     * @param string noticeDate дата оповещения
     * @param string message текст события
     * @param string priority приоритет события, вида 'grey'
     * @return Json response
     */
    public function updateEvent($id) {
        $this->verifyRequiredParams(['noticeDate', 'message', 'priority']);
        $notice_date = $this->app->request()->put('noticeDate');
        $message = $this->app->request()->put('message');
        $priority = $this->app->request()->put('priority');
        $user_id = $this->app->auth_user_id;
        $db = new \CRM\Service\EventService();
        $result = $db->saveEvent($id, $notice_date, $message, $priority, $user_id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Удаление события
     * url - /events/:id
     * @method DELETE
     * @return Json response
     */
    public function removeEvent($id) {
        $db = new \CRM\Service\EventService();
        $user_id = $this->app->auth_user_id;
        $result = $db->removeEvent($id, $user_id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Принятие события
     * url - /events/accept/:id
     * @method PUT
     * @return Json response
     */
    public function acceptEvent($id) {
        $db = new \CRM\Service\EventService();
        $user_id = $this->app->auth_user_id;
        $result = $db->acceptEvent($id, $user_id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
    /**
     * Восстановление события
     * url - /events/restore/:id
     * @method PUT
     * @return Json response
     */
    public function restoreEvent($id) {
        $db = new \CRM\Service\EventService();
        $user_id = $this->app->auth_user_id;
        $result = $db->restoreEvent($id, $user_id);
        if ($result) {
            $this->echoResponse(200, ['error' => false, 'result' => $result]);
        } else {
            $this->echoResponse(404, ['error' => true, 'message' => 'Неизвестная ошибка, повторите попытку.']);
        }
    }
}
?>