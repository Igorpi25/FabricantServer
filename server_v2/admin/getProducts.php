<?php
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
$fdb = new DbHandlerFabricant();
$contractorProducts=$fdb->getProductsOfContractor($_GET['id']);

$infos=array();
/*foreach($contractorProducts as $product) {
	echo '<tr>
		<td>'.$id=$product["id"].
		'</td><td>'.$contractorid=$product["contractorid"].
		'</td><td>'.$name=$product["name"].
		'</td><td>'.$status=$product["status"].
		'</td><td>'.$price=$product["price"].
		//'</td><td>'.$info=$product["info"].
		'</td><td>'.$changed_at=$product["changed_at"].
		'</td><tr>';
		$infos[]=$product["info"];
}*/
echo json_encode($contractorProducts);
?>