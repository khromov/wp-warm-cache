<?php
/**
* Part of WordPress Plugin: Warm cache
* Based on script from : http://blogs.tech-recipes.com/johnny/2006/09/17/handling-the-digg-effect-with-wordpress-caching/
*/

if(defined('WC_CALLED'))
{	
	$warm_cache = new warm_cache();
	$warm_cache->google_sitemap_generator_options = get_option("sm_options");

	$timer = new warm_cache_timer();
	$timer->start();
	
	//FIXME: Export output type. Add options to select
	$output_format = 'xml';
	
	@set_time_limit(0);
	
	//FIXME: Why?
	$warm_cache->enable_gzip();

	// Get url
	$sitemap_url = $warm_cache->get_sitemap_url();

	// For stats
	$statdata = get_option('plugin_warm_cache_statdata');
	
	if(!isset($statdata) || !is_array($statdata))
		add_option('plugin_warm_cache_statdata', array(), NULL, 'no');

	$newstatdata = array();
	$keep_time = 60*60*24*7; // 7 days for now (TODO: admin setting)
	foreach($statdata as $key => $value)
	{
		if($key >= time()-$keep_time)
			$newstatdata[$key] = $value;
	}
	
	$newtime = time();
	$newkey = 'plugin_warm_cache'.$newtime;

	$newvalue = array();
	$newvalue['url'] = $sitemap_url;
	$newvalue['time_start'] = $newtime;
	$newvalue['pages'] = array();
	
	$crawl_data = $warm_cache->process_sitemap($sitemap_url, $output_format);
	
	echo $warm_cache->template->show("cron/{$output_format}", array('crawl_data' => $crawl_data, 'elapsed_time' => $timer->get(8)));
	

	$newvalue['pages_count'] = sizeof($crawl_data);
	$newvalue['time'] = $timer->get(2);

	set_transient($newkey, $newvalue, $keep_time);
	$newstatdata[$newtime] = $newkey;

	update_option('plugin_warm_cache_statdata', $newstatdata);
	
	die(); //Stop rest of WP load TODO: Possible to do cleaner?
}