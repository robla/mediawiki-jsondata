This is an extension which allows for form-based editing of arbitrary JSON data on-wiki.
To see this in action, visit http://jsonwidget.org/wiki/JsonData

Installation instructions for JsonData
======================================
1.  Copy the extension as "JsonData" into the extensions directory
2.  Configure the extension in LocalSettings.php.  An example configuration
    can be found in example/LocalSettings_example.php

This should be enough to get up and running.  

Testing
=======
A few tests to try with the example configuration:

1.  Create a page "Address:Test".  This is an example of a basic address book.
2.  Create a page "Data:Test".  This is an example of free-form JSON editing sans schema.
3.  Copy an example schema to the wiki.  Create "Schema:SimpleAddr", and copy
    in the contents of simpleaddr-schema.json.  Surround the contents with
    a <jsonschema> tag.
4.  Move your config on-wiki and make a modification
    a.  Copy the contents of example/configexample.json to a new article called
    "JsonConfig:Test".  Be sure to leave either <json> or <jsonconfig> tags
    around the JSON data.
    b.  Set $wgJsonDataConfigArticle = "JsonConfig:Test" in LocalSettings.php
    c.  Edit JsonConfig:Test, changing the following values:
        ['tags']['address']['schema']['srctype'] from "predefined" to "article"
        ['tags']['address']['schema']['src'] to "Schema:SimpleAddr"
5.  Edit "Schema:SimpleAddr", adding or removing a field
6.  Create a page "Address:Test2", and note your change to the schema.


