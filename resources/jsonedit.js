// Copyright (c) 2005-2012 Rob Lanphier.
// See http://robla.net/jsonwidget/LICENSE for license (BSD style)

var jsonwidget = function() {}

jsonwidget.localstrings = {
    'fr': {'help': 'aide',
        'no': 'non',
        'yes': 'oui',
        'del': 'suppr',
        'hide': 'masquer',
        'show': 'afficher',
        'delete': 'supprimer',
        'Root node': 'Racine',
        'Add property': 'Ajouter un attribut',
        'Add ': 'Ajouter un ',
        ' to ': ' a ',
    },
};

/*
 * faux-gettext() implementation.
 * @param {object} nodeindex - the key in an object, or index in an array
 * @return {string} string suitable for use as a title
 */
function _(s) {
    rc = s
    if (typeof(jsonwidget.language)!='undefined' &&
        typeof(jsonwidget.localstrings[jsonwidget.language])!='undefined' &&
        jsonwidget.localstrings[jsonwidget.language][s]) {
        rc = jsonwidget.localstrings[jsonwidget.language][s];
    }
    return rc;
}

/*
 * Converts the string into something safe for an HTML id.
 * performs the easiest transformation to safe id, but is lossy
 * @param {string} str - pretransform string
 * @return {string} result of transformation
 */
jsonwidget.stringToId = function (str) {
    // performs the easiest transformation to safe id, but is lossy
    if((typeof str)=='number') {
        return str.toString();
    }
    else if ((typeof str)=='string') {
        return str.replace(/[^a-z0-9\-_:\.]/gi, "");
    }
    else error();
}

/*
 * Given a type (e.g. 'object', 'integer', 'string'), return the default/empty
 * value for that type.
 * @param {string} thistype - the type as defined in the schema
 * @return {any} an empty value appropriate for the type
 */
jsonwidget.getNewValueForType = function(thistype) {
    switch(thistype) {
    case 'object':
        var newvalue = new Object();
        break;

    case 'array':
        var newvalue = new Array();
        break;

    case 'number':
    case 'integer':
        var newvalue = 0;
        break;

    case 'string':
        var newvalue = "";
        break;

    case 'boolean':
        var newvalue = false;
        break;
    default:
        var newvalue = null;
        break;
    }
    return newvalue;
}


/*
 * Return a JSON-schema type for arbitrary data in var foo
 * @param {any} foo - data to get the type for
 * @return {string} JSON-schema type for the data foo
 */
jsonwidget.getType = function (foo) {
    if(foo==null) {
        return undefined;
    }

    switch(typeof foo) {
        case "object":
        if(foo.constructor == Array) {
            return "array";
        }
        else {
            return "object";
        }
        break;

        case "number":
        return "number";
        break;

        case "boolean":
        return "boolean";
        break;

        case "string":
        return "string";
        break;

        default:
        return undefined;
        break;
    }
}

/*
 * Generate a schema from a data example (var parent)
 * @param {object} parent - raw data to generate schema for
 * @return {object} a schema object appropriate for that element
 */
jsonwidget.getSchemaArray = function (parent) {
    var schema = new Object();

    schema.type = jsonwidget.getType(parent);

    switch (schema.type) {
    case 'object':
        schema.properties = new Object();
        for (var name in parent) {
            schema.properties[name]= jsonwidget.getSchemaArray(parent[name]);
        }
        break;
    case 'array':
        schema.items = new Array();
        schema.items[0] = jsonwidget.getSchemaArray(parent[0]);
        break;
    }
    return schema;
}


/*
 * Internal terminology:
 *   Node: "node" in the graph theory sense, but specifically, a node in the
 *    raw Javascript data representation of the structure
 *   Ref: a node in the object tree.  Refs contain nodes and metadata about the
 *    nodes, as well as pointers to parent refs
 */

/*
 * treeRef object:
 * Structure for representing a generic tree which each node is aware of its
 * context (can refer to its parent).  Used for schema refs.
 */
jsonwidget.treeRef = function (node, parent, nodeindex) {
    this.node = node;
    this.parent = parent;
    this.nodeindex = nodeindex;
}

/*
 * jsonTreeRef object
 * Structure for representing a data tree, where each node (ref) is aware of its
 * context and associated schema.
 */
jsonwidget.jsonTreeRef = function (node, parent, nodeindex, schemaref) {
    this.node = node;
    this.parent = parent;
    this.nodeindex = nodeindex;
    this.schemaref = schemaref;

    this.init = jsonwidget.jsonTreeRef.init;
    this.isUserKey = jsonwidget.jsonTreeRef.isUserKey;
    this.renamePropname = jsonwidget.jsonTreeRef.renamePropname;
    this.getType = jsonwidget.jsonTreeRef.getType;
    this.getTitle = jsonwidget.jsonTreeRef.getTitle;
    this.getFullIndex = jsonwidget.jsonTreeRef.getFullIndex;
    this.attachSchema = jsonwidget.jsonTreeRef.attachSchema;

    this.init();
}


jsonwidget.jsonTreeRef.init = function () {
    this.fullindex = this.getFullIndex();
    this.attachSchema();
}

/*
 * Associate the relevant node of the JSON schema to this node in the JSON
 */
jsonwidget.jsonTreeRef.attachSchema = function () {
    if(this.schemaref.node.type == 'any'
       || this.schemaref.node.type == undefined) {
        if(this.getType()=='object') {
            this.schemaref.node.additionalProperties = {};;
        }
        else if(this.getType()=='array') {
            this.schemaref.node.additionalItems = [{}];
        }
    }
}

/*
 *  Return the title for this ref, typically defined in the schema as the
 *  user-friendly string for this node.
 */
jsonwidget.jsonTreeRef.getTitle = function () {
    var title = this.schemaref.node.title;
    // generate sensible defaults if title isn't defined in schema
    if(typeof(title) != 'string') {
        // "this" is the root node
        if(!(this.parent instanceof jsonwidget.jsonTreeRef)) {
            return _("Root node");
        }
        // "this" is a property of an object
        else if(this.parent.getType() == 'object') {
            return this.nodeindex;
        }
        // "this" is an item in an array
        else {
            title = "Item";
        }
    }

    // tack on 1-based array index to title in arrays
    if(this.parent instanceof jsonwidget.jsonTreeRef && this.parent.getType() == 'array') {
        title = title + " #" + (parseInt(this.nodeindex)+1);
    }
    return title;
}

/*
 *  Is this node a "user key", as defined in http://jsonwidget.org/jsonschema/
 */
jsonwidget.jsonTreeRef.isUserKey = function () {
    return this.userkeyflag;
}

/*
 * Rename a user key.  Useful for interactive editing/modification, but not
 * so helpful for static interpretation.
 */
jsonwidget.jsonTreeRef.renamePropname = function (newindex) {
    var oldindex = this.nodeindex;
    this.parent.node[newindex] = this.node;

    this.nodeindex = newindex;
    this.fullindex = this.getFullIndex();

    delete this.parent.node[oldindex];
}

/*
 * Return the type of this node as specified in the schema.  If "any",
 * infer it from the data.
 */
jsonwidget.jsonTreeRef.getType = function () {
    var nodetype = this.schemaref.node.type;
    if(nodetype == 'any' || nodetype == undefined) {
        if(this.node == undefined) {
            return undefined;
        }
        else {
            return jsonwidget.getType(this.node);
        }
    }
    else {
        return nodetype;
    }
}

