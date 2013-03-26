<?php
namespace WebSocket;
require_once __DIR__.DIRECTORY_SEPARATOR.'Connection.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'MessageIncoming.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'Exception.php';

/**
 * interface for web-socket observers (objects which uses web-socket connection), 
 * clients and servers objects
 *
 * @author	Vedisoft.Semenov Pasha
 * @package	WebSocket
 */
interface iObserver
{
	/**
	 * Returns connection, which used by this observer
	 */
	public function getConnection();
	
	/**
	 * Handler for connection has established event
	 * @return	void
	 */
	public function onConnect();
	
	/**
	 * Handler for connection has closed event
	 * @param	string	$reason
	 * @return	void
	 */
	public function onDisconnect($reason = '');
	
	/**
	 * Handler for message has recieved event
	 * @param	MessageIncoming	$message
	 * @return	void
	 */
	public function onMessage(MessageIncoming $message);
	
	/**
	 * Handler for exception hash happend event
	 * @param	Exception	$exception
	 * @return	void
	 */
	public function onException(Exception $exception);
}