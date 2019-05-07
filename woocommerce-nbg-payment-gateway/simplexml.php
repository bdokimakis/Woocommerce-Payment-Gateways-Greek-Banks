<?php
class nbg_SimpleXMLExtended extends SimpleXMLElement {
	public function addCData( $cdata_text ) {
		$node = dom_import_simplexml( $this );
		$node->appendChild( $node->ownerDocument->createCDATASection( $cdata_text ) );
	}
}
?>