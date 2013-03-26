<?php
use WebSocket\Client;
use WebSocket\ClientException;
use WebSocket\Connection;
use WebSocket\Exception as WebSocketException;
use WebSocket\MessageIncoming;
use WebSocket\MessageOutcoming;

require_once __DIR__ . '/../ListenerProcess/ListenerProcess.php';
require_once __DIR__ . '/../WebSocket/Client.php';
require_once __DIR__ . '/CTIEvent.php';
require_once __DIR__ . '/CTIRequest.php';
require_once __DIR__ . '/CTIResponse.php';
require_once __DIR__ . '/CTIException.php';

/**
 * CTI client
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	CTI
 */
class CTIClient extends Client
{
	/**
	 * Minimum interval between reconnection attempts
	 * @var	integer
	 */
	const RECONNECTION_INTERVAL_MIN = 1000;
	
	/**
	 * Maximum interval between reconnection attempts
	 * @var	integer
	 */
	const RECONNECTION_INTERVAL_MAX = 60000000;
	
	/**
	 * Step of increasing of interval between reconnection attempts
	 * @var unknown_type
	 */
	const RECONNECTION_INTERVAL_STEP = 500000;
	
	/**
	 * Message process mask - do not process any type of messages
	 * @var	integer
	 */
	const MESSAGE_PROCESS_NONE = 0;
	
	/**
	 * Message process mask - process only messages type of Event
	 * @var	integer
	 */
	const MESSAGE_PROCESS_EVENT = 1;
	
	/**
	 * Message process mask - process only messages type of Response
	 * @var	integer
	 */
	const MESSAGE_PROCESS_RESPONSE = 2;
	
	/**
	 * Message process mask - process any type of messages
	 * @var	integer
	 */
	const MESSAGE_PROCESS_ALL = 3;
	
	/**
	 * Interval between reconnection attempts in microseconds
	 * @var	integer
	 */
	private $_reconnection_interval = 0;

	protected $_config = array(
		'client_id'           => 'CTIClient',
		'client_type'         => 'CTIClient',
		'host'                => 'localhost',
		'log_file_enabled'    => true,
		'log_file_name'       => 'CTIClient.log',
		'port'                => 10150,
		'unique_key'          => 'CTIClient',
		'command_ttl'         => 10,
	);
	
	protected $_process;
	protected $_ssl;	
	protected $_url;
	protected $_headers;
	protected $_log_enabled;
	protected $_log;

	protected $_command_ttl;
	protected $_commands_dir;
	protected $_events_dir;
	
	protected $_protocol_version;
	protected $_client_type;
	protected $_client_guid;
	protected $_client_id;
	
	protected $_salt = '';
	protected $_registered_requests = array();
	protected $_registered_requests_callbacks = array();
	protected $_message_process_mask = self::MESSAGE_PROCESS_ALL;

	protected $_proxy;
	protected $_proxy_string;

	/**
	 * Constructor
	 * @return	void
	 */
	public function __construct($guid_salt = '', array $config = array())
	{
		$this->_config = array_merge($this->_config, $config);
		$this->_process = new ListenerProcess();
		$this->updateProcessStatus(ListenerProcess::STATUS_INACTIVE);
		$this->_ssl = false;
		$this->_client_guid = $this->generateClientGUID($guid_salt);
		$this->_protocol_version = 1;
		$this->_client_type = $this->config('client_type');
		$this->_client_id = $this->config('client_id');		
		if ($this->_proxy = $this->config('proxy_enabled'))
			$this->_proxy_string = $this->config('proxy_key').$this->_client_id.':'.$this->getPort();
		$url = $this->getScheme().'://'.$this->config('host').':'.$this->getPort();
		$headers = array (
			'ClientGUID'	=> $this->_client_guid,
			'ClientID'		=> $this->_client_id,
			'ClientType'	=> $this->_client_type,
			'Test-Agent'	=> 'WebSocket++/0.2.0',
			'User-Agent'	=> 'WebSocket++/0.2.0dev'
		);
		parent::__construct($url, $headers);
		$this->_log_enabled = $this->config('log_file_enabled');
		if ($this->_log_enabled) {
			$this->_log = fopen($this->config('log_file_name'), 'a');
		}

		$this->_commands_dir = 'storage/commands';
		$this->_events_dir   = 'storage/events';
		$this->_command_ttl  = $this->config('command_ttl');
	}
	
	/**
	 * (non-PHPdoc)
	 * @see WebSocket.Listener::__destruct()
	 */
	public function __destruct()
	{
		parent::__destruct();
		if ($this->_log_enabled && is_resource($this->_log))
			fclose($this->_log);
	}

