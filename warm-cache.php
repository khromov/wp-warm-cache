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
		
		include('lib/warm_cache_logger.class.php');
		include('lib/warm_cache_timer.class.php');
		
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
		
		$warm_cache_admin->content_start();
		echo $warm_cache_admin->template->show('admin-head');
		
		//FIXME: Continue here
		//Check that everything is working out for us
		if($warm_cache_admin->configuration_check())
		{
			$stats = $warm_cache_admin->get_stats();

			if(!$stats['crawl'])
				$warm_cache_admin->show_message($warm_cache_admin->template->show('prompt/sitemap-never-crawled', array('cron_url' => trailingslashit(get_bloginfo('url')).'?warm_cache='.get_option('plugin_warm_cache_api'))));
			else
				$warm_cache_admin->show_message($warm_cache_admin->template->show('table/crawled-totals-bottom', $stats));

			echo $warm_cache_admin->template->show('table/crawled-wrapper', array('table_string' => $stats['table_string']));
		}
		
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
		$vars[] = 'wc';
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
	
		$cnt = count($xml->url);
		if($cnt > 0)
		{
			for($i = 0;$i < $cnt;$i++)
			{				
				$page = (string)$xml->url[$i]->loc;
				
				$logger->add(__("{$page}"));
				
				$newvalue['pages'][] = $page;
				$tmp = wp_remote_get($page);
				//TODO: Measure how long each page took with a new timer and send that data as well in ->get_array(); somehow...
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
		
		return $logger->get_array();
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
add_filter('plugin_row_meta', array('warm_cache', 'addPluginContent'), 10, 2);