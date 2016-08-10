<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Bootstrap 101 Template</title>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<!-- Optional theme -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
	<!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="../libs/json-editor/jsoneditor.js"></script>
</head>  
<body>
<?php
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
session_start();
include 'auth.php';

if (!isset($_GET['id'])){
	header("Location: http://".$_SERVER['HTTP_HOST']."/fabricant/server_v2/admin/contractors.php"); //"/v2/admin/contractors.php");
	exit;
}
$fdb = new DbHandlerFabricant();
$contractorProducts=$fdb->getProductsOfContractor($_GET['id']);
if($contractorProducts==NULL) {
	header("Location: http://".$_SERVER['HTTP_HOST']."/fabricant/server_v2/admin/contractors.php"); //"/v2/admin/contractors.php");
	exit;
}

//print_r($contractorProducts);
?>
<div class="container">
	<h2>Products</h2>
    <p>Contractor products</p>
    <table class="table">
	<thead>
		<tr>
			<th>#</th>
			<th>contractorid</th>
			<th>name</th>
			<th>status</th>
			<th>price</th>
            <!--th>info</th-->
            <th>changed at</th>
		</tr>
	</thead>
	<tbody>
	<?php
	$infos=array();
	foreach($contractorProducts as $product) {
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
	}
	$test=array('a'=>1);
	echo '<script> var infos='.json_encode($test).'</script>';
	?>
	</tbody>
	</table>
    <div id="testdiv" class="row" style="background:#EBEBEB">
    	
    </div>
</div>
<script>
	// Initialize the editor with a JSON schema
      /*var editor = new JSONEditor(document.getElementById('editor_holder'),{
        schema: {
          type: "object",
          title: "Car",
          properties: {
            make: {
              type: "string",
              enum: [
                "Toyota",
                "BMW",
                "Honda",
                "Ford",
                "Chevy",
                "VW"
              ]
            },
            model: {
              type: "string"
            },
            year: {
              type: "integer",
              enum: [
                1995,1996,1997,1998,1999,
                2000,2001,2002,2003,2004,
                2005,2006,2007,2008,2009,
                2010,2011,2012,2013,2014
              ],
              default: 2008
            }
          }
        }
      });*/
	  $("#testdiv").append("asasdasdasddasssssssssssssssssssssas");
	  infos=JSON.parse(infos);
	  alert("mthfk");
	  
</script>
</body>
</html>