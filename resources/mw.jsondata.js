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
    this.beginContext = "<json>\n";
    this.endContext = "\n</json>";
}

mwjsondata.context.addContextText = function (jsontext) {
    return this.beginContext + jsontext + this.endContext;
}

// remove and store context
mwjsondata.context.removeContextText = function (jsontext) {
    var begintag = /^\s*<[\w]+[^>]*>\s*\n?/m;
    var endtag = /\n?\s*<\/[\w]+>\s*$/m;
    var m = jsontext.match(begintag);
    if(m != null) {
        this.beginContext = m;
    }
    m = jsontext.match(endtag);
    if(m != null) {
        this.endContext = m;
    }
    jsontext = jsontext.replace(begintag, "");
    jsontext = jsontext.replace(endtag, "");
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
    je.views = ['form','source','schemaexample'];
    je.setView('form');
}

