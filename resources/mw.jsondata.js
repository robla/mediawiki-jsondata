// JsonData integration into MediaWiki
// Copyright (c) 2011 Rob Lanphier.
// See http://robla.net/jsonwidget/LICENSE for license (3-clause BSD-style)


//
// Context object - this adds/strips context text.  This
// is used to add and remove <json>...</json> tags as appropriate.
//

var mwjsondata = function() {}

mwjsondata.context = function () {
    this.addContextText = mwjsondata.context.addContextText;
    this.removeContextText = mwjsondata.context.removeContextText;
}

mwjsondata.context.addContextText = function (jsontext) {
    return "<json>\n" + jsontext + "\n</json>";
}

mwjsondata.context.removeContextText = function (jsontext) {
    jsontext = jsontext.replace(/<json>/m, "");
    jsontext = jsontext.replace(/<\/json>$/, "");
    return jsontext;
}


// check for the jsonedit form div
if($("#je_formdiv").length > 0) {
    //initialize jsonwidget editor (jsonedit.js)
    jsonwidget.language = 'en';
    var je=new jsonwidget.editor();
	je.htmlids.sourcetextarea = 'wpTextbox1';
	je.htmlids.sourcetextform = 'editform';
    je.context = new mwjsondata.context();
    je.setView('form');
}

