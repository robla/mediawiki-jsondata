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

	public function __construct() {
		global $wgOut;
		$this->out = $wgOut;
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

		if( array_key_exists($this->ns, $wgJsonDataSchemaArticle) ) {
			$title = Title::newFromText( $wgJsonDataSchemaArticle[$this->ns] );
			$rev = Revision::newFromTitle( $title );
			$schema = preg_replace(array('/^<json[^>]*>/m', '/<\/json>$/m'), array("", ""), $rev->getText());
			$this->out->addHTML( htmlspecialchars( $schema ) );
		}
		else {
			$this->out->addHTML( file_get_contents( $wgJsonDataSchemaFile[$this->ns] ) );			
		}
	}
}
