<?php
$filename = $_POST["filename"];
header('Expires:0');
header("Last-Modified:".gmdate("D,d M YH:i:s")." GMT");
header('Cache-Control:no-cache,must-revalidate');
header('Pragma:no-cache');
header('Content-type:application/vnd.ms-excel');
header('Content-Transfer-Encoding:binary');
header('Content-Disposition:attachment;filename="'.$filename.'"');
readfile($filename);
exit();
?>