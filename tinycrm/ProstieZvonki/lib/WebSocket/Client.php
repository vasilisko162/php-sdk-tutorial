<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'iObserver.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'Connection.php';

/**
 * Basic web-socket client object.
 * 
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
abstract class Client implements iObserver
{
	/**
	 * Url address of web-socket
	 * @var	string
	 */
	protected $_url;
	
	/**
	 * Additional headers array
	 * @var	array
	 */
	protected $_headers;
	
	/**
	 * Connection object
	 * @var Connection
	 */
	protected $_connection;
	
	/**
	 * Constructor
	 * @return	void
	 */
	public function __construct($url, $headers = array())
	{
		$this->_url = $url;
		$this->_headers = $headers;
	}
	
	/**
	 * Desctructor
	 * @return	void
	 */
	public function __destruct()
	{
		$this->disconnect(Connection::CONNECTION_CLOSE_MODE_SOFT, __CLASS__.'::'.__FUNCTION__.' called');
	}
	
	/**
	 * Make a connection with remote server
	 * @throws	ConnectionException
	 * @param	string	$url
	 * @param	array	$headers
	 * @return	boolean
	 */
	public function connect()
	{
		try
		{
			$this->disconnect(Connection::CONNECTION_CLOSE_MODE_HARD, 'refresh connection');
			$this->_connection = new Connection($this->_url);
			if ($this->_proxy_string)
				$this->_connection->setProxyString($this->_proxy_string);
			$this->_connection->connect($this->_headers);
			$this->onConnect();
			return true;
		}
		catch(ConnectionException $exception)
		{
			$this->onException($exception);
			return false;
		}
	}
	
	/**
	 * Disconnect listener from remote server
	 * @return	boolean
	 */
	public function disconnect($mode = Connection::CONNECTION_CLOSE_MODE_SOFT, $message = '')
	{
		try
		{
			if (isset($this->_connection))
			{
				$this->_connection->disconnect($mode, $message);
				unset($this->_connection);
				$this->onDisconnect($message);
			}
			return true;
		}
		catch(ConnectionException $exception)
		{
			$this->onException($exception);
			return false;
		}
	}
	
	/**
	 * Check connection status, in case connection is 
	 * established and work returns true, false in other case.
	 * @param	boolean	$do_ping
	 * @return	boolean
	 */
	public function isConnected($do_ping = true)
	{
		if (!isset($this->_connection))
			return false;
		try
		{
			return $this->_connection->isConnected($do_ping);
		}
		catch(ConnectionException $exception)
		{
			$this->onException($exception);
			return false;
		}
	}
	
	/**
	 * Sends message to remote host
	 * @throws	ClientException
	 * @param	string	$data
	 * @param	integer	$data_type, @see MessageOutcoming::DATA_TYPE_* constants for more details
	 * @return	void
	 */
	public function sendMessage($data = '', $data_type = MessageOutcoming::DATA_TYPE_TEXT)
	{
		try
		{
			$this->_connection->writeMessage(new MessageOutcoming($data_type, $data));
		}
		catch (MessageException $exception)
		{
			$this->onException($exception);
			throw new ClientException(ClientException::outcomingMessageIsInvalid($exception->getMessage()));
		}
		catch (ConnectionException $exception)
		{
			$this->onException($exception);
			throw new ClientException(ClientException::connectionHasClosed($exception->getMessage()));
		}
	}
	
	/**
	 * Recieve message from remote host
	 * @throws	ClientException
	 * @return	MessageIncoming
	 */
	public function recieveMessage()
	{
		try
		{
			$this->onMessage($this->_connection->readMessage());
		}
		catch (ConnectionException $exception)
		{
			if ($exception->getCode() === ConnectionException::SOCKET_TIME_OUT)
			{
				throw new ClientException(ClientException::recieveMessageInterruption());
			}
			else
			{
				$this->onException($exception);
				throw new ClientException(ClientException::connectionHasClosed($exception->getMessage()));
			}
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see WebSocket.iObserver::getConnection()
	 */
	public function getConnection()
	{
		return $this->_connection;
	}
}