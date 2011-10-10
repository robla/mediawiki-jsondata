<?php
# Example PHP
require_once("$IP/extensions/JsonData/JsonData.php");

$wgJsonDataNamespace[202] = "Data";
$wgJsonDataNamespace[204] = "Schema";
$wgJsonDataNamespace[206] = "JsonConfig";
$wgJsonDataNamespace[208] = "Address";

$wgJsonDataDefaultTagHandlers[]='address';
$wgJsonDataDefaultTagHandlers[]='jsonconfig';

foreach ($wgJsonDataNamespace as $nsnum => $nskey) {
	$wgExtraNamespaces[$nsnum] = $nskey;
	$wgExtraNamespaces[$nsnum+1] = $nskey . "_Talk";
}

