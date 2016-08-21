<?php
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
$fdb = new DbHandlerFabricant();

if (isset($_GET['type']) && $_GET['type'] == 2 && isset($_GET['contractorId'])){
	echo json_encode($fdb->getProductsOfContractor($_GET['contractorId']));
}
else
	echo null;

if (isset($_POST['type']) && $_POST['type'] == 3){
	$fdb->updateProduct($_POST['id'], $_POST['name'], $_POST['price'], $_POST['info'], $_POST['status']);
	echo true;
}
else
	echo null;

if (isset($_GET['type']) && $_GET['type'] == 1 && isset($_GET['contractorId'])) {
	$insertedId = $fdb->createProduct($_GET['contractorId'], "", 0, "");
	$id = array('id'=>$insertedId, 'contractorId'=>$_GET['contractorId']);
	echo json_encode($id);
}
?>