<?php
require_once __DIR__ . '/CTIMessage.php';

/**
 * CTI event, based on CTI message
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	CTI
 */
class CTIEvent extends CTIMessage
{
	/**
	 * Event types
	 */
	/**
	 * All event types, used only in CTI requests.
	 * Arithmetic sum of all event types
	 * @var	integer
	 */
	const TYPE_ALL = 7;
	
	/**
	 * Transfer request event
	 * @var integer
	 */
	const TYPE_TRANSFER_REQUEST = 1;
	
	/**
	 * Call start event
	 * @var integer
	 */
	const TYPE_CALL_START = 2;
	
	/**
	 * Call end event
	 * @var integer
	 */
	const TYPE_CALL_END = 4;
	
	/**
	 * Call direction indicators
	 */
	/**
	 * Incoming call indicator
	 * @var	integer
	 */
	const DIRECTION_INCOMING = 0;
	
	/**
	 * Outgoing call indicator
	 * @var	integer
	 */
	const DIRECTION_OUTCOMING = 1;
	
	/**
	 * (non-PHPdoc)
	 * @see CTIMessage::getXMLRootElementName()
	 */
	protected function getXMLRootElementName()
	{
		return 'Event';
	}
	
	/**
	 * Returns atrributes of event, based on xml tag attributes accessor
	 * @param	string	$attr, event attribute field
	 * @return	mixed, returns NULL in case attribute not defined
	 */
	public function __get($attr)
	{
		return ($this->_xml->documentElement->hasAttribute($attr)
				? $this->_xml->documentElement->getAttribute($attr)
				: null);
	}
	
	/**
	 * Set specified attribute of event, based on xml tag attributes accessor
	 * @param	string	$attr
	 * @param	mixed	$value, any scalar type, will converted to string
	 * @return	void
	 */
	public function __set($attr, $value)
	{
		$this->_xml->documentElement->setAttribute($attr, $value);
	}
}