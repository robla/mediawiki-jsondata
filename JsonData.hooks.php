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
		global $wgOut, $wgJsonDataNamespace;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		$ns = $title->getNamespace();
		if( $ns == $wgJsonDataNamespace  ) {
			$wgOut->addHTML( <<<HEREDOC
<div id="je_servererror">${servererror}</div>
<div id="je_warningdiv">
</div>

<div class="vectorTabs">
	<ul>
					<li class="selected"><span id="je_formbutton" style="cursor: pointer"><a>Edit w/Form</a></span></li>
					<li class="new"><span id="je_sourcebutton" style="cursor: pointer"><a>Edit Source</a></span></li>
			</ul>
</div>

<div id="je_formdiv" style="text-background: white">
</div>

<div>

<div id="je_schemaformdiv" style="text-background: white">
</div>

<textarea id="je_schematextarea" style="display: none" rows="30" cols="80">
{
  "type":"map",
  "title":"Address Book Entry",
  "mapping":
  {
    "firstName":
    {
      "type":"str",
      "title":"First Name",
      "required":true
    },
    "lastName":
    {
      "type":"str",
      "title":"Last Name",
      "required":true
    },
    "streetAddress":
    {
      "type":"str",
      "title":"Street Address",
      "required":true
    },
    "city":
    {
      "type":"str",
      "title":"City",
      "required":true
    },
    "state":
    {
      "type":"str",
      "title":"State",
      "required":true
    },
    "postalCode":
    {
      "type":"str",
      "title":"Postal (Zip) Code",
      "required":true
    },
    "phoneNumbers":
    {
      "type":"seq",
      "title":"Phone numbers",
      "required":true,
      "sequence":
      [
        {
          "type":"str",
          "required":true,
          "title":"Phone number"
        }
      ]
    }
  }
}
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
