<?php
/*
Plugin Name: Warm Cache
Plugin URI: http://www.mijnpress.nl
Description: Crawls your website-pages based on google XML sitemap (google-sitemap-generator). If you have a caching plugin this wil keep your cache warm. Speeds up your site.
Version: 2.0
Author: Ramon Fincken, Stanislav Khromov
Author URI: http://www.mijnpress.nl
*/

if (!defined('ABSPATH')) 
{
	if(!isset($_GET['warm_cache']))
		die("Aren't you supposed to come here via WP-Admin?");
}

if(!class_exists('mijnpress_plugin_framework'))
	include('lib/mijnpress_plugin_framework.php');

class warm_cache extends mijnpress_plugin_framework
{
	public $google_sitemap_generator_options;
	public $sitemap_url;
	public $keep_time;
	public $template;
	
	public function __construct()
	{
		/**
		 * Init emplate engine
		 */
		if(!class_exists('MicroTemplate_v2') && !class_exists('MT_v2'))
			include('lib/microtemplate.class.php');		
		
		/**
		 * Init class dependencies
		 */		
		include('lib/warm_cache_logger.class.php');
		include('lib/warm_cache_timer.class.php');
		include('lib/warm_cache_sanitizer.class.php');
		
		//Register template directory by getting directory of current file
		$this->template = new MicroTemplate_v2(dirname(__FILE__).'/templates/');
	}
	
	/**
	 * Activation function
	 */
	static function activate()
	{
		//Create stats data 
		add_option('plugin_warm_cache_statdata', array());
		
		//TODO: Create other settings
		
		//Generate a random API password without special characters
		add_option('plugin_warm_cache_api', wp_generate_password(9, false));
	}
	
	function warm_cache()
	{
		$this->keep_time = 60*60*24*7; // 7 days for now (TODO: admin setting)
	}

	function addPluginSubMenu()
	{
		add_submenu_page('options-general.php', __("Warm Cache"), __("Warm Cache"), 'manage_options', 'warm_cache', array('warm_cache', 'admin_menu'));
	}

	/**
	 * Additional links on the plugin page
	 */
	function addPluginContent($links, $file)
	{
		$links = parent::addPluginContent('warm_cache/warm-cache.php', $links, $file);
		return $links;
	}

	public function admin_menu()
	{
		/**
		 * Where the main plugin function is created
		 */
		$warm_cache_admin = new warm_cache();
		$warm_cache_admin->plugin_title = 'Warm cache';
		
		//Register settings
		$warm_cache_admin->register_settings();
		
		echo $warm_cache_admin->template->show('admin-header');
		
		//FIXME: Continue here
		//Check that everything is working out for us
		if($warm_cache_admin->configuration_check())
		{
			$stats = $warm_cache_admin->get_stats();

			if(!$stats['crawl'])
				$warm_cache_admin->show_message($warm_cache_admin->template->show('prompt/sitemap-never-crawled', array('cron_url' => trailingslashit(get_bloginfo('url')).'?warm_cache='.get_option('plugin_warm_cache_api'))));
			else
				$warm_cache_admin->show_message($warm_cache_admin->template->show('table/crawled-totals-bottom', $stats));
			
			//Show settings form
			echo $warm_cache_admin->template->show('forms/wrapper');
			
			echo $warm_cache_admin->template->show('table/crawled-wrapper', array('table_string' => $stats['table_string']));
		}

		
		echo $warm_cache_admin->template->show('admin-footer');
		
		$warm_cache_admin->content_end();
	}

	/**
	* Gets table and stats
	*/
	private function get_stats()
	{
		//Stats data and API key are always present at this point due to  activate(), no need to check.
		$statdata = array_reverse(get_option('plugin_warm_cache_statdata'));
		
		$table_string = '';
		
		if(!count($statdata))
		{
			$table_string = $this->template->show('table/crawled-empty');
			return array('crawl' => false, 'table_string' => $table_string);
		}

		$stats_pages = 0;
		$stats_times = 0;
		$site_url = site_url();
		
		foreach($statdata as $key => $value)
		{
			$temp = get_transient($value);
			$string_length = 0;
			
			if($temp !== false)
			{
				$table_string .= $this->template->show('table/crawled-row', array('time_start' => $temp['time_start'], 'time' => $temp['time'], 'pages_count' => $temp['pages_count']));

				$stats_pages += $temp['pages_count'];
				$stats_times += $temp['time'];
			}
		}
		return array('crawl' => true, 'stats_pages' => $stats_pages, 'stats_times' => $stats_times, 'table_string' => $table_string);
	}

