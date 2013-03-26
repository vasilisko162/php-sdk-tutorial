<?php
require_once __DIR__ . '/CTIMessage.php';
require_once __DIR__ . '/CTIEvent.php';

/**
 * CTI requests
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	CTI
 */
class CTIRequest extends CTIMessage
{
	/**
	 * Used at request ID generator
	 * @var	integer
	 */
	protected static $_id_start;
	
	/**
	 * Used at request ID generator
	 * @var	integer
	 */
	protected static $_id_offset;
	
	/**
	 * Request identifier
	 * @var	integer
	 */
	protected $_id;
	
	/**
	 * Create request of CTI method GetEvents
	 * @param	CTIClient	$client
	 * @param	integer	$event_mask
	 * @return	CTIRequest
	 */
	public static function createGetEvents(CTIClient $client, $event_mask = CTIEvent::TYPE_ALL)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		$request->_xml->getElementsByTagName('Data')->item(0)->appendChild($request->_xml->createElement('EventMask', intval($event_mask)));
		return $request;
	}
	
	/**
	 * Create request of CTI method Call
	 * @param	CTIClient	$client
	 * @param	string	$from
	 * @param	string	$to
	 * @return	CTIRequest
	 */
	public static function createCall(CTIClient $client, $from, $to)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		$data = $request->_xml->getElementsByTagName('Data')->item(0);
		$data->appendChild($request->_xml->createElement('From', $from));
		$data->appendChild($request->_xml->createElement('To', $to));
		return $request;
	}
	
	/**
	 * Create request of CTI method Transfer
	 * @param	CTIClient	$client
	 * @param	string	$call_id
	 * @param	string	$to
	 * @return	CTIRequest
	 */
	public static function createTransfer(CTIClient $client, $call_id, $to)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		$data = $request->_xml->getElementsByTagName('Data')->item(0);
		$data->appendChild($request->_xml->createElement('CallID', $call_id));
		$data->appendChild($request->_xml->createElement('To', $to));
		return $request;
	}
	
	/**
	 * Create request of CTI method GetVersion
	 * @param	CTIClient	$client
	 * @return	CTIRequest
	 */
	public static function createGetVersion(CTIClient $client)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		return $request;
	}
	
	/**
	 * Create request of CTI method GetVersion
	 * @param	CTIClient	$client
	 * @param	boolean	$mode
	 * @return	CTIRequest
	 */
	public static function createSimulation(CTIClient $client, $mode)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		$request->_xml->getElementsByTagName('Data')->item(0)->appendChild($request->_xml->createElement('Mode', empty($mode) ? 'off' : 'on'));
		return $request;
	}
	
	/**
	 * Create request of CTI method GetClients
	 * @param	CTIClient	$client
	 * @return	CTIRequest
	 */
	public static function createGetClients(CTIClient $client)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		return $request;
	}
	
	/**
	 * Create request of CTI method Generate
	 * @param	CTIClient	$client
	 * @param	integer	$event, @see CTIEvent::TYPE_* constants
	 * @param	string	$from, source phone number 
	 * @param	string	$to, destination phone number
	 * @param	integer	$start, unixtimestamp of call start
	 * @param	integer	$end, unixtimestamp of call start
	 * @param	integer	$duration, call duration in seconds
	 * @param	string	$record, full file name of call audio record, (it can be ftp*, http* or local file system)
	 * @param	integer	$direction, call direction, @see CTIEvent::DIRECTION_* constants
	 * @param	string	$client_guid, client guid of event recipient, to send event to all of clients, specify '*' symbol
	 * @return	CTIRequest
	 */
	public static function createGenerate(CTIClient $client, $event, $from, $to = null, $start = null, $end = null, $duration = null, $record = null, $direction = null, $client_guid = null)
	{
		$request = new self($client);
		$request->_xml->getElementsByTagName('Method')->item(0)->nodeValue = substr(__FUNCTION__, 6);
		$data = $request->_xml->getElementsByTagName('Data')->item(0);
		$data->appendChild($request->_xml->createElement('Event', $event));
		switch($event)
		{
			case CTIEvent::TYPE_TRANSFER_REQUEST:
				$data->appendChild($request->_xml->createElement('From', $from));
				break;
			case CTIEvent::TYPE_CALL_START:
				$data->appendChild($request->_xml->createElement('From', $to));
				$data->appendChild($request->_xml->createElement('To', $to));
				break;
			case CTIEvent::TYPE_CALL_END:
				$data->appendChild($request->_xml->createElement('From', $to));
				$data->appendChild($request->_xml->createElement('To', $to));
				$data->appendChild($request->_xml->createElement('Start', $start));
				$data->appendChild($request->_xml->createElement('End', $end));
				$data->appendChild($request->_xml->createElement('Duration', $duration));
				$data->appendChild($request->_xml->createElement('Record', $record));
				$data->appendChild($request->_xml->createElement('Direction', $direction));
				$data->appendChild($request->_xml->createElement('ClientGUID', $client_guid));
				break;;
		}
		return $request;
	}
	
	/**
	 * Constructor
	 * @param	CTIClient	$client
	 * @param	mixed	$xml, DOMDOcument instance or string or NULL
	 * @return	void
	 */
	public function __construct(CTIClient $client, $xml = null)
	{
		if (!($xml instanceof DOMDocument) && empty($xml))
			$this->_id = self::generateID();
		
		parent::__construct($client, $xml);
		
		if (empty($this->_id))
			$this->_id = $this->_xml->getElementsByTagName('RequestID')->item(0)->nodeValue;
	}
	
	/**
	 * Generate unique ID of operation
	 * @return	integer
	 */
	protected static function generateID()
	{
		if (!isset(self::$_id_start))
			self::$_id_start = time();
		if (!isset(self::$_id_offset))
			self::$_id_offset = mt_rand(-86400, 86400);
		return self::$_id_start + ++self::$_id_offset;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CTIMessage::initXMLDefault()
	 */
	protected function initXMLDefault()
	{
		parent::initXMLDefault();
		$this->ProtocolVersion = $this->_client->getProtocolVersion();
		$this->RequestID = $this->_id;
		$this->ClientID = $this->_client->getClientID();
		$this->ClientType = $this->_client->getClientType();
		$this->ClientGUID = $this->_client->getClientGUID();
		$this->Method = null;
		$this->Data = null;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CTIMessage::getXMLRootElementName()
	 */
	protected function getXMLRootElementName()
	{
		return 'Request';
	}
}