<?php
class JsonUtil {
	/*
	 * Converts the string into something safe for an HTML id.
	 * performs the easiest transformation to safe id, but is lossy
	 */
	public static function stringToId( $var ) {
		if ( is_int( $var ) ) {
			return (string)$var;
		}

		elseif ( is_string( $var ) ) {
			return preg_replace( '/[^a-z0-9\-_:\.]/i', '', $var );
		} else {
			throw new Exception( 'Cannot convert var to id' . print_r( $var, true ) );
		}

	}


	/*
	 * Return the title defined for $schemanode.
	 * TODO: ditch this function; it's kinda useless, and needs to get
	 * folded into JsonTreeRef
	 */
	public static function getTitleFromNode( $schemanode, $nodeindex ) {
		if ( isset( $schemanode['title'] ) ) {
			return $schemanode['title'];
		} else {
			return $nodeindex;
		}

	}

	/*
	 * Given a type (e.g. 'map', 'int', 'str'), return the default/empty
	 * value for that type.
	 */
	public static function getNewValueForType( $thistype ) {
		switch( $thistype ) {
			case 'map':
				$newvalue = array();
				break;
			case 'seq':
				$newvalue = array();
				break;
			case 'number':
				case 'int':
					$newvalue = 0;
					break;
				case 'str':
					$newvalue = "";
					break;
				case 'bool':
					$newvalue = false;
					break;
				default:
					$newvalue = null;
					break;
		}

		return $newvalue;
	}

	/*
	 * Return a JSON-schema type for arbitrary data $foo
	 */
	public static function getType ( $foo ) {
		if ( is_null( $foo ) ) {
			return null;
		}

		switch( gettype( $foo ) ) {
			case "array":
				$retval = "seq";
				foreach ( array_keys( $foo ) as $key ) {
					if ( !is_int( $key ) ) {
						$retval = "map";
					}
				}
				return $retval;
				break;
			case "integer":
			case "double":
				return "number";
				break;
			case "boolean":
				return "bool";
				break;
			case "string":
				return "str";
				break;
			default:
				return null;
				break;
		}

	}

	public static function getSchemaArray( $parent ) {
		$schema = array();
		$schema['type'] = JsonUtil::getType( $parent );
		switch ( $schema['type'] ) {
			case 'map':
				$schema['mapping'] = array();
				foreach ( $parent as $name ) {
					$schema['mapping'][$name] = JsonUtil::getSchemaArray( $parent[$name] );
				}

				break;
			case 'seq':
				$schema['sequence'] = array();
				$schema['sequence'][0] = JsonUtil::getSchemaArray( $parent[0] );
				break;
	}

	return $schema;
	}

}


class TreeRef {
	public $node;
	public $parent;
	public $nodeindex;
	public $nodename;
	public function __construct( $node, $parent, $nodeindex, $nodename ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
	}
}


class JsonTreeRef {
	public function __construct( $node, $parent = null, $nodeindex = null, $nodename = null, $schemaref = null ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
		$this->schemaref = $schemaref;
		$this->fullindex = $this->getFullIndex();
		if ( !is_null( $schemaref ) ) {
			$this->attachSchema();
		}
	}

	public function attachSchema( $schema = null ) {
		if ( !is_null( $schema ) ) {
			$this->schemaindex = new JsonSchemaIndex( $schema );
			$this->nodename = JsonUtil::getTitleFromNode( $schema, "Root node" );
			$this->schemaref = $this->schemaindex->newRef( $schema, null, null, $this->nodename );
		}
		elseif ( !is_null( $this->parent ) ) {
			$this->schemaindex = $this->parent->schemaindex;
		}
		if ( $this->schemaref->node['type'] == 'any' ) {
			if ( $this->getType() == 'map' ) {
				$this->schemaref->node['mapping'] = array( "extension" => array(                "title" => "extension field",                 "type" => "any"            )        );
				$this->schemaref->node['user_key'] = "extension";
			}

			elseif ( $this->getType() == 'seq' ) {
				$this->schemaref->node['sequence'] = array( array( "title" => "extension field",                   "type" => "any" )        );
				$this->schemaref->node['user_key'] = "extension";
			}

		}

	}

	public function getTitle() {
		if ( isset( $this->nodename ) ) {
			return $this->nodename;
		} else {
			return JsonUtil::getTitleFromNode( $this->node, $this->nodeindex );
		}
	}

	public function isUserKey() {
		return $this->userkeyflag;
	}

