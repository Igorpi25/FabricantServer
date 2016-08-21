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
	// This is the starting value for the editor
	// We will use this to seed the initial editor 
	// and to provide a "Create" button.
	var startval = '{"contractorid":0,"id":0,"name":"","status":1,"price":0,"info":{"name":{"text":""},"name_full":{"text":""},"price":0,"summary":{"text":""},"icon":{"image_url":""},"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]},"changed_at":""}';
	</script>
    <style>
		img {
			vertical-align: middle;
			border: 0;
			page-break-inside: avoid;
			max-width: 100% !important;
		}
	</style>
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
<div class="container">
	<p>Contractor products</p>
	<div class="row">
    	<div class="btn-group">
			<button id="createNewModal" type="button" class="btn btn-default btn-sm">Create</button>
			<button id="updateModal" type="button" class="btn btn-default btn-sm">Update</button>
            <button id="deleteProduct" type="button" class="btn btn-default btn-sm">Delete</button>
 		</div>
	</div>
	<div class="row">
        <table id="ptable" class="table table-striped table-hover">
        <thead>
            <tr>
                <th>#</th>
                <th>id</th>
                <th>name</th>
                <th>price</th>
                <th>changed at</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        </table>
    </div>
</div>
<!-- Modal-->
	<div class="modal fade" id="productModal" role="dialog">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title">Product</h4>
				</div>
				<div class="modal-body">
					<div id="editor_holder">
					</div>
				</div>
				<div class="modal-footer">
					<button id="updateData" type="button" class="btn btn-primary" data-dismiss="modal">Save changes</button>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				</div>
			</div>
		</div>
	</div>
