<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Продукты</title>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<!-- Optional theme -->
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css">
	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	<!-- Latest compiled and minified JavaScript -->
	<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
	<!-- JSON-Editor plugins -->
	<script src="../libs/json-editor/jsoneditor.js"></script>
	<!--Table sort and filter plugin-->
	<script src="js/plugins.table.js"></script>

	<!--script src="js/scripts.contractorproducts.js"></script-->
	<!--SCEditor plugin and themes-->
	<script src="//cdn.jsdelivr.net/sceditor/1.4.3/jquery.sceditor.xhtml.min.js"></script>
	<link rel="stylesheet" href="//cdn.jsdelivr.net/sceditor/1.4.3/themes/default.min.css">
	<!--Correcting css styles-->
	<link rel="stylesheet" href="css/main.css">
</head>
<body>
<?php
require_once dirname(__FILE__).'/../include/DbHandlerFabricant.php';
session_start();
include 'auth.php';
if (!isset($_GET['id'])) {
	//header("Location: http://".$_SERVER['HTTP_HOST']."/v2/admin/contractors.php");
	header("Location: http://".$_SERVER['HTTP_HOST']."/v2/admin/");
	exit;
}
?>
<div class="container">
	<div class="page-header">
		<h4><a href=<?php $groupname=$db->getGroupById($_GET["id"]);echo '"http://'. $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] . '">' . $groupname[0]["name"];?></a></h4>
	</div>
	<div class="row">
		<div class="btn-group col-md-9">
			<button id="newProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus-sign"></span> Создать</button>
			<button id="editProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-edit"></span> Изменить</button>
			<button id="publishProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-share"></span> Опубликовать</button>
			<button id="removeProduct" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-minus-sign"></span> Удалить</button>
		</div>
		<div class="col-md-3 pull-right">
			<div class="form-group has-feedback">
				<input type="text" class="form-control" id="search" placeholder="Поиск">
				<span class="glyphicon glyphicon-search form-control-feedback"></span>
			</div>
		</div>
	</div>
	<div class="row">
		<table id="ptable" class="table table-hover table-condensed">
		<thead>
			<tr>
				<th class="col-md-1"># <span style="font-size:0.75em"></span></th>
				<th class="col-md-1" id="id">ИД <span style="font-size:0.75em"></span></th>
				<th class="col-md-6" id="name">Наименование <span style="font-size:0.75em"></span></th>
				<th class="col-md-1" id="price">Цена <span style="font-size:0.75em"></span></th>
				<th class="col-md-1" id="status">Статус <span style="font-size:0.75em"></span></th>
				<th class="col-md-2" id="changed_at">Последнее изменение <span style="font-size:0.75em"></span></th>
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
					<h4 class="modal-title">Продукты</h4>
				</div>
				<div class="modal-body">
					<div id="editor_holder">
					</div>
				</div>
				<div class="modal-footer">
					<button id="updateModalData" type="button" class="btn btn-success"><span class="glyphicon glyphicon-ok"></span> Сохранить</button>
					<button id="cancelModalData" type="button" class="btn btn-default" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span> Отмена</button>
				</div>
			</div>
		</div>
	</div>
<!-- Modal-->

