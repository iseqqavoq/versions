<?php
/*
Plugin Name: Versions
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 0.2
Author: Iseqqavoq
Author URI: http://www.iqq.se
License:
*/

/**
 * Versions
 *
 * @author	Iseqqavoq
 */

class Versions
{
	/**
	 * Initialize all hooks
	 */
	public static function init()
	{
		add_action( 'wp_print_scripts', array( __CLASS__, 'handle_enqueued_scripts' ), 999);
		add_action( 'wp_print_styles', array( __CLASS__, 'handle_enqueued_styles' ), 999);
		add_action( 'plugins_loaded', array( __CLASS__, 'start_html_buffer' ) );
		add_action( 'wp_footer', array( __CLASS__, 'stop_html_buffer' ) );
	}
	
	/**
	 * Check which scripts in script queue are in need of an altered version number and alter it.
	 */
	public static function handle_enqueued_scripts()
	{
		if(is_admin()) return; // If in backoffice, abort.
		global $wp_scripts;
		if($wp_scripts)
		{
			foreach($wp_scripts->registered as $handle => $args)
			{
				if( in_array($handle, $wp_scripts->queue ) )
				{
					if(!$wp_scripts->registered[$handle]->ver)
					{
						$versionNumber = self::generate_version_number($wp_scripts->registered[$handle]->src);
						
						if($versionNumber)
						{
							$wp_scripts->registered[$handle]->ver = $versionNumber;
						}
					}
				}
			}			
		}
	}
	
	public static function handle_enqueued_styles()
	{
		if(is_admin()) return; // If in backoffice, abort.
		global $wp_styles;
		if($wp_styles)
		{
			foreach($wp_styles->registered as $handle => $args)
			{
				if( in_array($handle, $wp_styles->queue ) )
				{
					if(!$wp_styles->registered[$handle]->ver)
					{
						$versionNumber = self::generate_version_number($wp_styles->registered[$handle]->src);
						if($versionNumber)
						{
							$wp_styles->registered[$handle]->ver = $versionNumber;
						}
					}
				}
			}			
		}
	}
	
	/**
	 * 
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public static function start_html_buffer()
	{
		ob_start( 'self::filter_output' );
	}
	
	/**
	 * Filters through the output for external resources that should be altered and alters them.
	 */
	public static function filter_output( $buffer )
	{
		include_once('simple_html_dom.php');
		$html = str_get_html($buffer);
		
		$links = $html->find('link');
		
		$regexp = '/\.css$/';
		
		foreach($links as $index => $value)
		{
			if(preg_match($regexp, $links[$index]->href)) // If link is a css file...
			{
				$versionNumber = self::generate_version_number($links[$index]->href);
				if($versionNumber)
				{
					$links[$index]->href .= '?ver=' . $versionNumber;
				}
			}
		}
		
		$scripts = $html->find('script');
		
		$regexp = '/\.js$/';
		
		foreach($scripts as $index => $value)
		{
			if(preg_match($regexp, $links[$index]->href)) // If link is a js file...
			{
				$versionNumber = self::generate_version_number($scripts[$index]->href);
				if($versionNumber)
				{
					$scripts[$index]->href .= '?ver=' . $versionNumber;
				}
			}
		}		

		return $html->save();
	}

	/**
	 * 
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	public static function stop_html_buffer()
	{
		ob_end_flush();
	}

		
	/**
	 * Generate a version number based on a files filemtime value.
	 *
	 * @access	public
	 * @param	string
	 * @return	string on success || boolean false on failure
	 */
	public static function generate_version_number($fileUrl)
	{
		$filePath = self::get_file_path_from_url($fileUrl);
		
		if(!$filePath) return FALSE; // If file was not found, abort.
		
		$filemtime = filemtime($filePath);
		return $filemtime;
	}
	
	/**
	 * Retrieve the given files path on server
	 *
	 * @access	public
	 * @param	string
	 * @return	string on success || boolean false on failur
	 */
	public static function get_file_path_from_url($fileUrl)
	{
		$parsedUrl = parse_url($fileUrl);
		
		// If file is not on server, abort.
		if($parsedUrl && isset($parsedUrl['host']) && $parsedUrl['host'] !== $_SERVER['HTTP_HOST']) return false;
		
		$filePath = $_SERVER['DOCUMENT_ROOT'] . $parsedUrl['path'];
		
		return (file_exists($filePath)) ? $filePath : FALSE; // Returns filePath if file exists and FALSE if not.
	}
}

Versions::init();