	/**
	 * Get or set config parameter
	 * @param  string $name
	 * @param  mixed $value
	 * @return mixed
	 */
	public function config($name, $value = null)
	{
		if ($value)
			$this->_config[$name] = $value;
		else
			return $this->_config[$name];
	}

	/**
	 * Handler for connection has established event
	 * @return	void
	 */
	public function onConnect()
	{
		$this->_salt = strval(microtime(true));
		$this->log(__CLASS__.'::'.__FUNCTION__.' - Connected');
		$this->updateProcessStatus(ListenerProcess::STATUS_ACTIVE);
	}

	/**
	 * Handler for connection has closed event
	 * @param	string	$reason
	 * @return	void
	 */
	public function onDisconnect($reason = '')
	{
		$this->log(__CLASS__.'::'.__FUNCTION__.' - Disconnected'.(!empty($reason) ? ' on reason: '.$reason : ''));
		$this->updateProcessStatus(ListenerProcess::STATUS_INACTIVE);
	}

	/**
	 * Handler for message has recieved event
	 * @throws	CTIException
	 * @param	MessageIncoming	$message
	 * @return	void
	 */
	public function onMessage(MessageIncoming $message)
	{
		// break connection in case some other listener script has started
		$this->updateProcessTimeActivity();
		$message = $message->getData();
		if (!$this->isSSLEnabled())
			$message = base64_decode($message);
		$this->log('INC: '.$message);
		$xml = new DOMDocument('1.0', 'UTF-8');
		$xml->loadXML($message);
		switch($xml->documentElement->nodeName)
		{
			case 'Event':
				if (($this->_message_process_mask & self::MESSAGE_PROCESS_EVENT) === self::MESSAGE_PROCESS_EVENT)
					$this->processEvent(new CTIEvent($this, $xml));
				break;
			case 'Response':
				if (($this->_message_process_mask & self::MESSAGE_PROCESS_RESPONSE) === self::MESSAGE_PROCESS_RESPONSE)
					$this->processResponse(new CTIResponse($this, $xml));
				break;
		}
	}
	
	/**
	 * Updates process status
	 * @throws	CTIException
	 * @param	string	$status
	 * @return	void
	 */
	protected function updateProcessStatus($status)
	{
		if (!$this->_process->updateStatus($status))
		{
			$this->log(__CLASS__.'::'.__FUNCTION__.' Can\'t update listener process status, ID = '.$this->_process->id);
			$this->disconnect(Connection::CONNECTION_CLOSE_MODE_SOFT, 'Other listener process captured control');
			throw new CTIException(CTIException::otherListenerCapturedControl(ListenerProcess::loadCurrentProcessInfo(ListenerProcess::PROCESS_INFO_KEY_ID), $this->_process->id));
		}
	}
	
	/**
	 * Updates process activity time
	 * @throws	CTIException
	 * @return	void
	 */
	protected function updateProcessTimeActivity()
	{
		if (!$this->_process->updateTimeLastActivity())
		{
			$this->log(__CLASS__.'::'.__FUNCTION__.' Can\'t update listener process activity time, ID = '.$this->_process->id);
			$this->disconnect(Connection::CONNECTION_CLOSE_MODE_SOFT, 'Other listener process captured control');
			throw new CTIException(CTIException::otherListenerCapturedControl(ListenerProcess::loadCurrentProcessInfo(ListenerProcess::PROCESS_INFO_KEY_ID), $this->_process->id));
		}
	}
	
	/**
	 * Set message process mask
	 * @param	integer	$mask
	 * @return	void
	 */
	public function setMessageProcessMask($mask = self::MESSAGE_PROCESS_ALL)
	{
		$this->_message_process_mask = $mask;
	}

