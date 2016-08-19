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
	// Get contractor id
	var contructorId = '<?php echo $_GET["id"]; ?>';
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
	<table id="cp" class="table table-striped table-hover">
	<thead>
		<tr>
			<th>#</th>
			<th>id</th>
			<th>name</th>
			<!--th>status</th-->
			<th>price</th>
            <!--th>info</th-->
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
$(document).ready(function() {
	getProducts(contructorId);
	
	$('#cp tr').click(function() {
		alert($(this).html());
	});
	
	
	// Get products list with ajax+php
	function getProducts(contructorId, productId=null) {
		$.ajax({
			url: "getProducts.php",
			data: {contructorId: contructorId, productId: productId},
			cache: false,
			async: true,
			dataType: 'json',
			success: function(jsondata) {
				showProductsTable(jsondata);
			}
		});
	}
	// Create table with products
	function showProductsTable(jsondata) {
		//parsedJSON = jsondata;
		$('#cp tbody:first').empty();
		$.each(jsondata, function(i, value) {
			var changedat = new Date(value["changed_at"]*1000).toLocaleDateString();
			$('#cp').append('<tbody><tr><td></td><td>' + value["id"] + '</td><td>' + value["name"] + '</td><td>' + value["price"] + '</td><td>' + changedat + '</td></tr></tbody>');
			//parsedJSON[i]["info"] = JSON.parse(value["info"]);
		});
	}
	// Get product object with ajax+php
	function getProduct(id) {
		$.ajax({
			url: "getProducts.php",
			data: {id: id},
			cache: false,
			dataType: 'json',
			success: function(jsondata) {
				showProductsTable(jsondata);
			}
		});
	}
	// Create table with JS example
	function showProductsTableJS(jsondata) {
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
	// xlmhttp example from w3schools
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
	// Specify upload handler
	JSONEditor.defaults.options.upload = function(type, file, cbs) {
		var tick = 0;
		var tickFunction = function() {
			tick += 1;
			console.log('progress: ' + tick);
			if (tick < 100) {
			cbs.updateProgress(tick);
				window.setTimeout(tickFunction, 50)
			} else if (tick == 100) {
				cbs.updateProgress();
				window.setTimeout(tickFunction, 500)
			} else {
				cbs.success('http://www.example.com/images/' + file.name);
			}
		};
		window.setTimeout(tickFunction)
	};
	
	// Initialize the editor with a JSON schema
	/*var editor = new JSONEditor(document.getElementById('editor_holder'), {
		theme: 'bootstrap3',
		iconlib: "bootstrap3",
		schema: {
			"type": "object",
			"title": "Product",
			"format": "grid",
			"options": {
				"layout": "grid"
			},
			"properties": {
				"name": {
					"type": "object",
					"title": "Product name",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 4
					},
					"properties": {
						"text": {
							"type": "string",
							"title": "Enter the name of product",
							"format": "text",
							"minLength": 2,
							"maxLength": 255
						}
					},
					"propertyOrder": 1
				},
				"name_full": {
					"type": "object",
					"title": "Product full name",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 8
					},
					"properties": {
						"text": {
							"type": "string",
							"title": "Enter the full name of product",
							"format": "text",
							"maxLength": 255
						}
					},
					"propertyOrder": 2
				},
				"price": {
					"type": "number",
					"title": "Product price",
					"options": {
						//"input_width": "90%",
						"grid_columns": 2
					},
					"minimum": 0,
					"maximum": 99999,
					"propertyOrder": 5
				},
				"summary": {
					"type": "object",
					"title": "Product summary information",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 8
					},
					"properties": {
						"text": {
							"type": "string",
							"title": "Enter summary information of product",
							"format": "textarea",
							"options": {
								"expand_height": true
							},
							"minLength": 2
						}
					},
					"propertyOrder": 4
				},
				"icon": {
					"type": "object",
					"title": "Product icon image url",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 4
					},
					"properties": {
						"image_url": {
							"type": "string",
							"title": "Enter icon image url of product",
							"format": "url",
							"media": {
								//"binaryEncoding": "base64",
								"type": "image/png"
							},
							"options": {
								"upload": true
							},
							"links": [
								{
									"href": "{{self}}"
								}
							]
						}
					},
					"propertyOrder": 3
				},
				"details": {
					"type": "array",
					"title": "Details",
					"options": {
						"grid_columns": 10
					},
					"propertyOrder": 6,
					"format": "tabs",
					"items": {
						"type": "object",
						"title": "Detail",
						"options": {
							"disable_collapse": true,
							"disable_edit_json": true,
							"disable_properties": true
						},
						"oneOf": [
							{
								"title": "Slider",
								"properties": {
									"type": {
										"type": "integer",
										"enum": [2],
										"options": {
											"hidden": true
										}
									},
									"slides": {
										"type": "array",
										"title": "Slides",
										"format": "tabs",
										"uniqueItems": true,
										"minItems": 1,
										"items": {
											"type": "object",
											"title": "Slide",
											"options": {
												"disable_collapse": true,
												"disable_edit_json": true,
												"disable_properties": true
											},
											"properties": {
												"photo": {
													"type": "object",
													"title": "Product photo url",
													"options": {
														"disable_collapse": true,
														"disable_edit_json": true,
														"disable_properties": true
													},
													"properties": {
														"image_url": {
															"type": "string",
															"title": "Enter product photo url",
															"format": "url"
														}
													}
												},
												"title": {
													"type": "object",
													"title": "Product photo description",
													"options": {
														"disable_collapse": true,
														"disable_edit_json": true,
														"disable_properties": true
													},
													"properties": {
														"text": {
															"type": "string",
															"title": "Enter description of product photo",
															"format": "textarea"
														}
													}
												}
											}
										}
									}
								},
								"required": ["type", "slides"],
								"additionalProperties": false
							},
							{
								"title": "Info",
								"properties": {
									"type": {
										"type": "integer",
										"enum": [1],
										"options": {
											"hidden": true
										}
									},
									"title": {
										"type": "object",
										"title": "Information title",
										"options": {
											"disable_collapse": true,
											"disable_edit_json": true,
											"disable_properties": true
										},
										"properties": {
											"text": {
												"type": "string",
												"title": "Enter information title",
												"format": "text",
												"minLength": 1
											}
										}
									},
									"photo": {
										"type": "object",
										"title": "Information photo",
										"description": "Default selected false, not yet realized",
										"options": {
											"disable_collapse": true,
											"disable_edit_json": true,
											"disable_properties": true
										},
										"properties": {
											"visible": {
												"type": "boolean",
												"format": "checkbox",
												"default": false
											}
										}
									},
									"value": {
										"type": "object",
										"title": "Information text",
										"options": {
											"disable_collapse": true,
											"disable_edit_json": true,
											"disable_properties": true
										},
										"properties": {
											"text": {
												"type": "string",
												"title": "Enter information text",
												"format": "textarea"
											}
										}
									}
								},
								"required": ["type", "title", "photo", "value"],
								"additionalProperties": false
							}
						]
					}
				}
			}
		}
		//,startval: start_value
	});
	// 
	//editor.setValue(schemajson);
	// If fullname input is empty, then will be filled by name input value
	var name = editor.getEditor('root.name.text');
	editor.watch(name.path, function() {
		name_full = editor.getEditor('root.name_full.text');
		if (name.getValue().length >= 2 && name_full.getValue() == "") {
			name_full.setValue(name.getValue());
		}
	});*/
});
</script>
</body>
</html>