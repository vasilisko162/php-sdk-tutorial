<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Message.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameOutcoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageOutcomingException.php';

/**
 * Class of web-socket message. Every message builes from some frames.
 * Used as wrapper of multiple frames belonging to one message (all frames in message is not final except last).
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class MessageOutcoming extends Message
{
	/**
	 * Constructor
	 * @throws	MessageOutcomingException
	 * @param	string	$data_type
	 * @param	string	$data
	 * @return	void
	 */
	public function __construct($data_type = self::DATA_TYPE_TEXT, $data = '')
	{
		parent::__construct($data_type);
		switch($this->_data_type)
		{
			case self::DATA_TYPE_BINARY:
				$data = str_split($data, FrameOutcoming::PLAYLOAD_LENGTH_OPTIMAL);
				$opcode = FrameOutcoming::OPCODE_BINARY;
				$flags = FrameOutcoming::FLAG_EMPTY;
				break;
			case self::DATA_TYPE_TEXT:
				$data = str_split($data, FrameOutcoming::PLAYLOAD_LENGTH_OPTIMAL);
				$opcode = FrameOutcoming::OPCODE_TEXT;
				$flags = FrameOutcoming::FLAG_EMPTY;
				break;
			default:
				throw new MessageOutcomingException(MessageOutcomingException::invalidDataType($this->_data_type));
				break;
		}
		$flags = $flags &~ FrameOutcoming::FLAG_FIN;
		while(($data_item = array_shift($data)) != null)
		{
			if (count($data) === 0)
				$flags = $flags | FrameOutcoming::FLAG_FIN;
			
			$this->_frames_chain[] = new FrameOutcoming($data_item, $opcode, $flags);
		}
	}
	
	/**
	 * Add payload data to message
	 * @throws MessageOutcomingException
	 * @param	string	$payload_data
	 * @return	void
	 */
	public function addData($data)
	{
		if (strlen($data) == 0)
			return;
		
		$first_frame = & $this->_frames_chain[0];
		$last_frame = & $this->_frames_chain[count($this->_frames_chain) - 1];
		$last_payload_length = $last_frame->getPayloadLength();
		if ($last_payload_length < FrameOutcoming::PLAYLOAD_LENGTH_OPTIMAL)
		{
			$last_frame->appendPayloadData(substr($data, 0, FrameOutcoming::PLAYLOAD_LENGTH_OPTIMAL - $last_payload_length));
			$data = substr($data, FrameOutcoming::PLAYLOAD_LENGTH_OPTIMAL - $last_payload_length);
			if ($data === false)
				return;
		}
		$opcode = FrameOutcoming::OPCODE_CONTINUATION;
		$flags = $last_frame->getFlags();
		$this->_frames_chain[] = new FrameOutcoming($data, $opcode, $flags);
		$last_frame->setFlags($flags &~ FrameOutcoming::FLAG_FIN);
	}
}