/*
 * Return a unique identifier that may be used to find a node.  This
 * is only as robust as stringToId is (i.e. not that robust), but is
 * good enough for many cases.
 */
jsonwidget.jsonTreeRef.getFullIndex = function () {
    if(this.parent==undefined) {
        return "json_root";
    }
    else {
        return this.parent.getFullIndex() + "." + jsonwidget.stringToId(this.nodeindex);
    }
}


/*
 * schemaIndex object
 * The schemaIndex object holds all schema refs with an "id", and is used
 * to resolve an idref to a schema ref.  This also holds the root of the schema
 * tree.  This also serves as sort of a class factory for schema refs.
 * The whole tree is indexed on instantiation of this class.
 */
jsonwidget.schemaIndex = function (schema) {
    this.root = schema;
    this.idtable = {};
    this.debugwindow = document.getElementById("je_warningdiv");

    if(!this.root) {
        return null;
    }
    this.indexSubtree = jsonwidget.schemaIndex.indexSubtree;
    this.newRef = jsonwidget.schemaIndex.newRef;

    this.indexSubtree(this.root);
}

/*
 * Recursively find all of the ids in this schema, and store them in the
 * index.
 */
jsonwidget.schemaIndex.indexSubtree = function (schemanode) {
    var nodetype = schemanode.type;

    switch(nodetype) {
    case 'object':
        for(var i in schemanode.properties) {
            this.indexSubtree(schemanode.properties[i]);
        }
        break;
    case 'array':
        for(var i in schemanode.items) {
            this.indexSubtree(schemanode.items[i]);
        }
        break;
    }

    if(undefined != schemanode.id) {
        this.idtable[schemanode.id] = schemanode;
    }
}

/*
 *  Generate a new schema ref, or return an existing one from the index if
 *  the node is an idref.
 */
jsonwidget.schemaIndex.newRef = function (node, parent, nodeindex) {
    if(node.type == '$ref') {
        node =  this.idtable[node['$ref']];
    }
    return new jsonwidget.treeRef(node, parent, nodeindex);
}

//
// Context object - this adds/strips context text.  For example, in MediaWiki, this
// is overridden to add and remove <json>...</json> tags as appropriate.  By default,
// this doesn't do much.
//
jsonwidget.context = function () {
    this.addContextText = jsonwidget.context.addContextText;
    this.removeContextText = jsonwidget.context.removeContextText;
}

jsonwidget.context.addContextText = function (jsontext) {
    return jsontext;
}

jsonwidget.context.removeContextText = function (jsontext) {
    return jsontext;
}


/*
 * editor object
 * This is the part that actually constructs the web form.  As of this writing,
 * this object also contains important bits of schema handling mixed in (a
 * regrettable design decision that will hopefully be untangled in the future).
 */
jsonwidget.editor = function () {
    this.buffer = new Array();
    this.bufferTree = document.createElement("div");
    this.bufferCurrent = this.bufferTree;

    this.jsonLookupById = new Array();
    this.bgcolor = "#f0f0ff";

    //override if you want to use different ids
    this.htmlids = {
        "warningdiv": "je_warningdiv",
        "formdiv": "je_formdiv",
        "schemaformdiv": "je_schemaformdiv",
        "sourcetextarea": "je_sourcetextarea",
        "schematextarea": "je_schematextarea",
        "schemaexamplearea": "je_schemaexamplearea",
        "schemaschematextarea": "je_schemaschematextarea",
        "byexamplebutton": "je_byexamplebutton",
        "sourcetextform": "je_sourcetextform"
    }
    this.debuglevel=0;

    this.views = ['form','source'];
    this.viewHandler = [];
    this.formview = 'form';

    this.showByExampleButton = false;

    this.htmlbuttons = {
        form: "je_formbutton",
        source: "je_sourcebutton",
        schemasource: "je_schemasourcebutton",
        schemaform: "je_schemaformbutton",
        schemaexample: "je_schemaexamplebutton"
    }

    this.classname = {
        fgbutton: "je_foreground",
        bgbutton: "je_background"
    }

    this.context = new jsonwidget.context();

    this.formdiv = document.getElementById(this.htmlids.formdiv);
    this.activehelp = null;

    this.showForm = jsonwidget.editor.showForm;
    this.clearForm = jsonwidget.editor.clearForm;
    this.attachHandlers = jsonwidget.editor.attachHandlers;
    this.setNode = jsonwidget.editor.setNode;
    this.updateNode = jsonwidget.editor.updateNode;
    this.deleteNode = jsonwidget.editor.deleteNode;
    this.getArrayInputAttrs = jsonwidget.editor.getArrayInputAttrs;
    this.getHelpButton = jsonwidget.editor.getHelpButton;
    this.getShowButton = jsonwidget.editor.getShowButton;
    this.addItemToSequence = jsonwidget.editor.addItemToSequence;
    this.getAddToSeqButton = jsonwidget.editor.getAddToSeqButton;
    this.getHideButton = jsonwidget.editor.getHideButton;
    this.getDeleteButton = jsonwidget.editor.getDeleteButton;
    this.addPropToMapping = jsonwidget.editor.addPropToMapping;
    this.getAddToObjectLinks = jsonwidget.editor.getAddToObjectLinks;
    this.attachArrayInput = jsonwidget.editor.attachArrayInput;
    this.showPropnameInput = jsonwidget.editor.showPropnameInput;
    this.getPropnameSpan = jsonwidget.editor.getPropnameSpan;
    this.attachSimplePropertyInput = jsonwidget.editor.attachSimplePropertyInput;
    this.getStringInput = jsonwidget.editor.getStringInput;
    this.getBoolInput = jsonwidget.editor.getBoolInput;
    this.getNumberInput = jsonwidget.editor.getNumberInput;
    this.getTypeSelectInput = jsonwidget.editor.getTypeSelectInput;
    this.attachNodeInput = jsonwidget.editor.attachNodeInput;
    this.getSetViewFunction = jsonwidget.editor.getSetViewFunction;
    this.setView = jsonwidget.editor.setView;
    this.getStatusLightDelay = jsonwidget.editor.getStatusLightDelay;
    this.setStatusLight = jsonwidget.editor.setStatusLight;
    this.clearStatusLight = jsonwidget.editor.clearStatusLight;
    this.toggleToFormActual = jsonwidget.editor.toggleToFormActual;
    this.updateArea = jsonwidget.editor.updateArea;
    this.updateJSON = jsonwidget.editor.updateJSON;
    this.updateSchemaExample = jsonwidget.editor.updateSchemaExample;
    this.error = jsonwidget.editor.error;
    this.debugOut = jsonwidget.editor.debugOut;
    this.warningOut = jsonwidget.editor.warningOut;
    this.clearWarnings = jsonwidget.editor.clearWarnings;
    this.handleParseError = jsonwidget.editor.handleParseError;
    this.contextHelp = jsonwidget.editor.contextHelp;
    this.confirmDelete = jsonwidget.editor.confirmDelete;
    this.getSchema = jsonwidget.editor.getSchema;
    this.schemaEditInit = jsonwidget.editor.schemaEditInit;
    this.byExampleInit = jsonwidget.editor.byExampleInit;
    this.createSchemaFromExample = jsonwidget.editor.createSchemaFromExample;
    this.attachByExampleText = jsonwidget.editor.attachByExampleText;
    this.setFormOnSubmit = jsonwidget.editor.setFormOnSubmit;
}

/*
 * If the schema editing tool is in use, initialize that too.
 */