	private function configuration_check()
	{
		$this->google_sitemap_generator_options = get_option("sm_options");

		if(!is_plugin_active('google-sitemap-generator/sitemap.php'))
		{
			$this->show_message($this->template->show('prompt/install-sitemap-generator'));
			return false;
		}
		else
		{
			$this->show_message($this->template->show('prompt/configuration-check-ok', array('sitemap_url' => warm_cache::get_sitemap_url(), 'cron_url' => trailingslashit(get_bloginfo('url')).'?warm_cache='.get_option('plugin_warm_cache_api'))));
			return true;
		}
	}

	public function get_sitemap_url()
	{
		if($this->google_sitemap_generator_options["sm_b_location_mode"]=="manual") {
			$sitemap_url = $this->google_sitemap_generator_options["sm_b_fileurl_manual"];
		} else {
			$sitemap_url =  trailingslashit(get_bloginfo('url')). $this->google_sitemap_generator_options["sm_b_filename"];
		}
		$this->sitemap_url = $sitemap_url;
		return $this->sitemap_url;
	}
	
	/**
	 * Add query_vars filter to allow running crawling through Wordpress
	 */
	public function add_query_vars_filter($vars)
	{
		$vars[] = 'warm_cache';
		$vars[] = 'warm_cache_export_type';
		return $vars;
	}
	
	/**
	 * Function that initiates crawling
	 */
	function crawl()
	{
				
		if(get_query_var('warm_cache') === get_option('plugin_warm_cache_api') || get_query_var('wc') === get_option('plugin_warm_cache_api'))
    	{
			define('WC_CALLED', true);
			include('warm_cache_crawl.php');
		}
	}
	
	/**
	 * Function for processing the sitemap.
	 */
	function process_sitemap($sitemap_url)
	{
		$logger = new warm_cache_logger();
		
		global $newvalue;
		$xmldata = wp_remote_retrieve_body(wp_remote_get($sitemap_url));
		$xml = simplexml_load_string($xmldata);
		
		/**
		 * Load settings
		 */
		$always_cache_frontpage = true;
		$date_cutoff_enabled = true;
		
		//No older than the following date. 86400 = 1 day, 604800 = 1 week
		$date_cutoff = 86400;
		
		$pages_to_always_cache = array('http://rheldev/wpdev/sample-paf23f23f/', 'http://rheldev/wpdev/view-test-page');
	
		$cnt = count($xml->url);
		if($cnt > 0)
		{
			for($i = 0;$i < $cnt;$i++)
			{				
				$page = (string)$xml->url[$i]->loc;
				$last_modified_array = date_parse_from_format('Y-m-d\TH:i:s+00:00', $xml->url[$i]->lastmod);
				
				//http://se1.php.net/date_create_from_format might be better, but PHP >=5.3 only
				$year = $last_modified_array['year'];
				$month = str_pad($last_modified_array['month'], 2, "0", STR_PAD_LEFT);
				$day = str_pad($last_modified_array['day'], 2, "0", STR_PAD_LEFT);
				
				$last_modified = "{$year}-{$month}-{$day}";
				$last_modified_timestamp =  strtotime($last_modified);
				
				//Date cutoff functionality
				if($date_cutoff_enabled)
				{
					if(time() - $last_modified_timestamp < $date_cutoff)
					{
						//TODO: Measure how long each page took with a new timer and send that data as well in ->get_array(); somehow...
						$newvalue['pages'][] = $page;
						$tmp = wp_remote_get($page);
						$logger->add(__("{$page}"));
					}				
				}
				else
				{
					$newvalue['pages'][] = $page;
					$tmp = wp_remote_get($page);	
					$logger->add(__("{$page}"));				
				}				
			}			
		}
		else
		{
			// Sub sitemap?
			$cnt = count($xml->sitemap);
			if($cnt > 0)
			{
				for($i = 0;$i < $cnt;$i++)
				{
					//$logger->add(__("Start with submap: {$sub_sitemap_url}"));
					
					$sub_sitemap_url = (string)$xml->sitemap[$i]->loc;
					$this->process_sitemap($sub_sitemap_url);
				}				
			}
		}

		/** Always cache front page functionality **/
		if($always_cache_frontpage)
		{
			//Check if frontpage has already been cached. If not, cache it.
			if(!in_array($this->add_slash_to_url_if_needed(get_bloginfo('url')), ($newvalue['pages'] !== NULL ? $newvalue['pages'] : array())))
			{
				$start_page_url = get_bloginfo('url');
				$newvalue['pages'][] = $this->add_slash_to_url_if_needed($start_page_url);
				$tmp = wp_remote_get($this->add_slash_to_url_if_needed($start_page_url));	
				$logger->add(__("{$start_page_url}"));							
			}
		}		
		
		/** Fetch pages that are set to always be cached **/
		foreach($pages_to_always_cache as $page_to_always_cache)
		{
			$newvalue['pages'][] = $page_to_always_cache;
			$tmp = wp_remote_get($page_to_always_cache);
			$logger->add(__("{$page_to_always_cache}"));			
		}
		
		return $logger->get_array();
	}
	
