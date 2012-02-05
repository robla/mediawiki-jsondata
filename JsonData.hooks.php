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
		if ( !is_null( $wgJsonData ) ) {
			$out->addModules( 'ext.jsonwidget' );
		}
		return true;
	}

	/**
	 * Load the JsonData object if we're in one of the configured namespaces
	 * @param EditPage $editPage
	 */
	public static function onEditPageShowEditFormInitial( &$editPage ) {
		global $wgJsonData;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		$ns = $title->getNamespace();

		if ( JsonData::isJsonDataNeeded( $ns ) ) {
			$wgJsonData = new JsonData( $ns, $article );
			$wgJsonData->outputEditor();
		}
		return true;
	}

	/**
	 * Remove the edit toolbar from the form
	 */
	public static function onEditPageBeforeEditToolbar( &$toolbar )
	{
		$toolbar = '';
		return false;
	}

	/**
	 * Register the configured parser tags with default tag renderer.
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		global $wgJsonDataDefaultTagHandlers;
		foreach ( $wgJsonDataDefaultTagHandlers as $tag ) {
			$parser->setHook( $tag,  __CLASS__ . '::jsonTagRender' );
		}
		return true;
	}

	/**
	 * Default parser tag renderer
	 */
	public static function jsonTagRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		return "<pre>" . htmlspecialchars( $input ) . "</pre>";
	}

	public static function onGetPreferences( $user, &$preferences ) {
        $preferences['jsondata-schemaedit'] = array(
                'type' => 'toggle',
                'label-message' => 'jsondata-schemaedit-pref',
                'section' => 'misc/jsondata',
        );
        return true;
	}
}
