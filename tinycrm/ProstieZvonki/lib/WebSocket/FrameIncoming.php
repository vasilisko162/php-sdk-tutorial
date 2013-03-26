<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Frame.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameOutcoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameIncomingException.php';

/**
 * Class of web-socket frame, for more details about framing in web-socket 
 * @see http://tools.ietf.org/html/rfc6455#section-5.2
 * 
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
class FrameIncoming extends Frame
{
	/**
	 * Build chain of frames to send payload to remote client.
	 * Each frame in set contains maximum of payload data, but not more then self::PLAYLOAD_LENGTH_MAX
	 * @throws	FrameIncomingException
	 * @param	string	$frame_raw
	 * @return	void
	 */
	public function __construct($frame_raw = null)
	{
		parent::__construct();
		if ($frame_raw !== null)
			$this->appendFrameRaw($frame_raw);
	}
	
	/**
	 * Create outcoming frame with same fields as current frame
	 * @throws	FrameIncomingException
	 * @throws	FrameOutcomingException
	 * @return	FrameOutcoming
	 */
	public function convertToFrameOutcoming()
	{
		if (!$this->isReady())
			throw new FrameIncomingException(FrameIncomingException::notReady());
		
		$flags = self::FLAG_EMPTY;
		if ($this->isFinal())
			$flags = $flags | self::FLAG_FIN;
		if ($this->isMasked())
			$flags = $flags | self::FLAG_MASK;
		if (!empty($this->_rsv1))
			$flags = $flags | self::FLAG_RSV1;
		if (!empty($this->_rsv2))
			$flags = $flags | self::FLAG_RSV2;
		if (!empty($this->_rsv3))
			$flags = $flags | self::FLAG_RSV3;
		return new FrameOutcoming($this->_payload_data, $this->_opcode, $flags);
	}
	
	/**
	 * This method can be used only for non ready frames, 
	 * which was created from frame fragment. 
	 * Only missing raw data part wil appended
	 * Non appended part of passed payload will returned.
	 * @throws	FrameIncomingException
	 * @param	string	$frame_raw
	 * @return	string
	 */
	public function appendFrameRaw($frame_raw)
	{
		if ($this->isReady())
			throw new FrameIncomingException(FrameIncomingException::appendFrameRawFailed());
		
		// try to populate frame headers from raw
		if ($this->isValidHeaders() === false)
		{
			try
			{
				// initialize frame raw
				if (!isset($this->_frame_raw))
					$this->_frame_raw = '';
				
				// append frame raw
				$this->_frame_raw .= $frame_raw;
				$frame_raw = $this->populateFromFrameRaw();
			}
			catch (FrameIncomingException $e)
			{
				// throw fatal exception to upper level
				if ($e->getCode() !== FrameIncomingException::CANT_POPULATE_FROM_FRAME_RAW)
					throw $e;
				
				$frame_raw = '';
			}
		}
		elseif (($frame_raw_length = strlen($frame_raw)) > 0)
		{
			if (!isset($this->_payload_data))
				$this->_payload_data = '';
			
			$payload_missing_bytes = $this->_payload_length - strlen($this->_payload_data);
			$use_frame_raw_bytes = min($frame_raw_length, $payload_missing_bytes);
			$this->_payload_data .= $this->isMasked()
					? self::unmaskPayloadData(substr($frame_raw, 0, $use_frame_raw_bytes), $this->_masking_key)
					: substr($frame_raw, 0, $use_frame_raw_bytes);
			
			// append frame raw
			$this->_frame_raw .= substr($frame_raw, 0, $use_frame_raw_bytes);
			
			if ($frame_raw_length > $use_frame_raw_bytes)
				$frame_raw = substr($frame_raw, $use_frame_raw_bytes);
			else
				$frame_raw = '';
		}
		return $frame_raw;
	}
	
	/**
	 * Builds payload data from frame raw.
	 * Remains of frame raw data will be assigned to $frame_raw_remains
	 * @throws	FrameIncomingException
	 * @param	string	$frame_raw_remain
	 * @return	void
	 */
	protected function populateFromFrameRaw()
	{
		if (!isset($this->_frame_raw))
			throw new FrameIncomingException(FrameIncomingException::emptyFrameRaw());
		
		$frame_raw_offset = 0;
		
		// verify frame raw length
		if (strlen($this->_frame_raw) < ($frame_raw_offset + 1))
			throw new FrameIncomingException(FrameIncomingException::cantPopulateFromFrameRaw('fin', 'rsv1', 'rsv2', 'rsv3', 'opcode'));
		
		// extract data from first frame
		$byte = ord(substr($this->_frame_raw, $frame_raw_offset++, 1));
		$this->_fin = ($byte & 128) === 128;
		$this->_rsv1 = ($byte & 64) === 64;
		$this->_rsv2 = ($byte & 32) === 32;
		$this->_rsv3 = ($byte & 16) === 16;
		$this->_opcode = $byte & 15;
		
		if (!self::isValidOpcode($this->_opcode))
			throw new FrameIncomingException(FrameIncomingException::invalidOpcode($this->_opcode));
		
		// verify frame raw length
		if (strlen($this->_frame_raw) < ($frame_raw_offset + 1))
			throw new FrameIncomingException(FrameIncomingException::cantPopulateFromFrameRaw('mask', 'payload length'));
		
		// extract data from second frame
		$byte = ord(substr($this->_frame_raw, $frame_raw_offset++, 1));
		$this->_mask = ($byte & 128) === 128;
		
		if (!$this->isMasked())
			$this->_masking_key = 0;
		
		// define payload length and payload data and mask offset
		switch($byte & 127)
		{
			// payload data length in 17-96 bits (8 bytes from third) of frame
			case 127:
				// verify frame raw length
				if (strlen($this->_frame_raw) < ($frame_raw_offset + 8))
					throw new FrameIncomingException(FrameIncomingException::cantPopulateFromFrameRaw('payload length'));
					
				$this->_payload_length = self::decodeLongInt(substr($this->_frame_raw, $frame_raw_offset, 8));
				$frame_raw_offset += 8;
				break;
			// payload data length in 17-32 bits (2 bytes from third) bits of frame
			case 126:
				// verify frame raw length
				if (strlen($this->_frame_raw) < ($frame_raw_offset + 2))
					throw new FrameIncomingException(FrameIncomingException::cantPopulateFromFrameRaw('payload length'));
				
				$this->_payload_length = self::decodeLongInt(substr($this->_frame_raw, $frame_raw_offset, 2));
				$frame_raw_offset += 2;
				break;
			default:
				$this->_payload_length = $byte & 127;
				break;
		}
		// if masking enabled masking key is always 4 bytes length
		if ($this->isMasked())
		{
			// verify frame raw length
			if (strlen($this->_frame_raw) < ($frame_raw_offset + 4))
				throw new FrameIncomingException(FrameIncomingException::cantPopulateFromFrameRaw('masking key'));
			
			$this->_masking_key = self::decodeLongInt(substr($this->_frame_raw, $frame_raw_offset, 4));
			$frame_raw_offset += 4;
		}
		if ($this->_payload_length > 0)
		{
			// verify frame raw length
			if (strlen($this->_frame_raw) < ($frame_raw_offset + 1))
				throw new FrameIncomingException(FrameIncomingException::cantPopulateFromFrameRaw('payload data'));
			
			$this->_payload_data = $this->isMasked()
					? self::unmaskPayloadData(substr($this->_frame_raw, $frame_raw_offset, $this->_payload_length), $this->_masking_key)
					: substr($this->_frame_raw, $frame_raw_offset, $this->_payload_length);
		}
		else
		{
			$this->_payload_data = '';
		}
		$frame_raw_offset += strlen($this->_payload_data);	
		// add frame raw remains to $frame_raw_remains var
		if ($frame_raw_offset < strlen($this->_frame_raw))
		{
			$frame_raw_remains = substr($this->_frame_raw, $frame_raw_offset);
			$this->_frame_raw = substr($this->_frame_raw, 0, $frame_raw_offset);
		}
		else
		{
			$frame_raw_remains = '';
		}
		return $frame_raw_remains;
	}
	
	/**
	 * Unmasks payload data
	 * @param	string	$payload_data
	 * @param	integer	$masking_key
	 * @return	string
	 */
	protected static function unmaskPayloadData($payload_data, $masking_key)
	{
		$masking_bytes = array(
			 $masking_key &  255,
			($masking_key & (255 << 8))  >> 8,
			($masking_key & (255 << 16)) >> 16,
			($masking_key & (255 << 24)) >> 24,
		);
		$payload_length = strlen($payload_data);
		for ($i = 0; $i < $payload_length; $i++)
		{
			$payload_data[$i] = chr(ord($payload_data[$i]) ^ $masking_bytes[$i % 4]);
		}
		return $payload_data;
	}
	
	/**
	 * Decode long integer from ASCII string representation to integer
	 * @param	string	$str
	 * @return	integer (or float in case of large number)
	 */
	protected static function decodeLongInt($str)
	{
		$int = 0;
		$length = strlen($str);
		for ($i = 0; $i < $length; $i++)
		{
			$int = 256 * $int + ord($str[$i]);
		}
		return $int;
	}
}