<?php
/**
 * Basic class for CTI xml messages
 * 
 * @author	Vedisoft.Semenov Pasha
 * @package	CTI
 */
abstract class CTIMessage
{
	/**
	 * Link to CTIClient, which has recieves this event
	 * @var CTIClinet
	 */
	protected $_client;
	
	/**
	 * DOMDocument instance
	 * @var DOMDocument
	 */
	protected $_xml;
	
	private $_xml_root;

	/**
	 * Constructor
	 * @param	CTIClient	$client
	 * @param	mixed	$xml, DOMDOcument instance or string or NULL
	 * @return	void
	 */
	public function __construct(CTIClient $client, $xml = '')
	{
		$this->_client = $client;
		if ($xml instanceof DOMDocument)
		{
			$this->_xml = $xml;
		}
		elseif (!empty($xml))
		{
			$this->_xml = new DOMDocument('1.0', 'UTF-8');
			$this->_xml->loadXML($xml);
		}
		else
		{
			$this->initXMLDefault();
		}
	}
	
	/**
	 * Initialize default xml structure, uses in case empty $xml param in constructir
	 * @return	void
	 */
	protected function initXMLDefault()
	{
		$this->_xml = new DOMDocument('1.0', 'UTF-8');
		$this->_xml->appendChild($this->_xml->createElement($this->getXMLRootElementName()));
	}
	
	/**
	 * Returns name of xml root element
	 * @return	string
	 */
	abstract protected function getXMLRootElementName();
	
	/**
	 * Returns atrributes of event, based on xml tag attributes accessor
	 * @param	string	$attr, event attribute field
	 * @return	mixed, returns NULL in case attribute not defined
	 */
	public function __get($attr)
	{
		$nodes = $this->_xml->getElementsByTagName($attr);
		switch($nodes->length)
		{
			case 0:
				$return = null;
				break;
			case 1:
				$return = $nodes->item(0)->nodeValue;
				break;
			default:
				$return = array();
				for ($i = 0; $i < $nodes->length; $i++)
					$return[] = $nodes->item($i)->nodeValue;
				break;
		}
		return $return;
	}
	
	/**
	 * Set specified attribute of event, based on xml tag attributes accessor
	 * @param	string	$attr
	 * @param	mixed	$value, any scalar type, will converted to string
	 * @return	void
	 */
	public function __set($attr, $value)
	{
		$nodes = $this->_xml->getElementsByTagName($attr);
		switch($nodes->length)
		{
			case 0:
				$this->_xml->documentElement->appendChild($this->_xml->createElement($attr, $value));
				break;
			case 1:
				$nodes->item(0)->nodeValue = $value;
				break;
			default:
				for ($i = 0; $i < $nodes->length; $i++)
					$nodes->item($i)->nodeValue = $value;
				break;
		}
	}
	
	/**
	 * Returns message xml as string, without <?xml?> tag
	 * @return	string
	 */
	public function getXML()
	{
		return $this->_xml->saveXML($this->_xml->documentElement);
	}
}