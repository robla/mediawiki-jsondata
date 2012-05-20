<?php
/**
 * Json 
 *
 * @file JsonSchema.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @licence http://jsonwidget.org/LICENSE BSD 3-clause
 */

/*
 * Note, this is a standalone component.  Please don't mix MediaWiki-specific
 * code or library calls into this file.
 */

class JsonSchemaException extends Exception {
	public $subtype;
	// subtypes: "validate-fail", "validate-fail-null"
}

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
			$msg = JsonUtil::uiMessage( 'jsonschema-idconvert', print_r( $var, true ) );
			throw new JsonSchemaException( $msg );
		}

	}

	/*
	 * Given a type (e.g. 'object', 'integer', 'string'), return the default/empty
	 * value for that type.
	 */
	public static function getNewValueForType( $thistype ) {
		switch( $thistype ) {
			case 'object':
				$newvalue = array();
				break;
			case 'array':
				$newvalue = array();
				break;
			case 'number':
				case 'integer':
					$newvalue = 0;
					break;
				case 'string':
					$newvalue = "";
					break;
				case 'boolean':
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
				$retval = "array";
				foreach ( array_keys( $foo ) as $key ) {
					if ( !is_int( $key ) ) {
						$retval = "object";
					}
				}
				return $retval;
				break;
			case "integer":
			case "double":
				return "number";
				break;
			case "boolean":
				return "boolean";
				break;
			case "string":
				return "string";
				break;
			default:
				return null;
				break;
		}

	}

	/*
	 * Generate a schema from a data example ($parent)
	 */
	public static function getSchemaArray( $parent ) {
		$schema = array();
		$schema['type'] = JsonUtil::getType( $parent );
		switch ( $schema['type'] ) {
			case 'object':
				$schema['properties'] = array();
				foreach ( $parent as $name ) {
					$schema['properties'][$name] = JsonUtil::getSchemaArray( $parent[$name] );
				}

				break;
			case 'array':
				$schema['items'] = array();
				$schema['items'][0] = JsonUtil::getSchemaArray( $parent[0] );
				break;
		}

		return $schema;
	}

	/*
	 * User interface messages suitable for translation.
	 * Note: this merely acts as a passthrough to MediaWiki's wfMessage call.
	 */
	public static function uiMessage() {
		if( function_exists( 'wfMessage' ) ) {
			return call_user_func_array( 'wfMessage', $params = func_get_args() );
		}
		else {
			// TODO: replace this with a real solution that works without 
			// MediaWiki
			$params = func_get_args();
			return implode( " ", $params );
		}
	}
}


/*
 * Internal terminology:
 *   Node: "node" in the graph theory sense, but specifically, a node in the
 *    raw PHP data representation of the structure
 *   Ref: a node in the object tree.  Refs contain nodes and metadata about the
 *    nodes, as well as pointers to parent refs
 */

/*
 * Structure for representing a generic tree which each node is aware of its
 * context (can refer to its parent).  Used for schema refs.
 */

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

/*
 * Structure for representing a data tree, where each node (ref) is aware of its
 * context and associated schema.
 */

class JsonTreeRef {
	public function __construct( $node, $parent = null, $nodeindex = null, $nodename = null, $schemaref = null ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
		$this->schemaref = $schemaref;
		$this->fullindex = $this->getFullIndex();
		$this->datapath = array();
		if ( !is_null( $schemaref ) ) {
			$this->attachSchema();
		}
	}

	/*
	 * Associate the relevant node of the JSON schema to this node in the JSON
	 */
	public function attachSchema( $schema = null ) {
		if ( !is_null( $schema ) ) {
			$this->schemaindex = new JsonSchemaIndex( $schema );
			$this->nodename =
				isset( $schema['title'] ) ? $schema['title'] : "Root node";
			$this->schemaref = $this->schemaindex->newRef( $schema, null, null, $this->nodename );
		}
		elseif ( !is_null( $this->parent ) ) {
			$this->schemaindex = $this->parent->schemaindex;
		}
		if ( $this->schemaref->node['type'] == 'any' ) {
			if ( $this->getType() == 'object' ) {
				$this->schemaref->node['additionalProperties'] =
					array( "type" => "any" );
			}

			elseif ( $this->getType() == 'array' ) {
				$this->schemaref->node['items'] =
					array( array( "type" => "any" ) );
				$this->schemaref->node['user_key'] = "extension";
			}

		}

	}

