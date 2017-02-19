<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Производители</title>
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

	<!--script src="js/scripts.contractors.js"></script-->
	<!--SCEditor plugin and themes-->
	<script src="//cdn.jsdelivr.net/sceditor/1.4.3/jquery.sceditor.xhtml.min.js"></script>
	<link rel="stylesheet" href="//cdn.jsdelivr.net/sceditor/1.4.3/themes/default.min.css">
	<!--Correcting css styles-->
	<link rel="stylesheet" href="css/main.css">
</head>
<body>
<?php
session_start();
include 'auth.php';
//Provides db (the instance of DbProfileHandler.php)
?>
<div class="container">
	<div class="page-header">
		<h4><a href="http://igorserver.ru/v2/admin/contractors.php">Производители</a></h4>
	</div>
	<div class="row">
    	<div class="btn-group col-md-9">
			<button id="newContractor" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus-sign"></span> Создать</button>
			<button id="editContractor" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-edit"></span> Изменить</button>
            <button id="removeContractor" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-minus-sign"></span> Удалить</button>
 		</div>
 		<div class="col-md-3 pull-right">
 			<div class="form-group has-feedback">
 				<input type="text" class="form-control" id="search" placeholder="Поиск">
 				<span class="glyphicon glyphicon-search form-control-feedback"></span>
 			</div>
 		</div>
	</div>
	<div class="row">
		<table id="ctable" class="table table-hover table-condensed">
		<thead>
			<tr>
				<th class="col-md-1">#</th>
				<th class="col-md-1" id="id">ИД <span style="font-size:0.75em"></span></th>
				<th class="col-md-4" id="name">Наименование <span style="font-size:0.75em"></span></th>
				<th class="col-md-1" id="status">Статус <span style="font-size:0.75em"></span></th>
				<th class="col-md-2" id="changed_at">Последнее изменение <span style="font-size:0.75em"></span></th>
				<th class="col-md-1">Детали</th>
				<th class="col-md-1">Пользователи</th>
				<th class="col-md-1">Продукты</th>
			</tr>
		</thead>
		<tbody>
		</tbody>
		</table>
	</div>
	<!--div class="row">
		<a href="add_group.php"><button id='add' type="button" class="btn btn-lg btn-default">Создать группу</button></a>
	</div-->
<!-- Contractor modal-->
<div class="modal fade" id="contractorModal" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Производители</h4>
			</div>
			<div class="modal-body">
				<div id="editor_holder">
				</div>
			</div>
			<div class="modal-footer">
				<button id="updateModalData" type="button" class="btn btn-success updatemodaldata"><span class="glyphicon glyphicon-ok"></span> Сохранить</button>
				<button id="cancelModalData" type="button" class="btn btn-default" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span> Отмена</button>
			</div>
		</div>
	</div>
</div>
<!-- Contractor modal-->
<!-- Contractor users modal-->
<div class="modal fade" id="contractorUsersModal" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Пользователи производителя</h4>
			</div>
			<div class="modal-body">
				<div id="users_editor_holder">
				</div>
			</div>
			<div class="modal-footer">
				<button id="updateUsersModalData" type="button" class="btn btn-success updatemodaldata"><span class="glyphicon glyphicon-ok"></span> Сохранить</button>
				<button id="cancelUsersModalData" type="button" class="btn btn-default" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span> Отмена</button>
			</div>
		</div>
	</div>
</div>
<!-- Contractor users modal-->
<!-- Contractor details modal-->
<div class="modal fade" id="contractorDetailsModal" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Детали производителя</h4>
			</div>
			<div class="modal-body">
				<div id="details_editor_holder">
				</div>
			</div>
			<div class="modal-footer">
				<button id="updateDetailsModalData" type="button" class="btn btn-success updatemodaldata"><span class="glyphicon glyphicon-ok"></span> Сохранить</button>
				<button id="cancelDetailsModalData" type="button" class="btn btn-default" data-dismiss="modal"><span class="glyphicon glyphicon-remove"></span> Отмена</button>
			</div>
		</div>
	</div>
</div>
<!-- Contractor users modal-->
</div>
	<!-- Include all compiled plugins (below), or include individual files as needed>
	<script src="js/bootstrap.min.js"></script-->
