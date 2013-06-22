<?php
/**
* Part of WordPress Plugin: Warm cache
* Based on script from : http://blogs.tech-recipes.com/johnny/2006/09/17/handling-the-digg-effect-with-wordpress-caching/
*/
if(defined('CALLED'))
{	
	$warm_cache = new warm_cache();
	$warm_cache->google_sitemap_generator_options = get_option("sm_options");

	$mtime = microtime();
	$mtime = explode(' ', $mtime);
	$mtime = $mtime[1] + $mtime[0];
	$starttime = $mtime;
	
	@set_time_limit(0);
	
	if (extension_loaded('zlib')) {
		$z = strtolower(ini_get('zlib.output_compression'));
		if ($z == false || $z == 'off')
		{
			ob_start('ob_gzhandler');
		}
	}

	if (file_exists('./wp-load.php')) 
	{
		require_once ('./wp-load.php');
	}

	// Get url
	$sitemap_url = $warm_cache->get_sitemap_url();
	// Override here if needed

	// For stats
	$statdata = get_option('plugin_warm_cache_statdata');
	if(!isset($statdata) || !is_array($statdata))
	{
		add_option('plugin_warm_cache_statdata', array(), NULL, 'no');
	}

	$newstatdata = array();
	$keep_time = 60*60*24*7; // 7 days for now (TODO: admin setting)
	foreach($statdata as $key => $value)
	{
		if($key >= time()-$keep_time)
		{
			$newstatdata[$key] = $value;
		}
	}
	$newtime = time();
	$newkey = 'plugin_warm_cache'.$newtime;

	$newvalue = array();
	$newvalue['url'] = $sitemap_url;
	$newvalue['time_start'] = $newtime;
	$newvalue['pages'] = array();
	
	function mp_process_sitemap($sitemap_url)
	{
		global $newvalue;
		$xmldata = wp_remote_retrieve_body(wp_remote_get($sitemap_url));
		$xml = simplexml_load_string($xmldata);
	
		$cnt = count($xml->url);
		if($cnt > 0)
		{
			for($i = 0;$i < $cnt;$i++){
				$page = (string)$xml->url[$i]->loc;
				echo '<br/>Busy with: '.$page;
				$newvalue['pages'][] = $page;
				$tmp = wp_remote_get($page);
			}
		}
		else
		{
			// Sub sitemap?
			$cnt = count($xml->sitemap);
			if($cnt > 0)
			{
				for($i = 0;$i < $cnt;$i++){
					$sub_sitemap_url = (string)$xml->sitemap[$i]->loc;
					echo "<br/>Start with submap: ".$sub_sitemap_url;
					mp_process_sitemap($sub_sitemap_url);
				}				
			}
		}
	}
	// GOGOGO!
	mp_process_sitemap($sitemap_url);
	
	
	echo '<br><br><strong>Done!</strong>';
	

	$mtime = microtime();
	$mtime = explode(" ", $mtime);
	$mtime = $mtime[1] + $mtime[0];
	$endtime = $mtime;
	$totaltime = ($endtime - $starttime);
	$cnt = count($newvalue['pages']);
	$returnstring = 'Crawled '.$cnt. ' pages in ' .$totaltime. ' seconds.';
	echo '<br>'. $returnstring;

	$newvalue['pages_count'] = $cnt;
	$newvalue['time'] = $totaltime;


	set_transient($newkey, $newvalue, $keep_time);
	$newstatdata[$newtime] = $newkey;

	update_option('plugin_warm_cache_statdata',$newstatdata);
	die();
}
?>