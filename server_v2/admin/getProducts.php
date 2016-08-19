<?php
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
$fdb = new DbHandlerFabricant();
$contractorProducts=$fdb->getProductsOfContractor($_GET['contructorId']);
echo json_encode($contractorProducts);
?>