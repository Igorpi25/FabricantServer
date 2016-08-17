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
	getProducts('<?php echo $_GET["id"]; ?>');
	var stringjson = '{"name":{"text":"БАТОН 8 ЗЛАКОВ"},"name_full":{"text":"БАТОН 8 ЗЛАКОВ"},"price":51,"summary":{"text":"Размер - 28х10х7 см. Хлеб 8 злаков – это хлебобулочное изделие, которое выпекается из мучной композиции, которая в своем составе содержит 8 злаков, это: мука пшеничная, пшеничные хлопья, соевые хлопья, семена подсолнечника, пшеничная сухая клейковина, семена льна, хлопья ржаные, кукуруза экструдированная. Состав данного хлеба включает такой состав зерновых злаков, что данное хлебобулочное изделие приносит организму неоценимую пользу, ведь злаковые культуры имеют богатый витаминно-минеральный комплекс. Витамины группы В, Е, А, РР и природные соединения: холин, молибден, железо, йод, фосфор; калий, кальций, натрий, делают хлеб источником полезных и необходимых компонентов. Полезные свойства: Зерновой хлеб 8 злаков лучше других сортов хлеба насыщает организм витаминами группы В и микроэлементами. Сбалансированный состав данного хлеба помогает человеческому организму усваивать полезные витаминные соединения. Постоянное употребление хлеба 8 злаков положительно воздействует на работу желудочно-кишечного тракта. Природная клетчатка, содержащаяся в рассматриваемом хлебе, делает его необычайно полезным и особенным. Хлеб 8 злаков – полезный продукт питания в рационе человека любого возраста."},"icon":{"image_url":"http://igorserver.ru/v2/images/products/41_21_icon.jpg"},"details":[{"type":2,"slides":[{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_154401.jpg"},"title":{"text":"Фото 1"}},{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_154802.jpg"},"title":{"text":"Фото 2"}},{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_154903.jpg"},"title":{"text":"Фото 3"}},{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_155304.jpg"},"title":{"text":"Фото 4"}},{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_155405.jpg"},"title":{"text":"Фото 5"}},{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_155706.jpg"},"title":{"text":"Фото 6"}},{"photo":{"image_url":"http://igorserver.ru/v2/images/products/41_21_156208.jpg"},"title":{"text":"Фото 7"}}]},{"type":1,"title":{"text":"Характеристики"},"photo":{"visible":false},"value":{"text":"СоставВода (фильтрованная), дрожжи, соль, мука (высший сорт), смесь 8 злаков.Пищевая ценность в 100 гБелки – 13,7 г, Жиры – 5,2 г, углеводы – 43 гЭнергетическая ценность в 100 г269 ккалРазмер28х10х7 смВес400 г"}},{"type":1,"title":{"text":"Описание"},"photo":{"visible":false},"value":{"text":"Хлеб 8 злаков – это хлебобулочное изделие, которое выпекается из мучной композиции, которая в своем составе содержит 8 злаков, это: мука пшеничная, пшеничные хлопья, соевые хлопья, семена подсолнечника, пшеничная сухая клейковина, семена льна, хлопья ржаные, кукуруза экструдированная. Состав данного хлеба включает такой состав зерновых злаков, что данное хлебобулочное изделие приносит организму неоценимую пользу, ведь злаковые культуры имеют богатый витаминно-минеральный комплекс. Витамины группы В, Е, А, РР и природные соединения: холин, молибден, железо, йод, фосфор; калий, кальций, натрий, делают хлеб источником полезных и необходимых компонентов. Полезные свойства: Зерновой хлеб 8 злаков лучше других сортов хлеба насыщает организм витаминами группы В и микроэлементами. Сбалансированный состав данного хлеба помогает человеческому организму усваивать полезные витаминные соединения. Постоянное употребление хлеба 8 злаков положительно воздействует на работу желудочно-кишечного тракта. Природная клетчатка, содержащаяся в рассматриваемом хлебе, делает его необычайно полезным и особенным. Хлеб 8 злаков – полезный продукт питания в рационе человека любого возраста."}}]}';
	
	var schemajson = JSON.parse(stringjson);
	
	//console.log(schemajson['name']['text']);
	// Initialize the editor with a JSON schema
	var editor = new JSONEditor(document.getElementById('editor_holder'), {
		theme: 'bootstrap3',
		iconlib: "bootstrap3",
		//startval: starting_value
		schema: {
			"type": "object",
			"title": "Product",
			//format: "grid", "tabs", "normal", "table"
			"format": "frid",
			"options": {
				"layout": "grid"
			},
			"properties": {
				"name": {
					"type": "object",
					"description": "Product name",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 4
					},
					"properties": {
						"text": {
							"type": "string",
							"format": "text",
							"minLength": 2
						}
					},
					"propertyOrder": 1
				},
				"name_full": {
					"type": "object",
					"description": "Product full name",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 8
					},
					"properties": {
						"text": {
							"type": "string",
							"format": "textarea",
							"minLength": 2
						}
					},
					"propertyOrder": 2
				},
				"price": {
					"type": "number",
					"options": {
						"grid_columns": 2
					},
					"minimum": 0,
					"maximum": 99999,
					"propertyOrder": 5
				},
				"summary": {
					"type": "object",
					"description": "Product summary information",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 12
					},
					"properties": {
						"text": {
							"type": "string",
							"format": "textarea"
						}
					},
					"propertyOrder": 3
				},
				"icon": {
					"type": "object",
					"description": "Product icon image url",
					"options": {
						"disable_collapse": true,
						"disable_edit_json": true,
						"disable_properties": true,
						"grid_columns": 12
					},
					"properties": {
						"image_url": {
							"type": "string",
							"format": "url"
						}
					},
					"propertyOrder": 4
				},
				"details": {
					"type": "array",
					"title": "Details",
					"options": {
						"grid_columns": 10
					},
					"format": "tabs",
					"items": {
						"type": "object",
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
										"minimum": 0
									},
									"slides": {
										"type": "array",
										"title": "Slides",
										"format": "tabs",
										"items": {
											"type": "object",
											"options": {
												"disable_collapse": true,
												"disable_edit_json": true,
												"disable_properties": true
											},
											"properties": {
												"photo": {
													"type": "object",
													"options": {
														"disable_collapse": true,
														"disable_edit_json": true,
														"disable_properties": true
													},
													"properties": {
														"image_url": {
															"type": "string",
															"format": "url"
														}
													}
												},
												"title": {
													"type": "object",
													"options": {
														"disable_collapse": true,
														"disable_edit_json": true,
														"disable_properties": true
													},
													"properties": {
														"text": {
															"type": "string",
															"format": "text",
															"minLength": 1
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
										"minimum": 0
									},
									"title": {
										"type": "object",
										"options": {
											"disable_collapse": true,
											"disable_edit_json": true,
											"disable_properties": true
										},
										"properties": {
											"text": {
												"type": "string",
												"format": "textarea"
											}
										}
									},
									"photo": {
										"type": "object",
										"options": {
											"disable_collapse": true,
											"disable_edit_json": true,
											"disable_properties": true
										},
										"properties": {
											"visible": {
												"type": "boolean",
												"default": false
											}
										}
									},
									"value": {
										"type": "object",
										"options": {
											"disable_collapse": true,
											"disable_edit_json": true,
											"disable_properties": true
										},
										"properties": {
											"text": {
												"type": "string",
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
			},
			"definations": {
				"slider": {
					"properties": {
						"type": {
							"type": "integer"
						},
						"slides": {
							"type": "array",
							"title": "Slides",
							"items": {
								"type": "object",
								"properties": {
									"photo": {
										"type": "object",
										"properties": {
											"image_url": {
												"type": "string"
											}
										}
									},
									"title": {
										"type": "object",
										"properties": {
											"text": {
												"type": "string"
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
				"info": {
					"properties": {
						"type": {
							"type": "integer"
						},
						"title": {
							"type": "object",
							"properties": {
								"text": {
									"type": "string"
								}
							}
						},
						"photo": {
							"type": "object",
							"properties": {
								"visible": {
									"type": "boolean"
								}
							}
						},
						"value": {
							"type": "object",
							"properties": {
								"text": {
									"type": "string"
								}
							}
						}
					},
					"required": ["type", "title", "photo", "value"],
					"additionalProperties": false
				}
			}
		}
		//,startval: start_value
	});
	
	//editor.setValue(schemajson);
});
</script>
</body>
</html>