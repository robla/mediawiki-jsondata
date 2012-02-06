<?php


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

	public function __construct( $ns, $article ) {
		global $wgOut, $wgJsonDataNamespace;
		$this->out = $wgOut;
		$this->ns = $ns;
		$this->article = $article;
		$this->nsname = $wgJsonDataNamespace[$this->ns];
	}

	/**
	 * All of the PHP-generated HTML associated with JsonData goes here
	 */
	public function outputEditor() {
		global $wgUser;
		$servererror = '';
		try {
			$schema = $this->getSchema();
		}
		catch ( Exception $e ) {
			$schema = $this->readJsonFromPredefined( 'openschema' );
			$servererror .= htmlspecialchars( $e->getMessage() );
		}
		$this->out->addHTML( <<<HEREDOC
<div id="je_servererror">${servererror}</div>
<div id="je_warningdiv"></div>

<div style="height:20px;">
	<div class="vectorTabs">
		<ul>
HEREDOC
			);
		if ( $this->out->getRequest()->getVal( 'wpPreview' ) ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_previewpane"><a>Preview</a></span></li>
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
	 * Read the config text, which is currently hardcoded to come from
	 * a specific file.
	 */
	public function getConfig() {
		global $wgJsonDataConfigArticle, $wgJsonDataConfigFile;
		if ( !is_null( $wgJsonDataConfigArticle ) ) {
			$configText = $this->readJsonFromArticle( $wgJsonDataConfigArticle );
			$config = json_decode( $configText, TRUE );
		}
		elseif ( !is_null( $wgJsonDataConfigFile ) ) {
			$configText = file_get_contents( $wgJsonDataConfigFile );
			$config = json_decode( $configText, TRUE );
		}
		else {
			$config = $this->getDefaultConfig();
		}
		return $config;
	}

	public function getDefaultConfig() {
		// TODO - better default config mechanism
		$configText = $this->readJsonFromPredefined( 'configexample' );
		$config = json_decode( $configText, TRUE );
		return $config;
	}

	/**
	 * Find the correct schema and output that schema in the right spot of
	 * the form.  The schema may come from one of several places:
	 * a.  If the "schemaattr" is defined for a namespace, then from the
	 *     associated attribute of the json/whatever tag.
	 * b.  A configured article
	 * c.  A configured file in wgJsonDataPredefinedData
	 */
	public function getSchema() {
		global $wgJsonDataNamespace, $wgJsonDataSchemaFile, $wgJsonDataSchemaArticle;

		$config = $this->getConfig();
		$tag = $config['namespaces'][$this->nsname]['defaulttag'];

		$schemaconfig = $config['tags'][$tag]['schema'];
		$schemaTitle = null;
		if ( isset( $schemaconfig['schemaattr'] ) && ( preg_match( '/^(\w+)$/', $schemaconfig['schemaattr'] ) > 0 ) ) {
			// on preview, pull $revtext out from the submitted text, so that the author can change
			// schemas during preview
			$revtext = $this->out->getRequest()->getText( 'wpTextbox1' );
			// wpTextbox1 is empty in normal editing, so pull it from article->getText() instead
			if ( empty( $revtext ) ) {
				$revtext = $this->article->getText();
			}
			if ( preg_match( '/^<[\w]+\s+([^>]+)>/m', $revtext, $matches ) > 0 ) {
				/*
				 * Quick and dirty regex for parsing schema attributes that hits the 99% case.
				 * Bad matches: foo="bar' , foo='bar"
				 * Bad misses: foo='"r' , foo="'r"
				 * Works correctly in most common cases, though.
				 * \x27 is single quote
				 */
				if ( preg_match( '/\b' . $schemaconfig['schemaattr'] . '\s*=\s*["\x27]([^"\x27]+)["\x27]/', $matches[1], $subm ) > 0 ) {
					$schemaTitle = $subm[1];
				}
			}
		}
		if ( !is_null( $schemaTitle ) ) {
			$schema = $this->readJsonFromArticle( $schemaTitle );
			if ( $schema == '' ) {
				throw new Exception( "Invalid schema definition in ${schemaTitle}" );
			}
		}
		elseif ( $config['tags'][$tag]['schema']['srctype'] == 'article' ) {
			$titleName = $config['tags'][$tag]['schema']['src'];
			$schema = $this->readJsonFromArticle( $titleName );
			if ( $schema == '' ) {
				throw new Exception( "Invalid schema definition in ${titleName}.  Check your site configuation for this tag." );
			}
		}
		elseif ( $config['tags'][$tag]['schema']['srctype'] == 'predefined' ) {
			$filekey = $config['tags'][$tag]['schema']['src'];
			$schema = $this->readJsonFromPredefined( $filekey );
		}
		else {
			throw new Exception( "Invalid srctype value in JsonData site config" );
		}
		return $schema;
	}

	/*
	 * Read json-formatted data from an article, stripping off parser tags
	 * surrounding it.
	 */
	public function readJsonFromArticle( $titleText ) {
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
	 * Read json-formatted data from a predefined data file.
	 */
	public function readJsonFromPredefined( $filekey ) {
		global $wgJsonDataPredefinedData;
		return file_get_contents( $wgJsonDataPredefinedData[$filekey] );
	}
}
