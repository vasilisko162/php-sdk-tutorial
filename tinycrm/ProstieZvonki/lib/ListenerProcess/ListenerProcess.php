<?php
class ListenerProcess
{
	/**
	 * Listener process statuses
	 * @var	string
	 */
	const STATUS_STARTED = 'started';
	const STATUS_ACTIVE = 'active';
	const STATUS_INACTIVE = 'inactive';
	const STATUS_STOPPED = 'stopped';
	
	const PROCESS_INFO_KEY_ID = 0;
	const PROCESS_INFO_KEY_INTERFACE = 1;
	const PROCESS_INFO_KEY_TIME_START = 2;
	const PROCESS_INFO_KEY_TIME_LAST_ACIVITY = 3;
	const PROCESS_INFO_KEY_TIME_STOP = 4;
	const PROCESS_INFO_KEY_STATUS = 5;
	
	/**
	 * Name of listener process PHP script
	 * @var	string
	 */
	const PHP_SCRIPT = 'listener_process.php';
	
	/**
	 * File, where process info stored
	 * @var	string
	 */
	protected static $_file_name;
	
	/**
	 * Current PHP script
	 * @var	string
	 */
	protected $_php_self;
	
	/**
	 * Process id
	 * @var	string
	 */
	protected $_id;
	
	/**
	 * SIP Interface used in this process
	 * @var	string
	 */
	protected $_interface;
	
	/**
	 * Process start time, unix timestamp
	 * @var	integer
	 */
	protected $_time_start;
	
	/**
	 * Process last activity time, unix timestamp
	 * @var	integer
	 */
	protected $_time_last_acivity;
	
	/**
	 * Process stop time, unix timestamp
	 * @var	integer
	 */
	protected $_time_stop;
	
	/**
	 * Process status
	 * @var	string
	 */
	protected $_status;
	
	/**
	 * Constructor
	 * @return	void
	 */
	public function __construct($data = null)
	{
		$file_name = self::getFileName();
		$this->_php_self = $_SERVER['PHP_SELF'];
		if ($data !== null)
		{
			$this->_id = $data[self::PROCESS_INFO_KEY_ID];
			$this->_interface = $data[self::PROCESS_INFO_KEY_INTERFACE];
			$this->_time_start = $data[self::PROCESS_INFO_KEY_TIME_START];
			$this->_time_last_acivity = $data[self::PROCESS_INFO_KEY_TIME_LAST_ACIVITY];
			$this->_time_stop = !empty($data[self::PROCESS_INFO_KEY_TIME_STOP]) ? $data[self::PROCESS_INFO_KEY_TIME_STOP] : null;
			$this->_status = $data[self::PROCESS_INFO_KEY_STATUS];
		}
		else
		{
			$this->_id = strval(getmypid());
			$this->_interface = 'cti';
			$this->_time_start = time();
			$this->_time_last_acivity = $this->_time_start;
			$this->_time_stop = null;
			$this->_status = self::STATUS_STARTED;
		}
		$this->save();
	}
	
	/**
	 * Destructor
	 * @return	void
	 */
	public function __destruct()
	{
		$this->stop();
	}
	
	/**
	 * Access protected prpperties of process
	 * @param unknown_type $attr
	 */
	public function __get($attr)
	{
		$attr = '_'.$attr;
		return $this->$attr;
	}
	
	/**
	 * Change object properties on script stopping
	 * @return	void
	 */
	public function stop()
	{
		$this->_status = self::STATUS_STOPPED;
		$this->_time_stop = time();
		if ($this->verify())
			$this->save();
	}
	
	/**
	 * Save process information to file
	 * @return	boolean
	 */
	public function save()
	{
		// do not save process info to file, because process launched not as daemon
		if ($this->_php_self !== self::PHP_SCRIPT)
		{
			return true;
		}
		else
		{
			return (file_put_contents(self::getFileName(), implode(', ', array (
				self::PROCESS_INFO_KEY_ID => $this->_id,
				self::PROCESS_INFO_KEY_INTERFACE => $this->_interface,
				self::PROCESS_INFO_KEY_TIME_START => $this->_time_start,
				self::PROCESS_INFO_KEY_TIME_LAST_ACIVITY => $this->_time_last_acivity,
				self::PROCESS_INFO_KEY_TIME_STOP => $this->_time_stop,
				self::PROCESS_INFO_KEY_STATUS => $this->_status
			))) > 0);	
		}
	}
	
	/**
	 * Verify current process with process, ehich data stored in file
	 * @return boolean
	 */
	public function verify()
	{
		// verification always TRUE, wneh process launched not as daemon
		if ($this->_php_self !== self::PHP_SCRIPT)
		{
			return true;
		}
		else
		{
			$data = self::loadCurrentProcessInfo();
			return ($data !== null && strcmp($data[self::PROCESS_INFO_KEY_ID], $this->_id) === 0);
		}
	}
	
