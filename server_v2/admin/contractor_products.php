<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Constractor products</title>
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
	var contructorid = '<?php echo $_GET["id"]; ?>';
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
	header("Location: http://".$_SERVER['HTTP_HOST']."/v2/admin/contractors.php");
	exit;
}
$fdb = new DbHandlerFabricant();
$contractorProducts=$fdb->getProductsOfContractor($_GET['id']);
if($contractorProducts==NULL) {
	header("Location: http://".$_SERVER['HTTP_HOST']."/v2/admin/contractors.php");
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
			<button id="newProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus-sign"></span> Create</button>
			<button id="editProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-edit"></span> Edit</button>
            <button id="removeProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-minus-sign"></span> Delete</button>
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
					<button id="updateModalData" type="button" class="btn btn-success"><span class="glyphicon glyphicon-ok"></span> Save changes</button>
					<button id="cancelModalData" type="button" class="btn btn-default" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span> Cancel</button>
				</div>
			</div>
		</div>
	</div>
<!-- Modal-->

<script>
$(document).ready(function() {
//$(document).ready(function (e) {

	$("#uploadimage").on('submit',function(e) {
		e.preventDefault();
		//new FormData(this)
		console.log(new FormData(this))
		//console.log(this);
	});


	// Setting edit and remove button disabled
	$('#editProduct').prop('disabled', true);
	$('#removeProduct').prop('disabled', true);
	// Getting products list
	getProducts(contructorid);
	// Create event listener on modal creating
	$('#productModal').on('shown.bs.modal', function () {
		editor.on('change', editorChange);
		$('#updateModalData').prop('disabled', true);
	});
	// Remove event listener on modal hide
	$('#productModal').on('hide.bs.modal', function () {
		editor.off('change', editorChange);
		$('#editProduct').prop('disabled', true);
		$('#removeProduct').prop('disabled', true);
		$('input:checkbox').prop('checked', false);
	});
	// 'Update' button clicked
	$('#updateModalData').on('click', function() {
		var value = editor.getValue();
		updateProduct(value.id, value.name, value.price, JSON.stringify(value.info), value.status);
		$('#productModal').modal('hide');
	});
	// 'Create' button clicked
	$('#newProduct').on('click', function() {
		createProduct(contructorid);
	});
	// 'Edit' button clicked
	$('#editProduct').on('click', function() {
		editModal($('input:checkbox:checked').closest('tr').data());
	});
	// 'Delete' button clicked
	$('#removeProduct').on('click', function() {
		removeProduct($('input:checkbox:checked').closest('tr').data('id'));
	});
	// On checkbox check/uncheck enable/disable edit and remove button
	$('#ptable tbody:first').on('change', ':checkbox', function() {
		var status = $(this).prop('checked');
		$('input:checkbox').prop('checked', false);
		$(this).prop('checked', status);
		$('#editProduct').prop('disabled', !status);
		$('#removeProduct').prop('disabled', !status);
		/*if ($(this).is(':checked')) {
			//console.log($(this).prop('checked'));
		}
		else if ($(this).is(':not(:checked)')) {
			//console.log($(this).prop('checked'));
		}*/
	});
	// Show modal window of product on row click
	$('#ptable tbody:first').on('click', 'td:not(:first-child)', function() {
		editModal($(this).parent().data());
	});
	//
	function editModal(data) {
		editor.setValue(data);
		$('.btn-primary').text(' Save changes');
		$('#productModal').modal({backdrop: 'static'});
	}
	// Get products list with ajax+php
	function getProducts(contractorid) {
		$.ajax({
			method: "GET",
			url: "http://igorserver.ru/v2/admin/products",
			data: {contractorid: contractorid},
			cache: false,
			dataType: "json",
			success: function(response) {
				if (response["error"] == false)
					showProductsTable(response["products"]);
			},
			error: function(jqXhr, textStatus, errorThrown ) {
				console.log(errorThrown);
			}
		});
	}
	// Create product with ajax+php
	function createProduct(contractorid) {
		$.ajax({
			method: "POST",
			url: "http://igorserver.ru/v2/admin/products",
			data: {contractorid: contractorid},
			cache: false,
			dataType: "json",
			success: function(response) {
				editor.setValue(JSON.parse(startval));
				var cId = editor.getEditor("root.contractorid");
				cId.setValue(response["contractorid"]);
				var id = editor.getEditor("root.id");
				id.setValue(response["id"]);
				$('.btn-primary').text(' Create product');
				$('#productModal').modal({backdrop: 'static'});
			},
			error: function(jqXhr, textStatus, errorThrown ) {
				console.log(errorThrown);
			}
		});
	}
	// Update product with ajax+php
	function updateProduct(id, name, price, info, status) {
		$.ajax({
			method: "PUT",
			url: "http://igorserver.ru/v2/admin/products/" + id,
			data: {id: id, name: name, price: price, info: info, status: status},
			cache: false,
			dataType: "json",
			success: function(response) {
				if (response["error"] == false)
					getProducts(contructorid);
			},
			error: function(jqXhr, textStatus, errorThrown) {
				console.log(errorThrown);
			}
		});
	}
	// Update product with ajax+php
	function removeProduct(id) {
		$.ajax({
			method: "DELETE",
			url: "http://igorserver.ru/v2/admin/products/" + id,
			cache: false,
			dataType: "json",
			success: function(response) {
				if (response["error"] == false)
					getProducts(contructorid);
				alert(response["message"]);
			},
			error: function(jqXhr, textStatus, errorThrown ) {
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
			$('#ptable tbody:first').append('<tr><td><input type="checkbox" value=""></td><td>' + value["id"] + '</td><td>' + value["name"] + '</td><td>' + value["price"] + '</td><td>' + value["changed_at"] + '</td></tr>');
			$('#ptable tbody:first').find('tr:last').data(value);
		});
	}
	// Specify upload handler
	JSONEditor.defaults.options.upload = function(type, file, cbs) {
		cbs.updateProgress();
		var formdata = new FormData();
		formdata.append("image", file);
		var prefix = editor.getEditor('root.contractorid').getValue().toString() + editor.getEditor('root.id').getValue().toString();
		$.ajax({
			method: "POST",
			url: "http://igorserver.ru/v2/admin/products/upload/" + prefix,
			data: formdata,
			cache: false,
			contentType: false,
			processData : false,
			dataType: "json",
			success: function(response) {
				if (response["error"] == false)
					cbs.success("http://" + response["url"]);
			},
			error: function(jqXhr, textStatus, errorThrown ) {
				console.log(errorThrown);
			}
		});
	};
	
	// Initialize the editor with a JSON schema
	var editor = new JSONEditor(document.getElementById('editor_holder'), {
		theme: 'bootstrap3',
		iconlib: "bootstrap3",
		schema: {
			"type": "object",
			"title": "Product",
			"format": "grid",
			"options": {
				"disable_edit_json": true
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
						"disable_edit_json": true,
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
									//"minLength": 2,
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
									}
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
	});
	// Would change the option and call `onChange`
	editor.setOption('show_errors', 'always');
	// Disable not necessary fields
	editor.getEditor('root.contractorid').disable();
	editor.getEditor('root.id').disable();
	editor.getEditor('root.status').disable();
	editor.getEditor('root.changed_at').disable();
	editor.getEditor('root.info.icon').disable();
	
	//editor.on('change', editorChange);

	function editorChange() {
		var errors = editor.validate();
		if(!errors.length) {
			$('#updateModalData').prop('disabled', false);
		}
	}

	//editor.watch(function(){
	//	console.log('this watched');
	//});

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

});
</script>
</body>
</html>