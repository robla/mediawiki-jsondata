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
		$this->out->addHTML( <<<HEREDOC
<div id="je_servererror"></div>
<div id="je_warningdiv"></div>

<div style="height:20px;">
	<div class="vectorTabs">
		<ul>
			<li><span id="je_formbutton"><a>Edit w/Form</a></span></li>
			<li><span id="je_sourcebutton"><a>Edit Source</a></span></li>
		</ul>
	</div>
</div>

<div id="je_formdiv"></div>

<textarea id="je_schematextarea" style="display: none" rows="30" cols="80">
HEREDOC
			);
			$this->outputSchema();
			$this->out->addHTML( <<<HEREDOC
</textarea>
HEREDOC
			);
	}
	
	/**
	 * Output the schema in the right spot of the form
	 */
	public function outputSchema() {
		global $wgJsonDataNamespace, $wgJsonDataSchemaFile, $wgJsonDataSchemaArticle;

		$configText = $this->readJsonFromArticle( "JsonConfig:Sample" );

		$config = json_decode( $configText, TRUE );
		$tag = $config['namespaces'][$this->nsname]['defaulttag'];


		$schemaconfig = $config['tags'][$tag]['schema'];
		$schemaTitle = null;
		if(isset($schemaconfig['schemaattr']) && (preg_match('/^(\w+)$/', $schemaconfig['schemaattr']) > 0)) {
			$revtext = $this->article->getText();
			if (preg_match('/^<[\w]+\s+([^>]+)>/m', $revtext, $matches) > 0) {
				// Quick and dirty regex for parsing schema attributes that hits the 99% case.
				// Bad matches: foo="bar' , foo='bar"
				// Bad misses: foo='"r' , foo="'r"
				// Works correctly in most common cases, though.
				// \x27 is single quote
				if (preg_match('/\b'.$schemaconfig['schemaattr'].'\s*=\s*["\x27]([^"\x27]+)["\x27]/', $matches[1], $subm) > 0) {
					$schemaTitle = $subm[1];
				}
			}
		}
		if(!is_null($schemaTitle)) {
			$schema = $this->readJsonFromArticle( $schemaTitle );
			$this->out->addHTML( htmlspecialchars( $schema ) );
		}
		elseif($config['tags'][$tag]['schema']['srctype'] == 'article') {
			$titleName = $config['tags'][$tag]['schema']['src'];
			$schema = $this->readJsonFromArticle( $titleName );
			$this->out->addHTML( htmlspecialchars( $schema ) );
		}
		elseif($config['tags'][$tag]['schema']['srctype'] == 'predefined') {
			$filekey = $config['tags'][$tag]['schema']['src'];
			$schema = $this->readJsonFromPredefined( $filekey );
			$this->out->addHTML( $schema );
		}
		else {
			print "Invalid srctype value";
			exit();
		}
	}
	
	public function readJsonFromArticle( $titleText ) {
		$retval = array('json'=>null, 'tag'=>null, 'attrs'=>null);
		$title = Title::newFromText( $titleText );
		$rev = Revision::newFromTitle( $title );
		$revtext = $rev->getText();
		return preg_replace(array('/^<[\w]+[^>]*>/m', '/<\/[\w]+>$/m'), array("", ""), $revtext);
	}

	public function readJsonFromPredefined( $filekey ) {
		global $wgJsonDataPredefinedData;
		return file_get_contents( $wgJsonDataPredefinedData[$filekey] );
	}
}
