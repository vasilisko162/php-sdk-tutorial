<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Frame.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageException.php';

/**
 * Class of web-socket message. Every message builes from some frames.
 * Used as wrapper of multiple frames belonging to one message (all frames in message is not final except last).
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
abstract class Message
{
	/**
	 * Indicated message with undefined data type
	 * @var integer
	 */
	const DATA_TYPE_UNDEFINED = 0;
	
	/**
	 * Indicated message with binary data type
	 * @var integer
	 */
	const DATA_TYPE_BINARY = 1;
	
	/**
	 * Indicated message with textual data type
	 * @var integer
	 */
	const DATA_TYPE_TEXT = 2;
	
	/**
	 * Contains message frames chain
	 * @var array
	 */
	protected $_frames_chain;
	
	/**
	 * Message data type,
	 * @see	self::DATA_TYPE_* constants
	 * @var integer
	 */
	protected $_data_type;
	
	/**
	 * Constructor
	 * @param	integer	$data_type
	 * @return	void
	 */
	public function __construct($data_type = self::DATA_TYPE_UNDEFINED)
	{
		$this->_frames_chain = array();
		$this->_data_type = $data_type;
	}
	
	/**
	 * Tell us whehter message ready or not
	 * @return	boolean
	 */
	public function isReady()
	{
		// empty chain 
		if (count($this->_frames_chain) === 0)
			return false;
		
		// one of frames is not ready
		foreach ($this->_frames_chain as $index => $frame)
			if (!$frame->isReady())
				return false;
		
		// last frame is not final
		if (!$this->_frames_chain[count($this->_frames_chain) - 1]->isFinal())
			return false;
		
		return true;
	}
	
	/**
	 * Returns message data type
	 * @return	integer
	 */
	public function getDataType()
	{
		return $this->_data_type;
	}
	
	/**
	 * Returns all payload data of message in the order of chain.
	 * @throws	MessageException
	 * @return	string
	 */
	public function getData()
	{
		if (!$this->isReady())
			throw new MessageException(MessageException::messageNotReadyYet());
		
		$data = '';
		foreach ($this->_frames_chain as $frame)
		{
			$data .= $frame->getPayloadData();
		}
		return $data;
	}
	
	/**
	 * Returns chain of message frames
	 * @throws	MessageException
	 * @return	array
	 */
	public function getFramesChain()
	{
		if (!$this->isReady())
			throw new MessageException(MessageException::messageNotReadyYet());

		return $this->_frames_chain;
	}
}