<?php
namespace CRM\Service;

require_once dirname(__FILE__) . '/../../include/DbHandler.php';

class GroupService extends \DbHandler
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Get groups of user
     */
    public function getGroupsOfUser($user_id)
    {
        $stmt = $this->conn->prepare("
        SELECT g.id AS id, g.name AS name, g.address AS address, g.phone AS phone, g.status AS status, 
            u.status AS status_in_group, g.type AS type, g.created_at AS created_at, g.changed_at AS changed_at, 
            a.filename_icon AS filename_icon, a.filename_avatar AS filename_avatar, a.filename_full AS filename_full
        FROM
            group_users AS u
        INNER JOIN groups g ON u.groupid = g.id
        LEFT OUTER JOIN avatars a ON g.avatar = a.id
        WHERE ( (u.userid = ?) AND ((u.status=0)||(u.status=1)||(u.status=2)||(u.status=8)) )
        GROUP BY u.groupid
    ");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $address, $phone, $status, $status_in_group, $type, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["address"] = $address;
                $res["phone"] = $phone;
                $res["status"] = $status;
                $res["status_in_group"] = $status_in_group;
                $res["type"] = $type;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars['full'] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars['avatar'] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars['icon'] = URL_HOME . path_icons . $icon;
                if (count($avatars))
                    $res['avatars'] = $avatars;
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }
    /**
     * Get groups of user
     */
    public function getContractorsOfUser($user_id)
    {
        $stmt = $this->conn->prepare("
        SELECT g.id AS id, g.name AS name, g.address AS address, g.phone AS phone, g.status AS status, 
            u.status AS status_in_group, g.type AS type, g.created_at AS created_at, g.changed_at AS changed_at, 
            a.filename_icon AS filename_icon, a.filename_avatar AS filename_avatar, a.filename_full AS filename_full
        FROM
            group_users AS u
        INNER JOIN groups g ON u.groupid = g.id
        LEFT OUTER JOIN avatars a ON g.avatar = a.id
        WHERE g.type = 0 ( (u.userid = ?) AND ((u.status=0)||(u.status=1)||(u.status=2)||(u.status=8)) )
        GROUP BY u.groupid
    ");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $address, $phone, $status, $status_in_group, $type, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["address"] = $address;
                $res["phone"] = $phone;
                $res["status"] = $status;
                $res["status_in_group"] = $status_in_group;
                $res["type"] = $type;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars['full'] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars['avatar'] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars['icon'] = URL_HOME . path_icons . $icon;
                if (count($avatars))
                    $res['avatars'] = $avatars;
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }
    /**
     * Все группы для CRM
     */
    public function fetchAllGroups()
    {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.phone, g.address, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
        ;");
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = $info;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars["full"] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars["avatar"] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars["icon"] = URL_HOME . path_icons . $icon;
                if (count($avatars)) {
                    $res["avatars"] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }
    /**
     * Все поставщики для CRM
     * без удаленных
     */
    public function fetchAllContractors()
    {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.phone, g.address, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
            WHERE g.type = 0 AND g.status <> 4
        ;");
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = $info;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars["full"] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars["avatar"] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars["icon"] = URL_HOME . path_icons . $icon;
                if (count($avatars)) {
                    $res["avatars"] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }
    /**
     * Все заказчики для CRM
     * без удаленных
     */
    public function fetchAllCustomers()
    {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.phone, g.address, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
            WHERE g.type = 1 AND g.status <> 4
        ;");
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = $info;
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars["full"] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars["avatar"] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars["icon"] = URL_HOME . path_icons . $icon;
                if (count($avatars)) {
                    $res["avatars"] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            return $result;
        }
        else {
            return NULL;
        }
    }
    /**
     * Все поставщики/заказчики для CRM
     * @param string $group_type тип группы
     * @return mixed
     */
    public function fetchGroupsByType($group_type)
    {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.phone, g.address, g.status, g.type, g.info, g.created_at, 
                    g.changed_at, a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
            WHERE g.type = ? 
            ORDER BY id 
        ;");
        $stmt->bind_param("i", $group_type);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = json_decode($info, TRUE);
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars["full"] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars["avatar"] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars["icon"] = URL_HOME . path_icons . $icon;
                if (count($avatars)) {
                    $res["avatars"] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            $groupsCount = $this->groupsCountByType($group_type);
            return ["total" => $groupsCount, "items" => $result];
        }
        else {
            return NULL;
        }
    }
    /**
     * Все поставщики/заказчики для CRM по страницам
     * @param string $group_type тип группы
     * @param string $sort_by название колонки сортировки
     * @param string $order направление сортировки ASC или DESC
     * @param int $rows_per_page количество строк в странице
     * @param int $offset смещение, номер страницы умноженная на $rows_per_page
     * @param string $query строка запроса фильтрации
     * @return mixed
     */
    public function fetchGroupsByTypeAndFilters($group_type, $sort_by, $order, $rows_per_page, $offset, $query)
    {
        $stmt = $this->conn->prepare("
            SELECT g.id, g.name, g.phone, g.address, g.status, g.type, g.info, g.created_at, g.changed_at, 
                a.filename_icon, a.filename_avatar, a.filename_full 
            FROM groups g 
            LEFT JOIN avatars a ON g.avatar = a.id 
            WHERE g.type = ? AND 
                (CONCAT_WS('|', g.id, g.name, g.phone, g.address, g.status) REGEXP ? ) 
            ORDER BY $sort_by $order 
            LIMIT ? OFFSET ? 
        ;");
        $stmt->bind_param("isii", $group_type, $query, $rows_per_page, $offset);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $name, $phone, $address, $status, $type, $info, $created_at, $changed_at, $icon, $avatar, $full);
            $result = array();
            while ($stmt->fetch()) {
                $res = array();
                $res["id"] = $id;
                $res["name"] = $name;
                $res["phone"] = $phone;
                $res["address"] = $address;
                $res["status"] = $status;
                $res["type"] = $type;
                $res["info"] = json_decode($info, TRUE);
                $res["created_at"] = $created_at;
                $res["changed_at"] = $changed_at;
                $avatars = array();
                if ($full) $avatars["full"] = URL_HOME . path_fulls . $full;
                if ($avatar) $avatars["avatar"] = URL_HOME . path_avatars . $avatar;
                if ($icon) $avatars["icon"] = URL_HOME . path_icons . $icon;
                if (count($avatars)) {
                    $res["avatars"] = $avatars;
                }
                $result[] = $res;
            }
            $stmt->close();
            $groupsCount = $this->groupsCountByType($group_type);
            return ["total" => $groupsCount, "items" => $result];
        }
        else {
            return NULL;
        }
    }
    /**
     * Количество группы по типу
     * @param int $type тип группы
     * @return int count
     */
    function groupsCountByType($type)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM groups WHERE type = ?;");
        $stmt->bind_param("i", $type);
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