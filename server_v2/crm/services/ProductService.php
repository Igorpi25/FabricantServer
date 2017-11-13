<?php
namespace CRM\Service;

require_once dirname(__FILE__) . '/../../include/DbHandler.php';

class ProductService extends \DbHandler
{
  public function __construct()
  {
    parent::__construct();
  }
  /**
   * Все продукты для CRM
   * @param string $sort_by название колонки сортировки
   * @param string $order направление сортировки ASC или DESC
   * @param int $rows_per_page количество строк в странице
   * @param int $offset смещение, номер страницы умноженная на $rows_per_page
   * @param string $query строка запроса фильтрации
   * @return mixed
   */
  public function fetchAllProducts($sort_by, $order, $rows_per_page, $offset, $query)
  {
    $stmt = $this->conn->prepare("
      SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.code1c, p.article, p.changed_at 
      FROM products p 
      WHERE 
          ( CONCAT_WS('|', p.id, p.contractorid, p.name, p.status, p.price, p.code1c, p.article, p.changed_at) REGEXP ? ) 
      ORDER BY $sort_by $order 
      LIMIT ? OFFSET ? 
    ;");
    $stmt->bind_param("sii", $query, $rows_per_page, $offset);
    if ($stmt->execute()) {
      $stmt->bind_result($id, $contractorid,$name, $status, $price, $info, $code1c, $article, $changed_at);
      $result = array();
      while ($stmt->fetch()) {
        $res = array();
				$res["id"] = $id;
				$res["contractorid"] = $contractorid;
				$res["name"] = $name;
				$res["status"] = $status;
				$res["price"] = $price;
				$res["info"] = json_decode($info, TRUE);;
				$res["code1c"] = $code1c;
				$res["article"] = $article;
				$res["changed_at"] = $changed_at;
				$result[] = $res;
      }
      $stmt->close();
      $productsCount = $this->productsCount();
      return ["total" => $productsCount, "items" => $result];
    }
    else {
      return NULL;
    }
  }
  /**
   * Все продукты поставщика для CRM
   * @param int $groupid ид группы
   * @param string $sort_by название колонки сортировки
   * @param string $order направление сортировки ASC или DESC
   * @param int $rows_per_page количество строк в странице
   * @param int $offset смещение, номер страницы умноженная на $rows_per_page
   * @param string $query строка запроса фильтрации
   */
  public function fetchProductsOfContrator($groupid, $sort_by, $order, $rows_per_page, $offset, $query)
  {
    $stmt = $this->conn->prepare("
      SELECT p.id, p.contractorid, p.name, p.status, p.price, p.info, p.code1c, p.article, p.changed_at 
      FROM products p 
      WHERE p.contractorid = ? AND p.status <> 0 AND 
          ( CONCAT_WS('|', p.id, p.contractorid, p.name, p.status, p.price, p.code1c, p.article, p.changed_at) REGEXP ? ) 
      ORDER BY $sort_by $order 
      LIMIT ? OFFSET ? 
    ;");
    $stmt->bind_param("isii", $groupid, $query, $rows_per_page, $offset);
    if ($stmt->execute()) {
      $stmt->bind_result($id, $contractorid, $name, $status, $price, $info, $code1c, $article, $changed_at);
      $result = array();
      while ($stmt->fetch()) {
        $res = array();
        $res["id"] = $id;
        $res["contractorid"] = $contractorid;
        $res["name"] = $name;
        $res["status"] = $status;
        $res["price"] = $price;
        $res["info"] = json_decode($info, TRUE);
        $res["code1c"] = $code1c;
        $res["article"] = $article;
        $res["changed_at"] = $changed_at;
        $result[] = $res;
      }
      $stmt->close();
      $productsCount = $this->productsCountOfContractor($groupid);
      return ["total" => $productsCount, "items" => $result];
    }
    else {
      return NULL;
    }
  }
  /**
   * Все продукты поставщика для CRM
   * @param int $groupid ид группы
   * @param string $sort_by название колонки сортировки
   * @param string $order направление сортировки ASC или DESC
   * @param int $rows_per_page количество строк в странице
   * @param int $offset смещение, номер страницы умноженная на $rows_per_page
   * @param string $query строка запроса фильтрации
   */
  public function fetchProductsArticles($articles)
  {
    $stmt = $this->conn->prepare("
      SELECT id, article 
      FROM products 
      WHERE id IN (?)
    ;");
    $stmt->bind_param("s", $articles);
    if ($stmt->execute()) {
      $stmt->bind_result($id, $article);
      $result = array();
      while ($stmt->fetch()) {
        $res = array();
        $res["id"] = $id;
        $res["article"] = $article;
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
   * Количество всех продуктов
   * @return int count
   */
  function productsCount()
  {
    $stmt = $this->conn->prepare("SELECT COUNT(*) FROM products WHERE status <> 0;");
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
   * Количество проукдты поставщика
   * @param int $groupid ид поставщика
   * @return int count
   */
  function productsCountOfContractor($groupid)
  {
    $stmt = $this->conn->prepare("SELECT COUNT(*) FROM products WHERE contractorid = ? AND status <> 0;");
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