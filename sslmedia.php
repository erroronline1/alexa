<?php
$query=$query=array_keys($_GET);

$query[0]=str_replace(["__/","_p","_j","__"],["../",".p",".j","_."], $query[0]);
if (file_exists($query[0])) {
    header('Content-Type: '.mime_content_type($query[0]));
    header("Content-Disposition: inline; filename=".$query[0]);
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.filesize($query[0]));
    ob_clean();
    flush();
    readfile($query[0]);
	exit;}
?>