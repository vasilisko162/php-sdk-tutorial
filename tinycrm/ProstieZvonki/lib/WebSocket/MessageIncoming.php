<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Message.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameIncoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageIncomingException.php';

/**
 * Class of web-socket message. Every message builes from some frames.
 * Used as wrapper of multiple frames belonging to one message (all frames in message is not final except last).
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class MessageIncoming extends Message
{
	/**
	 * Constructor
	 * @throws	MessageIncomingException
	 * @param	FrameIncoming	$frame
	 * @return	void
	 */
	public function __construct($data_type = self::DATA_TYPE_UNDEFINED, FrameIncoming $frame = null)
	{
		if ($data_type instanceof FrameIncoming)
		{
			$frame = $data_type;
			$data_type = self::DATA_TYPE_UNDEFINED;
		}
		parent::__construct($data_type);
		
		if ($frame !== null)
			$this->addFrame($frame);
	}
	
	/**
	 * Add frame to message. It is impossible to add frames to ready messages.
	 * @throws	MessageIncomingException
	 * @param	Frame $frame
	 * @return	void
	 */
	public function addFrame(FrameIncoming $frame)
	{
		if ($this->isReady())
			throw new MessageIncomingException(MessageIncomingException::cantAddFrameToReadyMessage());
		
		switch($frame->getOpcode())
		{
			case FrameIncoming::OPCODE_CONTINUATION:
				if (count($this->_frames_chain) === 0)
					throw new MessageIncomingException(MessageIncomingException::firstFrameMustBeNonContinuation());
				break;
			case FrameIncoming::OPCODE_PING:
			case FrameIncoming::OPCODE_PONG:
			case FrameIncoming::OPCODE_CONNECTION_CLOSE:
				if (count($this->_frames_chain) > 0)
					throw new MessageIncomingException(MessageIncomingException::frameWithInvalidOpcode());
				break;
			case FrameIncoming::OPCODE_TEXT:
				if (count($this->_frames_chain) > 0)
					throw new MessageIncomingException(MessageIncomingException::nonFirstFrameMustBeContinuation());
				
				$this->_data_type = self::DATA_TYPE_TEXT;
				break;
			case FrameIncoming::OPCODE_BINARY:
				if (count($this->_frames_chain) > 0)
					throw new MessageIncomingException(MessageIncomingException::nonFirstFrameMustBeContinuation());
				
				$this->_data_type = self::DATA_TYPE_BINARY;
				break;
		}
		$this->_frames_chain[] = $frame;
	}
}