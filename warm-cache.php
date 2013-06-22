<?php
/*
Plugin Name: Warm cache
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
	include('mijnpress_plugin_framework.php');

class warm_cache extends mijnpress_plugin_framework
{
	public $google_sitemap_generator_options;
	public $sitemap_url;
	public $keep_time;
	public $template;
	
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
		add_submenu_page('options-general.php', __("Warm cache"), __("Warm cache"), 'manage_options', 'warm_cache', array('warm_cache', 'admin_menu'));
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
		
		/**
		 * Template engine
		 */
		if(!class_exists('MicroTemplate') && !class_exists('MT'))
			include('lib/microtemplate.class.php');
		
		//Register template directory by getting directory of current file
		$warm_cache_admin->template = new MicroTemplate(dirname(__FILE__).'/templates/');
		
		//FIXME: Continue here
		//Check that everything is working out for us
		if($warm_cache_admin->configuration_check())
		{
			$warm_cache_admin->content_start();
			$stats = $warm_cache_admin->get_stats();

			if(!$stats['crawl'])
			{
				$msg = 'Ok, we have detected your sitemap url but it has not been visited by the plugin\'s crawler.<br/>';
				$warm_cache_api_url = trailingslashit(get_bloginfo('url')).'?warm_cache='.get_option('plugin_warm_cache_api');
				$msg .= 'This plugin will not work standalone. <strong>You need to set up a wget or curl cron job from your webhost every hour.</strong><br/>';
				$msg .= 'The url you should call with wget or curl is: '.$warm_cache_api_url;

				$warm_cache_admin->show_message($msg);
				echo '<br/><br/>';
			}
			else
			{
				$msg = 'Crawled in total '.$stats['stats_pages'].' pages in a total of '.$stats['stats_times']. ' seconds<br/>';
				if($stats['stats_pages'])
				{
					$msg .= 'Average page to load a page in seconds: '. $stats['stats_times']/$stats['stats_pages'];
				}
				$warm_cache_admin->show_message($msg);
			}
			echo '<table class="widefat">';
			echo '<tr><th class="manage-column" style="width: 150px;">Crawled at</th><th class="manage-column">Time needed</th><th class="manage-column" style="width: 120px;">Number of pages</th><th class="manage-column">Average load time per page</th><th class="manage-column">Pages</th></tr>';
			echo $stats['table_string'];
			echo '</table>';
			$warm_cache_admin->content_end();
		}
	}

	/**
	* Gets table and stats
	*/
	private function get_stats()
	{
		//Stats data and API key are always present at this point due to  activate(), no need to check.
		$statdata = get_option('plugin_warm_cache_statdata');
		
		$table_string = '';
		if(!count($statdata))
		{
			$table_string .= '<tr><td valign="top" colspan="5">';
			$table_string .= __('Your site has not been crawled by the plugin','plugin_warm_cache');
			$table_string .= '</td></tr>';
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
				$table_string .= '<tr><td valign="top">';
				$table_string .= date('l jS F Y h:i:s A',$temp['time_start']).'</td><td valign="top" style="text-align: center;">';
				$table_string .= $temp['time'].'</td><td valign="top">';
				$table_string .= $temp['pages_count'].'</td><td valign="top">';
				$table_string .= (intval($temp['pages_count'])!=0) ? $temp['time']/$temp['pages_count'].'</td><td valign="top">' : '- </td><td valign="top">';
				
				if(intval($temp['pages_count']) > 0)
				{
					foreach($temp['pages'] as $p_key => $p_value)
					{
						$table_string .= '<a href="'.$p_value.'" title="'.$p_value.'">';
						$temp_string = str_replace($site_url,'',$p_value);
						if($temp_string == '/')	{ $temp_string = $site_url; } // Site url, show this instead of "/"			
						$table_string .= $temp_string;
						$table_string .= '</a>';
						$string_length += strlen($temp_string);
						if($string_length > 70) {$string_length =0; $table_string .= '<br/>';} // New line
						$table_string .= "\n";
					}
				}
				$table_string .= '</td></tr>';
				$table_string .= "\n\n";

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
			$this->show_message($this->template->t('prompt/install-sitemap-generator'));
			return false;
		}
		else
		{
			$this->show_message($this->template->t('prompt/configuration-check-ok', array('sitemap_url' => warm_cache::get_sitemap_url(), 'cron_url' => trailingslashit(get_bloginfo('url')).'?warm_cache='.get_option('plugin_warm_cache_api'))));
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
}

/**
 * Plugin registration
 */
if(isset($_GET['warm_cache']) && !empty($_GET['warm_cache']) && $_GET['warm_cache'] == get_option('plugin_warm_cache_api'))
{
	define('CALLED',true);
	include('warm_cache_crawl.php');
}
else
{
	if(is_admin())
	{
		/**
		 * Register hooks, languages etc
		 **/
		register_activation_hook( __FILE__, array( 'warm_cache', 'activate' ) );
		load_plugin_textdomain('plugin_warm_cache', false, basename( dirname( __FILE__ ) ) . '/languages' );
		
		add_action('admin_menu',  array('warm_cache', 'addPluginSubMenu'));
		add_filter('plugin_row_meta',array('warm_cache', 'addPluginContent'), 10, 2);
	}
}