<?php

class JsonDataHooks {
	/**
	 * BeforePageDisplay hook
	 * Adds the modules to the page
	 *
	 * @param $out OutputPage output page
	 * @param $skin Skin current skin
	 * @return Boolean: always true
	 */
	public static function beforePageDisplay( $out, $skin ) {
		global $wgJsonData;
		if( !is_null( $wgJsonData ) ) {
			$out->addModules( 'ext.jsonwidget' );
		}
		return true;
	}

	/**
	 * Muck with the editor interface
	 * @param EditPage $editPage
	 */
	public static function onEditPageShowEditFormInitial( &$editPage ) {
		global $wgJsonData;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		$ns = $title->getNamespace();

		if( JsonData::isJsonDataNeeded( $ns ) ) {
			$wgJsonData = new JsonData( $ns );
			$wgJsonData->outputEditor();
		}
		return true;
	}

	public static function onEditPageBeforeEditToolbar(&$toolbar)
	{
		$toolbar = '';
		return false;
	}

	public static function onParserFirstCallInit( Parser &$parser ) {
		global $wgJsonDataDefaultTagHandlers;
		foreach ($wgJsonDataDefaultTagHandlers as $tag) {
			$parser->setHook( $tag,  __CLASS__ . '::jsonTagRender' );
		}
		return true;
	}
 
	public static function jsonTagRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		return "<pre>" . htmlspecialchars( $input ) . "</pre>";
	}
}
