<?php
/**
 * Timer class from:
 * http://davidwalsh.name/php-timer-benchmark
 */

class warm_cache_timer
{
	var $start;
	var $pause_time;

	/*  start the timer  */
	function __construct($start = false)
	{
		if($start)
			$this->start();
	}

	/*  start the timer  */
	function start()
	{
		$this->start = $this->get_time();
		$this->pause_time = 0;
	}

	/*  pause the timer  */
	function pause()
	{
		$this->pause_time = $this->get_time();
	}

	/*  unpause the timer  */
	function unpause()
	{
		$this->start += ($this->get_time() - $this->pause_time);
		$this->pause_time = 0;
	}

	/*  get the current timer value  */
	function get($decimals = 4)
	{
		return round(($this->get_time() - $this->start), $decimals);
	}

	/*  format the time in seconds  */
	function get_time()
	{
		list($usec,$sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}
}