	/** Register plugin settings **/
	public function register_settings()
	{
		//register_setting('warm-cache-group', 'warm_cache_always_cache_frontpage' , array('warm_cache_sanitizer','sanitize_form_checkbox'));
		//register_setting('warm-cache-group', 'warm_cache_date_cutoff_enabled', array('warm_cache_sanitizer','sanitize_form_checkbox') );	
		//register_setting('warm-cache-group', 'warm_cache_date_cutoff', array('warm_cache_sanitizer','sanitize_form_integer') );
		//register_setting('warm-cache-group', 'plugin_warm_cache_api', array('warm_cache_sanitizer','sanitize_form_string') );		
		register_setting('warm-cache-group', 'warm_cache_pages_to_always_cache', array('warm_cache', 'sanitize_form_string') );	
		 
		add_settings_section( 'warm-cache-main', __('Main configuration'), array(&$this,'admin_main_part'), 'warm-cache' );
		
		//add_settings_field( 'warm_cache_always_cache_frontpage', __('Selected theme'), array(&$this,'field_warm_cache_always_cache_frontpage'), 'warm-cache', 'warm-cache-main');
		//add_settings_field( 'warm_cache_date_cutoff_enabled', __('Custom CSS'), array(&$this,'field_warm_cache_date_cutoff_enabled'), 'warm-cache', 'warm-cache-main');
		//add_settings_field( 'warm_cache_date_cutoff', __('Selected theme'), array(&$this,'field_warm_cache_date_cutoff'), 'warm-cache', 'warm-cache-main');
		//add_settings_field( 'plugin_warm_cache_api', __('Selected theme'), array(&$this,'field_plugin_warm_cache_api'), 'warm-cache', 'warm-cache-main');
		add_settings_field( 'warm_cache_pages_to_always_cache', __('URLs to always cache'), array(&$this,'field_warm_cache_pages_to_always_cache'), 'warm-cache', 'warm-cache-main');
	}

	/**
	 * Basic boolean sanitizing
	 */
	function sanitize_form_checkbox($in)
	{
		if($in != "true")
			return "false";
	}
	
	/**
	 * Basic int sanitizing
	 */
	 function sanitize_form_integer($in)
	 {
	 	return (int)$in;
	 }
	 
	 /**
	  * Basic string sanitizing
	  * 
	  * NOTE: Disallows HTML tags.
	  */
	 function sanitize_form_string($in)
	 {
	 	return strip_tags($in);
	 }
	
	/** Fields **/
	function admin_main_part()
	{
	}	
	
	function field_warm_cache_pages_to_always_cache()
	{
		echo $this->template->show('forms/fields/pages_to_always_cache');
	}

	function field_warm_cache_always_cache_frontpage()
	{
		echo $this->template->show('forms/fields/always_cache_frontpage');
	}
	
	function field_warm_cache_date_cutoff_enabled()
	{
		echo $this->template->show('forms/fields/date_cutoff_enabled');
	}
	
	function field_warm_cache_date_cutoff()
	{
		echo $this->template->show('forms/fields/date_cutoff');
	}
	
	function field_plugin_warm_cache_api()
	{
		echo $this->template->show('forms/fields/warm_cache_api');
	}
	
	/**
	 * Enables gzip at the PHP level.
	 */
	function enable_gzip()
	{
		/**
		 * Why is this needed?
		 * 
		 * TODO: Find out and remove if found unnecessary.
		 */
		if (extension_loaded('zlib'))
		{
			$z = strtolower(ini_get('zlib.output_compression'));
			if ($z == false || $z == 'off')
			{
				ob_start('ob_gzhandler');
			}
		}
	}
	
	/**
	 * Adds a final slash to an URL, if needed
	 */
	function add_slash_to_url_if_needed($url)
	{
		if(substr($url, -1) != '/')
			return $url.'/';
		else
			return $url;
	}
}

/**
 * Plugin registration
 */
add_filter('query_vars', array('warm_cache', 'add_query_vars_filter'));

/** http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect **/
add_action('template_redirect', array('warm_cache', 'crawl'));

register_activation_hook( __FILE__, array( 'warm_cache', 'activate' ) );
load_plugin_textdomain('plugin_warm_cache', false, basename( dirname( __FILE__ ) ) . '/languages' );

add_action('admin_menu', array('warm_cache', 'addPluginSubMenu'));
add_action( 'admin_init', array('warm_cache', 'register_settings'));

add_filter('plugin_row_meta', array('warm_cache', 'addPluginContent'), 10, 2);