jsonwidget.editor.schemaEditInit = function () {
    this.schemaedit = new jsonwidget.editor();
    var je = this;
    this.schemaedit.setView = function (viewtoset) {
        je.setView(viewtoset);
    }
    this.schemaedit.setStatusLight = function (statustext) {
        je.setStatusLight(statustext);
    }
    this.schemaedit.clearStatusLight = function () {
        je.clearStatusLight();
    }
    this.schemaedit.getStatusLightDelay = function (jsonref) {
        return je.getStatusLightDelay(jsonref);
    }
    this.schemaedit.formview = 'schemaform';
    this.schemaedit.htmlids.formdiv = this.htmlids.schemaformdiv;
    this.schemaedit.htmlids.sourcetextarea = this.htmlids.schematextarea;
    this.schemaedit.htmlids.schematextarea = this.htmlids.schemaschematextarea;
}

/*
 * TODO: delete this.  kinda lame.  Only really used by the demo.
 */
jsonwidget.editor.byExampleInit = function () {
    this.showByExampleButton = true;
}

/*
 * Generate a schema based on the JSON in the edit form.  The real work for
 * this is done by jsonwidget.getSchemaArray()
 */
jsonwidget.editor.createSchemaFromExample = function () {
    this.updateJSON();
    if(this.jsondata == null) {
        alert("I can't generate a very interesting schema from a blank document.  Try adding a little something to your JSON first");
    }
    else {
        this.schemaedit.jsondata = jsonwidget.getSchemaArray(this.jsondata);
        this.showByExampleButton = false;
        this.schemaedit.updateJSON();
        this.setView(this.formview);
    }
}

/*
 * This simply creates the button used for "create by example" function in the
 * demo.  TODO: move this out into the demo since this isn't core functionality.
 */

jsonwidget.editor.attachByExampleText = function () {
    var examplebutton = document.createElement('span');
    var je = this;
    examplebutton.id = this.htmlids.byexamplebutton;
    examplebutton.style.cursor = "pointer";
    examplebutton.appendChild(document.createTextNode("[Create schema by example]"));
    examplebutton.onclick = function () {
        je.createSchemaFromExample();
    }

    this.formdiv.appendChild(examplebutton);
    this.formdiv.appendChild(document.createTextNode(" - clicking this button will generate a schema based on the current JSON file, and load it into the schema area.  It will also lock the current document into its current structure."));
}


/*
 * There are generally at least two views: source view and form view.  This
 * function shows form view.
 */
jsonwidget.editor.showForm = function (formnode) {
    this.formdiv.appendChild(formnode);
    formnode.style.background = "#ffffff";
    this.formdiv.style.display="inline";
}

/*
 * Nuke all of the built-up form HTML.  The form gets rebuilt from JSON when
 * switching from another view, so this can get called frequently.
 */
jsonwidget.editor.clearForm = function () {
    var parent = this.formdiv.parentNode;
    var nextsibling = this.formdiv.nextSibling;
    parent.removeChild(this.formdiv);
    this.formdiv.innerHTML = "";
    parent.insertBefore(this.formdiv, nextsibling);
}

/*
 * This is where the form-building work begins.  This builds the
 * form for the root of the tree.
 */
jsonwidget.editor.attachHandlers = function () {
    var jsoneditobj = this;
    document.body.style.background = jsoneditobj.bgcolor;
    this.formdiv.onchange = function (event) {
        if(event.target.className == "jeclass") {
            var jsonnode = jsoneditobj.jsonLookupById[event.target.id.substr(8)];
            var nodetype = jsonnode.getType();

            if(nodetype == undefined) {
                var value=jsonwidget.getNewValueForType(event.target.value);
                jsonnode.node = value;
                jsoneditobj.updateNode(jsonnode);
            }
            else {
                if(nodetype == 'string') {
                    var value = event.target.value;
                }
                else if(nodetype == 'number' || nodetype == 'integer') {
                    var value = parseInt(event.target.value);
                }
                else if(nodetype == 'boolean') {
                    var value = event.target.checked;
                }

                jsonnode.node = value;
                if(jsonnode.parent != undefined) {
                    jsonnode.parent.node[jsonnode.nodeindex] = value;
                }
                else {
                    jsoneditobj.jsondata = value;
                }
            }
        }
    }

    this.formdiv.onclick = function (event) {
        var target = window.event ? window.event.srcElement : event.target;
        if(target.className == "rmx") {
            var jsonnode = jsoneditobj.jsonLookupById[target.id.substr(9)];
            jsonwidget.editor.confirmDelete(jsoneditobj, jsonnode, target);
        }
        if(target.className == "je_hide") {
            var nodeid = target.id.substr("hide".length+1);
            document.getElementById("contents."+nodeid).style.display="none";
            document.getElementById("hide."+nodeid).style.display="none";
            document.getElementById("show."+nodeid).style.display="inline";
        }
        if(target.className == "je_show") {
            var nodeid = target.id.substr("show".length+1);
            document.getElementById("contents."+nodeid).style.display="inline";
            document.getElementById("hide."+nodeid).style.display="inline";
            document.getElementById("show."+nodeid).style.display="none";
        }
        if(target.className == "je_help") {
            var jsonnode = jsoneditobj.jsonLookupById[target.id.substr("help".length+1)];
            jsonwidget.editor.contextHelp(event, jsonnode);
        }
    }
}

/*
 * Assign a value to this node.
 */
jsonwidget.editor.setNode = function (jsonnode, value) {
    if(jsonnode.parent != undefined) {
        jsonnode.parent.node[jsonnode.nodeindex] = value;
    }
    else {
        this.jsondata = value;
    }
}

/*
 * Update the form elements of a node to match the newly updated data in
 * that node.
 */
jsonwidget.editor.updateNode = function (jsonnode) {
    var value = jsonnode.node;
    if(jsonnode.parent != undefined) {
        jsonnode.parent.node[jsonnode.nodeindex] = value;
        jsonnode.attachSchema();

        var rownode = document.createElement("tr");
        jsonnode.domparent.parentNode.replaceChild(rownode, jsonnode.domparent);
        jsonnode.domparent = rownode;

        var je = this;
        this.setStatusLight("working...");
        setTimeout(function () {je.attachNodeInput(jsonnode);je.clearStatusLight();},this.getStatusLightDelay(jsonnode));
    }
    else {
        this.jsondata = value;
        this.updateJSON();
        this.setView(this.formview);
    }
}

/*
 * Delete the node, removing the associated HTML from the form.
 */
jsonwidget.editor.deleteNode = function (jsonindex) {
    var jsonnode = this.jsonLookupById[jsonindex];
    var parent = jsonnode.parent;
    jsonnode.domparent.parentNode.removeChild(jsonnode.domparent);
    if(parent != undefined) {
        if (parent.node instanceof Array) {
            parent.node.splice(jsonnode.nodeindex,1);
        }
        else {
            delete parent.node[jsonnode.nodeindex];
        }
        delete this.jsonLookupById[jsonindex];
        this.updateNode(parent);
    }
    else {
        this.jsondata = null;
        delete this.jsonLookupById[jsonindex];
        this.updateJSON();
        this.setView(this.formview);
    }
}

/*
 * This is where a lot of the heavy lifting is done.  This builds the form
 * elements for array data types (objects and arrays).  The UI is recursively
 * built for all of the data inside those arrays (calling attachNodeInput,
 * which in turn calls attachSimplePropertyInput or attachArrayInput, which
 * then calls this function).
 */

