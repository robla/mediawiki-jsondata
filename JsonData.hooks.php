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
		global $wgJsonDataNamespace;
		$title = $out->getTitle();
		$ns = $title->getNamespace();
		if( array_key_exists($ns, $wgJsonDataNamespace ) ) {
			$out->addModules( 'ext.jsonwidget' );
		}
		return true;
	}

	/**
	 * Muck with the editor interface
	 * @param EditPage $editPage
	 */
	public static function onEditPageShowEditFormInitial( &$editPage ) {
		global $wgOut, $wgJsonDataNamespace, $wgJsonDataSchemaFile, $wgJsonDataSchemaArticle;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		$ns = $title->getNamespace();
		if( array_key_exists($ns, $wgJsonDataNamespace ) ) {
			$wgOut->addHTML( <<<HEREDOC
<div id="je_servererror">${servererror}</div>
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
			if( array_key_exists($ns, $wgJsonDataSchemaArticle) ) {
				// copied from Lingo
                $title = Title::newFromText( $wgJsonDataSchemaArticle[$ns] );
                //if ( $title->getInterwiki() ) {
                //        $this->getMessageLog()->addError( wfMsgForContent( 'lingo-terminologypagenotlocal' , $page ) );
                //        return false;
                //}

                $rev = Revision::newFromTitle( $title );
                //if ( !$rev ) {
                //        $this->getMessageLog()->addWarning( wfMsgForContent( 'lingo-noterminologypage' , $page ) );
                //        return false;
                //}
                $schema = preg_replace(array('/^<json[^>]*>/m', '/<\/json>$/m'), array("", ""), $rev->getText());
				$wgOut->addHTML( htmlspecialchars( $schema ) );
			}
			else {
				$wgOut->addHTML( file_get_contents( $wgJsonDataSchemaFile[$ns] ) );			
			}
			$wgOut->addHTML( <<<HEREDOC
</textarea>
HEREDOC
			);
		}
		else {
			$wgOut->addHTML('wgJsonDataNamespace: '.$wgJsonDataNamespace);
			$wgOut->addHTML('<br/>ns: '.$ns);
		}
		return true;
	}

	function onEditPageBeforeEditToolbar(&$toolbar)
	{
		$toolbar = '';
		return false;
	}

	function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'json',  __CLASS__ . '::jsonTagRender' );
		return true;
	}
 
	function jsonTagRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		return "<pre>" . htmlspecialchars( $input ) . "</pre>";
	}
}
