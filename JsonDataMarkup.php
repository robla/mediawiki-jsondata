<?php
/*
 * Class to generate wiki markup from a JSON file and it's corresponding
 * schema
 */
class JsonDataMarkup {
	public static function getMarkup( $jsonref, $depth ) {
		switch ( $jsonref->getType() ) {
			case 'object':
				return JsonDataMarkup::getMappingMarkup( $jsonref, $depth );
			case 'array':
				return JsonDataMarkup::getSequenceMarkup( $jsonref, $depth );
			default:
				return JsonDataMarkup::getSimpleMarkup( $jsonref, $depth );
		}
	}

	public static function getMappingMarkup( $jsonref, $depth ) {
		$markup = JsonDataMarkup::getTitleMarkup( $jsonref, $depth );
		$markup .= "\n";
		foreach ( $jsonref->node as $key => $value ) {
			try {
				$jsoni = $jsonref->getMappingChildRef( $key );
			}
			catch ( JsonSchemaException $e ) {
				// swallow this key and move on
				wfDebug( __METHOD__ . ": " . htmlspecialchars( $e->getMessage() ) . "\n" );
				continue;
			}
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
		if ( is_bool( $jsonref->node ) ) {
			$true = wfMessage( 'jsondata-true' );
			$false = wfMessage( 'jsondata-false' );
			$markup .= $jsonref->node ? $true : $false;
		}
		else {
			$markup .= $jsonref->node;
		}
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


