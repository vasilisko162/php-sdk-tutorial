<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameException.php';

/**
 * Exceptions which happens in FrameOutcoming.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class FrameOutcomingException extends FrameException
{
	const PLAYLOAD_MAX_SIZE_EXCCEEDED = 201;
	const EMPTY_PLAYLOAD_DATA = 202;
	
	/**
	 * Happens when payload max size exeeded
	 * @param	integer	$data_length
	 * @param	integer	$max_data_length
	 * @return	array
	 */
	public static function payloadMaxSizeExcceeded($data_length, $max_data_length)
	{
		return array('Payload data is too long, current length: '.$data_length.', and max length is '.$max_data_length, self::PLAYLOAD_MAX_SIZE_EXCCEEDED);
	}
	
	/**
	 * Happens when payload data must be defined, but not yet
	 * @return	array
	 */
	public static function emptyPayloadData()
	{
		return array('Payload data is empty', self::EMPTY_PLAYLOAD_DATA);
	}
}