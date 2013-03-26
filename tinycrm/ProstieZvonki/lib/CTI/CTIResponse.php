<?php
require_once __DIR__ . '/CTIMessage.php';
require_once __DIR__ . '/CTIClient.php';
require_once __DIR__ . '/CTIRequest.php';
require_once __DIR__ . '/CTIException.php';

/**
 * CTI responses
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	CTI
 */
class CTIResponse extends CTIMessage
{
	/**
	 * Request processed with succeed
	 * @var	integer
	 */
	const ERROR_NONE = 0;
	
	/**
	 * Request not processed because of authentication error
	 * @var	integer
	 */
	const ERROR_AUTHENTICATION = 1;
	
	/**
	 * Request not processed because of any other error
	 * @var	integer
	 */
	const ERROR_OTHER = 2;
	
	/**
	 * Process response on request of any CTI method
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function process(CTIResponse $response, CTIRequest $request)
	{
		switch(intval($response->Code))
		{
			case self::ERROR_NONE:
				// all right
				break;
			case self::ERROR_AUTHENTICATION:
				throw new CTIException(CTIException::requestAuthenticationError($response->Details));
				break;
			case self::ERROR_OTHER:
				throw new CTIException(CTIException::requestOtherError($response->Details));
				break;
		}
	}
	
	/**
	 * Process response on request of CTI method GetEvents
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processGetEvents(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
		// TODO: process events (extract from Data attribute of Response)
	}
	
	/**
	 * Process response on request of CTI method Call
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processCall(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
	}
	
	/**
	 * Process response on request of CTI method Transfer
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processTransfer(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
	}
	
	/**
	 * Process response on request of CTI method GetVersion
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processGetVersion(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
		// TODO: process version (extract from Data attribute of Response)
	}
	
	/**
	 * Process response on request of CTI method GetVersion
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processSimulation(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
	}
	
	/**
	 * Process response on request of CTI method GetClients
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processGetClients(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
		// TODO: process clients (extract from Data attribute of Response)
	}
	
	/**
	 * Process response on request of CTI method Generate
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @param	CTIRequest	$request
	 * @return	void
	 */
	public static function processGenerate(CTIResponse $response, CTIRequest $request)
	{
		self::process($response, $request);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CTIMessage::initXMLDefault()
	 */
	protected function initXMLDefault()
	{
		parent::initXMLDefault();
		$response->appendChild($this->_xml->createElement('RequestID'));
		$response->appendChild($this->_xml->createElement('Code'));
		$response->appendChild($this->_xml->createElement('Details'));
		$response->appendChild($this->_xml->createElement('Data'));
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CTIMessage::getXMLRootElementName()
	 */
	protected function getXMLRootElementName()
	{
		return 'Response';
	}
}