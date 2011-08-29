<?php

class JsonDataHooks {
	/**
	 * Muck with the editor interface
	 * @param EditPage $editPage
	 */
	public static function onEditPageShowEditFormInitial( &$editPage ) {
		global $wgOut, $wgJsonDataNamespace;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		$ns = $title->getNamespace();
		if( $ns == $wgJsonDataNamespace  ) {
			$wgOut->addHTML( <<<HEREDOC
<html>
 
<head>
 
<title>JSON widget prototype</title>


<script src="json.js"></script> 
<script src="jsonedit.js"></script> 

<link rel="stylesheet" type="text/css" href="jsonwidget.css" />

<script type="text/javascript" language="javascript">

function sample_init() {
    jsonwidget.language = 'en';
    var je=new jsonwidget.editor();

    je.setView('form');
}

</script>

</head>
 
<body onload="sample_init();">

<div id="je_servererror">${servererror}</div>
<div id="je_warningdiv">
</div>

<div>
<span id="je_formbutton" style="cursor: pointer">[Edit w/Form]</span>
<span id="je_sourcebutton" style="cursor: pointer">[Edit Source]</span>
</div>


<div id="je_formdiv" style="text-background: white">
</div>

<div>

<div id="je_schemaformdiv" style="text-background: white">
</div>

<textarea id="je_schematextarea" style="display: none" rows="30" cols="80">
${schema}
</textarea>

<form method='POST' id="je_sourcetextform">
<textarea id="je_sourcetextarea" rows="30" cols="80" name="sourcearea">
${json}
</textarea>
<p>
<input type="hidden" name="jsonsubmit" value="true"/>
      <input type="submit" value="Submit JSON"/>
</p>
</form>

</div>


</body>
</html>
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
