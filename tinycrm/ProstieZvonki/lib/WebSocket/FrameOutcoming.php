<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Frame.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameIncoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameOutcomingException.php';

/**
 * Class of web-socket frame, for more details about framing in web-socket 
 * @see http://tools.ietf.org/html/rfc6455#section-5.2
 * 
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class FrameOutcoming extends Frame
{
	/**
	 * Constructor, in case frame with empty payload data 
	 * set $payload_data to empty string
	 * @throws	FrameOutcomingException
	 * @param	string	$payload_data
	 * @param	integer	$opcode
	 * @param	integer	$flags
	 * @return	void
	 */
	public function __construct($payload_data, $opcode, $flags)
	{
		parent::__construct();
		$this->_fin = ($flags & self::FLAG_FIN) === self::FLAG_FIN;
		$this->_rsv1 = ($flags & self::FLAG_RSV1) === self::FLAG_RSV1;
		$this->_rsv2 = ($flags & self::FLAG_RSV2) === self::FLAG_RSV2;
		$this->_rsv3 = ($flags & self::FLAG_RSV3) === self::FLAG_RSV3;
		$this->_opcode = $opcode;
		$this->_mask = ($flags & self::FLAG_MASK) === self::FLAG_MASK;
		if ($this->_mask)
			$this->_masking_key = 
				(mt_rand(0, 255) * pow(2, 24)) + 
				(mt_rand(0, 255) * pow(2, 16)) + 
				(mt_rand(0, 255) * pow(2, 8)) + 
				 mt_rand(0, 255);
		else 
			$this->_masking_key = 0;
		
		$this->_payload_data = $payload_data;
		$this->_payload_length = strlen($this->_payload_data);
		
		if ($this->_payload_length > self::PLAYLOAD_LENGTH_MAX)
			throw new FrameOutcomingException(FrameOutcomingException::payloadMaxSizeExcceeded($this->_payload_length, self::PLAYLOAD_LENGTH_MAX));
	
		if (!self::isValidOpcode($this->_opcode))
			throw new FrameOutcomingException(FrameIncomingException::invalidOpcode($this->_opcode));
		
		if (!$this->isValidHeaders())
			throw new FrameOutcomingException(FrameIncomingException::invalidHeaders(
				$this->_fin, $this->_rsv1, $this->_rsv2, $this->_rsv3,
				$this->_opcode, $this->_mask, $this->_payload_length, $this->_masking_key
			));
	}
	
	/**
	 * Create incoming frame with same fields as current frame
	 * @throws	FrameOutcomingException
	 * @throws	FrameIncomingException
	 * @return	FrameIncoming
	 */
	public function convertToFrameIncoming()
	{
		if (!$this->isReady())
			throw new FrameOutcomingException(FrameOutcomingException::notReady());
		
		return new FrameIncoming($this->_frame_raw);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see WebSocket.Frame::getFrameRaw()
	 * @throws	FrameOutcomingException
	 */
	public function getFrameRaw()
	{
		$this->populateFrameRaw();
		return parent::getFrameRaw();
	}
	
	/**
	 * Updates frame flags, to unset flag use ~ binary operator
	 * @param	integer	$flags
	 * @return	void
	 */
	public function setFlags($flags)
	{
		$this->_fin = ($flags & self::FLAG_FIN) === self::FLAG_FIN;
		$this->_rsv1 = ($flags & self::FLAG_RSV1) === self::FLAG_RSV1;
		$this->_rsv2 = ($flags & self::FLAG_RSV2) === self::FLAG_RSV2;
		$this->_rsv3 = ($flags & self::FLAG_RSV3) === self::FLAG_RSV3;
	}
	
	/**
	 * Appends payload data to frame
	 * @throws	FrameOutcomingException
	 * @param	string	$payload_data
	 * @return	void
	 */
	public function appendPayloadData($payload_data)
	{
		if (($this->_payload_length + strlen($payload_data)) > self::PLAYLOAD_LENGTH_MAX)
			throw new FrameOutcomingException(FrameOutcomingException::payloadMaxSizeExcceeded($this->_payload_length + strlen($payload_data), self::PLAYLOAD_LENGTH_MAX));
		
		$this->_payload_data .= $payload_data;
		$this->_payload_length += strlen($payload_data);
	}
	
	/**
	 * Builds frame raw from payload data
	 * @throws	FrameOutcomingException
	 * @return	void
	 */
	protected function populateFrameRaw()
	{
		if (!isset($this->_payload_data))
			throw new FrameOutcomingException(FrameOutcomingException::emptyPayloadData());
		
		$this->_frame_raw = '';
		
		$byte = ($this->isFinal() ? 128 : 0) + (255 & $this->_opcode);
		$this->_frame_raw .= chr($byte);
		$this->_frame_raw .= $this->getFrameRawPayloadLengthAndMaskBytes();
		if ($this->isMasked())
		{
			$this->_frame_raw .= self::encodeLongInt($this->_masking_key, 4);
			$this->_frame_raw .= self::maskPayloadData($this->_payload_data, $this->_masking_key);
		}
		else
		{
			$this->_frame_raw .= $this->_payload_data;
		}
	}
	
	/**
	 * Returns raw necoded frame header bytes 
	 * which contains payload length and MASK flag
	 * @return	string
	 */
	protected function getFrameRawPayloadLengthAndMaskBytes()
	{
		switch (true)
		{
			case $this->_payload_length <= 125:
				return chr(($this->isMasked() ? 128 : 0) + $this->_payload_length);
				break;
			case $this->_payload_length <= 65535:
				return chr(($this->isMasked() ? 128 : 0) + 126).self::encodeLongInt($this->_payload_length, 2);
				break;
			default:
				return chr(($this->isMasked() ? 128 : 0) + 127).self::encodeLongInt($this->_payload_length, 8);
				break;
		}
	}
	
	/**
	 * Masks payload data
	 * @param	string	$payload_data
	 * @param	integer	$masking_key
	 * @return	string
	 */
	protected static function maskPayloadData($payload_data, $masking_key, $offset = 0)
	{
		$masking_bytes = array(
			 $masking_key &  255,
			($masking_key & (255 << 8))  >> 8,
			($masking_key & (255 << 16)) >> 16,
			($masking_key & (255 << 24)) >> 24,
		);
		$payload_length = strlen($payload_data);
		$offset = $offset % 4;
		for ($i = 0; $i < $payload_length; $i++)
		{
			$payload_data[$i] = chr(ord($payload_data[$i]) ^ $masking_bytes[($offset + $i) % 4]);
		}
		return $payload_data;
	}
	
	/**
	 * Encode long integer to ASCII string representation with length equal to $length
	 * @param	integer	$int (or float in case of large number)
	 * @param	integer	$length
	 * @return	string
	 */
	protected static function encodeLongInt($int, $length)
	{
		// convert int to float to avoid wring 
		// encoding of negative values (see at / operation)
		$int = $int * 1.0;
		$str = '';
		for ($i = 0; $i < $length; $i++)
		{
			$str = chr($int & 255).$str;
			$int = $int > 255 ? $int / 256 : 0;
		}
		return $str;
	}
}