	/**
	 * Update process status
	 * @param	string	$status
	 * @return	boolean
	 */
	public function updateStatus($status)
	{
		if ($this->verify())
		{
			$this->_time_last_acivity = time();
			$this->_status = $status;
			if ($this->_status === self::STATUS_STOPPED)
				$this->_time_stop = $this->_time_last_acivity;
			
			return $this->save();
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Update last activity time
	 * @return	boolean
	 */
	public function updateTimeLastActivity()
	{
		if ($this->verify())
		{
			$this->_time_last_acivity = time();
			return $this->save();
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Check whether process, which data currently stored in file as alive or not
	 * @return	boolean
	 */
	public static function isCurrentProcessRunning($any_interface = false)
	{
		$data = self::loadCurrentProcessInfo();
		
		if ($data === null)
			return false;
		
		// other interface used by currently running script
		if (!$any_interface && $data[self::PROCESS_INFO_KEY_INTERFACE] !== 'cti')
			return false;
		
		if ($data[self::PROCESS_INFO_KEY_STATUS] === self::STATUS_STOPPED)
			return false;
			
		if (self::isWindows())
		{
			$regexp = '/^\s*php\.exe\s+'.$data[self::PROCESS_INFO_KEY_ID].'\s/i';
			$cmd = 'tasklist';
		}
		else
		{
			$regexp = '/^\s*'.$data[self::PROCESS_INFO_KEY_ID].'\s.*\sphp\s*$/i';
			$cmd = 'ps | grep '.$data[self::PROCESS_INFO_KEY_ID];
		}
		exec($cmd, $output);
		
		// search for active script process
		foreach($output as $task)
			if (preg_match($regexp, $task) > 0)
				return true;
		
		return false;
	}
	
	/**
	 * Launch new listener process
	 * @return	boolean
	 */
	public static function processLaunch()
	{
		$php_executable = empty($_SERVER['PHPRC'])
				? 'php'
				: $_SERVER['PHPRC'].DIRECTORY_SEPARATOR.'php';
		$script_path = dirname(realpath(__FILE__));
		$script_path = explode(DIRECTORY_SEPARATOR, $script_path);
		$script_path = array_slice($script_path, 0, -2);
		$script_path = implode(DIRECTORY_SEPARATOR, $script_path);
		if (self::isWindows())
		{
			$application_name = 'cti';
			$command = 'start "'.$application_name.'" /D'.escapeshellarg($script_path).' /HIGH '.escapeshellarg($php_executable).' -f '.escapeshellarg(self::PHP_SCRIPT);
			$result =
				false !== ($process = popen($command, 'r')) &&
				false !== pclose($process);
		}
		else
		{
			$current_dir = getcwd();
			chdir($script_path);
			$command = 'nohup '.escapeshellarg($php_executable).' -f '.escapeshellarg(self::PHP_SCRIPT).' 1>/dev/null 2>&1 &';
			$result = 
				false !== ($process = popen($command, 'r')) && 
				false !== pclose($process);
			chdir($current_dir);
		}
		return $result;
	}
	
	/**
	 * Kill currently running process
	 * @return	boolean
	 */
	public static function processKill()
	{
		if (self::isCurrentProcessRunning(true))
		{
			$pid = self::loadCurrentProcessInfo(self::PROCESS_INFO_KEY_ID);
			if (self::isWindows())
				$cmd = 'taskkill /PID '.$pid;
			else
				$cmd = 'kill '.$pid;
			
			exec($cmd, $output, $return_var);
			
			// for windows sometimes its impossible to kill running task
			// so we just remove process info file
			if ($return_var !== 0)
				unlink(self::getFileName());
			
			return true;
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * Returns file name where process information saved
	 * @return	string
	 */
	protected static function getFileName()
	{
		if (!isset(self::$_file_name))
			self::$_file_name = __DIR__ . '/.listener_process';
		return self::$_file_name;
	}
	
	/**
	 * Load saved information from file
	 * @return	array or null on failed to load data
	 */
	public static function loadCurrentProcessInfo($process_info_key = null)
	{
		$file_name = self::getFileName();
		if (!file_exists($file_name) || !is_readable($file_name))
			return null;
		
		$data = file_get_contents($file_name);
		
		if (empty($data))
			return null;
		
		$data = explode(', ', $data);
		
		if (empty($data[self::PROCESS_INFO_KEY_TIME_STOP]))
			$data[self::PROCESS_INFO_KEY_TIME_STOP] = null;
		
		if ($process_info_key === null)
			return $data;
		
		return (isset($data[$process_info_key])
				? $data[$process_info_key]
				: $data);
	}

	protected static function isWindows()
	{
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}
}