	/*
	 *  Return the title for this ref, typically defined in the schema as the
	 *  user-friendly string for this node.
	 */
	public function getTitle() {
		if ( isset( $this->nodename ) ) {
			return $this->nodename;
		} elseif ( isset( $this->node['title'] ) ) {
			return $this->node['title'];
		} else {
			return $this->nodeindex;
		}
	}

	/*
	 * Rename a user key.  Useful for interactive editing/modification, but not
	 * so helpful for static interpretation.
	 */
	public function renamePropname( $newindex ) {
		$oldindex = $this->nodeindex;
		$this->parent->node[$newindex] = $this->node;
		$this->nodeindex = $newindex;
		$this->nodename = $newindex;
		$this->fullindex = $this->getFullIndex();
		unset( $this->parent->node[$oldindex] );
	}

	/*
	 * Return the type of this node as specified in the schema.  If "any",
	 * infer it from the data.
	 */
	public function getType() {
		$nodetype = $this->schemaref->node['type'];

		if ( $nodetype == 'any' ) {
			if ( $this->node === null ) {
				return null;
			} else {
				return JsonUtil::getType( $this->node );
			}
		} else {
			return $nodetype;
		}

	}

	/*
	 * Return a unique identifier that may be used to find a node.  This
	 * is only as robust as stringToId is (i.e. not that robust), but is
	 * good enough for many cases.
	 */
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
		if ( !is_object( $this->parent ) ) {
			return array();
		} else {
			$retval = $this->parent->getDataPath();
			$retval[] = $this->nodeindex;
			return $retval;
		}
	}

	/*
	 *  Return path in something that looks like an array path.  For example,
	 *  for this data: [{'0a':1,'0b':{'0ba':2,'0bb':3}},{'1a':4}]
	 *  the leaf node with a value of 4 would have a data path of '[1]["1a"]',
	 *  while the leaf node with a value of 2 would have a data path of
	 *  '[0]["0b"]["oba"]'
	 */
	public function getDataPathAsString() {
		$retval = "";
		foreach( $this->getDataPath() as $item ) {
			$retval .= '[' . json_encode( $item ) . ']';
		}
		return $retval;
	}

	/*
	 *  Return data path in user-friendly terms.  This will use the same
	 *  terminology as used in the user interface (1-indexed arrays)
	 */
	public function getDataPathTitles() {
		if ( !is_object( $this->parent ) ) {
			return $this->getTitle();
		} else {
			return $this->parent->getDataPathTitles() . ' -> ' 
				. $this->getTitle();
		}
	}

	/*
	 * Return the child ref for $this ref associated with a given $key
	 */
	public function getMappingChildRef( $key ) {
		$snode = $this->schemaref->node;
		if( array_key_exists( 'properties', $snode ) &&
			array_key_exists( $key, $snode['properties'] ) ) {
			$schemadata = $snode['properties'][$key];
		}
		elseif ( array_key_exists( 'additionalProperties', $snode ) &&
				 $snode['additionalProperties'] !== false ) {
			$schemadata = $snode['additionalProperties'];
		}
		else {
			$msg = JsonUtil::uiMessage( 'jsonschema-invalidkey',
										$key, $this->getDataPathTitles() );
			throw new JsonSchemaException( $msg );
		}
		$value = $this->node[$key];
		$nodename = isset( $schemadata['title'] ) ? $schemadata['title'] : $key;
		$schemai = $this->schemaindex->newRef( $schemadata, $this->schemaref, $key, $key );
		$jsoni = new JsonTreeRef( $value, $this, $key, $nodename, $schemai );
		return $jsoni;
	}

	/*
	 * Return the child ref for $this ref associated with a given index $i
	 */
	public function getSequenceChildRef( $i ) {
		$schemanode = $this->schemaref->node['items'][0];
		$itemname = isset( $schemanode['title'] ) ? $schemanode['title'] : "Item";
		$nodename = $itemname . " #" . ( (string)$i + 1 );
		$schemai = $this->schemaindex->newRef( $this->schemaref->node['items'][0], $this->schemaref, 0, $i );
		$jsoni = new JsonTreeRef( $this->node[$i], $this, $i, $nodename, $schemai );
		return $jsoni;
	}

	/*
	 * Validate the JSON node in this ref against the attached schema ref.
	 * Return true on success, and throw a JsonSchemaException on failure.
	 */
	public function validate() {
		$datatype = JsonUtil::getType( $this->node );
		$schematype = $this->getType();
		if ( $datatype == 'array' && $schematype == 'object' ) {
			// PHP datatypes are kinda loose, so we'll fudge
			$datatype = 'object';
		}
		if ( $datatype == 'number' && $schematype == 'integer' &&
			 $this->node == (int)$this->node) {
			// Alright, it'll work as an int
			$datatype = 'integer';
		}
		if ( $datatype != $schematype ) {
			if ( is_null( $datatype ) && !is_object( $this->parent )) {
				$msg = JsonUtil::uiMessage( 'jsonschema-invalidempty' );
				$e = new JsonSchemaException( $msg );
				$e->subtype = "validate-fail-null";
				throw( $e );
			} else {
				$datatype = is_null( $datatype ) ? "null" : $datatype;
				$msg = JsonUtil::uiMessage( 'jsonschema-invalidnode', $schematype, $datatype, $this->getDataPathTitles() );
				$e = new JsonSchemaException( $msg );
				$e->subtype = "validate-fail";
				throw( $e );
			}
		}
		switch ( $schematype ) {
			case 'object':
				$this->validateObjectChildren();
				break;
			case 'array':
				$this->validateArrayChildren();
				break;
		}
		return true;
	}

	/*
	 */
	private function validateObjectChildren() {
		foreach ( $this->schemaref->node['properties'] as $skey => $svalue ) {
			$keyOptional = array_key_exists( 'optional', $svalue ) ? $svalue['optional'] : false;
			if( !$keyOptional && !array_key_exists( $skey, $this->node ) ) {
				$msg = JsonUtil::uiMessage( 'jsonschema-invalid-missingfield' );
				$e = new JsonSchemaException( $msg );
				$e->subtype = "validate-fail-missingfield";
				throw( $e );
			}
		}
		foreach ( $this->node as $key => $value ) {
			$jsoni = $this->getMappingChildRef( $key );
			$jsoni->validate();
		}
		return true;
	}

	/*
	 */
	private function validateArrayChildren() {
		for ( $i = 0; $i < count( $this->node ); $i++ ) {
			$jsoni = $this->getSequenceChildRef( $i );
			$jsoni->validate();
		}
	}
}