jsonwidget.editor.getArrayInputAttrs = function (jsonref) {
    var jsoni;
    var schemai;

    var contentsid='contents.'+jsonref.fullindex;
    var jsoneditobj = this;

    var retval = document.createElement("table");

    retval.id=contentsid;

    var tbody = document.createElement("tbody");

    for(var i in jsonref.node) {
        var rownode = document.createElement("tr");
        var userkeyflag;
        if(jsonref.getType()=='object') {
            if(jsonref.schemaref.node.properties == undefined ||
               jsonref.schemaref.node.properties[i] == undefined) {
                if(jsonref.schemaref.node.additionalProperties==false) {
                    this.warningOut("warning: unrecognized key: "+i);
                    continue;
                }
                else {
                    userkeyflag = true;
                    schemai = this.schemaindex.newRef(jsonref.schemaref.node.additionalProperties, jsonref.schemaref, null);
                }
            }
            else {
                userkeyflag = false;
                schemai = this.schemaindex.newRef(jsonref.schemaref.node.properties[i], jsonref.schemaref, i);

            }
            jsoni = new jsonwidget.jsonTreeRef(jsonref.node[i], jsonref, i, schemai);
        }
        else if (jsonref.getType()=='array') {
            var schemanode = {};
            if('items' in jsonref.schemaref.node) {
                schemanode = jsonref.schemaref.node.items[0];
            }
            schemai = this.schemaindex.newRef(schemanode, jsonref.schemaref, i);
            jsoni = new jsonwidget.jsonTreeRef(jsonref.node[i], jsonref, i, schemai);
        }
        jsoni.userkeyflag = userkeyflag;
        jsoni.domparent = rownode;
        this.jsonLookupById[jsoni.fullindex]=jsoni;
        this.attachNodeInput(jsoni);
        tbody.appendChild(rownode);
    }
    if(jsonref.getType()=='object') {
        var schemap = jsonref.schemaref.node.properties;
        for(var i in schemap) {
            //this.debugOut(1, schemap[i].required);

            if(jsonref.node[i]==undefined && schemap[i].required == true) {
                var rownode = document.createElement("tr");
                jsoni = this.addPropToMapping(jsonref, i);
                jsoni.domparent = rownode;
                this.attachNodeInput(jsoni);
                tbody.appendChild(rownode);
            }
        }
    }
    else if(jsonref.getType()=='array') {
        var schemanode = {};
        if('items' in jsonref.schemaref.node) {
            schema = jsonref.schemaref.node.items[0];
        }
        if(jsonref.node.length == 0 && ('required' in schemanode ) &&
           schemanode.required == true) {
            var rownode = document.createElement("tr");
            jsoni = this.addItemToSequence(jsonref);
            jsoni.domparent = rownode;
            this.attachNodeInput(jsoni);
            tbody.appendChild(rownode);
        }
    }

    retval.appendChild(tbody);
    return retval;
}

/*
 * Get the clickable help text.
 */
jsonwidget.editor.getHelpButton = function (jsonref) {
    var helpid='help.'+jsonref.fullindex;
    var retval = document.createElement("span");

    retval.className="je_help";
    retval.setAttribute("title", "help");
    retval.id=helpid;
    retval.appendChild(document.createTextNode("["+_('help')+"]"));
    return retval;
}

/*
 * Get the clickable "hide" text, which collapses the UI for a section of the
 * data hierarchy.
 */
jsonwidget.editor.getHideButton = function (jsonref) {
    var hideid='hide.'+jsonref.fullindex;
    var retval = document.createElement("span");

    retval.className="je_hide";
    retval.setAttribute("title", "hide");
    retval.id=hideid;
    retval.appendChild(document.createTextNode("["+_("hide")+"]"));
    return retval;
}

/*
 * Counterpart to "hide".  Clickable text to uncollapse a portion of the form.
 */
jsonwidget.editor.getShowButton = function (jsonref) {
    var showid='show.'+jsonref.fullindex;
    var retval = document.createElement("span");

    retval.className="je_show";
    retval.setAttribute("title", "show");
    retval.id=showid;
    retval.appendChild(document.createTextNode("["+_("show")+"]"));
    retval.style.display = "none";
    return retval;
}

/*
 * Clickable text which prunes this node and its children from the data tree.
 * A confirmation question is inserted inline before nuking the text.
 */
jsonwidget.editor.getDeleteButton = function (jsonref) {
    var retval = document.createElement("span");
    var rmid='rmbutton.'+jsonref.fullindex;
    var allowdelete = !jsonref.schemaref.node.required;
    // for sequences with required children, allow deletion if there's more than one child
    if(jsonref.parent != null && jsonref.parent.getType() == "array" && jsonref.parent.node.length > 1) {
        allowdelete = true;
    }
    if(allowdelete) {
        retval.className="rmx";
        retval.setAttribute("title", "delete");
    }
    else {
        retval.className="je_disabled";
        retval.setAttribute("title", "required attribute - deletion not allowed");
    }
    retval.id=rmid;
    retval.appendChild(document.createTextNode("["+_("del")+"]"));
    return retval;
}

/*
 * This adds a new key to an object, providing as sensible default value and adding
 * the new ref to the schemaIndex.
 */
jsonwidget.editor.addPropToMapping = function (jsonref, prop) {
    var newname = prop;
    var newindex = prop;
    var schemap = {};

    // 'null' key means this is a new additionalProperty
    if(prop == null) {
        newindexbase = jsonwidget.stringToId("newprop");
        propschema = jsonref.schemaref.node.additionalProperties;
        for(var i=1;true;i++) {
            newindex = newindexbase + "_" + i;
            if(jsonref.node[newindex] == undefined) {
                var nodenum = i;
                newname = newindex;
                break;
            }
        }
    }
    else {
        propschema = jsonref.schemaref.node.properties[prop];
    }

    var newschema = this.schemaindex.newRef(propschema, jsonref.schemaref, prop);
    var newvalue = jsonwidget.getNewValueForType(newschema.node.type);
    var newjson = new jsonwidget.jsonTreeRef(newvalue, jsonref, newindex, newschema);
    //TODO: figure out if this is vestigal code, or if there's ever a case
    // where this is valid.
    if (jsonref.node instanceof Array) {
        jsonref.node.push(newvalue);
    }
    else {
        jsonref.node[newindex]=newvalue;
    }
    this.jsonLookupById[newjson.fullindex]=newjson;
    return newjson;
}

/*
 * Links for adding properties to an object
 */
jsonwidget.editor.getAddToObjectLinks = function (jsonref) {
    // build a list of properties that don't have values yet
    var availableprops = [];
    for(var i in jsonref.schemaref.node.properties) {
        if(jsonref.node[i]==undefined) {
            availableprops.push(i);
        }
    }
    // add a "null" to availableprops to signify something from
    //"additionalProperties"
    if(jsonref.schemaref.node.additionalProperties != false) {
        availableprops.push(null);
    }

    // now build the HTML based on availableprops
    var retval = document.createElement("div");
    if(availableprops.length == 0) {
         return retval;
    }

    retval.appendChild(document.createTextNode(_("Add property")+": "));

    // create a comma-separated list of available properties
    for(var i = 0; i < availableprops.length; i++) {
        var prop = availableprops[i];
        var propschema = {};
        if( prop == null && (jsonref.schemaref.node.additionalProperties != undefined)) {
            propschema = jsonref.schemaref.node.additionalProperties;
        }
        else {
            propschema = jsonref.schemaref.node.properties[prop];
        }

        var je = this;

        var addlink = document.createElement("a");
        addlink.style.textDecoration = "underline";
        addlink.style.cursor = "pointer";
        addlink.onclick = function () {
            je.addPropToMapping(jsonref, prop);
            je.updateNode(jsonref);
        }
        var title;
        if(typeof(propschema.title) == 'string') {
            title = propschema.title;
        }
        else if (prop == null) {
            title = "New property";
        }
        else {
            title = prop;
        }
        addlink.appendChild(document.createTextNode(title));
        retval.appendChild(addlink);

        if(i < (availableprops.length - 1)) {
            retval.appendChild(document.createTextNode(", "));
        }
    }
    return retval;
}

