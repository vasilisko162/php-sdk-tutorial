<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageIncoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageOutcoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'ConnectionException.php';

/**
 * Class for web-socket connection object. 
 * All interaction between server and client is performed throw this interface.
 * No other object has access to socket connection.
 * 
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class Connection
{
	/**
	 * URL scheme used for ssl connection
	 * @var string
	 */
	const SCHEME_SSL = 'wss';
	
	/**
	 * URL scheme used for non-ssl connection
	 * @var string
	 */
	const SCHEME_NOSSL = 'ws';

	/**
	 * URL scheme used for ssl connection with proxy
	 * @var string
	 */
	const SCHEME_SSL_PROXY = 'wssp';

	/**
	 * URL scheme used for non-ssl connection with proxy
	 * @var string
	 */
	const SCHEME_NOSSL_PROXY = 'wsp';
	
	/**
	 * Size of socket read buffer
	 * @var integer
	 */
	const READ_BUFFER_SIZE = 8192;
	
	/**
	 * Size of socket read buffer
	 * @var integer
	 */
	const WRITE_BUFFER_SIZE = 8192;
	
	/**
	 * Timeout of reading from socket, in seconds
	 * @var	integer
	 */
	const READ_TIMEOUT = 10;
	
	/**
	 * Force socket closing, without sending CONNECTION_CLOSE frames
	 * @var integer
	 */
	const CONNECTION_CLOSE_MODE_HARD = 0;
	
	/**
	 * Needs to send request to remote host to close connection and accept confirmation.
	 * Or if remote host already asked to close connection we need to send confirmation before closing connection.
	 * @var integer
	 */
	const CONNECTION_CLOSE_MODE_SOFT = 1;
	
	/**
	 * Remote host identifier, uses in disconnect method
	 * @var	string
	 */
	const HOST_REMOTE = 0;
	
	/**
	 * Local host identifier, uses in disconnect method
	 * @var	string
	 */
	const HOST_LOCAL = 1;
	
	/**
	 * Connection id, can be used to associate connection with other objects
	 * @var string
	 */
	protected $_id;
	
	/**
	 * Connection attributes
	 */
	/**
	 * URL of current connection. Examples:
	 *  - for non-ssl connection: ws://www.example.com:8000
	 *  - for ssl connection: wss://www.example.com:8000
	 * @var string
	 */
	protected $_url;
	
	/**
	 * URL scheme of current connection
	 * @var string
	 */
	protected $_scheme;
	
	/**
	 * Remote host name
	 * @var string
	 */
	protected $_host;
	
	/**
	 * Remote host port
	 * @var integer
	 */
	protected $_port = 80;
	
	/**
	 * Path on remote host
	 * @var string
	 */
	protected $_path = '/';
	
	/**
	 * Stream socket pointer
	 * @var string
	 */
	protected $_socket;
	
	/**
	 * Read buffer string
	 * @var string
	 */
	protected $_read_buffer = '';
	
	/**
	 * Write buffer string
	 * @var string
	 */
	protected $_write_buffer = '';
	
	/**
	 * Frame which reading in progress
	 * @var FrameIncoming
	 */
	protected $_read_frame = null;
	
	/**
	 * Message which reading in progress
	 * @var MessageIncoming
	 */
	protected $_read_message = null;
	
	/**
	 * Flag, which indicates connectio state.
	 * TRUE after connection was esteblished by self::connect method, and
	 * FALSE after connection was closed by self::disconnect method
	 * @var boolean
	 */
	protected $_connected = false;
	
	/**
	 * Process control frames flag, if TRUE, then all control frames will be processed in readFrame method,
	 * if FALSE, readFrame always returns frame, accepted from remote host
	 * @var	boolean
	 */
	protected $_process_control_frames = true;
	
	/**
	 * String for initiating proxy server handshake
	 * @var string
	 */
	protected $_proxy_string;

	/**
	 * Constructor
	 * @throws	ConnectionException
	 * @param	string	$url
	 * @return	void
	 */
	public function __construct($url)
	{
		$this->_id = md5(microtime(true).sprintf('%020u', mt_rand(0, PHP_INT_MAX)));
		$parsed_url = parse_url($url);
		
		if (empty($parsed_url))
			throw new ConnectionException(ConnectionException::invalidURL($url));
		
		if (!$this->isSchemeCorrect($parsed_url['scheme']))
			throw new ConnectionException(ConnectionException::invalidURLScheme($url, $parsed_url['scheme']));
		
		$this->_scheme = $parsed_url['scheme'];
		$this->_host = $parsed_url['host'];
		
		if (isset($parsed_url['port']))
			$this->_port = intval($parsed_url['port'], 10);
		if (isset($parsed_url['path']))
			$this->_path = $parsed_url['path'];
	}
	
	/**
	 * Desctructor
	 * @return	void
	 */
	public function __destruct()
	{
		$this->disconnect(self::CONNECTION_CLOSE_MODE_SOFT, __CLASS__.'::'.__FUNCTION__.' called', self::HOST_LOCAL);
	}
	
	/**
	 * Open connection on remote server
	 * @TODO: use handshaker object
	 * @throws	ConnectionException
	 * @param	array	$headers
	 * @return	void
	 */
	public function connect($headers = array())
	{
		if (!is_resource($this->_socket))
		{
			try
			{
				$socket_host = ($this->shouldUseSSL($this->_scheme) ? 'ssl' : 'tcp').'://'.$this->_host;
				$socket_port = $this->_port;
				$socket_errno = null;
				$socket_errstr = null;
				$socket_timeout = 10;
				$this->_socket = fsockopen($socket_host, $socket_port, $socket_errno, $socket_errstr, $socket_timeout);
				
				if (empty($this->_socket))
					throw new ConnectionException(ConnectionException::cantOpenSocket($socket_errno, trim($socket_errstr)));

				if ($this->shouldUseProxy($this->_scheme))
				{
					if (empty($this->_proxy_string))
						throw new ConnectionException(ConnectionException::emptyProxyString());

					$response_headers = $this->handshake("CONNECT {$this->_proxy_string} HTTP/1.0\r\n\r\n");

					if ($response_headers[0] !== 'HTTP/1.0 200 Connection established')
						throw new ConnectionException(ConnectionException::invalidHandshakesResponse($response));
				}
				
				$sec_key = call_user_func(function() {
					$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';
					$string = array ();
					for ($i = 0; $i < 16; $i++)
						$string[] = $characters[mt_rand(0, strlen($characters) - 1)];
					shuffle($string);
					return base64_encode(implode('', $string));
				});
				
				$headers = array (
					'Host' => $this->_host.':'.$this->_port,
					'Origin' => 'http://'.$this->_host.':'.$this->_port.($this->_path !== '/' ? $this->_path : ''),
					'Upgrade' => 'websocket',
					'Connection' => 'Upgrade',
					'Sec-WebSocket-Key' => $sec_key,
					'Sec-WebSocket-Version' => '13'
				) + $headers;
				array_walk($headers, function (& $value, $name) {
					$value = $name.': '.$value;
				});

				$response_headers = $this->handshake('GET '.$this->_path.' HTTP/1.1'."\r\n".implode("\r\n", $headers)."\r\n\r\n");
				
				if (empty($response_headers))
					throw new ConnectionException(ConnectionException::invalidHandshakesResponse($response));
				
				//if (!preg_match('/^HTTP\/[0-9]+(?:\.[0-9]+)? *([0-9]+) (.+)$/', $response_headers[0], $response_http_status))
				if (!preg_match('/^HTTP\/[0-9]+(?:\.[0-9]+)? *([0-9]+) (.*)$/', $response_headers[0], $response_http_status))
					throw new ConnectionException(ConnectionException::invalidHandshakesResponse($response));
				
				// remove first element in headers (HTTP status)
				array_shift($response_headers);
				$response_headers_parsed = array();
				array_walk($response_headers, 
					function ($value, $index) use (& $response_headers_parsed)
					{
						$value = preg_split('/\:\s+/', $value, 2);
						$response_headers_parsed[$value[0]] = $value[1];
					});
				$response_headers = $response_headers_parsed;
				unset($response_headers_parsed);
				switch($response_http_status[1])
				{
					case 101:
						if (!isset($response_headers['Sec-WebSocket-Accept']))
							throw new ConnectionException(ConnectionException::missedHandshakesResponseHeader('Sec-WebSocket-Accept', $response));
						
						$expected_sec_websocket_accept = base64_encode(pack('H*', sha1($sec_key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
						
						if ($expected_sec_websocket_accept !== $response_headers['Sec-WebSocket-Accept'])
							throw new ConnectionException(ConnectionException::invalidHandshakesResponseHeader(
								'Sec-WebSocket-Accept', 
								$response_headers['Sec-WebSocket-Accept'], 
								$expected_sec_websocket_accept
							));
						
						break;
					default:
						throw new ConnectionException(ConnectionException::invalidHandshakesResponseHTTPStatus(
							$response_http_status[1], 
							$response_http_status[2]
						));
						break;;
				}
				$this->_connected = true;
			}
			catch (ConnectionException $exception)
			{
				$this->disconnect(self::CONNECTION_CLOSE_MODE_HARD, 'Unable to connect to remote host', self::HOST_LOCAL);
				throw $exception;
			}
		}
	}
	
	/**
	 * Close connection with remote web-socket server
	 * @param	integer	$mode, disconnect mode, @see at self::CONNECTION_CLOSE_MODE* constants
	 * @param	string	$message, disconnection message, will be passed to observers
	 * @param	string	$host, disconnection initiator host
	 * @return	void
	 */
	public function disconnect($mode = self::CONNECTION_CLOSE_MODE_HARD, $message = '', $host = self::HOST_LOCAL)
	{
		if (is_resource($this->_socket))
		{
			switch($mode)
			{
				case self::CONNECTION_CLOSE_MODE_HARD:
					switch($host)
					{
						case self::HOST_LOCAL:
							$reason = 'Local host force connection closing with message "'.$message.'".';
							break;
						case self::HOST_REMOTE:
							$reason = 'Remote host force connection closing with message "'.$message.'".';
							break;
					}
					break;
				case self::CONNECTION_CLOSE_MODE_SOFT:
					try
					{
						switch($host)
						{
							case self::HOST_LOCAL:
								$this->writeFrame(new FrameOutcoming($message, FrameOutcoming::OPCODE_CONNECTION_CLOSE, FrameOutcoming::FLAG_FIN));
								$reason = 'Local host has asked to close connection with message "'.$message.'".';
								$this->processControlFrames(false);
								do
									$frame = $this->readFrame();
								while($frame->getOpcode() !== FrameIncoming::OPCODE_CONNECTION_CLOSE);
								$reason .= ' Remote host confirm closing of connection with message "'.$frame->getPayloadData().'".';
								$this->processControlFrames(true);
								break;
							case self::HOST_REMOTE:
								$reason = 'Remote host has asked to close connection with message "'.$message.'".';
								$message = 'Confirm connection close';
								$this->writeFrame(new FrameOutcoming($message, FrameOutcoming::OPCODE_CONNECTION_CLOSE, FrameOutcoming::FLAG_FIN));
								$reason .= ' Local host has confirmed connection closing.';
								break;
						}
					}
					catch(ConnectionException $exception)
					{
						$reason .= ' '.
							'Local host force connection closing because exception catched: '.
							$exception->getCode().', '.$exception->getMessage().'. Happens at line '.$exception->getLine().' in file '.$exception->getFile().'.';
					}
					break;
			}
			fclose($this->_socket);
			$this->_connected = false;
		}
	}
	
	/**
	 * Tell us whether to process control frames or not
	 * @param	boolean	$process
	 * @return	boolean
	 */
	protected function processControlFrames($process = null)
	{
		if ($process !== null)
			$this->_process_control_frames = !empty($process);
		return $this->_process_control_frames;
	}
	
	/**
	 * Check connection alive. In case $do_ping is TRUE 
	 * PING sygnal will send, but do not wait for PONG reply.
	 * @param	boolean	$do_ping
	 * @return	boolean
	 */
	public function isConnected($do_ping = true)
	{
		if (!$this->_connected || !is_resource($this->_socket))
			return false;
		elseif (!$do_ping)
			return true;
		try
		{
			$this->writeFrame(new FrameOutcoming('', FrameOutcoming::OPCODE_PING, FrameOutcoming::FLAG_FIN));
			return true;
		}
		catch (ConnectionException $e)
		{
			return false;
		}
	}
	
	/**
	 * Returns connection identifier
	 * @return	string
	 */
	public function getId()
	{
		return $this->_id;
	}
	
	/**
	 * Flush write buffer, just clean write buffer string
	 * @return	void
	 */
	public function flushWriteBuffer()
	{
		$this->_write_buffer = '';
	}
	
	/**
	 * Write raw data to web-socket
	 * @throws	ConnectionException
	 * @param	string	$raw_data
	 * @return	void
	 */
	public function writeRaw($raw_data)
	{
		if (!is_resource($this->_socket))
			throw new ConnectionException(ConnectionException::socketIsNotResource());
		
		// append data to write buffer
		$this->_write_buffer .= $raw_data;
		unset($raw_data);
		
		while(strlen($this->_write_buffer) > 0)
		{
			$bytes_has_written = fwrite($this->_socket, substr($this->_write_buffer, 0, self::WRITE_BUFFER_SIZE), self::WRITE_BUFFER_SIZE);
			
			// connection broken, because of writing to socket failed 
			if ($bytes_has_written === false)
			{
				$this->disconnect(self::CONNECTION_CLOSE_MODE_HARD, 'Writing to socket has failed', self::HOST_LOCAL);
				throw new ConnectionException(ConnectionException::connectionBroken('Writing to socket has failed'));
			}
			if ($bytes_has_written === 0 && strlen($this->_write_buffer) > 0)
			{
				$this->disconnect(self::CONNECTION_CLOSE_MODE_HARD, 'Writing to socket has failed, 0 bytes has written', self::HOST_LOCAL);
				throw new ConnectionException(ConnectionException::connectionBroken('Writing to socket has failed, 0 bytes has written'));
			}
			
			// trim write buffer
			$this->_write_buffer = substr($this->_write_buffer, $bytes_has_written);
			// substr can return false in case there is no more symbols in string afrter substr 
			if ($this->_write_buffer === false)
				$this->_write_buffer = '';
		}
	}
	
	/**
	 * Write raw data to web-socket
	 * @TODO: handle non ConnectionException
	 * @throws	ConnectionException
	 * @throws	FrameOutcomingException
	 * @param	FrameOutcoming	$frame
	 * @return	void
	 */
	public function writeFrame(FrameOutcoming $frame)
	{
		$this->writeRaw($frame->getFrameRaw());
	}
	
	/**
	 * Writes message to web-socket
	 * @TODO: handle non ConnectionException
	 * @throws	MessageException
	 * @param	MessageOutcoming $message
	 * @return	void
	 */
	public function writeMessage(MessageOutcoming $message)
	{
		foreach($message->getFramesChain() as $frame)
		{
			$this->writeFrame($frame);
		}
	}
	
	/**
	 * Flush read buffer, just clean read buffer string
	 * @return	void
	 */
	public function flushReadBuffer()
	{
		$this->_read_buffer = '';
	}
	
	/**
	 * Read message from web-socket
	 * @return	string
	 */
	public function readRaw()
	{
		if (!is_resource($this->_socket))
			throw new ConnectionException(ConnectionException::socketIsNotResource());
		
		$buffer_size = self::READ_BUFFER_SIZE;
		do
		{
			// TODO: stream_set_timeout does not affect ssl sockets
			stream_set_timeout($this->_socket, self::READ_TIMEOUT);
			$readed_item = fread($this->_socket, $buffer_size);
			
			// connection has been broken, reading is not available
			if ($readed_item === false)
			{
				$this->disconnect(self::CONNECTION_CLOSE_MODE_HARD, 'Reading from socket has failed', self::HOST_LOCAL);
				throw new ConnectionException(ConnectionException::connectionBroken('Reading from socket has failed'));
			}
			
			$metadata = stream_get_meta_data($this->_socket);
			$buffer_size = min($buffer_size, $metadata['unread_bytes']);
			$this->_read_buffer .= $readed_item;
			
			// close the connection if it has been broken
			if ($metadata['timed_out'] === true && !$this->isConnected(true))
			{
				$this->disconnect(self::CONNECTION_CLOSE_MODE_HARD, 'Socket reading timeout has occurred', self::HOST_LOCAL);
				throw new ConnectionException(ConnectionException::connectionBroken('Socket reading timeout has occurred'));
			}
			// close the connection if it has been broken
			if ($metadata['eof'] === true && !$this->isConnected(true))
			{
				$this->disconnect(self::CONNECTION_CLOSE_MODE_HARD, 'Socket EOF has reached', self::HOST_LOCAL);
				throw new ConnectionException(ConnectionException::connectionBroken('Socket EOF has reached'));
			}
			
			if ($metadata['timed_out'] === true)
				throw new ConnectionException(ConnectionException::socketTimeOut());
		}
		while($buffer_size > 0);
		return $this->_read_buffer;
	}
	
	/**
	 * Read frame from web-socket
	 * @return	FrameIncoming
	 */
	public function readFrame()
	{
		try
		{
			if (!isset($this->_read_frame))
				$this->_read_frame = new FrameIncoming();
			
			$frame = $this->_read_frame;
			
			while(!$frame->isReady())
			{
				if (strlen($this->_read_buffer) === 0)
					$this->readRaw();
					
				$this->_read_buffer = $frame->appendFrameRaw($this->_read_buffer);
			}
			$this->_read_frame = null;
		}
		catch (FrameIncomingException $exception)
		{
			$this->disconnect(self::CONNECTION_CLOSE_MODE_SOFT, 'Invalid frame recieved: '.$frame->getFrameRaw(), self::HOST_LOCAL);
			throw new ConnectionException(ConnectionException::connectionBroken('FrameIncomingException: '.$exception->getMessage().' Frame raw: '.$frame->getFrameRaw()));
		}
		// process control frames, recieved from remote server
		if ($this->processControlFrames())
			switch($frame->getOpcode())
			{
				case FrameIncoming::OPCODE_CONNECTION_CLOSE:
					$this->disconnect(self::CONNECTION_CLOSE_MODE_SOFT, $frame->getPayloadData(), self::HOST_REMOTE);
					throw new ConnectionException(ConnectionException::connectionCloseMessageRecieved($frame->getPayloadData()));
					break;
				case FrameIncoming::OPCODE_PING:
					// try to send PONG frame in response
					try
					{
						$this->writeFrame(new FrameOutcoming($frame->getPayloadData(), FrameOutcoming::OPCODE_PONG, FrameOutcoming::FLAG_FIN));
					}
					catch(FrameOutcomingException $exception)
					{
						$this->disconnect(self::CONNECTION_CLOSE_MODE_SOFT, 'PONG frame is invalid: '.$frame->getFrameRaw(), self::HOST_LOCAL);
						throw new ConnectionException(ConnectionException::connectionBroken('FrameOutcomingException: '.$exception->getMessage().' Frame raw: '.$frame->getFrameRaw()));
					}
					$frame = $this->readFrame();
					break;
				case FrameIncoming::OPCODE_PONG:
					$frame = $this->readFrame();
					break;
			}
		
		return $frame;
	}
	
	/**
	 * Read message from web-socket, return null on fail
	 * @throws	ConnectionException
	 * @return	MessageIncoming
	 */
	public function readMessage()
	{
		try
		{
			if (!isset($this->_read_message))
				$this->_read_message = new MessageIncoming();
			
			$message = $this->_read_message;
			
			while(!$message->isReady())
				$message->addFrame($this->readFrame());
			
			$this->_read_message = null;
			
			return $message;
		}
		catch (MessageIncomingException $exception)
		{
			$this->disconnect(self::CONNECTION_CLOSE_MODE_SOFT, 'Invalid frames sequence recieved: '.implode(', ', $message->getFramesChain()), self::HOST_LOCAL);
			throw new ConnectionException(ConnectionException::connectionBroken('Message exception: '.$exception->getMessage().' Message frames: '.implode(', ', $message->getFramesChain())));
		}
	}

	/**
	 * Check if given scheme is correct
	 * @param  string  $scheme
	 * @return boolean
	 */
	protected function isSchemeCorrect($scheme)
	{
		return in_array($scheme, array(self::SCHEME_SSL, self::SCHEME_NOSSL, self::SCHEME_SSL_PROXY, self::SCHEME_NOSSL_PROXY));
	}

	/**
	 * Check ig connection should use ssl encryption
	 * @param  string $scheme
	 * @return boolean
	 */
	protected function shouldUseSSL($scheme)
	{
		return in_array($scheme, array(self::SCHEME_SSL, self::SCHEME_SSL_PROXY));
	}

	/**
	 * Check ig connection should use proxy
	 * @param  string $scheme
	 * @return boolean
	 */
	protected function shouldUseProxy($scheme)
	{
		return in_array($scheme, array(self::SCHEME_SSL_PROXY, self::SCHEME_NOSSL_PROXY));
	}

	public function setProxyString($string)
	{
		$this->_proxy_string = $string;
	}

	/**
	 * Send handshake and get response
	 * @param  string $request
	 * @return array  response
	 */
	protected function handshake($request)
	{
		$this->writeRaw($request);
		$this->flushWriteBuffer();
		$response = $this->readRaw();
		$this->flushReadBuffer();
		
		return explode("\r\n", substr($response, 0, strpos($response, "\r\n\r\n")));
	}
}