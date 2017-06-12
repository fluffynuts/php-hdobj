<html>
<head>
<title>HDObj test page</title>
<style>
body {
	background: #eeeeee;
}
h3 {
	text-align: center;
}
th {
	border: 1px solid black;
	background: #aaaaaa;
}
td {
	text-align: left;
	vertical-align: top;
	border: 1px solid black;
}
</style>
</head>
<body>
<h3>HDObj test page</h3>
<?php
include_once("hdobj.php");

function r_dump_errors(&$obj, $name="root") {
	$obj->dump_errors();
	foreach ($obj->children as $child) {
		r_dump_errors($obj->$child, $child);
	}
}
// load unit test
if (!file_exists("data.xml")) {
	die("no data.xml in ".dirname(__FILE__));
}
$fp = fopen("data.xml", "r");
$xml = fread($fp, filesize("data.xml"));
fclose($fp);
$obj = new HDObj($xml);
//append unit test
if (file_exists("append.xml")) {
	print("(appending xml from append.xml)<br>");
	$fp = fopen("append.xml", "r");
	$appendxml = fread($fp, filesize("append.xml"));
	fclose($fp);
	$obj->append_xml($appendxml, 1);
}
print("hdobject's error log:<br>");
$obj->dump_errors();
/*
print("<hr>");
$obj->printout();
*/
print("<hr>");
print("and now the hdobject reconstructs xml from its structure:<br>");
print($obj->toXML(true, true));
?>
<hr>
how about we do something more constructive with the object?
<?
	foreach ($obj->form as $form) {
		print("<table><thead><th colspan=\"2\">Form #"
			.$form->get_attrib("id")."</th></thead>");
		foreach($form->action as $action) {
			print("<tr><td>Action #".$action->get_attrib("id")."</td>");
			print("<td><table>");
			print("<tr><td>name</td><td>".$action->name."</td></tr>");
			print("<tr><td>description</td><td>".$action->descr."</td></tr>");
			print("<tr><td>type</td><td>".$action->type."</td></tr>");
			print("</table></td></tr>");
		}
		print("</table>");
	}
?>
</body>
</html>
