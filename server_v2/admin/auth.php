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
    header("Location: http://".$_SERVER['HTTP_HOST']."/v2/admin/index.php");
    exit;
}

if ( isset($_SESSION['apiKey']) && $db->isValidApiKey($_SESSION['apiKey']) ) {
	
	$res = $db->getUserId($_SESSION['apiKey']);	
	$user=$db->getUserById($res["id"]);
	
	echo '<div class="pull-right" style="padding-right:30px;">';
	//echo '	<img src="'.$user["avatars"]["icon"].'" style="width: 30px; height: 30px; margin-right:5px;" alt="No icon">'.$user["name"].' <a href="?action=logout">(logout)</a></br>';
	echo '	<img src="" style="width: 30px; height: 30px; margin-right:5px;" alt="No icon">'.$user["name"].' <a href="?action=logout">(logout)</a></br>';
	echo '</div>';
	
}else{
?>
<!-- Custom styles for this template -->
    <link href="css/signin.css" rel="stylesheet">

<div class="container">

      <form class="form-signin"  method="POST">
        <h2 class="form-signin-heading">Admin-panel sign in</h2>
        <label for="inputEmail" class="sr-only">Email address</label>
        <input name="email" type="email" id="inputEmail" class="form-control" placeholder="Email address" required autofocus>
        <label for="inputPassword" class="sr-only">Password</label>
        <input name="password" type="password" id="inputPassword" class="form-control" placeholder="Password" required>
        <div class="checkbox">
          <label>
            <input type="checkbox" value="remember-me"> Remember me
          </label>
        </div>
        <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
      </form>

    </div> <!-- /container -->


    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <script src="../../assets/js/ie10-viewport-bug-workaround.js"></script>

<?php 
exit;
}
?>