<script>
// This is the starting value for the editor
// We will use this to seed the initial editor
// and to provide a "Create" button.
var starting_value = {"id":0,"name":"","address":"","phone":"","status":1,"created_at":"","changed_at":"","info":{"name":{"text":""},"name_full":{"text":""},"summary":{"text":""},"icon":{"image_url":""},"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]}};
// Specify json-editor upload handler
JSONEditor.defaults.options.upload = function(type, file, cbs) {
	cbs.updateProgress();
	var formdata = new FormData();
	formdata.append("image", file);
	var id = editor.getEditor('root.id').getValue().toString();
	if (type == 'root.info.icon.image_url')
		var uri = 'v2/admin/avatar/upload/' + id;
	else
		var uri = 'v2/admin/slides/upload/c' + id;
	$.ajax({
		method: "POST",
		url: "http://igorserver.ru/" + uri,
		data: formdata,
		cache: false,
		contentType: false,
		processData : false,
		dataType: "json",
		success: function(response) {
			console.log(response);
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
	schema: { "$ref": "js/contractor.json" }
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
});
// Get contractors list with ajax+php
function getContractors() {
	$.ajax({
		method: "GET",
		url: "http://igorserver.ru/v2/admin/contractors",
		cache: false,
		dataType: "json",
		success: function(response) {
			if (response["error"] == false)
				updateTable(response["contractors"]);
		},
		error: function(jqXhr, textStatus, errorThrown ) {
			console.log(errorThrown);
		}
	});
}
// Create table with and set data attributes to JQuery
function updateTable(jsondata) {
	$('#ctable tbody:first').empty();
	$.each(jsondata, function(i, value) {
		value["created_at"] = new Date(value["created_at"]*1000).toLocaleDateString();
		value["changed_at"] = new Date(value["changed_at"]*1000).toLocaleDateString();
		value["info"] = JSON.parse(value["info"]);
		var trstat = (value["status"] == 4)?'danger':((value["status"] == 2)?'success':((value["status"] == 1)?'default':'warning'));
		$('#ctable tbody:first').append('<tr class="' + trstat +'"><td style="cursor:default"><input type="checkbox" value=""></td><td>' + value["id"] + '</td><td>' + value["name"] + '</td><td>' + value["status"] + '</td><td>' + value["changed_at"] + '</td><td style="cursor:default"><a href="#" onclick="showDetails(' + value["id"] + ')">details</a></td><td style="cursor:default"><a href="#" onclick="showUsers(' + value["id"] + ')">users</a></td><td style="cursor:default"><a href="contractor_products.php?id=' + value["id"] + '">products</a></td></td>');
		$('#ctable tbody:first').find('tr:last').data(value);
		$('#ptable .danger').find(':checkbox').prop('disabled', true);
	});
}

function createContractor() {
	$.ajax({
		method: "POST",
		url: "http://igorserver.ru/v2/admin/contractors",
		cache: false,
		dataType: "json",
		success: function(response) {
			if (response["error"] == false) {
				editor.setValue(starting_value);
				var id = editor.getEditor("root.id");
				id.setValue(response["id"]);
				$('#updateModalData').text(' Создать');
				$('#contractorModal').modal({backdrop: 'static'});
			}
		},
		error: function(jqXhr, textStatus, errorThrown ) {
			console.log(errorThrown);
		}
	});
}

function updateContractor(id, name, address, phone, status, info) {
	$.ajax({
		method: "PUT",
		url: "http://igorserver.ru/v2/admin/contractors/" + id,
		data: {name: name, address: address, phone: phone, status: status, info: info},
		cache: false,
		dataType: "json",
		success: function(response) {
			if (response["error"] == false)
				getContractors();
		},
		error: function(jqXhr, textStatus, errorThrown) {
			console.log(errorThrown);
		}
	});
}

function removeContractor(id) {
	$.ajax({
		method: "DELETE",
		url: "http://igorserver.ru/v2/admin/contractors/" + id,
		cache: false,
		dataType: "json",
		success: function(response) {
			if (response["error"] == false)
				getContractors();
		},
		error: function(jqXhr, textStatus, errorThrown ) {
			console.log(errorThrown);
		}
	});
}
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
	$('#contractorModal').modal({backdrop: 'static'});
}

function showUsers(id) {
	$('#contractorUsersModal').modal();
	console.log(id);
};

function showDetails(id) {
	$('#contractorDetailsModal').modal();
	console.log(id);
};

$(document).ready(function() {
	// Updating contractor table
	getContractors();
	// Searching row
	$('#ctable').filtertable();
	// Sorting table on table head click
	$('#ctable').sorttable();
	// Setting edit and remove button disabled
	$('#editContractor').prop('disabled', true);
	$('#removeContractor').prop('disabled', true);
	// Create event listener on modal creating
	$('#contractorModal').on('shown.bs.modal', function () {
		editor.on('change', editorChange);
		$('#updateModalData').prop('disabled', true);
	});
	// Remove event listener on modal hide
	$('#contractorModal').on('hide.bs.modal', function () {
		editor.off('change', editorChange);
		editor.setValue(starting_value);
		if(!editor.isEnabled()) 
			editor.enable();
		$('#editContractor').prop('disabled', true);
		$('#removeContractor').prop('disabled', true);
		$('input:checkbox').prop('checked', false);
	});

	// 'Update' button clicked
	$('#updateModalData').on('click', function() {
		var value = editor.getValue();
		updateContractor(value.id, value.name, value.address, value.phone, value.status, JSON.stringify(value.info));
		$('#contractorModal').modal('hide');
	});

	// 'Create' button clicked
	$('#newContractor').on('click', function() {
		createContractor();
	});
	// 'Edit' button clicked
	$('#editContractor').on('click', function() {
		editModal($('input:checkbox:checked').closest('tr').data());
	});
	// 'Delete' button clicked
	$('#removeContractor').on('click', function() {
		removeContractor($('input:checkbox:checked').closest('tr').data('id'));
	});

	// On checkbox check/uncheck enable/disable edit, publish and remove button
	$('#ctable tbody:first').on('change', ':checkbox', function() {
		var status = $(this).prop('checked');
		$('input:checkbox').prop('checked', false);
		$(this).prop('checked', status);
		$('#editContractor').prop('disabled', !status);
		$('#removeContractor').prop('disabled', !status);
	});
	// Show modal window of product on row click
	$('#ctable tbody:first').on('click', 'td:not(:first-child):not(:has(a))', function() {
		if ($(this).parent().hasClass('danger'))
			editor.disable();
		editModal($(this).parent().data());
	});

});
</script>
</body>
</html>