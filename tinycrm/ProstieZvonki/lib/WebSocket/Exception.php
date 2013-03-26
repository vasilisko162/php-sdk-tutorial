<?php
namespace WebSocket;

/**
 * Basic class for WebSocket exceptions.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
abstract class Exception extends \Exception
{
	/**
	 * Constructor, its ppossible to pass exception message and code in single argument.
	 * Use Exception static methods to generate array with exception message and codes,
	 * message is first element of array and code is second
	 * @param	string	$message
	 * @param	integer	$code
	 * @param	Excption	$previous
	 * @return	void
	 */
	public function __construct($message = null, $code = null, $previous = null)
	{
		if (is_array($message) && $code === null)
		{
			list($message, $code) = $message;
		}
		parent::__construct($message, $code, $previous);
	}
}