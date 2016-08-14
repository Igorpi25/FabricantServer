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
	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
    <!-- JSON-Editor plugins -->
    <script src="../libs/json-editor/jsoneditor.js"></script>
    
    <script>
	function getProductJSON(id) {
		var info = null;
		var xmlhttp = new XMLHttpRequest();
			xmlhttp.onreadystatechange = function() {
				if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
					$.each(xmlhttp.responseText, function(index, value) {
						if (index == "info")
							info = value;
					});
				}
			}
			xmlhttp.open("GET", "getProducts.php?id="+id, true);
			xmlhttp.send();
		return info;
	}
	
	function getProducts(id) {
		$.ajax( {
        	url: "getProducts.php",
			data: {id: id},
			cache: false,
			dataType: 'json',
            success: function(jsondata) {
				showProductsTable(jsondata);
            }
        });
	}
	
	function showProductsTable(jsondata) {
		var l = jsondata.length;
		$('#cp tbody:first').empty();
		var tbody = document.getElementById('cp').getElementsByTagName('tbody')[0];
		
		for (var i = 0; i < l; i++) {
			var nrow = tbody.insertRow(-1);
			nrow.insertCell(-1).innerHTML = i + 1;
			$.each(jsondata[i], function(index, value) {
				/*if (index == "info") {
					$.each(JSON.parse(value), function(i, val) {
						if (i == "summary")
							nrow.insertCell(-1).innerHTML = val;
					});
				}*/
				nrow.insertCell(-1).innerHTML = value;
			});
		}
	}
	
	function getProductsXML(index) {
		if (index.length == 0) {
			document.getElementById("txtHint").innerHTML = "";
			return;
		} else {
			var xmlhttp = new XMLHttpRequest();
			xmlhttp.onreadystatechange = function() {
				if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
					document.getElementById("txtHint").innerHTML = xmlhttp.responseText;
				}
			}
			xmlhttp.open("GET", "getProducts.php?id="+index, true);
			xmlhttp.send();
		}
	}
	</script>
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
?>
<div class="page-header">
	<h1>Продукты</h1>
</div>
<p>Contractor products</p>
<div class="row">
<div class="container">
	<table id="cp" class="table">
	<thead>
		<tr>
			<th>#</th>
			<th>id</th>
			<th>name</th>
			<!--th>status</th-->
			<th>price</th>
            <th>info</th>
            <th>changed at</th>
		</tr>
	</thead>
	<tbody>
	</tbody>
	</table>
    <div id="editor_holder">
    </div>
</div>
</div>
<script>
$(document).ready(function(e) {
	var starting_value = getProductJSON('<?php echo $_GET["id"]; ?>');
	alert(starting_value);
	// Initialize the editor with a JSON schema
	/*var editor = new JSONEditor(document.getElementById('editor_holder'), {
		theme: 'bootstrap3',
		iconlib: "bootstrap3",
		startval: starting_value
	});*/
});
</script>
<script>

    </script>
</body>
</html>