<?php

interface CTIInterface
{
	/**
	 * Connect to server
	 * @return bool
	 */
	public function connect();

	/**
	 * Dissconnect from server
	 * @return bool
	 */
	public function disconnect();

	/**
	 * Make outgoing call
	 * @param  string $src source phone number
	 * @param  string $dst destination phone number
	 * @return bool
	 */
	public function call($src, $dst);

	/**
	 * Tell server to transfer incoming call
	 * @param  string $call_id
	 * @param  string $dst     destination phone number
	 * @return bool
	 */
	public function transfer($call_id, $dst);

	/**
	 * Get new events from server
	 * @return array
	 */
	public function getEvents();
}