	public function renamePropname( $newindex ) {
		$oldindex = $this->nodeindex;
		$this->parent->node[$newindex] = $this->node;
		$this->nodeindex = $newindex;
		$this->nodename = $newindex;
		$this->fullindex = $this->getFullIndex();
		unset( $this->parent->node[$oldindex] );
	}

	public function getType() {
		$nodetype = $this->schemaref->node['type'];

		if ( $nodetype == 'any' ) {

			if ( $this->node == null ) {
				return null;
			} else {
				return JsonUtil::getType( $this->node );
			}

		} else {
			return $nodetype;
		}

	}

	public function getFullIndex() {
		if ( is_null( $this->parent ) ) {
			return "json_root";
		} else {
			return $this->parent->getFullIndex() + "." + JsonUtil::stringToId( $this->nodeindex );
		}
	}

	/*
	 *  Get a path to the element in the array.  if $foo['a'][1] would load the
	 *  node, then the return value of this would be array('a',1)
	 */
	public function getDataPath() {
		if ( !is_array( $this->parent ) ) {
			return array();
		} else {
			// TODO: confirm this is doing a proper deep copy
			$retval = $this->parent;
			$retval[] = $this->nodeindex;
			return $retval;
		}
	}

	public function getMappingChildRef( $key ) {
		if ( array_key_exists( 'user_key', $this->schemaref->node ) &&
			!array_key_exists( $key, $this->schemaref->node['mapping'] ) ) {
			$userkeyflag = true;
			$masterkey = $this->schemaref->node['user_key'];
			$schemadata = $this->schemaref->node['mapping'][$masterkey];
		}
		else {
			$userkeyflag = false;
			$schemadata = $this->schemaref->node['mapping'][$key];
		}
		$value = $this->node[$key];
		$nodename = JsonUtil::getTitleFromNode( $schemadata, $key );
		$schemai = $this->schemaindex->newRef( $schemadata, $this->schemaref, $key, $key );
		$jsoni = new JsonTreeRef( $value, $this, $key, $nodename, $schemai );
		return $jsoni;
	}

	public function getSequenceChildRef( $i ) {
		$itemname = JsonUtil::getTitleFromNode( $this->schemaref->node['sequence'][0], 0 );
		$nodename = $itemname . " #" . ( (string)$i + 1 );
		$schemai = $this->schemaindex->newRef( $this->schemaref->node['sequence'][0], $this->schemaref, 0, $i );
		$jsoni = new JsonTreeRef( $this->node[$i], $this, $i, $nodename, $schemai );
		return $jsoni;
	}

	public function validate() {
		$datatype = JsonUtil::getType( $this->node );
		$schematype = $this->getType();
		if ( $datatype != $schematype ) {
			throw new Exception( 'Invalid node: expecting ' . $schematype .
								 ', got ' . $datatype . ' path: ' .
								 print_r( $this->getDataPath(), true ) );
		}
		switch ( $schematype ) {
			case 'map':
				foreach ( $this->node as $key => $value ) {
					$jsoni = $this->getMappingChildRef( $key );
					$jsoni->validate();
				}
				break;
			case 'seq':
				for ( $i = 0; $i < count( $this->node ); $i++ ) {
					$jsoni = $this->getSequenceChildRef( $i );
					$jsoni->validate();
				}
				break;
		}
		return true;
	}
}


//
// schemaIndex object
//
class JsonSchemaIndex {
	public $root;
	public $idtable;
	public function __construct( $schema ) {
		$this->root = $schema;
		$this->idtable = array();

		if ( is_null( $this->root ) ) {
			return null;
		}

		$this->indexSubtree( $this->root );
	}

	public function indexSubtree( $schemanode ) {
		$nodetype = $schemanode['type'];
		switch( $nodetype ) {
			case 'map':
				foreach ( $schemanode['mapping'] as $key => $value ) {
					$this->indexSubtree( $value );
				}

				break;
			case 'seq':
				foreach ( $schemanode['sequence'] as $value ) {
					$this->indexSubtree( $value );
				}

				break;
		}
		if ( isset( $schemanode['id'] ) ) {
			$this->idtable[$schemanode['id']] = $schemanode;
		}
	}

	public function newRef( $node, $parent, $nodeindex, $nodename ) {
		if ( $node['type'] == 'idref' ) {
			$node = $this->idtable[$node['idref']];
		}

		return new TreeRef( $node, $parent, $nodeindex, $nodename );
	}
}