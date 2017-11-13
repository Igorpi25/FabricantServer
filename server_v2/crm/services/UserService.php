<?php
namespace CRM\Service;

require_once dirname(__FILE__) . '/../../include/DbHandler.php';
require_once dirname(__FILE__) . '/../services/GroupService.php';

class UserService extends \DbHandler
{
  public function __construct()
  {
    parent::__construct();
  }
  /**
   * Проверка на наличие доступа
   * @param int $user_id ид пользователя
   * @return mixed
   */
  public function getRole($user_id)
  {
    $stmt = $this->conn->prepare("
      SELECT r.id, r.name 
        FROM crm_users_roles p 
        LEFT JOIN crm_role r ON p.role_id = r.id 
        WHERE p.user_id = ?
    ;");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
      $stmt->bind_result($role_id, $role_name);
      $result = array();
      if ($stmt->fetch()) {
        $result["id"] = $role_id;
        $result["name"] = $role_name;
      }
      $stmt->close();
      if (count($result) > 0) {
        return $result;
      }
      return NULL;
    }
    else {
      return NULL;
    }
  }
  /**
   * Все пользователи для CRM
   * @return mixed
   */
  public function fetchAllUsers() {
    $stmt = $this->conn->prepare("
      SELECT u.id, u.name, u.phone, u.email, u.status, u.info, u.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
      FROM users u LEFT OUTER JOIN avatars a ON u.avatar = a.id 
    ;");
    if ($stmt->execute()) {
      $stmt->bind_result($id, $name, $phone, $email, $status, $info, $changed_at, $icon, $avatar, $full);
      $result = array();
      while($stmt->fetch()) {
        $res = array();
        $res["id"] = $id;
        $res["name"] = $name;
        $res["phone"] = $phone;
        $res["email"] = $email;
        $res["status"] = $status;
        if (isset($info) && !empty($info)) {
          $info = json_decode($info, TRUE);
        } else {
          $info = array();
        }
        $res["info"] = $info;
        $res["changed_at"] = $changed_at;
        $avatars = array();
        if ($full) $avatars["full"] = URL_HOME.path_fulls.$full;
        if ($avatar) $avatars["avatar"] = URL_HOME.path_avatars.$avatar;
        if ($icon) $avatars["icon"] = URL_HOME.path_icons.$icon;
        if (count($avatars)) {
          $res["avatars"] = $avatars;
        }
        $result[] = $res;
      }
      $stmt->close();
      $usersCount = $this->usersCount();
      return ["total" => $usersCount, "items" => $result];
    } else {
      return NULL;
    }
  }
  /**
   * Все пользователи для CRM по страницам
   * @param string $sort_by название колонки сортировки
   * @param string $order направление сортировки ASC или DESC
   * @param int $rows_per_page количество строк в странице
   * @param int $offset смещение, номер страницы умноженная на $rows_per_page
   * @param string $query строка запроса фильтрации
   * @return mixed
   */
  public function fetchUsersWithFilters($sort_by, $order, $rows_per_page, $offset, $query) {
    $stmt = $this->conn->prepare("
      SELECT u.id, u.name, u.phone, u.email, u.status, u.info, u.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
      FROM users u LEFT OUTER JOIN avatars a ON u.avatar = a.id 
      WHERE (CONCAT_WS('|', u.id, u.name, u.phone, u.email, u.status) REGEXP ? ) 
      ORDER BY $sort_by $order 
      LIMIT ? OFFSET ? 
    ;");
    $stmt->bind_param("sii", $query, $rows_per_page, $offset);
    if ($stmt->execute()) {
      $stmt->bind_result($id, $name, $phone, $email, $status, $info, $changed_at, $icon, $avatar, $full);
      $result = array();
      while($stmt->fetch()) {
        $res = array();
        $res["id"] = $id;
        $res["name"] = $name;
        $res["phone"] = $phone;
        $res["email"] = $email;
        $res["status"] = $status;
        if (isset($info) && !empty($info)) {
          $info = json_decode($info, TRUE);
        } else {
          $info = array();
        }
        $res["info"] = $info;
        $res["changed_at"] = $changed_at;
        $avatars = array();
        if ($full) $avatars["full"] = URL_HOME.path_fulls.$full;
        if ($avatar) $avatars["avatar"] = URL_HOME.path_avatars.$avatar;
        if ($icon) $avatars["icon"] = URL_HOME.path_icons.$icon;
        if (count($avatars)) {
          $res["avatars"] = $avatars;
        }
        $result[] = $res;
      }
      $stmt->close();
      $usersCount = $this->usersCount();
      return ["total" => $usersCount, "items" => $result];
    } else {
      return NULL;
    }
  }
  /**
   * Количество пользователей
   * @return int count
   */
  function usersCount() {
    $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users;");
    $result = 0;
    if ($stmt->execute()) {
      $stmt->bind_result($count);
      if ($stmt->fetch()) {
        $result = $count;
      } else {
        $stmt->close();
        return FALSE;
      }
      $stmt->close();
    } else {
      return FALSE;
    }
    return $result;
  }
}
?>