// Add a new schema-compliant ref to the sequence stored in jsonref, returning
// the new ref.
jsonwidget.editor.addItemToSequence = function (jsonref) {
    var childschema = {};
    if('items' in jsonref.schemaref.node) {
        childschema = jsonref.schemaref.node.items[0];
    }
    var newname = 0;
    var newindex = ( jsonref.node instanceof Array ) ? jsonref.node.length : 0;
    var newschema = je.schemaindex.newRef(childschema, jsonref.schemaref, newindex);
    var newvalue = jsonwidget.getNewValueForType(newschema.node.type);

    var newjson = new jsonwidget.jsonTreeRef(newvalue, jsonref, newindex, newschema);
    if (jsonref.node instanceof Array) {
        jsonref.node.push(newvalue);
    }
    else {
        jsonref.node[newindex]=newvalue;
    }
    this.jsonLookupById[newjson.fullindex]=newjson;
    return newjson;
}

/*
 * Button to add another item to a sequence.
 */
jsonwidget.editor.getAddToSeqButton = function (jsonref) {
    var childschema = {};
    if('items' in jsonref.schemaref.node) {
        childschema = jsonref.schemaref.node.items[0];
    }
    var je = this;
    var addlink = document.createElement("a");
	var itemname = (undefined != childschema.title) ? childschema.title : "Item";
    addlink.style.textDecoration = "underline";
    addlink.style.cursor = "pointer";
    addlink.onclick = function () {
        je.addItemToSequence(jsonref);
        je.updateNode(jsonref);
    }
    var nodename = jsonref.getTitle();

    addlink.appendChild(document.createTextNode(_("Add ")+ itemname + _(" to ") + nodename ));
    return addlink;
}

/*
 * Adds the input for an object or an array associated with the given jsonref to
 * the form.
 */
jsonwidget.editor.attachArrayInput = function (jsonref) {
    var retval = document.createElement("fieldset");
    var localnode = document.createElement("legend");

    if(jsonref.isUserKey()) {
        localnode.appendChild(this.getPropnameSpan(jsonref));
    }
    else {
        localnode.appendChild(document.createTextNode(jsonref.getTitle()));
    }

    retval.appendChild(localnode);

    localnode = document.createElement("div");
    localnode.className="je_topcontrols";
    localnode.appendChild(this.getHelpButton(jsonref));
    localnode.appendChild(this.getHideButton(jsonref));
    localnode.appendChild(this.getShowButton(jsonref));
    localnode.appendChild(this.getDeleteButton(jsonref));
    retval.appendChild(localnode);

    retval.appendChild(this.getArrayInputAttrs(jsonref));

    if(jsonref.getType()=='object') {
        retval.appendChild(this.getAddToObjectLinks(jsonref));
    }
    if(jsonref.getType()=='array') {
        retval.appendChild(this.getAddToSeqButton(jsonref));
    }
    //wrap it all in a td
    if(jsonref.domparent.tagName == 'TR') {
        localnode = retval;
        retval = document.createElement("td");
        retval.setAttribute("colspan", 3);
        retval.appendChild(localnode);
    }
    jsonref.domparent.appendChild(retval);
}

/*
 * If the schema allows for editing the object key (i.e. if this is an
 * additionalProperty), this function provides the input to change the name of
 * the key (propname).  This isn't generally called directly, but rather
 * via getPropnameSpan, which only shows the editing input after an
 * onclick event.
 */
jsonwidget.editor.showPropnameInput = function (jsonref,htmlnode) {
    var nameinput = document.createElement("input");
    var nameinputid = 'nameinputid.'+jsonref.fullindex;
    nameinput.className = "jeclass";
    nameinput.className="je_userkey";
    nameinput.id=nameinputid;
    nameinput.size=20;
    nameinput.type="text";
    nameinput.setAttribute("value", jsonref.getTitle());

    var je = this;
    var parent = htmlnode.parentNode;
    var putback = function () {
        jsonref.renamePropname(this.value);
        var propnamespan=je.getPropnameSpan(jsonref);
        parent.replaceChild(propnamespan,nameinput);
        je.updateNode(jsonref);
        je.jsonLookupById[jsonref.fullindex]=jsonref;
        je.updateJSON();
    }

    nameinput.onblur = putback;
    //2011-10-08 - robla - commenting out onchange.  I'm guessing this was an IE5/6 hack
    // I needed in 2005
    //nameinput.onchange = putback;
    parent.replaceChild(nameinput, htmlnode);
    nameinput.focus();
}

/*
 * Displays an object key (propname) for editable keys (userkeys).  Adds an
 * onclick handler (showPropnameInput) to edit the key onclick.
 */
jsonwidget.editor.getPropnameSpan = function (jsonref) {
    var nameinput = document.createElement("span");
    nameinput.appendChild(document.createTextNode(jsonref.getTitle()));
    var nameinputid = 'nameinputid.'+jsonref.fullindex;
    nameinput.className="je_userkey";
    nameinput.id=nameinputid;
    var je = this;
    nameinput.onclick = function (event) {
        je.showPropnameInput(jsonref,this);
    }
    return nameinput;
}

/*
 * This inserts an input field for simple, non-array field types (e.g.
 * str, bool, int) via the DOM.  The HTML is appended to parent of the
 * jsonref parameter and a reference to that DOM node is maintained within
 * the jsonref.
 * @param jsonref {jsonwidget.jsonTreeRef} node to associate HTML with
 */
jsonwidget.editor.attachSimplePropertyInput = function (jsonref) {
    var valuetype = jsonref.getType();

    switch (valuetype) {
    case 'string':
        var inputelement = this.getStringInput(jsonref);
        break;

    case 'boolean':
        var inputelement = this.getBoolInput(jsonref);
        break;

    case 'number':
    case 'integer':
        var inputelement = this.getNumberInput(jsonref);
        break;

    case 'any':
    case undefined:
        var inputelement = this.getTypeSelectInput(jsonref);
        break;

    default:
        this.warningOut("unknown type: " + valuetype);
        var inputelement = this.getTypeSelectInput(jsonref);
        break;
    }


    if(jsonref.domparent.tagName == 'TR') {
        var parent = jsonref.domparent;
        var namenode = document.createElement("td");
        if(jsonref.isUserKey()) {
            namenode.appendChild(this.getPropnameSpan(jsonref));
        }
        else {
            namenode.appendChild(document.createTextNode(jsonref.getTitle()));
        }
        var valuenode = document.createElement("td");
        var controlnode = document.createElement("td");
    }
    else {
        var parent = document.createElement("fieldset");
        var localnode = document.createElement("legend");
        localnode.appendChild(document.createTextNode(jsonref.getTitle()));
        parent.appendChild(localnode);
        jsonref.domparent.appendChild(parent);

        var namenode = document.createElement("span");
        if(jsonref.getType() == undefined ) {
            namenode.appendChild(document.createTextNode("value type: "));
        }
        else {
            namenode.appendChild(document.createTextNode("value: "));
        }
        var valuenode = document.createElement("span");
        var controlnode = document.createElement("span");
        controlnode.appendChild(document.createTextNode(" "));
    }

    valuenode.appendChild(inputelement);

    var controldiv = document.createElement("span");
    controldiv.className="je_topcontrols";
    controldiv.appendChild(this.getHelpButton(jsonref));
    controldiv.appendChild(this.getDeleteButton(jsonref));

    controlnode.appendChild(controldiv);

    parent.appendChild(namenode);
    parent.appendChild(valuenode);
    parent.appendChild(controlnode);
}
/*
 * Return a string input, which can be a dropdown.
 * @param {object} jsone
 * @return {object} DOM input element
 */
