<?php

require_once dirname(__FILE__).'/../include/DbHandlerProfile.php';
require_once dirname(__FILE__).'/../include/PassHash.php';

$db = new DbHandlerProfile();

if (isset($_POST['email'])){
	// reading post params
	$email = $_POST['email'];
	$password = $_POST['password'];
	$response = array();

	// check for correct email and password
	if ($db->checkLogin($email, $password)) {
		// get the user by email
		$user = $db->getUserByEmail($email);

		if ($user != NULL) {	
			$_SESSION['apiKey'] = $user['api_key'];
		}
	}

    header("Location: http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_GET['action']) AND $_GET['action']=="logout") {
    session_start();
    session_destroy();
    header("Location: http://".$_SERVER['HTTP_HOST']."/v2/admin/");
    exit;
}

if (isset($_SESSION['apiKey']) && $db->isValidApiKey($_SESSION['apiKey']) ) {

	$res=$db->getUserId($_SESSION['apiKey']);
	$user=$db->getUserById($res["id"]);
	
	//echo '<div class="col-md-6 pull-right" style="padding-right:30px;">';
	//echo '	<img src="'.$user["avatars"]["icon"].'" style="width: 30px; height: 30px; margin-right:5px;" alt="No icon">'.$user["name"].' <a href="?action=logout">(logout)</a></br>';
	//echo '</div>';

    // Check if user has avatar
    if (!empty($user["avatars"]["icon"]))
        $avatar = '<img src="'.$user["avatars"]["icon"].'" alt="No icon" class="img-circle" width="30px" height="30px"> ';
    else
        $avatar = '<span class="glyphicon glyphicon-user"></span> ';
        
	echo '<nav class="navbar navbar-default">';
    echo '<div class="container">';
    echo '<div class="container-fluid">';
    echo '<div class="navbar-header"><a class="navbar-brand" href="http://'.$_SERVER['HTTP_HOST'].'/v2/admin/">Админка</a></div>';
    //echo '<ul class="nav navbar-nav"><li class="active"><a href="#">Home</a></li></ul>';

    echo '<ul class="nav navbar-nav navbar-right">';
    echo '<li class="dropdown"><a class="dropdown-toggle" data-toggle="dropdown" href="#">'.$avatar.$user["name"].'<span class="caret"></span></a>';
    
    echo '<ul class="dropdown-menu">';
    echo '<li><a href="?action=logout"><span class="glyphicon glyphicon-log-out"></span> Выход</a></li>';
    echo '</ul></li></ul></div></div></nav>';
    
}
else {
?>
<!-- Custom styles for this template -->
<link href="css/signin.css" rel="stylesheet">

<div class="container">
    <form class="form-signin" method="POST">
        <h2 class="form-signin-heading">Авторизация</h2>
        <label for="inputEmail" class="sr-only">Email</label>
        <input name="email" type="email" id="inputEmail" class="form-control" placeholder="Введите email" required autofocus>
        <label for="inputPassword" class="sr-only">Пароль</label>
        <input name="password" type="password" id="inputPassword" class="form-control" placeholder="Введите пароль" required>
        <div class="checkbox">
            <label>
            <input type="checkbox" value="remember-me"> Запомнить
            </label>
        </div>
        <button class="btn btn-lg btn-primary btn-block" type="submit">Войти</button>
    </form>
</div> <!-- /container -->

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <!--script src="../../assets/js/ie10-viewport-bug-workaround.js"></script-->

<?php
exit;
}
?>