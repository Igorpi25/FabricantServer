<?php
namespace CRM\Controller;

require_once dirname(__FILE__) . '/../services/ProductService.php';

class ProductController extends BaseController
{
    public function __construct($app)
    {
        parent::__construct($app);
        $app->get('', [$this, 'getAllProducts']);
        $app->get('/contractors/:id', [$this, 'getContractorProducts']);

        $app->get('/articles', [$this, 'getProductsArticles']);
    }
    /**
     * Все продукты
     * url - /products
     * @method GET
     * @return Json response
     */
    public function getProductsArticles()
    {
        $articles = $this->app->request()->get('articles');
        try {
            $articles = implode(",", $articles);
            $db = new \CRM\Service\ProductService();
            $result = $db->fetchProductsArticles($articles);
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
     * Все продукты
     * url - /products
     * @method GET
     * @return Json response
     */
    public function getAllProducts()
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
            $db = new \CRM\Service\ProductService();
            $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
            $result = $db->fetchAllProducts($sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
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
     * Все продукты поставщика
     * url - /products/contractors/:id
     * @method GET
     * @return Json response
     */
    public function getContractorProducts($groupid)
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
            $db = new \CRM\Service\ProductService();
            $offset = ($page > 1) ? (intval($page) - 1) * intval($rows_per_page) : 0;
            $result = $db->fetchProductsOfContrator($groupid, $sort_by, $order, intval($rows_per_page), intval($offset), $queryString);
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
     * Проверка сортируемого столбца на корректность
     * @return bool
     */
    function isSortableCol($colName)
    {
        if ($colName == 'id' || $colName == 'contractorid' || $colName == 'name' || $colName == 'status' ||
            $colName == 'price' || $colName == 'code1c' || $colName == 'article' || $colName == 'created_at' || $colName == 'changed_at') {
            return TRUE;
        }
        return FALSE;
    }
}
?>