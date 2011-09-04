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
		$out->addModules( 'ext.jsonwidget' );
		return true;
	}

	/**
	 * Muck with the editor interface
	 * @param EditPage $editPage
	 */
	public static function onEditPageShowEditFormInitial( &$editPage ) {
		global $wgOut, $wgJsonDataNamespace, $wgJsonDataSchemaFile;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		$ns = $title->getNamespace();
		if( $ns == $wgJsonDataNamespace  ) {
			$wgOut->addHTML( <<<HEREDOC
<div id="je_servererror">${servererror}</div>
<div id="je_warningdiv"></div>

<div class="vectorTabs">
	<ul>
		<li class="selected"><span id="je_formbutton" style="cursor: pointer"><a>Edit w/Form</a></span></li>
		<li class="new"><span id="je_sourcebutton" style="cursor: pointer"><a>Edit Source</a></span></li>
	</ul>
</div>

<div id="je_formdiv"></div>

<textarea id="je_schematextarea" style="display: none" rows="30" cols="80">
HEREDOC
			);
			$wgOut->addHTML( file_get_contents( $wgJsonDataSchemaFile ) );			

			$wgOut->addHTML( <<<HEREDOC
</textarea>
<form method='POST' id="je_sourcetextform">
<input type="hidden" name="jsonsubmit" value="true"/>
<input type="hidden" value="Submit JSON"/>
</p>
</form>
HEREDOC
			);
		}
		else {
			$wgOut->addHTML('wgJsonDataNamespace: '.$wgJsonDataNamespace);
			$wgOut->addHTML('<br/>ns: '.$ns);
		}
		return true;
	}
}
