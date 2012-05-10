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
		$title = $editPage->getTitle();
		$ns = $title->getNamespace();

		if ( JsonData::isJsonDataNeeded( $ns ) ) {
			$wgJsonData = new JsonData( $title );
			try {
				$jsonref = $wgJsonData->getJsonRef();
				$jsonref->validate();
			}
			catch ( JsonSchemaException $e ) {
				// if the JSON is null, don't sweat an error, since that will
				// frequently be the case for new pages
				if( $e->subtype != 'validate-fail-null' || !$editPage->firsttime ) {
					//TODO: clean up server error mechanism
					$wgJsonData->servererror .= "<b>" .
						wfMessage( 'jsondata-server-error' ) . "</b>: " .
						htmlspecialchars( $e->getMessage() ) . "<br/>";
				}
			}
			catch ( Exception $e ) {
				$wgJsonData->servererror .= "<b>" . 
					wfMessage('jsondata-server-error') . "</b>: " . 
					htmlspecialchars( $e->getMessage() ) . "<br/>";
			}
			$wgJsonData->outputEditor( &$editPage );
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
		global $wgJsonData;
		$wgJsonData = new JsonData( $frame->title );

		$json = $input;
		$goodschema = true;
		try {
			$schematext = $wgJsonData->getSchemaText();
		}
		catch ( JsonDataException $e ) {
			$schematext = $wgJsonData->readJsonFromPredefined( 'openschema' );
			wfDebug( __METHOD__ . ": " . htmlspecialchars( $e->getMessage() ) . "\n" );
			$goodschema = false;
		}

		$schematitletext = $wgJsonData->getSchemaTitleText();
		if ( $goodschema && !is_null( $schematitletext ) ) {
		    // Register dependency in templatelinks, using technique (and a
			// little code) from http://www.mediawiki.org/wiki/Manual:Tag_extensions
		    $schematitle = Title::newFromText( $schematitletext );
		    $schemarev = Revision::newFromTitle( $schematitle );
		    $schemaid = $schemarev ? $schemarev->getPage() : 0;
		    $parser->mOutput->addTemplate( $schematitle, $schemaid,
				$schemarev ? $schemarev->getId() : 0 );
		}

		$data = json_decode( $json, true );
		$schema = json_decode( $schematext, true );
		$rootjson = new JsonTreeRef( $data );
		$rootjson->attachSchema( $schema );
		$markup = JsonDataMarkup::getMarkup( $rootjson, 0 );
		return $parser->recursiveTagParse( $markup, $frame );
	}

	public static function onGetPreferences( $user, &$preferences ) {
        $preferences['jsondata-schemaedit'] = array(
                'type' => 'toggle',
                'label-message' => 'jsondata-schemaedit-pref',
                'section' => 'misc/jsondata',
        );
        return true;
	}

	public static function validateDataEditFilter( $editor, $text, $section, &$error, $summary ) {
		// I can't remember if jsondataobj needs to be a singleton/global, but
		// will chance calling a new instance here.
		$title = $editor->getTitle();
		$ns = $title->getNamespace();
		if ( !JsonData::isJsonDataNeeded( $ns ) ) {
			return true;
		}
		$jsondataobj = new JsonData( $title );
		$json = JsonData::stripOuterTagsFromText( $text );
		try {
			$schematext = $jsondataobj->getSchemaText();
		}
		catch ( JsonDataException $e ) {
			$schematext = $jsondataobj->readJsonFromPredefined( 'openschema' );
			$error = "<b>" . wfMessage( 'jsondata-servervalidationerror' ) . "</b>: ";
			$error .= wfMessage( 'jsondata-invalidjson' );
		}
		$data = json_decode( $json, true );
		if( is_null( $data ) ) {
			$error = "<b>" . wfMessage( 'jsondata-servervalidationerror' ) . "</b>: ";
			$error .= wfMessage( 'jsondata-invalidjson' );
			return true;
		}
		$schema = json_decode( $schematext, true );
		$rootjson = new JsonTreeRef( $data );
		$rootjson->attachSchema( $schema );
		try {
			$rootjson->validate();
		} 
		catch ( JsonSchemaException $e ) {
			$error = "<b>" . wfMessage( 'jsondata-servervalidationerror' ) . "</b>: ";
			$error .= htmlspecialchars( $e->getMessage() );
		}
		return true;
	}
}
