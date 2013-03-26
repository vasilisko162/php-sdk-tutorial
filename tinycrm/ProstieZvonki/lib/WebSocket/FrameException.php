<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Exception.php';

/**
 * Exceptions which happens in Frame.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class FrameException extends Exception
{
	const NOT_READY = 1;
	const INVALID_OPCODE = 2;
	const INVALID_HEADERS = 3;
	
	/**
	 * Happens when try to perfome action which assumes that frame is ready
	 * @param	integer	$opcode
	 * @return	array
	 */
	public static function notReady()
	{
		return array('Frame not ready', self::NOT_READY);
	}
	
	/**
	 * Happens when OPCODE is not valid, 
	 * @see Frame::OPCODE_* constants for more details
	 * @param	integer	$opcode
	 * @return	array
	 */
	public static function invalidOpcode($opcode)
	{
		return array('Invalid opcode '.$opcode, self::INVALID_OPCODE);
	}
	
	/**
	 * Happens when some of headers frame is invalid 
	 * @return	array
	 */
	public static function invalidHeaders($fin, $rsv1, $rsv2, $rsv3, $opcode, $mask, $payload_length, $masking_key)
	{
		return array('Some of frame headers in invalid, '.
			'FIN '.var_export($fin, true).', '.
			'RSV1 '.var_export($rsv1, true).', '.
			'RSV2 '.var_export($rsv2, true).', '.
			'RSV3 '.var_export($rsv3, true).', '.
			'OPCODE '.var_export($opcode, true).', '.
			'MASK '.var_export($mask, true).', '.
			'PLAYLOAD LENGTH '.var_export($payload_length, true).', '.
			'MASKING KEY '.var_export($masking_key, true), self::INVALID_HEADERS);
	}
}