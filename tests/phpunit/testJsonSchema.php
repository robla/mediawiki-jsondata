<?php
require_once('JsonSchema.php');

class JsonSchemaTestFuncs {
	public static function loadJsonRef($jsonfile, $schemafile) {
		$json = file_get_contents($jsonfile);
		$schematext = file_get_contents($schemafile);
		$data = json_decode($json, true);
		$schema = json_decode($schematext, true);
		$jsonref = new JsonTreeRef( $data );
		$jsonref->attachSchema( $schema );
		return $jsonref;
	}
}

class JsonTreeRefTest extends PHPUnit_Framework_TestCase
{
	public function getSimpleTestData() {
		$testdata = array();
		$json = '{"a":1,"b":2,"c":3,"d":4,"e":5}';
		$schematext = '{"title": "Unrestricted JSON", "type": "any", "optional": true}';
		$testdata['data'] = json_decode($json, true);
		$testdata['schema'] = json_decode($schematext, true);
		return array( $testdata );
	}

    /**
     * @dataProvider getSimpleTestData
     */
    public function testJsonSimpleTestValidate($data, $schema) {
		$schemaIndex = new JsonSchemaIndex( $schema );
        $this->assertEquals($schemaIndex->root['type'], 'any');
		$nodename = isset( $schema['title'] ) ? $schema['title'] : "Root node";
    	$rootschema = $schemaIndex->newRef($schema, null, null, $nodename);
    	$rootjson = new JsonTreeRef($data, null, null, $nodename, $rootschema);
        $this->assertEquals($rootjson->getTitle(), 'Unrestricted JSON');
		return $schemaIndex;
    }

    /**
     * @dataProvider getSimpleTestData
     */
    public function testJsonUtilGetTitleFromNode($data, $schema) {
		$nodename = isset( $schema['title'] ) ? $schema['title'] : "Root node";
        $this->assertEquals($nodename, "Unrestricted JSON");
		return $nodename;
    }

    /**
     * @dataProvider getSimpleTestData
     */
    public function testJsonSimpleTestSchemaValidate($data, $schema) {
		$schemaschematext = file_get_contents('schemas/schemaschema.json');
		$schemaschema = json_decode($schemaschematext, true);
		$jsonref = new JsonTreeRef( $schema );
		$jsonref->attachSchema( $schemaschema );
		$jsonref->validate();
		return $jsonref;
    }

	public function getAddressTestData() {
		$testdata = array();
		$json = file_get_contents('example/addressexample.json');
		$schematext = file_get_contents('schemas/addressbookschema.json');
		$testdata['data'] = json_decode($json, true);
		$testdata['schema'] = json_decode($schematext, true);
		return array( $testdata );
	}

    /**
     * @dataProvider getAddressTestData
     */
    public function testJsonAddressTestValidate($data, $schema) {
		$schemaIndex = new JsonSchemaIndex( $schema );
        $this->assertEquals($schemaIndex->root['type'], 'array');
		$nodename = isset( $schema['title'] ) ? $schema['title'] : "Root node";
    	$rootschema = $schemaIndex->newRef($schema, null, null, $nodename);
    	$rootjson = new JsonTreeRef($data, null, null, $nodename, $rootschema);
        $this->assertEquals($rootjson->getTitle(), 'Address Book');
		return $schemaIndex;
    }

	public function testJsonSchemaValidateAddressExample() {
		$jsonfile = 'example/addressexample.json';
		$schemafile = 'schemas/addressbookschema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testJsonSchemaValidateSuccessfulExample() {
		$jsonfile = 'tests/phpunit/data/2/test5.json';
		$schemafile = 'tests/phpunit/data/2/schematest5.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

    /**
     * @expectedException JsonSchemaException
     */
	public function testJsonSchemaValidateBadIdref() {
		$jsonfile = 'tests/phpunit/data/1/test5.json';
		$schemafile = 'tests/phpunit/data/1/schematest5.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

    /**
     * @expectedException JsonSchemaException
     */
	public function testJsonSchemaValidateBadData() {
		$jsonfile = 'tests/phpunit/data/1/test5.json';
		$schemafile = 'tests/phpunit/data/2/schematest5.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testJsonSchemaValidateTestSchema2() {
		$jsonfile = 'tests/phpunit/data/2/schematest5.json';
		$schemafile = 'schemas/schemaschema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testJsonSchemaValidateGoodLocation() {
		$jsonfile = 'tests/phpunit/data/validlocation.json';
		$schemafile = 'tests/phpunit/data/schemalocation.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

    /**
     * @expectedException JsonSchemaException
     */
	public function testJsonSchemaValidateMissingFieldLocation() {
		$jsonfile = 'tests/phpunit/data/missingfieldlocation.json';
		$schemafile = 'tests/phpunit/data/schemalocation.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

    /**
     * @expectedException JsonSchemaException
     */
	public function testJsonSchemaValidateBadDataLocation() {
		$jsonfile = 'tests/phpunit/data/invalidlocation.json';
		$schemafile = 'tests/phpunit/data/schemalocation.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testJsonSchemaValidateTestLocationSchema() {
		$jsonfile = 'tests/phpunit/data/schemalocation.json';
		$schemafile = 'schemas/schemaschema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testExtensionFieldGetTitle () {
		$jsonfile = 'tests/phpunit/data/ab.json';
		$schemafile = 'schemas/openschema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		// if the titles for two different extension fields is the same, that
		// means the titles are probably pretty useless.
		$atitle = $jsonref->getMappingChildRef( 'a' )->getTitle();
		$btitle = $jsonref->getMappingChildRef( 'b' )->getTitle();
		$this->assertNotEquals( $atitle, $btitle );
	}

	public function testJsonSchemaValidateEmptyMap() {
		$jsonfile = 'tests/phpunit/data/emptymap.json';
		$schemafile = 'schemas/datatype-example-schema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testJsonSchemaValidateInteger() {
		$jsonfile = 'tests/phpunit/data/inttest.json';
		$schemafile = 'schemas/datatype-example-schema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testTypeBooleanFreeformField () {
		$jsonfile = 'tests/phpunit/data/boolean.json';
		$schemafile = 'schemas/openschema.json';
		$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemafile);
		$jsonref->validate();
	}

	public function testJsonSchemaValidateStandardSchemas() {
		$schemafiles = array( 'schemas/addressbookschema.json',
							  'schemas/datatype-example-schema.json',
							  'schemas/jsondata-config-schema.json',
							  'schemas/openschema.json',
							  'schemas/schemaschema.json',
							  'schemas/simpleaddr-schema.json' );
		$schemaschema = 'schemas/schemaschema.json';
		foreach( $schemafiles as $jsonfile ) {
			$jsonfile = 'schemas/datatype-example-schema.json';
			$jsonref = JsonSchemaTestFuncs::loadJsonRef($jsonfile, $schemaschema);
			$jsonref->validate();
		}
	}
}

