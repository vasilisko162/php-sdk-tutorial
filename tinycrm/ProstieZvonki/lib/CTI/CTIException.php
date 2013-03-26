<?php
use WebSocket\ClientException;

require_once __DIR__ . '/../WebSocket/ClientException.php';

/**
 * CTI exceptions class
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	CTI
 */
class CTIException extends ClientException
{
	const REQUEST_AUTHENTICATION_ERROR = 101;
	const REQUEST_OTHER_ERROR = 102;
	const OTHER_LISTENER_CAPTURED_CONTROL = 103;
	
	/**
	 * Happens, when request not processed by CTI server because of authentication error
	 * @param	string	$error
	 * @return	CTIException
	 */
	public static function requestAuthenticationError($error)
	{
		return array('Request failed because of authentication error: '.$error, self::REQUEST_AUTHENTICATION_ERROR);
	}
	
	/**
	 * Happens, when request not processed by CTI server because of any other error (not authentication)
	 * @param	string	$error
	 * @return	CTIException
	 */
	public static function requestOtherError($error)
	{
		return array('Request failed because of some error: '.$error, self::REQUEST_OTHER_ERROR);
	}
	
	/**
	 * Happens, when other listened process captured control
	 * @param	string	$error
	 * @return	CTIException
	 */
	public static function otherListenerCapturedControl($other_process_id, $current_process_id)
	{
		return array('Other listener (id: '.$other_process_id.') has launched and current listener (id: '.$current_process_id.') must shutdown', self::OTHER_LISTENER_CAPTURED_CONTROL);
	}
}