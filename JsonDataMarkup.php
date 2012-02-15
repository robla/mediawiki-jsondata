<?php
/*
 * Class to generate wiki markup from a JSON file and it's corresponding
 * schema
 */
class JsonDataMarkup {
	public static function testMarkup() {
		$testdata = array();
		$json = file_get_contents( 'example/addressexample.json' );
		$schematext = file_get_contents( 'schemas/addressbookschema.json' );
		$data = json_decode( $json, true );
		$schema = json_decode( $schematext, true );
		$rootjson = new JsonTreeRef( $data );
		$rootjson->attachSchema( $schema );
		return JsonDataMarkup::getMarkup( $rootjson, 0 );
	}

	public static function getMarkup( $jsonref, $depth ) {
		switch ( $jsonref->getType() ) {
			case 'map':
				return JsonDataMarkup::getMappingMarkup( $jsonref, $depth );
			case 'seq':
				return JsonDataMarkup::getSequenceMarkup( $jsonref, $depth );
			default:
				return JsonDataMarkup::getSimpleMarkup( $jsonref, $depth );
		}
	}

	public static function getMappingMarkup( $jsonref, $depth ) {
		$markup = JsonDataMarkup::getTitleMarkup( $jsonref, $depth );
		$markup .= "\n";
		foreach ( $jsonref->node as $key => $value ) {
			$jsoni = $jsonref->getMappingChildRef( $key );
			$markup .= JsonDataMarkup::getMarkup( $jsoni, $depth + 1 );
		}
		return $markup;
	}

	public static function getSequenceMarkup( $jsonref, $depth ) {
		$markup = JsonDataMarkup::getTitleMarkup( $jsonref, $depth );
		$markup .= "\n";
		for ( $i = 0; $i < count( $jsonref->node ); $i++ ) {
			$jsoni = $jsonref->getSequenceChildRef( $i );
			$markup .= JsonDataMarkup::getMarkup( $jsoni, $depth + 1 );
		}
		return $markup;
	}

	public static function getSimpleMarkup( $jsonref, $depth ) {
		$markup = JsonDataMarkup::getTitleMarkup( $jsonref, $depth );
		$markup .= ": ";
		$markup .= $jsonref->node;
		$markup .= "\n";
		return $markup;
	}

	public static function getTitleMarkup( $jsonref, $depth, $emphasis = "'''" ) {
		$markup = '';
		$markup .= str_repeat( "*", $depth + 1 );
		$markup .= " ";
		$markup .= $emphasis;
		$markup .= $jsonref->getTitle();
		$markup .= $emphasis;
		return $markup;
	}
}