jsonwidget.editor.getStringInput = function (jsonref) {
    var control = '';
    var curvalue = jsonref.node;
    var jsoneditobj = this;
    var inputid = 'inputid.'+jsonref.fullindex;

    if(jsonref.schemaref.node['enum']==undefined) {
        //switching to DOM creation in hopes of preventing injection problems
        var inputelement = document.createElement("input");
        inputelement.className = "jeclass";
        inputelement.id=inputid;
        inputelement.size=50;
        inputelement.type="text";
        inputelement.setAttribute("value", curvalue);
    } else {
        var inputelement = document.createElement("select");
        var validvalue = false;
        inputelement.className = "jeclass";
        inputelement.id=inputid;
        for(var i in jsonref.schemaref.node['enum']) {
            var option = document.createElement("option");
            option.appendChild(document.createTextNode(jsonref.schemaref.node['enum'][i]));
            if(jsonref.schemaref.node['enum'][i]==curvalue) {
                option.selected = true;
                validvalue = true;
            }
            inputelement.appendChild(option);
        }
        if(!validvalue) {
            this.setNode(jsonref, jsonref.schemaref.node['enum'][0]);
        }
    }
    return inputelement;
}

/*
 * When the schema allows type "any", then we need to declare what type
 * we want the value to be.  This returns a type selection dropdown.
 * @param {jsonTreeRef} JSON ref holding the data
 * @return {object} DOM node with type selector.
 */
jsonwidget.editor.getTypeSelectInput = function (jsonref) {
    var control = '';
    var curvalue = jsonref.node;
    var jsoneditobj = this;
    var inputid = 'inputid.'+jsonref.fullindex;
    var types = ['any','string','integer','number','boolean','array','object'];

    var inputelement = document.createElement("select");
    inputelement.className = "jeclass";
    inputelement.id=inputid;
    for(var i in types) {
        var option = document.createElement("option");
        option.appendChild(document.createTextNode(types[i]));
        inputelement.appendChild(option);
    }
    return inputelement;
}

/*
 * Return a checkbox to set a boolean value.
 * @param {jsonTreeRef} JSON ref holding the data
 * @return {object} DOM node with checkbox.
 */
jsonwidget.editor.getBoolInput = function getBoolInput(jsonref) {
    var jsoneditobj = this;
    var inputid = 'inputid.'+jsonref.fullindex;

    var inputelement = document.createElement("input");
    inputelement.id=inputid;
    inputelement.className = "jeclass";
    inputelement.type="checkbox";

    if(jsonref.node) {
        inputelement.setAttribute("checked", "true");
    }
    return inputelement;
}

/*
 * Return a field to type a number into.
 * @param {jsonTreeRef} JSON ref holding the data
 * @return {object} DOM node with checkbox.
 */
jsonwidget.editor.getNumberInput = function getNumberInput(jsonref) {
    var jsoneditobj = this;
    var inputid = 'inputid.'+jsonref.fullindex;

    if(jsonref.node == null) {
        jsonref.node = '';
    }
    var inputelement = document.createElement("input");
    inputelement.className = "jeclass";
    inputelement.id=inputid;
    inputelement.size=5;
    inputelement.type="text";
    inputelement.setAttribute("value", jsonref.node);
    inputelement.setAttribute("align", "right");

    return inputelement;
}

/*
 * Attach associated UI element to the part of the JSON tree referenced
 * by jsonref.  This is called in a number of contexts:
 * 1.  At the root to the JSON tree to build the UI
 * 2.  As part of recursively building the UI (from getArrayInputAttrs)
 * 3.  To refresh a portion of the UI
 * @param {jsonTreeRef} JSON ref holding the data
 */
jsonwidget.editor.attachNodeInput = function attachNodeInput(jsonref) {
    var nodetype = jsonref.getType();
    var startTime=new Date().getTime();
    var endTime;

    if(jsonref.schemaref == undefined) {
        this.warningOut("Unrecognized index: "+jsonref.toSource()+"<br/>\n");
        //try uncommenting to see if anything breaks...if so, add this
        //return;
    }

    if(jsonref.node == undefined) {
        jsonref.node = jsonwidget.getNewValueForType(nodetype);
    }

    switch(nodetype) {
        case 'object':
        case 'array':
        this.attachArrayInput(jsonref);
        break;

        default:
        this.attachSimplePropertyInput(jsonref);
        break;
    }
    endTime=new Date().getTime();
    jsonref.rendertime = endTime - startTime;
}

/*
 * Return a closure to set the view (e.g. to toggle between form and
 * source view)
 * @param {string} viewname - a valid view (see setView())
 * @return {function} a callback for setting the view.
 */
jsonwidget.editor.getSetViewFunction = function (viewname) {
    var je = this;
    return function () {
        je.setView(viewname);
    }
}

/*
 * Set the view (e.g. to toggle between form and source view).  Valid
 * view names include:
 * "form": automatically built form for editing JSON, derived from schema
 *    and its data
 * "source": edit the raw JSON source
 * "schemaform": edit the associated schema with a form
 * "schemasource": edit the schema source
 * "schemaexample": a schema automatically derived from the JSON data.
 * Other views can be defined in the viewHandler member variable.
 * @param {string} viewtoset - a valid view to make visible
 */
jsonwidget.editor.setView = function setView (viewtoset) {
    this.setStatusLight("working...");
    // iterate through all of the views, hiding all of them except for viewtoset
    for(var i in this.views) {
        var currentbutton = document.getElementById(this.htmlbuttons[this.views[i]]);
        // don't hide viewtoset
        if(viewtoset == this.views[i]) {
            currentbutton.className = this.classname.fgbutton;
            currentbutton.onclick = null;
        }
        else {
            // hide all of the others
			// @todo: make all views use viewHandler[*]['hide'] instead of
            //   having a big ugly case statement.
            switch (this.views[i]) {
            case 'form':
                this.formdiv.style.display="none";
                break;
            case 'source':
                document.getElementById(this.htmlids.sourcetextarea).style.display="none";
                break;
            case 'schemasource':
                document.getElementById(this.htmlids.schematextarea).style.display="none";
                break;
            case 'schemaform':
                this.schemaedit.formdiv.style.display="none";
                break;
            case 'schemaexample':
                document.getElementById(this.htmlids.schemaexamplearea).style.display="none";
                break;
            default:
                this.viewHandler[this.views[i]]['hide']();
            }
            currentbutton.onclick = this.getSetViewFunction(this.views[i]);
            currentbutton.className = this.classname.bgbutton;
        }
    }

    // if this.currentView is a form view, make sure the JSON is updated to
    // reflect the state of the form.
    switch (this.currentView) {
    case 'form':
        this.updateJSON();
        break;
    case 'schemaform':
        this.schemaedit.updateJSON();
        break;
    }

    var je = this;

    // now show viewtoset
    // @todo: make all views use viewHandler[*]['show'] instead of having
    //   a big ugly case statement.

    switch (viewtoset) {
    case "source":
        setTimeout(function () {
            document.getElementById(je.htmlids.sourcetextarea).style.display="inline";
            je.clearStatusLight();
        },this.getStatusLightDelay(null));
        break;

    case "form":
        setTimeout(function () {je.toggleToFormActual()},this.getStatusLightDelay(this.rootjson));
        break;

    case "schemasource":
        setTimeout(function () {
            je.updateJSON();
            document.getElementById(je.htmlids.schematextarea).style.display="inline";
            je.clearStatusLight();
        },this.schemaedit.getStatusLightDelay(null));
        break;
    case "schemaform":
        setTimeout(function () {je.schemaedit.toggleToFormActual()},this.getStatusLightDelay(this.schemaedit.rootjson));
        break;
    case "schemaexample":
        setTimeout(function () {
            je.updateJSON();
            je.updateSchemaExample();
            document.getElementById(je.htmlids.schemaexamplearea).style.display="inline";
            je.clearStatusLight();
        },this.getStatusLightDelay(null));
        break;
    default:
        setTimeout(function () {
            je.viewHandler[je.views[i]]['show']();
            je.clearStatusLight();
        },this.getStatusLightDelay(null));
    }
    this.currentView = viewtoset;
}

