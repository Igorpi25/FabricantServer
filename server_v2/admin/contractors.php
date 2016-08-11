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
</head>  
<body>  
<?php
session_start();
include 'auth.php';
//Provides db (the instance of DbProfileHandler.php)
?>
<div class="page-header">
	<h1>Админка</h1>
</div>
<h2><a href="http://igorserver.ru/v2/admin/contractors.php">Поставшики/</a></h2>
<div class="row">
	<div class="col-md-6">
		<table class="table table-striped">
		<thead>
			<tr>
				<th>#</th>
				<th>id</th>
				<th>name</th>
				<th>details</th>
				<th>products</th>
			</tr>
		</thead>
		<tbody>
        	<!--tr>
				<th>0</th>
				<th>11</th>
				<th>asd</th>
				<th ><a href="contractor_details?id=11">details</a></th>
				<th><a href="contractor_products.php?id=11">products</a></th>
			</tr-->
			<?php
			$groups=$db->getAllGroups();
			$i=1;
			foreach($groups as $group) {
			//Contractors
			//if ($group["status"]==1) {
				echo '<tr>
					<td>'.$i++.'</td>
					<td>'.$group["id"].'</td>
					<td>'.$group["name"].'</td>                
					<td><a href="contractor_details?id='.$group["id"].'">details</a></td>
					<td><a href="contractor_products.php?id='.$group["id"].'">products</a></td>
				</tr>';
			//}
			}?>
		</tbody>
		</table>
	</div>
</div>

	<a href="add_group.php"><button id='add' type="button" class="btn btn-lg btn-default">Создать группу</button></a>
	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	<!-- Include all compiled plugins (below), or include individual files as needed>
	<script src="js/bootstrap.min.js"></script-->
</body>
</html>