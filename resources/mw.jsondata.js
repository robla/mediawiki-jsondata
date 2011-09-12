// JsonData integration into MediaWiki
// Copyright (c) 2011 Rob Lanphier.
// See http://robla.net/jsonwidget/LICENSE for license (3-clause BSD-style)

function sample_init() {
    jsonwidget.language = 'en';
    var je=new jsonwidget.editor();
	je.htmlids.sourcetextarea = 'wpTextbox1';
	je.htmlids.sourcetextform = 'editform';
    je.setView('form');
}

if($("#je_formdiv").length > 0) {
	sample_init();
}

