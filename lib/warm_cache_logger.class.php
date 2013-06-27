<?php
/** Logger class form warm_cache **/
class warm_cache_logger
{
	var $log;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->log = array();
	}
	
	/**
	 * Add log entry
	 */
	public function add($entry)
	{
		$this->log[] = $entry;
	}
	
	/**
	 * Returns log entries in an array
	 */
	public function get_array()
	{
		return $this->log;
	}
}