<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageException.php';

/**
 * Exceptions which happens in MessageIncoming.
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class MessageIncomingException extends MessageException
{
	const CANT_ADD_FRAME_TO_READY_MESSAGE = 101;
	const CONTINUATION_FRAME_FIRST_IN_CHAIN = 102;
	const FRAME_WITH_INVALID_OPCODE = 103;
	
	/**
	 * Happens when you try to add frame to message with complete frames set.
	 * @return	array
	 */
	public static function cantAddFrameToReadyMessage()
	{
		return array('Can\'t add frame to message. Message ready and has all needed frames.', self::CANT_ADD_FRAME_TO_READY_MESSAGE);	
	}
	
	/**
	 * Happens, when 
	 */
	public static function firstFrameMustBeNonContinuation()
	{
		return array('Continuation frame can\'t be first in message frames chain.', self::CONTINUATION_FRAME_FIRST_IN_CHAIN);
	}
	
	/**
	 * Happens, when try to create message, which must sonsists of only one frame (such as PING, PONG, CONNECTION CLOSE)
	 * with more then one frame
	 * @return	
	 */
	public static function nonFirstFrameMustBeContinuation($opcode)
	{
		return array('Second and next frames of message must be with CONTINUATION ('.FrameIncoming::OPCODE_CONTINUATION.') opcode, not '.$opcode);
	}
	
	/**
	 * Happens, when try to add frame with invalid (control) opcode to message
	 * @param	integer	$opcode
	 * @return	array
	 */
	public static function frameWithInvalidOpcode($opcode)
	{
		return array(
			'Control frame with opcode '.$opcode.' can\'t be a part of message. '.
			'Valid opcodes are TEXT ('.Frame::OPCODE_TEXT.'), BINARY ('.Frame::OPCODE_BINARY.') and '.
			'CONTINUATION ('.Frame::OPCODE_CONTINUATION.') opcodes are valid for message', 
			self::FRAME_WITH_INVALID_OPCODE
		);
	}
}