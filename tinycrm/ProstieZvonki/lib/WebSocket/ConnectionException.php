<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Exception.php';

/**
 * Exceptions which happens in Connection.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class ConnectionException extends Exception
{
	const INVALID_URL = 1;
	const INVALID_URL_SCHEME = 2;
	const CANT_OPEN_SOCKET = 3;
	const SOCKET_IS_NOT_RESOURCE = 4;
	const INVALID_HANDSHAKES_RESPONSE = 5;
	const INVALID_HANDSHAKES_RESPONSE_HTTP_STATUS = 6;
	const MISSED_HANDSHAKES_RESPONSE_HEADER = 7;
	const INVALID_HANDSHAKES_RESPONSE_HEADER = 8;
	const CONNECTION_CLOSE_MESSAGE_RECIEVED = 9;
	const CONNECTION_BROKEN = 10;
	const SOCKET_TIME_OUT = 11;
	const EMPTY_PROXY_STRING = 12;
	
	/**
	 * Happens when remote web-socket url is invalid
	 * @param	string	$url
	 * @return	array
	 */
	public static function invalidURL($url)
	{
		return array('Invalid web-socket URL "'.$url.'"', self::INVALID_URL);
	}
	
	/**
	 * Happens when url scheme in remote web-socket url is invalid
	 * @param	string	$url
	 * @param	string	$scheme
	 * @return	array
	 */
	public static function invalidURLScheme($url, $scheme)
	{
		return array('Invalid web-socket URL shceme "'.$scheme.'", url: "'.$url.'"', self::INVALID_URL_SCHEME);
	}
	
	/**
	 * Happens on socket open error
	 * @param	integer	$socket_error_code
	 * @param	string	$socket_error_text
	 * @return	array
	 */
	public static function cantOpenSocket($socket_error_code, $socket_error_text)
	{
		return array('Can\'t open socket, returned error ['.$socket_error_code.'] "'.$socket_error_text.'"', self::CANT_OPEN_SOCKET);
	}
	
	/**
	 * Happens when socket is not a resource type
	 * @return	array
	 */
	public static function socketIsNotResource()
	{
		return array('Socket is not a resource', self::SOCKET_IS_NOT_RESOURCE);
	}
	
	/**
	 * Happens when invalid response recieved at handshakes connection stage
	 * @TODO: move this exception to handshakes object
	 * @return	array
	 */
	public static function invalidHandshakesResponse($response)
	{
		return array('Invalid handshakes response '.$response, self::INVALID_HANDSHAKES_RESPONSE);
	}
	
	/**
	 * Happens when invalid response HTTP status recieved at handshakes connection stage
	 * @TODO: move this exception to handshakes object
	 * @param	integer	$status
	 * @param	string	$status_text
	 * @return	array
	 */
	public static function invalidHandshakesResponseHTTPStatus($status, $status_text)
	{
		return array('Invalid handshakes response HTTP status '.$status.' '.$status_text, self::INVALID_HANDSHAKES_RESPONSE_HTTP_STATUS);
	}
	
	/**
	 * Happens when one of headers is missed in handshakes response
	 * @TODO: move this exception to handshakes object
	 * @param	string	$header_name
	 * @param	string	$response
	 * @return	array
	 */
	public static function missedHandshakesResponseHeader($header_name, $response)
	{
		return array('Missing required response header "'.$header_name.'", response: '.$response, self::MISSED_HANDSHAKES_RESPONSE_HEADER);
	}
	
	/**
	 * Happens when one of headers in handshakes response has invalid value
	 * @TODO: move this exception to handshakes object
	 * @param	string	$header_name
	 * @param	mixed	$header_value
	 * @param	mixed	$header_expexted_value
	 * @return	array
	 */
	public static function invalidHandshakesResponseHeader($header_name, $header_value, $header_expexted_value)
	{
		return array('Invalid header "'.$header_name.'" value "'.$header_value.'", extected: "'.$header_expexted_value.'"', self::INVALID_HANDSHAKES_RESPONSE_HEADER);
	}
	
	/**
	 * Happens, when connection close message had recieved from remote host
	 * @param	string	$payload_data
	 * @return	array
	 */
	public static function connectionCloseMessageRecieved($payload_data)
	{
		return array('Connection close message with payload "'.$payload_data.'" has been recieved and processed, connection closed', self::CONNECTION_CLOSE_MESSAGE_RECIEVED);
	}
	
	/**
	 * Happens, when connection closed because of some exception occurred
	 * @param	string	$comment
	 * @return	array
	 */
	public static function connectionBroken($comment)
	{
		return array('Connection has broken. '.$comment, self::CONNECTION_BROKEN);
	}
	
	/**
	 * Happens when reading from socket interrupted with socket time out reason
	 * @return	array
	 */
	public static function socketTimeOut()
	{
		return array('Socket is timed out.', self::SOCKET_TIME_OUT);
	}

	/**
	 * Happens, when proxy_string is not set
	 * @return array
	 */
	public static function emptyProxyString()
	{
		return array('Proxy string is empty', self::EMPTY_PROXY_STRING);
	}
}