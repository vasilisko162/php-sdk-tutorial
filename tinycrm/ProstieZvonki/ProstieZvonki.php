<?php

require_once __DIR__ . '/CTIInterface.php';
require_once __DIR__ . '/lib/ListenerProcess/ListenerProcess.php';
require_once __DIR__ . '/lib/CTI/CTIClient.php';

class ProstieZvonki implements CTIInterface
{
	/** @var string Директория для записи событий сервера */
	private $events_dir;

	/** @var string Директория для записи команд серверу */
	private $commands_dir;

	/** @var string Путь до конфигурационного файлв */
	private $config_file;

	/** @var boolean Переподключаться в случае утери соединения */
	private $reconnect_if_lost_connection = true;

	/** @var CTI ссылка на экземпляр класса */
	private static $instance;

	/**
	 * Получить экземпляр класса
	 * @return CTI
	 */
	public static function getInstance()
	{
		if (!static::$instance)
			$instance = new static;

		return $instance;
	}

	/**
	 * Конструктор.
	 *
	 * Задаём абсолютные пути.
	 */
	private function __construct()
	{
		$this->events_dir   = __DIR__ . '/storage/events';
		$this->commands_dir = __DIR__ . '/storage/commands';
		$this->config_file  = __DIR__ . '/config.ini';
	}

	/**
	 * Инициировать соединение с сервером
	 * 
	 * @param array $params      Список параметров подключения
	 * @see   CTIClient::_config Список параметров по-умолчанию
	 */
	public function connect(array $params = null)
	{
		$this->disconnect();

		if ($params)
			$this->updateConfig($params);

		$this->reconnect_if_lost_connection = true;

		$this->checkConnection();
	}

	/**
	 * Проверить состояние соединения с сервером
	 * 
	 * @return boolean
	 */
	public function isConnected()
	{
		return ListenerProcess::isCurrentProcessRunning() !== false;
	}

	/**
	 * Разорвать соединение с сервером
	 */
	public function disconnect()
	{
		$this->reconnect_if_lost_connection = false;

		ListenerProcess::processKill();
	}

	/**
	 * Сделать звонок
	 * 
	 * @param  string $src Номер, с которого следует позвонить
	 * @param  string $dst Номер, на который следует позвонить
	 */
	public function call($src, $dst)
	{
		$this->checkConnection();

		$filename = 'call_'.time().'_'.$src.'_'.$dst;

		touch($this->commands_dir.'/'.$filename);
	}

	/**
	 * Перевести звонок
	 * 
	 * @param  string $call_id Идентификатор звонка
	 * @param  string $dst     Номер, на который следует перевести звонок
	 */
	public function transfer($call_id, $dst)
	{
		$this->checkConnection();

		$filename = 'transfer_'.time().'_'.$call_id.'_'.$dst;

		touch($this->commands_dir.'/'.$filename);
	}

	/**
	 * Получить все события
	 * 
	 * @return array Массив событий
	 */
	public function getEvents()
	{
		$this->checkConnection();

		$dir = $this->events_dir;

		return array_filter(array_map(function($value) use($dir) {
			$filepath = $dir.'/'.$value;

			if (is_dir($filepath))
				return;

			$event = file_get_contents($filepath);

			unlink($filepath);

			return json_decode($event, true);
		}, scandir($this->events_dir)));
	}

	/**
	 * Проверить состояние подключения
	 *
	 * Если соединение разорвано и есть возможность переподключиться — переподключиться.
	 * Иначе, выбросить исключение.
	 * 
	 * @return bool
	 */
	private function checkConnection()
	{
		if ($this->isConnected())
			return true;

		if ($this->reconnect_if_lost_connection === false)
			throw new CTIException("Connection should be set explicitly", 1);

		if (ListenerProcess::processLaunch() === false)
			throw new CTIException("Cannot launch listener process", 1);

		sleep(2);

		if ($this->isConnected())
			return true;

		throw new CTIException("Cannot launch listener process", 1);
	}

	/**
	 * Перезаписать конфигурационный файл в соответствии с переданными параметрами.
	 * 
	 * @param  array $params Список параметров подключения
	 */
	private function updateConfig($params)
	{
		$params_string = '';

		$old_params = parse_ini_file($this->config_file, false, INI_SCANNER_RAW);

		$params = array_merge($old_params, $params);

		ksort($params);

		if ($params === $old_params)
			return true;

		foreach ($params as $key => $value) {
			$params_string .= $key.' = '.$value."\n";
		}

		file_put_contents($this->config_file, $params_string);
	}
}