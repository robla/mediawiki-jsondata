<?php
/**
 * JsonData is a generic JSON editing and templating interface for MediaWiki
 *
 * @file JsonData_body.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @licence GNU General Public Licence 2.0 or later
 */

class JsonDataException extends Exception {
}

class JsonDataUnknownTagException extends JsonDataException {
}

class JsonData {
	/**
	 * Variables referenced from context (e.g. $wg* vars)
	 */
	public $out;
	public $ns;

	/**
	 * Function which decides if we even need to get instantiated
	 */
	public static function isJsonDataNeeded( $ns ) {
		global $wgJsonDataNamespace;
		return array_key_exists( $ns, $wgJsonDataNamespace );
	}

	public function __construct( $title ) {
		global $wgOut, $wgJsonDataNamespace;
		$this->out = $wgOut;
		$this->title = $title;
		$this->nsname = $wgJsonDataNamespace[$this->title->getNamespace()];
		$this->editortext = null;
		$this->config = null;
		$this->schematext = null;
		$this->jsonref = null;
		$this->servererror = '';
	}

	/**
	 * All of the PHP-generated HTML associated with JsonData goes here
	 */
	public function outputEditor( &$editPage ) {
		global $wgUser;
		$servererror = $this->servererror;
		$config = $this->getConfig();
		$defaulttag = $config['namespaces'][$this->nsname]['defaulttag'];
		$this->out->addJsConfigVars( 'egJsonDataDefaultTag', $defaulttag );
		try {
			$schema = $this->getSchemaText();
		}
		catch ( JsonDataException $e ) {
			$schema = $this->readJsonFromPredefined( 'openschema' );
			//TODO: clean up server error mechanism
			$servererror .= "<b>Server error</b>: " . htmlspecialchars( $e->getMessage() );
		}
		$this->out->addHTML( <<<HEREDOC
<div id="je_servererror">${servererror}</div>
<div id="je_warningdiv"></div>

<div class="jsondata_tabs">
	<div class="vectorTabs">
		<ul>
HEREDOC
			);
		if ( $editPage->preview ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_previewpane"><a>Preview</a></span></li>
HEREDOC
				);
		}
		if ( $this->out->getRequest()->getVal( 'wpDiff' ) ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_diffpane"><a>Changes</a></span></li>
HEREDOC
				);
		}
		$this->out->addHTML( <<<HEREDOC
			<li><span id="je_formbutton"><a>Edit w/Form</a></span></li>
			<li><span id="je_sourcebutton"><a>Edit Source</a></span></li>
HEREDOC
			);

		if ( $wgUser->isLoggedIn() && $wgUser->getOption( 'jsondata-schemaedit' ) ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_schemaexamplebutton"><a>Generate Schema</a></span></li>
HEREDOC
			);
		}
		$this->out->addHTML( <<<HEREDOC
		</ul>
	</div>
</div>

<div id="je_formdiv"></div>
<textarea id="je_schemaexamplearea" style="display: none" rows="30" cols="80">
</textarea>

<textarea id="je_schematextarea" style="display: none" rows="30" cols="80">
HEREDOC
			);
			$this->out->addHTML( htmlspecialchars( $schema ) );
			$this->out->addHTML( <<<HEREDOC
</textarea>
HEREDOC
			);
	}

	/**
	 * Read the config text from either $wgJsonDataConfigArticle or
	 * $wgJsonDataConfigFile
	 */
	public function getConfig() {
		global $wgJsonDataConfigArticle, $wgJsonDataConfigFile;
		if ( is_null( $this->config ) ) {
			if ( !is_null( $wgJsonDataConfigArticle ) ) {
				$configText = $this->readJsonFromArticle( $wgJsonDataConfigArticle );
				$this->config = json_decode( $configText, TRUE );
			}
			elseif ( !is_null( $wgJsonDataConfigFile ) ) {
				$configText = file_get_contents( $wgJsonDataConfigFile );
				$this->config = json_decode( $configText, TRUE );
			}
			else {
				$this->config = $this->getDefaultConfig();
			}
		}
		return $this->config;
	}

	public function getDefaultConfig() {
		// TODO - better default config mechanism
		$configText = $this->readJsonFromPredefined( 'configexample' );
		$config = json_decode( $configText, TRUE );
		return $config;
	}

	/*
	 * Load appropriate editor text into the object (if it hasn't been yet), 
	 * and return it.  This will either be the contents of the title being
	 * viewed, or it will be the newly-edited text being previewed.
	 */
	public function getEditorText() {
		if( is_null( $this->editortext ) ) {
			// on preview, pull $editortext out from the submitted text, so 
			// that the author can change schemas during preview
			$this->editortext = $this->out->getRequest()->getText( 'wpTextbox1' );
			// wpTextbox1 is empty in normal editing, so pull it from article->getText() instead
			if ( empty( $this->editortext ) ) {
				$rev = Revision::newFromTitle( $this->title );
				if( is_object( $rev ) ) {
					$this->editortext = $rev->getText();
				}
				else {
					$this->editortext = "";
				}
			}
		}
		return $this->editortext;
	}

	/*
	 * Get the schema attribute from the editor text.
	 */
	private function getSchemaAttr() {
		$config = $this->getConfig();
		$editortext = $this->getEditorText();
		$tag = $config['namespaces'][$this->nsname]['defaulttag'];

		$schemaconfig = $config['tags'][$tag]['schema'];
		$schemaAttr = null;
		if ( isset( $schemaconfig['schemaattr'] ) && ( preg_match( '/^(\w+)$/', $schemaconfig['schemaattr'] ) > 0 ) ) {
			if ( preg_match( '/^<[\w]+\s+([^>]+)>/m', $editortext, $matches ) > 0 ) {
				/*
				 * Quick and dirty regex for parsing schema attributes that hits the 99% case.
				 * Bad matches: foo="bar' , foo='bar"
				 * Bad misses: foo='"r' , foo="'r"
				 * Works correctly in most common cases, though.
				 * \x27 is single quote
				 */
				if ( preg_match( '/\b' . $schemaconfig['schemaattr'] . '\s*=\s*["\x27]([^"\x27]+)["\x27]/', $matches[1], $subm ) > 0 ) {
					$schemaAttr = $subm[1];
				}
			}
		}
		return $schemaAttr;
	}

	/*
	 * Get the tag from the editor text.  Horrible kludge: this should probably
	 * be done with the MediaWiki parser somehow, but for now, just using a
	 * nasty regexp.
	 */
	private function getTagName() {
		//$config = $this->getConfig();
		$editortext = $this->getEditorText();
		$begintag = null;
		$endtag = null;
		if ( preg_match( '/^<([\w]+)[^>]*>/m', $editortext, $matches ) > 0 ) {
			$begintag = $matches[1];
			wfDebug(__METHOD__ . ': begin tag name: ' . $begintag . "\n");
		}
		if ( preg_match( '/<\/([\w]+)>$/m', $editortext, $matches ) > 0 ) {
			$endtag = $matches[1];
			wfDebug(__METHOD__ . ': end tag name: ' . $endtag . "\n");
		}
		if ( $begintag != $endtag ) {
			throw new JsonDataException( "Mismatched tags: ${begintag} and ${endtag}" );
		}
		return $begintag;
	}

	/**
	 * Return the schema title text.
	 */
	public function getSchemaTitleText() {
		if( is_null( $this->schemainfo ) ) {
			// getSchemaText populates schemainfo as an artifact
			$this->getSchemaText();
		}

		if( $this->schemainfo['srctype'] == 'article' ) {
			return $this->schemainfo['src'];
		}
		else {
			return null;
		}
	}

	/**
	 * Find the correct schema and output that schema in the right spot of
	 * the form.  The schema may come from one of several places:
	 * a.  If the "schemaattr" is defined for a namespace, then from the
	 *     associated attribute of the json/whatever tag.
	 * b.  A configured article
	 * c.  A configured file in wgJsonDataPredefinedData
	 */
	public function getSchemaText() {
		if( is_null( $this->schematext ) ) {
			$this->schemainfo = array();
			$schemaTitleText = $this->getSchemaAttr();
			$config = $this->getConfig();
			$tag = $this->getTagName();
			if( is_null( $tag ) ) {
				$tag = $config['namespaces'][$this->nsname]['defaulttag'];
			}
			if ( !is_null( $schemaTitleText ) ) {
				$this->schemainfo['srctype'] = 'article';
				$this->schemainfo['src'] = $schemaTitleText;
				$this->schematext = $this->readJsonFromArticle( $schemaTitleText );
				if ( $this->schematext == '' ) {
					throw new JsonDataException( "Invalid schema definition in ${schemaTitleText}" );
				}
			}
			elseif ( $config['tags'][$tag]['schema']['srctype'] == 'article' ) {
				$this->schemainfo = $config['tags'][$tag]['schema'];
				$schemaTitleText = $this->schemainfo['src'];
				$this->schematext = $this->readJsonFromArticle( $schemaTitleText );
				if ( $this->schematext == '' ) {
					throw new JsonDataException( "Invalid schema definition in ${schemaTitleText}.  Check your site configuation for this tag." );
				}
			}
			elseif ( $config['tags'][$tag]['schema']['srctype'] == 'predefined' ) {
				$this->schemainfo = $config['tags'][$tag]['schema'];
				$schemaTitleText = $config['tags'][$tag]['schema']['src'];
				$this->schematext = $this->readJsonFromPredefined( $schemaTitleText );
			}
			elseif ( empty( $config['tags'][$tag] ) ) {
				throw new JsonDataUnknownTagException( "Tag \"${tag}\" not defined in JsonData site config" );
			}
			else {
				throw new JsonDataException( "Unknown error with JsonData site config" );
			}
			if ( strlen( $this->schematext ) == 0 ) {
				throw new JsonDataException( "Zero-length schema: ". $schemaTitleText );
			}
		}
		return $this->schematext;
	}

	/*
	 *  Parse the article/editor text as well as the corresponding schema text, 
	 *  and load the result into an object (JsonTreeRef) that associates
	 *  each JSON node with its corresponding schema node.
	 */
	public function getJsonRef() {
		if( is_null( $this->jsonref ) ) {
			$json = JsonData::stripOuterTagsFromText( $this->getEditorText() );
			$schematext = $this->getSchemaText();
			$data = json_decode($json, true);
			$schema = json_decode($schematext, true);
			$this->jsonref = new JsonTreeRef( $data );
			$this->jsonref->attachSchema( $schema );
		}
		return $this->jsonref;
	}

	/*
	 * Read json-formatted data from an article, stripping off parser tags
	 * surrounding it.
	 */
	public static function readJsonFromArticle( $titleText ) {
		$retval = array( 'json' => null, 'tag' => null, 'attrs' => null );
		$title = Title::newFromText( $titleText );
		$rev = Revision::newFromTitle( $title );
		if ( is_null( $rev ) ) {
			return "";
		}
		else {
			$revtext = $rev->getText();
			return preg_replace( array( '/^<[\w]+[^>]*>/m', '/<\/[\w]+>$/m' ), array( "", "" ), $revtext );
		}
	}

	/*
	 * Strip the outer parser tags from some text
	 */
	public static function stripOuterTagsFromText( $text ) {
		return preg_replace( array( '/^<[\w]+[^>]*>/m', '/<\/[\w]+>$/m' ), array( "", "" ), $text );
	}

	/*
	 * Read json-formatted data from a predefined data file.
	 */
	public static function readJsonFromPredefined( $filekey ) {
		global $wgJsonDataPredefinedData;
		return file_get_contents( $wgJsonDataPredefinedData[$filekey] );
	}
}
