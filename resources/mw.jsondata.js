// JsonData integration into MediaWiki
// Copyright (c) 2011 Rob Lanphier.
// See http://robla.net/jsonwidget/LICENSE for license (3-clause BSD-style)

// check for the jsonedit form div
if($("#je_formdiv").length > 0) {
    //initialize jsonwidget editor (jsonedit.js)
    jsonwidget.language = 'en';
    var je=new jsonwidget.editor();
	je.htmlids.sourcetextarea = 'wpTextbox1';
	je.htmlids.sourcetextform = 'editform';
    je.setView('form');
}

