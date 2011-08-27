<?php

class JsonDataHooks {
	/**
	 * Muck with the editor interface
	 * @param EditPage $editPage
	 */
	public static function onEditPageShowEditFormInitial( &$editPage ) {
		return true;
	}
}
