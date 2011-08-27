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

$wgHooks['EditPage::showEditForm:initial'][] = 'JsonDataHooks::onEditPageShowEditFormInitial';

