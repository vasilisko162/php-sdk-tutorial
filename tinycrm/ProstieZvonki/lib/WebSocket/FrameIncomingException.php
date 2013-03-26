<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameException.php';

/**
 * Exceptions which happens in FrameIncoming.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class FrameIncomingException extends FrameException
{
	const EMPTY_FRAME_RAW = 101;
	const APPEND_FRAME_RAW_FAILED = 102;
	const CANT_POPULATE_FROM_FRAME_RAW = 103;
	
	/**
	 * Happens when frame raw must be defined, but not yet
	 * @return	array
	 */
	public static function emptyFrameRaw()
	{
		return array('Frame raw is empty', self::EMPTY_FRAME_RAW);
	}
	
	/**
	 * Happens when attempting to append raw data to ready frame
	 * @return	array
	 */
	public static function appendFrameRawFailed()
	{
		return array('Can\'t append frame raw data, because frame is ready.', self::APPEND_FRAME_RAW_FAILED);
	}
	
	/**
	 * Happens when frame raw not enougth to populate some frame fields 
	 * @return	array
	 */
	public static function cantPopulateFromFrameRaw()
	{
		return array('Can\'t populate frame next fields from raw data: '.implode(', ', func_get_args()), self::CANT_POPULATE_FROM_FRAME_RAW);
	}
}