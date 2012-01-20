<?php
# Example PHP
require_once("$IP/extensions/JsonData/JsonData.php");

$wgJsonDataNamespace[202] = "Data";
$wgJsonDataNamespace[204] = "Schema";
$wgJsonDataNamespace[206] = "JsonConfig";
$wgJsonDataNamespace[208] = "Address";

# Default tag handlers. Define these only for tags that don't have their own
# separately-defined tag handlers (e.g. by another extension), and thus need 
# the default tag handler.
$wgJsonDataDefaultTagHandlers[]='address';
$wgJsonDataDefaultTagHandlers[]='jsonconfig';

# Further configuration can be performed on-wiki via this article if this 
# variable is set
# $wgJsonDataConfigArticle = "JsonConfig:Sample";

# Associate talk pages with each namespace
foreach ($wgJsonDataNamespace as $nsnum => $nskey) {
	$wgExtraNamespaces[$nsnum] = $nskey;
	$wgExtraNamespaces[$nsnum+1] = $nskey . "_Talk";
}

