<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Exception.php';

/**
 * Exceptions which happens in Message.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class MessageException extends Exception
{
	const MESSAGE_NOT_READY_YET = 1;
	const INVALID_DATA_TYPE = 2;
	
	/**
	 * Happens when you try to access message data of message with empty or not complete frames set.
	 * @return	array
	 */
	public static function messageNotReadyYet()
	{
		return array('Message is not ready yet.', self::MESSAGE_NOT_READY_YET);
	}
	
	/**
	 * Happens when trying cteare message with wrong opcode
	 * @param	integer	$opcode
	 */
	public static function invalidDataType($data_type)
	{
		return array('Invalid opcode '.$data_type, self::INVALID_DATA_TYPE);
	}
}