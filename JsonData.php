<?php
/**
 * JsonData is a generic JSON editing and templating interface for MediaWiki
 *
 * @file
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011 Rob Lanphier
 * @licence GNU General Public Licence 2.0 or later
 */

if( !defined( 'MEDIAWIKI' ) ) die();

$wgExtensionCredits['Tasks'][] = array(
	'path'           => __FILE__,
	'name'           => 'JsonData',
	'author'         => 'Rob Lanphier',
	'descriptionmsg' => 'jsondata_desc',
	'url'            => 'http://www.mediawiki.org/wiki/Extension:JsonData',
);

$wgExtensionMessagesFiles['JsonData'] = dirname( __FILE__ ) . '/JsonData.i18n.php';
$wgAutoloadClasses['JsonDataHooks'] = dirname( __FILE__ ) . '/JsonData.hooks.php';
$wgAutoloadClasses['JsonData'] = dirname( __FILE__ ) . '/JsonData_body.php';

$wgHooks['BeforePageDisplay'][] = 'JsonDataHooks::beforePageDisplay';
$wgHooks['EditPage::showEditForm:initial'][] = 'JsonDataHooks::onEditPageShowEditFormInitial';
$wgHooks['EditPageBeforeEditToolbar'][] = 'JsonDataHooks::onEditPageBeforeEditToolbar';
$wgHooks['ParserFirstCallInit'][] = 'JsonDataHooks::onParserFirstCallInit';

$wgJsonDataNamespace = null;
$wgJsonDataSchemaFile = null;
$wgJsonData = null;

$wgResourceModules['ext.jsonwidget'] = array(
	'scripts' => array(
		'json.js',
		'jsonedit.js',
		'mw.jsondata.js'
		),
	'styles' => 'jsonwidget.css',
	'localBasePath' => dirname( __FILE__ ) . '/resources',
	'remoteExtPath' => 'JsonData/resources'
);

