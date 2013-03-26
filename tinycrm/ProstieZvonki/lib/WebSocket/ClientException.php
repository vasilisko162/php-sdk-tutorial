<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Exception.php';

/**
 * Exceptions which happens in Client.
 * Use thi class as basic for all Exception classes related with Client
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class ClientException extends Exception
{
	const OUTCOMING_MESSAGE_IS_INVALID = 1;
	const CONNECTION_HAS_CLOSED = 2;
	const RECIEVE_MESSAGE_INTERRUPTION = 3;
	
	/**
	 * Happens when outcoming message exception catched while sendMessage call
	 * @param	string	$comment
	 * @return	array
	 */
	public static function outcomingMessageIsInvalid($comment)
	{
		return array ('Outcoming message is invalid. '.$comment, self::OUTCOMING_MESSAGE_IS_INVALID);
	}
	
	/**
	 * Happens when connection closed by reason sspecified in comment
	 * @param	string	$comment
	 * @return	array
	 */
	public static function connectionHasClosed($comment)
	{
		return array ('Connection has closed. '.$comment, self::CONNECTION_HAS_CLOSED);
	}
	
	/**
	 * Happens when message reading has interrupted by connection
	 * @return	array
	 */
	public static function recieveMessageInterruption()
	{
		return array ('Interrupt message recieving', self::RECIEVE_MESSAGE_INTERRUPTION);
	}
}