<!-- Modal-->
<script>
$(document).ready(function() {
	getProducts(contructorId);
	
	$('#createNewModal').click(function(){
		//editor.setValue(JSON.parse(startval));
		//$('.btn-primary').text('Create product');
		//$(productModal).modal();
		//console.log(editor.getValue());
		createProduct(contructorId);
	});
	
	$('#updateModal').click(function(){
		alert('update clicked');
	});
	
	$('#deleteProduct').click(function(){
		alert('delete clicked');
	});
	
	$('#updateData').click(function(){
		var value = editor.getValue();
		updateProduct(value.id, value.name, value.price, JSON.stringify(value.info), value.status);
		getProducts(contructorId);
	});
	
	$('#ptable tbody:first').on('click', 'tr', function() {
		//editor.setValue(JSON.parse($(this).data('info')));
		editor.setValue($(this).data());
		$('.btn-primary').text('Save changes');
		$(productModal).modal();
	});
	
	
	// Get products list with ajax+php
	function getProducts(contractorId) {
		$.ajax({
			method: "GET",
			url: "getProducts.php",
			data: {contractorId: contractorId, type: 2},
			cache: false,
			dataType: "json",
			success: function(jsondata) {
				showProductsTable(jsondata);
			},
			error: function(jqXhr, textStatus, errorThrown ){
				console.log(errorThrown);
			}
		});
	}
	// Update product with ajax+php
	function updateProduct(id, name, price, info, status) {
		$.ajax({
			method: "POST",
			url: "getProducts.php",
			data: {id: id, name: name, price: price, info: info, status: status, type: 3},
			cache: false,
			dataType: "json",
			success: function(jsondata) {
				//console.log(jsondata);
			},
			error: function(jqXhr, textStatus, errorThrown ){
				console.log(errorThrown);
			}
		});
	}
	// Create product with ajax+php
	function createProduct(contractorId) {
		$.ajax({
			method: "GET",
			url: "getProducts.php",
			data: {contractorId: contractorId, type: 1},
			cache: false,
			dataType: "json",
			success: function(jsondata) {
				editor.setValue(JSON.parse(startval));
				var cId = editor.getEditor('root.contractorid');
				cId.setValue(jsondata['contractorId']);
				var id = editor.getEditor('root.id');
				id.setValue(jsondata['id']);
				$('.btn-primary').text('Create product');
				$(productModal).modal();
			},
			error: function(jqXhr, textStatus, errorThrown ){
				console.log(errorThrown);
			}
		});
	}
	// Create table with products and set data attributes to JQuery
	function showProductsTable(jsondata) {
		$('#ptable tbody:first').empty();
		$.each(jsondata, function(i, value) {
			value["changed_at"] = new Date(value["changed_at"]*1000).toLocaleDateString();
			value["info"] = JSON.parse(value["info"]);
			$('#ptable tbody:first').append('<tr><td></td><td>' + value["id"] + '</td><td>' + value["name"] + '</td><td>' + value["price"] + '</td><td>' + value["changed_at"] + '</td></tr>');
			$('#ptable tbody:first').find('tr:last').data(value);
		});
	}
	// Specify upload handler
	JSONEditor.defaults.options.upload = function(type, file, cbs) {
		var tick = 0;
		var tickFunction = function() {
			tick += 1;
			console.log('progress: ' + tick);
			if (tick < 100) {
			cbs.updateProgress(tick);
				window.setTimeout(tickFunction, 10)
			} else if (tick == 100) {
				cbs.updateProgress();
				window.setTimeout(tickFunction, 100)
			} else {
				cbs.success('http://www.example.com/images/' + file.name);
			}
		};
		window.setTimeout(tickFunction)
	};
	
	// Initialize the editor with a JSON schema
	var editor = new JSONEditor(document.getElementById('editor_holder'), {
		//ajax: true,
		theme: 'bootstrap3',
		iconlib: "bootstrap3",
		schema: {
			"type": "object",
			"title": "Product",
			"format": "grid",
			"options": {
				//"disable_edit_json": true
			},
			"properties": {
				"contractorid": {
					"type": "number",
					"title": "Contructor ID",
					"options": {
						"grid_columns": 3
					},
					"propertyOrder": 1
				},
				"id": {
					"type": "number",
					"title": "Product ID",
					"options": {
						"grid_columns": 3
					},
					"propertyOrder": 2
				},
				"name": {
					"type": "string",
					"title": "Product name",
					"format": "text",
					"options": {
						"grid_columns": 8
					},
					"minLength": 2,
					"maxLength": 255,
					"propertyOrder": 5
				},
				"status": {
					"type": "number",
					"title": "Product status",
					"enum": [1,2,3,4],
					"default": 1,
					"options": {
						"grid_columns": 3
					},
					"propertyOrder": 3
				},
				"price": {
					"type": "number",
					"title": "Product price",
					"options": {
						"grid_columns": 4
					},
					"minimum": 0,
					"maximum": 9999999999,
					"propertyOrder": 6
				},
				"info": {
					"type": "object",
					"title": "Product info",
					"format": "grid",
					"options": {
						//"disable_edit_json": true,
						"grid_columns": 12
					},
					"properties": {
						"name": {
							"type": "object",
							"title": "Product name",
							"options": {
								"disable_collapse": true,
								"disable_edit_json": true,
								"disable_properties": true,
								"grid_columns": 4,
								"hidden": true
							},
							"properties": {
								"text": {
									"type": "string",
									"title": "Enter the name of product",
									"format": "text",
									"template": "{{pname}}",
									"watch": {
										"pname": "root.name"
									},
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
								"input_width": "30%",
								"grid_columns": 12,
								"hidden": true
							},
							"template": "{{pprice}}",
							"watch": {
								"pprice": "root.price"
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
										//"expand_height": true,
										"input_height": "100px"
									},
									"minLength": 2
								}
							},
							"propertyOrder": 3
						},
						"icon": {
							"type": "object",
							"title": "Product icon image url",
							"description": "Frist image from slides array will be auto selected for icon",
							"options": {
								"disable_collapse": true,
								"disable_edit_json": true,
								"disable_properties": true,
								"grid_columns": 12
							},
							"properties": {
								"image_url": {
									"type": "string",
									"title": "Uploaded icon image url of product",
									//"format": "url",
									"media": {
										//"binaryEncoding": "base64",
										"type": "image/*"
									},
									"links": [
										{
											"href": "{{self}}",
											"mediaType": "image/*"
										}
									]
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
							"minItems": 1,
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
																	"title": "Upload product photo",
																	"format": "url",
																	"media": {
																		"type": "image/*"
																	},
																	"options": {
																		"upload": true
																	},
																	"links": [
																		{
																			"href": "{{self}}",
																			"mediaType": "image/*"
																		}
																	]
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
																	"format": "textarea",
																	"media": {
																		"type": "text/html"
																	},
																	"options": {
																		//"expand_height": true,
																		"input_height": "100px"
																	}
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
														//"format": "textarea",
														"media": {
															"type": "text/html"
														},
														"options": {
															//"expand_height": true,
															"input_height": "100px"
														}
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
					"propertyOrder": 7
				},
				"changed_at": {
					"type": "string",
					"format": "datetime",
					"options": {
						"grid_columns": 3
					},
					"propertyOrder": 4
				}
			}
		}
		/*schema: {
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
						"input_width": "50%",
						"grid_columns": 12
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
							//"options": {
							//	"expand_height": true
							//},
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
		}*/
		//,startval: start_value
	});
	editor.getEditor('root.contractorid').disable();
	editor.getEditor('root.id').disable();
	editor.getEditor('root.status').disable();
	editor.getEditor('root.changed_at').disable();
	editor.getEditor('root.info.icon').disable();
	
	// If fullname input is empty, then will be filled by name input value
	var name = editor.getEditor('root.name');
	editor.watch(name.path, function() {
		var name_full = editor.getEditor('root.info.name_full.text');
		if (name.getValue().length >= 2 && name_full.getValue() == "") {
			name_full.setValue(name.getValue());
		}
	});
	var fslide = editor.getEditor('root.info.details.0.slides.0.photo.image_url');
	editor.watch(fslide.path, function() {
		var icon = editor.getEditor('root.info.icon.image_url');
		icon.setValue(fslide.getValue());
	});
	
	//editor.setValue(schemajson);
});
</script>
</body>
</html>