/*
 * Ah, I remember writing Javascript in 2005!  This was some ugly hackary
 * for setting a status indicator, since back in 2005, Javascript (and
 * my computer) were slow enough that it often took a while to build the
 * form.  This function tries to guess how long the minimum time to show
 * the status indicator should be.
 * All of this callback stuff makes this more complicated than it needs
 * to be with modern Javascript implementations, and can probably be
 * ripped out.
 * @param {jsonTreeRef} place to pull historical render time information
 *   from
 * @return {int} timeout value
 */
jsonwidget.editor.getStatusLightDelay = function (jsonref) {
    if(jsonref != null && jsonref != undefined && jsonref.rendertime != undefined) {
        var timeout = jsonref.rendertime / 20;
    }
    else {
        if(document.getElementById(this.htmlids.sourcetextarea).value.length>5000) {
            var timeout = 400;
        }
        else {
            var timeout = 40;
        }
    }
    return timeout;
}

/*
 * Set a status indicator (e.g. "working...")
 * @param {string} statustext - the "working..." text
 */
jsonwidget.editor.setStatusLight = function (statustext) {
    var statustextnode=document.createTextNode(statustext);
    if(this.statusLight == undefined) {
        this.statusLight = document.createElement("div");
        this.statusLight.id = "statusLight";
        this.statusLight.style.position = "fixed";
        this.statusLight.style.top = "0";
        this.statusLight.style.right = "0";
        this.statusLight.style.background = "red";
        this.statusLight.style.color = "white";
        this.statusLight.appendChild(statustextnode);

        this.statusLight = document.body.insertBefore(this.statusLight, document.body.firstChild);
    }
    else {
        this.statusLight.appendChild(statustextnode);
        this.statusLight.style.visibility = "visible";
    }
}
/*
 * Clear the status indicator set by setStatusLight
 */
jsonwidget.editor.clearStatusLight = function () {
    this.statusLight.innerHTML =""
    this.statusLight.style.visibility = "hidden";
}

/*
 * This is toggleToFormActual().  You know that function toggleToForm()?
 * Imposter!  This is the real deal.  Ok, there's not actually a
 * toggleToForm() function, but the purpose of toggleToFormAcutal() is
 * to serve as a callback to call after the delay from
 * getStatusLightDelay().  This function loads the schema and the JSON
 * into a jsonTreeRef (implicitly validating the JSON against the schema)
 * and then calls attachNodeInput to recursively build the form.
 */
jsonwidget.editor.toggleToFormActual = function () {
    var endTime;
    var startTime=new Date().getTime();

    this.debugwindow = document.getElementById(this.htmlids.warningdiv);
    this.warningwindow = document.getElementById(this.htmlids.warningdiv);
    this.clearWarnings();

    // I wrote "need to move this" in 2005.  I don't know why now.
    this.setFormOnSubmit();

    // Get some timing info purely for performance testing.  This is
	// debug cruft that probably isn't that important anymore.
    var startTime=new Date().getTime();
    var endTime;
    var jsonarea = document.getElementById(this.htmlids.sourcetextarea);

    endTime=new Date().getTime();
    this.debugOut(1, '#1a Elapsed time: '+((endTime-startTime)/1000));

    try {
        var schema = this.getSchema();
    }
    catch (error) {
        if(error.name == 'jsonedit_schemaerror') {
            this.warningOut(error.message);
            this.currentView = 'source';
            this.setView('source');
            return;
        }
    }
    this.schemaindex = new jsonwidget.schemaIndex(schema);

    endTime=new Date().getTime();
    this.debugOut(1, '#1b Elapsed time: '+((endTime-startTime)/1000));

    if(!schema) {
        return null;
    }

    var jsontext = jsonarea.value;
    // remove any extra text around the JSON in question.  This was added
    // as a hook for MediaWiki integration, so that the parser tag could
    // be removed.
    jsontext = this.context.removeContextText(jsontext);

    try {
        this.jsondata = JSON.parse(jsontext);
    }
    catch (error) {
        if(/^\s*$/.test(jsontext)) {
            this.jsondata = jsonwidget.getNewValueForType(schema.type);
        }
        else {
            this.warningOut(this.handleParseError(error));
            this.currentView = 'source';
            this.setView('source');
            return;
        }
    }

    var rootschema = this.schemaindex.newRef(schema, null, null);
    // Associate the schema with the JSON via new jsonTreeRef object
    var rootjson = new jsonwidget.jsonTreeRef(this.jsondata, null, null, rootschema);
    this.rootjson = rootjson;
    this.jsonLookupById[rootjson.fullindex]=rootjson;

    // build the form
    this.clearForm();
    rootjson.domparent = document.createElement("form");
    this.showForm(rootjson.domparent);
    this.attachNodeInput(rootjson);
    if(this.showByExampleButton) {
        this.attachByExampleText();
    }

    // more debug cruft
    endTime=new Date().getTime();
    this.debugOut(1, '#2 Elapsed time: '+((endTime-startTime)/1000));

    // attach callbacks for the [show][hide][del] widgets
    this.attachHandlers();
    this.clearStatusLight();
}

/*
 * Fills textarea with JSON serialization of data, wrapping the JSON with
 * context text

 * @param {object} textarea - DOM node to put JSON text into
 * @param {object} data - Data to serialize as JSON and stuff into textarea
 * @param {object} context - The object that can prepend/append context text
 *   around the serialized text.
 */
jsonwidget.editor.updateArea = function (textarea, data, context) {
    var parent = textarea.parentNode;
    var nextsibling = textarea.nextSibling;
    var jsontext;

    parent.removeChild(textarea);
    if(data == null) {
        jsontext = "";
    }
    else {
        jsontext = JSON.stringify(data);
    }

    textarea.value = context.addContextText(jsontext);
    parent.insertBefore(textarea, nextsibling);
}

/*
 * Update the textarea in the form to match the data tree.
 */
jsonwidget.editor.updateJSON = function () {
    var textarea = document.getElementById(this.htmlids.sourcetextarea);
    var data = this.jsondata;
    var context = this.context;
    this.updateArea(textarea, data, context);
}

