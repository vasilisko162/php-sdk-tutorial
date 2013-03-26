<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'FrameException.php';

/**
 * Class of web-socket frame, for more details about framing in web-socket 
 * @see http://tools.ietf.org/html/rfc6455#section-5.2
 * 
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
abstract class Frame
{
	/**
	 * Frame header flags: FIN, RSV1, RSV2, RSV3, MASK
	 */
	/**
	 * Empty flag, used for suppress frame flags
	 * @var integer
	 */
	const FLAG_EMPTY = 0;
	
	/**
	 * This flag force frame is final
	 * @var integer
	 */
	const FLAG_FIN = 1;
	
	/**
	 * Set first reserved bit to 1
	 * @var integer
	 */
	const FLAG_RSV1 = 2;
	
	/**
	 * Set second reserved bit to 1
	 * @var integer
	 */
	const FLAG_RSV2 = 4;
	
	/**
	 * Set third reserved bit to 1
	 * @var integer
	 */
	const FLAG_RSV3 = 8;
	
	/**
	 * Set mask to 1, that means frame payload data will be masked in raw frame data
	 * @var integer
	 */
	const FLAG_MASK = 16;
	
	/**
	 * Opcodes
	 * %x3-7 are reserved for further non-control frames
	 * %xB-F are reserved for further control frames,
	 * @see http://tools.ietf.org/html/rfc6455#section-5.2
	 */
	/**
	 * %x0 denotes a continuation frame
	 * @var integer
	 */
	const OPCODE_CONTINUATION = 0x0;
	
	/**
	 * %x1 denotes a text frame
	 * @var integer
	 */
	const OPCODE_TEXT = 0x1;
	
	/**
	 * %x2 denotes a binary frame
	 * @var integer
	 */
	const OPCODE_BINARY = 0x2;
	
	/**
	 * %x8 denotes a connection close
	 * @var integer
	 */
	const OPCODE_CONNECTION_CLOSE = 0x8;
	
	/**
	 * %x9 denotes a ping
	 * @var integer
	 */
	const OPCODE_PING = 0x9;
	
	/**
	 *  %xA denotes a pong
	 * @var integer
	 */
	const OPCODE_PONG = 0xa;
	
	/**
	 * Max payload length defined by OS & PHP limitations
	 * @var integer
	 */
	const PLAYLOAD_LENGTH_MAX = PHP_INT_MAX;
	
	/**
	 * Optimal payload length, 32KB
	 * @var integer
	 */
	const PLAYLOAD_LENGTH_OPTIMAL = 32767;
	
	/**
	 * Web-socket protocol frame raw data, represeted as binary string
	 * @var string
	 */
	protected $_frame_raw;
	
	/**
	 * Is final frame of message
	 * @var boolean
	 */
	protected $_fin;
	
	/**
	 * First reserved bit
	 * @var boolean
	 */
	protected $_rsv1;
	
	/**
	 * Second reserved bit
	 * @var boolean
	 */
	protected $_rsv2;
	
	/**
	 * Third reserved bit
	 * @var boolean
	 */
	protected $_rsv3;
	
	/**
	 * Opcode of frame, 
	 * @see self::OPCODE_* constants
	 * @var integer
	 */
	protected $_opcode;
	
	/**
	 * Use frame masking or not?
	 * @var boolean
	 */
	protected $_mask;
	
	/**
	 * Payload length, due to RFC6455 max packet length is an 64bit unsigned int,
	 * but there is only 32 bit length on PHP at Windows
	 * @var integer
	 */
	protected $_payload_length;
	
	/**
	 * Key used for masking (or demasking), affects only if $_mask is setted to true
	 * @var integer
	 */
	protected $_masking_key;
	
	/**
	 * Frame payload data
	 * @var string
	 */
	protected $_payload_data;
	
	/**
	 * Constructor
	 * @return	void
	 */
	public function __construct()
	{
		
	}
	
	/**
	 * Tell us if frame is ready or not
	 * @return	boolean
	 */
	public function isReady()
	{
		return (
			$this->isValidHeaders() && 
			isset($this->_payload_data) &&
			($this->_payload_length === strlen($this->_payload_data))
		);
	}
	
	/**
	 * Check whether frame headers is valid or not
	 * @return	boolean
	 */
	public function isValidHeaders()
	{
		return (
			// bit 1
			is_bool($this->_fin) &&
			// bit 2-4, all reserved bits can't be set to 1
			is_bool($this->_rsv1) && $this->_rsv1 === false && 
			is_bool($this->_rsv2) && $this->_rsv2 === false && 
			is_bool($this->_rsv3) && $this->_rsv3 === false &&
			// bits 5-8
			self::isValidOpcode($this->_opcode) &&
			// bit 9
			is_bool($this->_mask) &&
			// bits 10-16 or 10-32 or 10-80
			(is_integer($this->_payload_length) || is_float($this->_payload_length)) &&
			// length must be less or equal to self::PLAYLOAD_LENGTH_MAX
			$this->_payload_length <= self::PLAYLOAD_LENGTH_MAX && 
			// 0 or 4 bits after, zero value in case mask not used
			(is_integer($this->_masking_key) || is_float($this->_masking_key)) && 
			// if mask not used, so masking key must be zero value
			($this->_mask === true || $this->_masking_key === 0)
		);
	}
	
	/**
	 * Check whether opcode is valid or not
	 * @param	integer	$opcode
	 * @return	boolean
	 */
	public static function isValidOpcode($opcode)
	{
		return (
			$opcode === self::OPCODE_TEXT ||
			$opcode === self::OPCODE_BINARY ||
			$opcode === self::OPCODE_CONTINUATION ||
			$opcode === self::OPCODE_PING ||
			$opcode === self::OPCODE_PONG ||
			$opcode === self::OPCODE_CONNECTION_CLOSE
		);
	}
	
	/**
	 * Tell us whether frmame is final or not
	 * @return	void
	 */
	public function isFinal()
	{
		return ($this->_fin && true);
	}
	
	/**
	 * Tell us whether frmame using masking or not
	 * @return	void
	 */
	public function isMasked()
	{
		return ($this->_mask && true);
	}
	
	/**
	 * Returns all flags as integer
	 * @return	integer
	 */
	public function getFlags()
	{
		$flags = self::FLAG_EMPTY;
		if (!empty($this->_fin))
			$flags = $flags | self::FLAG_FIN;
		if (!empty($this->_rsv1))
			$flags = $flags | self::FLAG_RSV1;
		if (!empty($this->_rsv2))
			$flags = $flags | self::FLAG_RSV2;
		if (!empty($this->_rsv3))
			$flags = $flags | self::FLAG_RSV3;
		if (!empty($this->_mask))
			$flags = $flags | self::FLAG_MASK;
		return $flags;
	}
	
	/**
	 * Tell us whether frmame using masking or not
	 * @return	void
	 */
	public function getOpcode()
	{
		return $this->_opcode;
	}
	
	/**
	 * Returns frame payload data, if frame was created from frame raw, so it will be decoded
	 * @see http://tools.ietf.org/html/rfc6455#section-5.2 for more details about web-socket frame
	 * @return	string
	 */
	public function getPayloadData()
	{
		return $this->_payload_data;
	}
	
	/**
	 * Returns frame payload length
	 * @return	integer
	 */
	public function getPayloadLength()
	{
		return $this->_payload_length;
	}
	
	/**
	 * Returns frame raw data
	 * @return string
	 */
	public function getFrameRaw()
	{
		return $this->_frame_raw;
	}
}