/*
 * The JsonSchemaIndex object holds all schema refs with an "id", and is used
 * to resolve an idref to a schema ref.  This also holds the root of the schema
 * tree.  This also serves as sort of a class factory for schema refs.
 */
class JsonSchemaIndex {
	public $root;
	public $idtable;
	/*
	 * The whole tree is indexed on instantiation of this class.
	 */
	public function __construct( $schema ) {
		$this->root = $schema;
		$this->idtable = array();

		if ( is_null( $this->root ) ) {
			return null;
		}

		$this->indexSubtree( $this->root );
	}

	/*
	 * Recursively find all of the ids in this schema, and store them in the
	 * index.
	 */
	public function indexSubtree( $schemanode ) {
		$nodetype = $schemanode['type'];
		switch( $nodetype ) {
			case 'object':
				foreach ( $schemanode['properties'] as $key => $value ) {
					$this->indexSubtree( $value );
				}

				break;
			case 'array':
				foreach ( $schemanode['items'] as $value ) {
					$this->indexSubtree( $value );
				}

				break;
		}
		if ( isset( $schemanode['id'] ) ) {
			$this->idtable[$schemanode['id']] = $schemanode;
		}
	}

	/*
	 *  Generate a new schema ref, or return an existing one from the index if
	 *  the node is an idref.
	 */
	public function newRef( $node, $parent, $nodeindex, $nodename ) {
		if ( array_key_exists( '$ref', $node ) ) {
			if ( strspn( $node['$ref'], '#' ) != 1 ) {
				$error = JsonUtil::uiMessage( 'jsonschema-badidref', $node['$ref'] );
				throw new JsonSchemaException( $error );
			}
			$idref = substr( $node['$ref'], 1 );
			try {
				$node = $this->idtable[$idref];
			}
			catch ( Exception $e ) {
				$error = JsonUtil::uiMessage( 'jsonschema-badidref', $node['$ref'] );
				throw new JsonSchemaException( $error );
			}
		}

		return new TreeRef( $node, $parent, $nodeindex, $nodename );
	}
}