/*
 * If using the "schema by example" feature, this will update the schema.
 */
jsonwidget.editor.updateSchemaExample = function () {
    var textarea = document.getElementById(this.htmlids.schemaexamplearea);
    var data = jsonwidget.getSchemaArray(je.jsondata);
    var context = this.context;
    this.updateArea(textarea, data, context);
}

/*
 * Generic error handler for jsonwidget.editor object.
 */
jsonwidget.editor.error = function (m) {
    throw {
        name: 'jsonedit_error',
        message: m
    }
}
/*
 * Attempt to gracefully handle a JSON parsing error with a descriptive
 * and specific error message.
 * @param {object} error - error object from JSON parser.
 */
jsonwidget.editor.handleParseError = function (error) {
    var errorstring = '';
    var errorpre = document.createElement("pre");
    if(error.at<40) {
        errorstring = error.text.substr(0,error.at);
        errorpre.appendChild(document.createTextNode(errorstring));
    }
    else {
        errorstring = error.text.substr(error.at-40,40);
        errorpre.appendChild(document.createTextNode(errorstring));
    }

    var highlighted = document.createElement("span");
    highlighted.style.background = "yellow";
    highlighted.appendChild(document.createTextNode(error.text.substr(error.at,1)));
    errorpre.appendChild(highlighted);
    errorpre.appendChild(document.createTextNode(error.text.substr(error.at+1,39)));

    var retval = document.createElement("span");
    var errorstart = "JSON Parse error at char "+error.at+" near the following text:";
    retval.appendChild(document.createTextNode(errorstart));
    retval.appendChild(errorpre);
    try {
        retval.appendChild(document.createTextNode("  Full error: "+error.toSource()));
    }
    catch (error2) {
        // can't get the full error.  oh well
    }
    return retval;
}

/*
 * Output to debug window if the debug message is relevant to the current
 * debug level.
 * @param {int} level
 */

jsonwidget.editor.debugOut = function (level, text) {
    if(level<=this.debuglevel) {
        this.debugwindow.appendChild(document.createTextNode(text));
        this.debugwindow.appendChild(document.createElement("br"));
    }
}
/*
 * Output a warning to the warning window.
 * @param {text|object} Either plain text, or a DOM node.  Plain text gets
 *   escaped on output.  Use a DOM node for formatted text.
 */
jsonwidget.editor.warningOut = function (text) {
    if(text instanceof Node) {
        this.warningwindow.appendChild(text);
    } else {
        this.warningwindow.appendChild(document.createTextNode(text));
    }
    this.warningwindow.appendChild(document.createElement("br"));
}

/*
 * Empty the contents of the warning window.
 */
jsonwidget.editor.clearWarnings = function () {
    this.warningwindow.innerHTML ="";
}

/*
 * Display context help based on an event.
 */
jsonwidget.editor.contextHelp = function(event, jsonnode) {
    if(this.activehelp != null) {
        var parent = this.activehelp.parentElement;
        if(parent!=null) {
            parent.removeChild(this.activehelp);
        }
        if(parent==jsonnode.domparent) {
            this.activehelp = null;
            return true;
        }
    }
    var helpdiv = document.createElement("div");
    var title = document.createElement("div");
    title.appendChild(document.createTextNode(jsonnode.getTitle()));
    title.style.fontWeight = "bold";

    var sourcename = document.createElement("div");
    var sourcetext = document.createTextNode("(JSON index: '" +
                                             jsonnode.schemaref.nodeindex +
                                             "')")
    sourcename.appendChild(sourcetext);
    sourcename.style.fontStyle="italic";
    sourcename.style.fontSize="smaller";

    var description = document.createElement("div");
    description.appendChild(document.createTextNode(jsonnode.schemaref.node.desc));

    helpdiv.appendChild(title);
    helpdiv.appendChild(sourcename);
    helpdiv.appendChild(description);
    helpdiv.className = "je_helpdiv";
    jsonnode.domparent.appendChild(helpdiv);

    helpdiv.style.top = event.pageY-10;
    helpdiv.style.left = event.pageX-10;

    var hideContextHelp = function (event) {
        if((helpdiv.compareDocumentPosition(event.relatedTarget)
            & Node.DOCUMENT_POSITION_CONTAINED_BY) == 0
           && helpdiv.compareDocumentPosition(event.relatedTarget) != 0) {
               jsonnode.domparent.removeChild(helpdiv);
           }
        else {
            event.returnValue=false;
        }
    }

    helpdiv.addEventListener('mouseout',hideContextHelp,false);
    this.activehelp = helpdiv;
}

/*
 * Display a makeshift confirmation dialog when deleting a node from the tree.
 * Called from attachHandlers
 * @param {object} the jsonwidget.editor object.
 */
jsonwidget.editor.confirmDelete = function (jsoneditobj, jsonref, el) {
    var rmyesid='rmyesid.'+jsonref.fullindex;
    var rmnoid='rmnoid.'+jsonref.fullindex;

    el.innerHTML='';
    el.appendChild(document.createElement("br"));
    el.appendChild(document.createTextNode(_("delete")+"? "));
    var yesbutton = document.createElement("span");
    yesbutton.onclick = function (event) {
        // ie workarounds:
        var target = window.event ? window.event.srcElement : event.target;
        if(!event) {event = window.event}

        jsoneditobj.deleteNode(jsonref.fullindex, target);
        if(undefined != event.stopPropagation) {
            event.stopPropagation();
        }
        else {
            event.cancelBubble = true;
        }
    }
    yesbutton.appendChild(document.createTextNode("["+_("yes")+"]"));

    var nobutton = document.createElement("span");
    nobutton.onclick = function (event) {
        // ie workarounds:
        var target = window.event ? window.event.srcElement : event.target;
        var parent = target.parentNode;
        if(!event) {event = window.event}

        parent.innerHTML="";
        parent.appendChild(document.createTextNode("["+_("del")+"]"));
        if(undefined != event.stopPropagation) {
            event.stopPropagation();
        }
        else {
            event.cancelBubble = true;
        }
    }
    nobutton.appendChild(document.createTextNode("["+_("no")+"]"));

    el.appendChild(yesbutton);
    el.appendChild(nobutton);
}
/*
 *  Pull in the schema from the (possibly hidden) textarea and parse it
 *  @retval {object} The parsed schema
 */
jsonwidget.editor.getSchema = function () {
    var schemaarea = document.getElementById(this.htmlids.schematextarea);

    try {
        var retval = JSON.parse(schemaarea.value);
    }
    catch (error) {
        var errorstring = '';
        if(error.text == undefined) {
            throw(error);
        }
        if(error.at<40) {
            errorstring += error.text.substr(0,error.at);
        }
        else {
            errorstring += error.text.substr(error.at-40,40);
        }
        //errorstring += "<span style='background-color: yellow'>";
        errorstring += error.text.substr(error.at,1);
        //errorstring += "</span>";
        errorstring += error.text.substr(error.at+1,39);
        var fullerror = "Schema parse error at char "+error.at+" near "+errorstring+"";
        throw {name : "jsonedit_schemaerror", message : fullerror};
    }
    return retval;
}

/*
 * Update the textarea from the form prior to submitting, so that the latest
 * edits get submitted.
 */
jsonwidget.editor.setFormOnSubmit = function () {
    var je = this;
    var sourcetextform = document.getElementById(this.htmlids.sourcetextform);
    sourcetextform.onsubmit = function () {
        if(je.currentView != 'source') {
            je.updateJSON();
        }
    }
}


