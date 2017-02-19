<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Админка</title>
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



	// if user in admin group
	$res = $db->getUserId($_SESSION['apiKey']);
	if ($db->isUserInGroup(41, $res["id"])) {
		echo '<div class="container"><h2>Главная страница</h2>';
		echo '<br><a href="http://igorserver.ru/v2/admin/contractors.php">Поставщики</a>';
    	echo '<br><a href="http://igorserver.ru/v2/admin/customers.php">Заказчики</a>';
    	echo '<br><a href="http://igorserver.ru/v2/admin/customers.php">Groups</a>';
	}
	else {
		echo '<div class="container"><h2>Мохсоголлохский хлебозавод</h2>';
		$gid = $db->getGroupsOfUser($res["id"]);
		echo '<br><a href="http://igorserver.ru/v2/admin/contractor_products.php?id=' . $gid[0]["id"] . '">Продукты</a>';
		$groupOfUserData=$db->getGroupById($gid[0]["id"]);

		echo '<br><a id="editGroup" data-id='.$groupOfUserData[0]["id"].' href="#" onClick="showModal();">Редактировать группу</a>';
	}

echo '</div>';
?>
<!-- Contractor modal-->
<div class="modal fade" id="contractorModal" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Производитель</h4>
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
<script>
// This is the starting value for the editor
// We will use this to seed the initial editor
// and to provide a "Create" button.
var starting_value = {"contractorid":0,"id":0,"name":"","status":1,"changed_at":"","price":0,"info":{"name":{"text":""},"name_full":{"text":""},"price":0,"summary":{"text":""},"icon":{"image_url":""},"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]}};
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
function getContractors(id) {
	$.ajax({
		method: "GET",
		url: "http://igorserver.ru/v2/admin/contractors",
		cache: false,
		dataType: "json",
		success: function(response) {
			if (response["error"] == false) {
				$.each(response["contractors"], function(i, value) {
					if (value["id"] == id) {
						value["created_at"] = new Date(value["created_at"]*1000).toLocaleDateString();
						value["changed_at"] = new Date(value["changed_at"]*1000).toLocaleDateString();
						value["info"] = JSON.parse(value["info"]);
						editor.setValue(response["contractors"][i]);
						$('#contractorModal').modal({backdrop: 'static'});
					}
				});
			}
		},
		error: function(jqXhr, textStatus, errorThrown ) {
			console.log(errorThrown);
		}
	});
}
function updateContractor(id, name, status, info) {
	$.ajax({
		method: "PUT",
		url: "http://igorserver.ru/v2/admin/contractors/" + id,
		data: {name: name, status: status, info: info},
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
// Make create/save button active on editor data change
function editorChange() {
	var errors = editor.validate();
	if(!errors.length) {
		$('#updateModalData').prop('disabled', false);
	}
}
// Showing modal windows
function editModal() {
	//editor.setValue(data);
	//$('#contractorModal').modal({backdrop: 'static'});
}

// Showing modal windows
function showModal() {
	getContractors($('#editGroup').data("id"));
}

$(document).ready(function() {
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
	});
	// 'Update' button clicked
	$('#updateModalData').on('click', function() {
		var value = editor.getValue();
		updateContractor(value.id, value.name, value.status, JSON.stringify(value.info));
		$('#contractorModal').modal('hide');
	});
});

</script>
</body>
</html>