<script>
// This is the starting value for the editor
// We will use this to seed the initial editor
// and to provide a "Create" button.
var starting_value = {"contractorid":0,"id":0,"name":"","status":1,"changed_at":"","price":0,"info":{"name":{"text":""},"name_full":{"text":""},"price":0,"summary":{"text":""},"icon":{"image_url":""},"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]}};
// Get contractor id
var contructorid = '<?php echo $_GET["id"]; ?>';

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
			editor.setValue(starting_value);
			var cId = editor.getEditor("root.contractorid");
			cId.setValue(response["contractorid"]);
			var id = editor.getEditor("root.id");
			id.setValue(response["id"]);
			$('#updateModalData').text(' Создать');
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
		},
		error: function(jqXhr, textStatus, errorThrown ) {
			console.log(errorThrown);
		}
	});
}
// Publish product with ajax+php
function publishProduct(id) {
	$.ajax({
		method: "POST",
		url: "http://igorserver.ru/v2/admin/products/publish/" + id,
		cache: false,
		dataType: "json",
		success: function(response) {
			if (response["error"] == false)
				getProducts(contructorid);
		},
		error: function(jqXhr, textStatus, errorThrown ) {
			console.log(errorThrown);
		}
	});
	$('#editProduct').prop('disabled', true);
	$('#removeProduct').prop('disabled', true);
	$('#publishProduct').prop('disabled', true);
}
// Create table with products and set data attributes to JQuery
function showProductsTable(jsondata) {
	$('#ptable tbody:first').empty();
	$.each(jsondata, function(i, value) {
		value["changed_at"] = new Date(value["changed_at"]*1000).toLocaleDateString();
		value["info"] = JSON.parse(value["info"]);
		var trstat = (value["status"] == 4)?'danger':((value["status"] == 2)?'success':((value["status"] == 1)?'default':'warning'));
		$('#ptable tbody:first').append('<tr class="' + trstat +'"><td style="cursor:default"><input type="checkbox" value=""></td><td>' + value["id"] + '</td><td>' + value["name"] + '</td><td>' + value["price"] + '</td><td>' + value["status"] + '</td><td>' + value["changed_at"] + '</td></tr>');
		$('#ptable tbody:first').find('tr:last').data(value);
		$('#ptable .danger').find(':checkbox').prop('disabled', true);
	});
}
// Specify json-editor upload handler
JSONEditor.defaults.options.upload = function(type, file, cbs) {
	cbs.updateProgress();
	var formdata = new FormData();
	formdata.append("image", file);
	var prefix = editor.getEditor('root.contractorid').getValue().toString() + editor.getEditor('root.id').getValue().toString();
	$.ajax({
		method: "POST",
		url: "http://igorserver.ru/v2/admin/slides/upload/" + prefix,
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
// SCEditor settings
JSONEditor.plugins.sceditor = {
	"toolbar": 'bold,italic,underline,strike,subscript,superscript|left,center,right,justify|font,size,removeformat,color|bulletlist,orderedlist,table|horizontalrule,maximize,source'
} 
JSONEditor.plugins.sceditor.style = "//cdn.jsdelivr.net/sceditor/1.4.3/jquery.sceditor.default.min.css";

// Initialize the editor with a JSON schema
var editor = new JSONEditor(document.getElementById('editor_holder'), {
	ajax: true,
	theme: 'bootstrap3',
	iconlib: "bootstrap3",
	schema: { "$ref": "js/product.json" }
});
// Would change the option and call `onChange`
editor.setOption('show_errors', 'always');
// Setting default options on editor ready
editor.on('ready', function() {
	// Setting starting value
	editor.setValue(starting_value);
	// If fullname input is empty, then will be filled by name input value
	var name = editor.getEditor('root.name');
	editor.watch(name.path, function() {
		var name_full = editor.getEditor('root.info.name_full.text');
		if (name.getValue().length >= 2 && name_full.getValue() == "") {
			name_full.setValue(name.getValue());
		}
	});
	// Pick first slide image to icon image
	var fslide = editor.getEditor('root.info.details.0.slides.0.photo.image_url');
	editor.watch(fslide.path, function() {
		var icon = editor.getEditor('root.info.icon.image_url');
		icon.setValue(fslide.getValue());
	});
});
// Make create/save button active on editor data change
function editorChange() {
	var errors = editor.validate();
	if(!errors.length) {
		$('#updateModalData').prop('disabled', false);
	}
}
// Showing modal windows
function editModal(data) {
	editor.setValue(data);
	$('#updateModalData').text(' Сохранить');
	$('#productModal').modal({backdrop: 'static'});
}

$(document).ready(function() {
	// Getting products list
	getProducts(contructorid);
	// Adding to navbar menu contractors
	/*$('.container-fluid').append('<ul class="nav navbar-nav"><li><a href="http://igorserver.ru/v2/admin/contractors.php">Contractors</a></li></ul>');*/
	// Searching row
	$('#ptable').filtertable();
	// Sorting table on table head click
	$('#ptable').sorttable();
	// Setting edit and remove button disabled
	$('#editProduct').prop('disabled', true);
	$('#removeProduct').prop('disabled', true);
	$('#publishProduct').prop('disabled', true);

	// Create event listener on modal creating
	$('#productModal').on('shown.bs.modal', function() {
		editor.on('change', editorChange);
		$('#updateModalData').prop('disabled', true);
	});
	// Remove event listener on modal hide
	$('#productModal').on('hide.bs.modal', function() {
		editor.off('change', editorChange);
		editor.setValue(starting_value);
		if(!editor.isEnabled()) 
			editor.enable();
		$('#editProduct').prop('disabled', true);
		$('#removeProduct').prop('disabled', true);
		$('#publishProduct').prop('disabled', true);
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
	// 'Publish' button clicked
	$('#publishProduct').on('click', function() {
		publishProduct($('input:checkbox:checked').closest('tr').data('id'));
	});

	// On checkbox check/uncheck enable/disable edit, publish and remove button
	$('#ptable tbody:first').on('change', ':checkbox', function() {
		var status = $(this).prop('checked');
		$('input:checkbox').prop('checked', false);
		$(this).prop('checked', status);
		$('#editProduct').prop('disabled', !status);
		$('#removeProduct').prop('disabled', !status);
		if ($(this).closest('tr').hasClass('default')) {
			$('#publishProduct').prop('disabled', !status);
		}
		else
			$('#publishProduct').prop('disabled', true);
	});

	// Show modal window of product on row click
	$('#ptable tbody:first').on('click', 'td:not(:first-child)', function() {
		if ($(this).parent().hasClass('danger'))
			editor.disable();
		editModal($(this).parent().data());
	});
});
</script>
</body>
</html>