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
		add_action( 'wp_print_scripts', array( __CLASS__, 'handle_queued_scripts' ), 999);
	}
	
	/**
	 * Check which scripts in script queue are in need of an altered version number and alter it.
	 */
	public static function handle_queued_scripts()
	{
		if(is_admin()) return; // If in backoffice, abort.
		global $wp_scripts;
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
		
		if($parsedUrl['host'] !== $_SERVER['HTTP_HOST']) return false; // If file is not on server, abort.
		
		$filePath = $_SERVER['DOCUMENT_ROOT'] . $parsedUrl['path'];
		
		return (file_exists($filePath)) ? $filePath : FALSE; // Returns filePath if file exists and FALSE if not.
	}
}

Versions::init();