	/**
	 * Handler for exception hash happend event
	 * @param	WebSocketException	$exception
	 * @return	void
	 */
	public function onException(WebSocketException $exception)
	{
		$this->log(
			__CLASS__.'::'.__FUNCTION__.' - Exception catched '.
				get_class($exception).'::'.$exception->getCode().', '.
				trim($exception->getMessage()).'. '.
				'Happens at line '.$exception->getLine().' in file '.$exception->getFile());
		
		throw $exception;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see WebSocket.Client::sendMessage()
	 */
	public function sendMessage($data = '', $data_type = MessageOutcoming::DATA_TYPE_TEXT)
	{
		$this->log('OUT: '.$data);
		if (!$this->isSSLEnabled())
			$data = base64_encode($data);
		parent::sendMessage($data, $data_type);
	}
	
	/**
	 * Tell us if SSL enabled
	 * @return	boolean
	 */
	public function isSSLEnabled()
	{
		return !empty($this->_ssl);
	}
	
	/**
	 * Returns protocol version, which supported by client
	 * @return	integer
	 */
	public function getProtocolVersion()
	{
		return $this->_protocol_version;
	}
	
	/**
	 * Set Client Guid
	 * @param	string	$guid
	 * @return	void
	 */
	protected function generateClientGUID($salt = '')
	{
		$guid = md5($this->config('unique_key').$salt);
		$guid =
			substr($guid, 0, 8).'-'.
			substr($guid, 8, 4).'-'.
			substr($guid, 12, 4).'-'.
			substr($guid, 16, 4).'-'.
			substr($guid, 20, 12);
		return $guid;
	}
	
	/**
	 * Returns client GUID
	 * @return	string
	 */
	public function getClientGUID()
	{
		return $this->_client_guid;
	}
	
	/**
	 * Returns client ID
	 * @return	string
	 */
	public function getClientID()
	{
		return $this->_client_id;
	}
	
	/**
	 * Returns client type
	 * @return	string
	 */
	public function getClientType()
	{
		return $this->_client_type;
	}
	
	/**
	 * Log messages to special file
	 * @param	string	$message
	 * @return	void
	 */
	public function log($message)
	{
		$message = date('Y-m-d H:i:s').' '.$message.PHP_EOL;

		$this->_log_enabled ? fwrite($this->_log, $message) : print($message);
	}
	
	/**
	 * Process events
	 * @throws	CTIException
	 * @param	CTIEvent	$event
	 * @return	void
	 */
	public function processEvent(CTIEvent $event)
	{
		$filename = $this->_events_dir.'/'.time().'_'.md5(mt_rand());

		switch($event->type)
		{
			case CTIEvent::TYPE_TRANSFER_REQUEST:
				$event_data = array(
					'type'   => $event->type,
					'callID' => $event->callID,
					'from'   => $event->from,
				);
				break;
			case CTIEvent::TYPE_CALL_START:
				$event_data = array(
					'type'   => $event->type,
					'callID' => $event->callID,
					'from'   => $event->from,
					'to'     => $event->to,
				);
				break;
			case CTIEvent::TYPE_CALL_END:
				$event_data = array(
					'type'      => $event->type,
					'callID'    => $event->callID,
					'from'      => $event->from,
					'to'        => $event->to,
					'start'     => $event->start,
					'end'       => $event->end,
					'duration'  => $event->duration,
					'direction' => $event->direction,
					'record'    => $event->record,
				);
				break;
		}

		file_put_contents($filename, json_encode($event_data));
	}
	
	/**
	 * Send request to server
	 * @param	CTIRequest	$request
	 * @param	callback	$callback, function which will called after 
	 * 			response on $request will processed, arguments of function is
	 * 			- CTIResponse	$reponse 
	 * 			- CTIRequest	$request
	 * 			- boolean		$result (TRUE in case succsessfull response and FALSE in case error)
	 * 			- CTIException	$exception, exception which thrown by response processor, NULL in case success
	 * @return	void
	 */
	public function sendRequest(CTIRequest $request, $callback = null)
	{
		$this->sendMessage($request->getXML());
		$this->registerRequest($request, $callback);
	}
	
	
	/**
	 * Register request needs to handle response
	 * @param	CTIRequest	$request
	 * @param	callback	$callback
	 * @return	void
	 */
	public function registerRequest(CTIRequest $request, $callback)
	{
		$this->_registered_requests[$request->RequestID] = $request;
		if (is_callable($callback))
			$this->_registered_requests_callbacks[$request->RequestID] = $callback;
	}
	
	/**
	 * Process response of request. 
	 * If there is no request which wait for this response then response won't processed
	 * @throws	CTIException
	 * @param	CTIResponse	$response
	 * @return	void
	 */
	public function processResponse(CTIResponse $response)
	{
		$request_id = $response->RequestID;
		if (isset($this->_registered_requests[$request_id]))
		{
			$request = $this->_registered_requests[$request_id];
			unset($this->_registered_requests[$request_id]);
			$method_name = 'process'.$request->Method;
			try
			{
				if (method_exists('CTIResponse', $method_name))
					call_user_func(array('CTIResponse', $method_name), $response, $request);
				else
					CTIResponse::process($response, $request);
				$result = true;
				$exception = null;
			}
			catch(CTIException $exception)
			{
				$result = false;
			}
			if (isset($this->_registered_requests_callbacks[$request_id]))
			{
				$callback = $this->_registered_requests_callbacks[$request_id];
				unset($this->_registered_requests_callbacks[$request_id]);
				
				call_user_func($callback, $response, $request, $result, $exception);
			}
			
			if ($exception !== null)
				throw $exception;
		}
	}
	
	/**
	 * Start listener process
	 * @param	boolean	$reconnect
	 * @return	void
	 */
	public function listenLoop($reconnect = false)
	{
		while(true)
		{
			try
			{
				$this->updateProcessTimeActivity();
				
				if (!$this->isConnected(false))
				{
					if (!$reconnect)
						break;
					
					// try to recover connection
					usleep($this->_reconnection_interval);
					$this->_reconnection_interval += self::RECONNECTION_INTERVAL_STEP;
					
					if ($this->_reconnection_interval > self::RECONNECTION_INTERVAL_MAX)
						$this->_reconnection_interval = self::RECONNECTION_INTERVAL_MAX;
					
					if ($this->connect() && $this->isConnected(true))
						$this->_reconnection_interval = self::RECONNECTION_INTERVAL_MIN;
					else
						continue;
				}

				if ($commands = $this->getCommands())
					$this->sendCommands($commands);

				$this->recieveMessage();
			}
			catch(ClientException $exception)
			{
				switch($exception->getCode())
				{
					case ClientException::RECIEVE_MESSAGE_INTERRUPTION:
						// do nothing, skip this exception
						break;
					case ClientException::CONNECTION_HAS_CLOSED:
						// this exception already registered with onException method
						break;
					case ClientException::OUTCOMING_MESSAGE_IS_INVALID:
						// this exception already registered with onException method
						break;
					case CTIException::OTHER_LISTENER_CAPTURED_CONTROL:
						// break isten looping, other process captured control
						$this->onException($exception);
						break 2;
					case CTIException::REQUEST_AUTHENTICATION_ERROR:
						// authentication error, we can't authenticate at remote server
						// cript should be restarted
						$this->onException($exception);
						break 2;
					case CTIException::REQUEST_OTHER_ERROR:
						// just register error
						$this->onException($exception);
						break;
					default:
						// @TODO: define all exceptions handler
						$this->log('Unregistered exception type');
						$this->onException($exception);
						break;
				}
			}
		}
	}
	
	/**
	 * Generate unique ID for call event
	 * @param	string	$id
	 * @return	string
	 */
	final protected function getCallEvetId($id)
	{
		$id = md5($this->_salt.'-'.$id);
		$id = 
			substr($id, 0, 8).'-'.
			substr($id, 8, 4).'-'.
			substr($id, 12, 4).'-'.
			substr($id, 16, 4).'-'.
			substr($id, 20, 12);
		return $id;
	}

	/**
	 * Get commands that should be sent to server
	 * 
	 * @return array
	 */
	protected function getCommands()
	{
		$now = time();

		$commands = array(
			'calls' => array(),
			'transfers' => array(),
		);

		foreach (scandir($this->_commands_dir) as $value)
		{
			$filepath = $this->_commands_dir.'/'.$value;

			if (is_dir($filepath))
				continue;

			if ($now - filemtime($filepath) <= $this->_command_ttl)
			{
				$params = explode('_', $value);

				if (count($params) === 4)
				{
					if ($params[0] === 'call')
					{
						$call_data = array(
							'from' => $params[2],
							'to'   => $params[3],
						);

						$commands['calls'][md5(serialize($call_data))] = $call_data;
					}
					else if ($params[0] === 'transfer')
					{
						$transfer_data = array(
							'callID' => $params[2],
							'to'   => $params[3],
						);

						$commands['transfers'][md5(serialize($transfer_data))] = $transfer_data;
					}
				}
			}

			unlink($filepath);
		}

		return $commands;
	}

	/**
	 * Send commands to server
	 * 
	 * @param  array  $commands
	 */
	protected function sendCommands(array $commands)
	{
		foreach ($commands['calls'] as $call)
			$this->sendRequest(CTIRequest::createCall($this, $call['from'], $call['to']));

		foreach ($commands['transfers'] as $transfer)
			$this->sendRequest(CTIRequest::createTransfer($this, $transfer['callID'], $transfer['to']));
	}

	protected function getScheme()
	{
		$scheme = 'ws';

		if ($this->_ssl)   $scheme .= 's'; // wss
		if ($this->_proxy) $scheme .= 'p'; // wsp or wssp
		
		return $scheme;
	}

	protected function getPort()
	{
		return ($this->_proxy) ? $this->config('proxy_port') : $this->config('port');
	}
}