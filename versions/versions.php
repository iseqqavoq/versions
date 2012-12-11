<?php
/*
Plugin Name: Versions
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
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
		register_activation_hook( __FILE__, array( __CLASS__, 'activate_plugin' ) );
		
        add_action( 'admin_init', array( __CLASS__, 'plugin_admin_init' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_menus' ) );

        self::add_text_domain(); // Add text domain for localization.

        if(!is_admin() && !self::is_login_page())
        {
            $activation = (array) get_option( 'versions_activation_setting' );
            if( strcmp ( $activation[0] , 'yes' ) == 0 )
            {
                $filter = (array) get_option( 'versions_filter_setting' );
                if( strcmp ( $filter[0] , 'default' ) == 0 )
                {
	                add_action( 'plugins_loaded', array( __CLASS__, 'start_html_buffer' ) );
                    add_action( 'wp_footer', array( __CLASS__, 'stop_html_buffer' ) );
                }
                else if( strcmp ( $filter[0] , 'advanced' ) == 0 )
                {
                    add_action( 'wp_print_scripts', array( __CLASS__, 'handle_enqueued_scripts' ), 999);
                    add_action( 'wp_print_styles', array( __CLASS__, 'handle_enqueued_styles' ), 999);
                }
            }
        }
	}
	
	/**
	 * Set options that need to exist.
	 */
	public static function activate_plugin()
	{
		if(!get_option('versions_activation_setting'))
		{
			update_option('versions_activation_setting', 'yes');
		}
		
		if(!get_option('versions_filter_setting'))
		{
			update_option('versions_filter_setting', 'default');
		}
		
		if(!get_option('versions_catch_all_setting'))
		{
			update_option('versions_catch_all_setting', 'active');
		}
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
			$catchAll 			= get_option('versions_catch_all_setting');
			$handledScripts 	= array();
			$queuedScripts 		= $wp_scripts->queue;
			$scriptsToHandle 	= $queuedScripts;
		
			// Finalize array of scripts to handle, including dependencies.	
			foreach($queuedScripts as $handle)
			{
				if(sizeof($wp_scripts->registered[$handle]->deps) > 0)
				{
					foreach($wp_scripts->registered[$handle]->deps as $dep)
					{
						if(!in_array($dep, $scriptsToHandle))
						{
							array_push($scriptsToHandle, $dep);
						}
					}
				}
			}
			
			foreach($scriptsToHandle as $scriptHandle)
			{			
				if(!$wp_scripts->registered[$scriptHandle]->ver || $catchAll === 'active')
				{
				    $versionNumber = self::generate_version_number($wp_scripts->registered[$scriptHandle]->src);
				    
				    if($versionNumber)
				    {
				    	$wp_scripts->registered[$scriptHandle]->ver = $versionNumber;
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
			$catchAll 			= get_option('versions_catch_all_setting');
			$handledStyles 		= array();
			$queuedStyles 		= $wp_styles->queue;
			$stylesToHandle 	= $queuedStyles;
		
			// Finalize array of scripts to handle, including dependencies.	
			foreach($queuedStyles as $handle)
			{
				if(sizeof($wp_styles->registered[$handle]->deps) > 0)
				{
					foreach($wp_styles->registered[$handle]->deps as $dep)
					{
						if(!in_array($dep, $stylesToHandle))
						{
							array_push($stylesToHandle, $dep);
						}
					}
				}
			}
			
			foreach($stylesToHandle as $styleHandle)
			{			
				if(!$wp_styles->registered[$styleHandle]->ver || $catchAll === 'active')
				{
				    $versionNumber = self::generate_version_number($wp_styles->registered[$styleHandle]->src);
				    
				    if($versionNumber)
				    {
				    	$wp_styles->registered[$styleHandle]->ver = $versionNumber;
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
		
		$catchAll = get_option('versions_catch_all_setting');
		
		$html = str_get_html($buffer);
		
		$links = $html->find('link');
		
		foreach($links as $index => $value)
		{
			$url 		= $links[$index]->href;
			$urlQuery	= parse_url($url, PHP_URL_QUERY);

			if((!isset($urlQuery['ver']) && !isset($urlQuery['v']) && !isset($urlQuery['version'])) || $catchAll === 'active')
			{
				parse_str($urlQuery, $queryArray);
				
				$versionNumber = self::generate_version_number($links[$index]->href);
				if($versionNumber)
				{
				    $delimiter = (sizeof($urlQuery) > 0) ? '&' : '?';
				    $links[$index]->href .= $delimiter . 'versions=' . $versionNumber;
				}
			}
		}
		
		$scripts = $html->find('script');
				
		foreach($scripts as $index => $value)
		{
			if(!$scripts[$index]->src) continue; // If not an external script, skip loop.
			$url 		= $scripts[$index]->src;
			$urlQuery	= parse_url($url, PHP_URL_QUERY);

			if((!isset($urlQuery['ver']) && !isset($urlQuery['v']) && !isset($urlQuery['version'])) || $catchAll === 'active')
			{
				parse_str($urlQuery, $queryArray);
				
				$versionNumber = self::generate_version_number($scripts[$index]->src);
				if($versionNumber)
				{
				    $delimiter = (sizeof($urlQuery) > 0) ? '&' : '?';
				    $scripts[$index]->src .= $delimiter . 'versions=' . $versionNumber;
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
		if(!$parsedUrl) return false;
		if(isset($parsedUrl['host']) && $parsedUrl['host'] !== $_SERVER['HTTP_HOST']) return false;
		if(!isset($parsedUrl['path'])) return false;
		
		$filePath = $_SERVER['DOCUMENT_ROOT'];
		$filePath .= (!isset($parsedUrl['host'])) ? dirname($_SERVER['SCRIPT_NAME']) : '';
		$filePath .= $parsedUrl['path'];
		
		return (file_exists($filePath)) ? $filePath : FALSE; // Returns filePath if file exists and FALSE if not.
	}

    /**
     * Initialize the admin setting for Versions
     *
     * @access	public
     */
	public static function plugin_admin_init()
	{
        register_setting( 'versions_group', 'versions_activation_setting' );
        register_setting( 'versions_group', 'versions_filter_setting' );
        register_setting( 'versions_group', 'versions_catch_all_setting' );

	    add_settings_section( 'versions_settings_section', __( 'Versions Settings', 'versions' ), array( __CLASS__, 'versions_settings_section_callback') , 'versions' );
        add_settings_field( 'versions_activation_setting', __( 'Activate Versions', 'versions' ), array( __CLASS__, 'versions_activation_setting_callback') , 'versions', 'versions_settings_section' );
        add_settings_field( 'versions_filter_setting', __( 'Filter method', 'versions' ), array( __CLASS__, 'versions_filter_setting_callback') , 'versions', 'versions_settings_section' );
        add_settings_field( 'versions_catch_all_setting', __( 'Catch all', 'versions' ), array( __CLASS__, 'versions_catch_all_setting_callback') , 'versions', 'versions_settings_section' );

	}

    /**
     * Add the Versions options page
     *
     * @access	public
     */
	public static function add_menus()
	{
        add_options_page( __( 'Versions Settings', 'versions' ), __( 'Versions Settings', 'versions' ), 'manage_options', 'versions', array( __CLASS__, 'versions_submenu_callback' ) );
	}

    /**
     * Callback for the Versions options page
     *
     * @access	public
     */
	public static function versions_submenu_callback()
	{ ?>
	    <div class='wrap'>
	    	<div id="icon-options-general" class="icon32"></div>
	        <h2><?php _e( 'Versions settings', 'versions' ) ?></h2>
	        <form method='POST' action='options.php'>
                <?php
                    settings_fields( 'versions_group' );
                    do_settings_sections( 'versions' );
                ?>
                <p><?php _e( 'For any questions related to the Versions plugin, check out the plugin\'s page and support forum at <a href="http://wordpress.org/extend/plugins/versions" target="_blank">WordPress</a> or the plugin\'s page at <a href="http://www.iqq.se/" target="_blank">Iseqqavoq.se.</a>', 'versions' ) ?></p>
                <p class='submit'>
                    <input name='submit' type='submit' id='submit' class='button-primary' value='<?php _e("Save Changes") ?>' />
                </p>
	        </form>
	    </div>
    <?php }

    /**
     * Callback for the "Activation" setting on the Versions options page
     *
     * @access	public
     */
    public static function versions_activation_setting_callback()
	{
        $options = array(
        	'name' 		=> 'Activate Versions',
            'id' 		=> 'versions_activation_setting',
            'type' 		=> 'radio',
            'options' 	=> array(
            	'yes' 		=> array(
            		'label' 		=> __('Yes', 'versions')
            	),
            	'no' 		=> array(
            		'label'			=> __('No', 'versions')
            	),
            ),
            'std' 		=> 'no'
        );

        Versions::create_section_for_radio($options);
    }

    /**
     * Callback for the "Filter method" setting on the Versions options page
     *
     * @access	public
     */
    public static function versions_filter_setting_callback()
    {
        $options = array(
        	'name' 		=> 'Filter Method',
            'id' 		=> 'versions_filter_setting',
            'type' 		=> 'radio',
            'options' 	=> array(
            	'default' 	=> array(
            		'label' 		=> __( 'Default', 'versions' ),
            		'description' 	=> __( 'The default method filters through all of the HTML before it is sent to the browser. It detects and alters script- and link-tags that need a good looking, automated version number. This is the preffered method to use if you do not have a deeper understanding of the code in your theme and/or plugins.', 'versions' )
            	),
            	'advanced' 	=> array(
            		'label'			=> __( 'Advanced', 'versions' ),
            		'description' 	=> __( 'Use this method if you know that all of your scripts and styles are registered and enqueued from your theme and/or plugins. It alters only script and stylesheet versions through the global scripts and styles arrays. Since it consumes a little less memory and time in execution it is aimed mainly to users who have a deeper insight in the code of the theme and/or plugins.', 'versions' )
            	)
            ),
            'std' 		=> 'default'
        );

        Versions::create_section_for_radio($options);
    }
    
    /**
     * Callback for the "Catch all" setting on the Versions options page
     *
     * @access	public
     */
    public static function versions_catch_all_setting_callback()
    {
        $options = array(
        	'name' 		=> 'Catch all',
            'id' 		=> 'versions_catch_all_setting',
            'type' 		=> 'checkbox',
            'options' 	=> array(
            	'active' 	=> array(
            		'label' 		=> __( 'Active', 'versions' ),
            		'description' 	=> __( 'If active, the catch-all handles all scripts\' and styles\' version numbers. Including those who already have a version number set. This is a preffered option to have active since the version numbers generated by the Versions plugin is more reliable and practical than those set manually.', 'versions' )
            	),
            ),
            'std' 		=> 'active'
        );

        Versions::create_section_for_checkboxes($options);
    }

    /**
     * Callback for the section on the Versions options page
     *
     * @access	public
     */
	public static function versions_settings_section_callback()
	{

    }

    /**
     * Create radio buttons for Versions options page
     *
     * @access	public
     * @param	array
     */
    public static function create_section_for_radio($value)
    {
        foreach ($value['options'] as $option_name => $option_data) {
            $checked = ' ';
            if (get_option($value['id']) == $option_name) {
                $checked = ' checked="checked" ';
            }
            else if (get_option($value['id']) === FALSE && $value['std'] == $option_name){
                $checked = ' checked="checked" ';
            }
            else {
                $checked = ' ';
            }
            
            $output = '<div class="versions-radio">';
            $output .= '<label>';
            $output .= '<input type="radio" name="'.$value['id'].'" value="'.$option_name.'"'.$checked.'/>';
            $output .= '<span>'.$option_data['label'].'</span></label>';
            $output .= (isset($option_data['description']) && $option_data['description']) ? '<p>'.$option_data['description'].'</p>' : '';
            $output .= '</div>';
            
            echo $output;
        }
    }
    
    /**
     * Create checkboxes for Versions options page
     *
     * @access	public
     * @param	array
     */
    public static function create_section_for_checkboxes($value)
    {
        foreach ($value['options'] as $option_name => $option_data) {
            $checked = ' ';
            if (get_option($value['id']) == $option_name) {
                $checked = ' checked="checked" ';
            }
            else if (get_option($value['id']) === FALSE && $value['std'] == $option_name){
                $checked = ' checked="checked" ';
            }
            else {
                $checked = ' ';
            }
            
            $output = '<div class="versions-checkbox">';
            $output .= '<label>';
            $output .= '<input type="checkbox" name="'.$value['id'].'" value="'.$option_name.'"'.$checked.'/>';
            $output .= '<span>'.$option_data['label'].'</span></label>';
            $output .= (isset($option_data['description']) && $option_data['description']) ? '<p>'.$option_data['description'].'</p>' : '';
            $output .= '</div>';
            
            echo $output;
        }
    }
    
    /**
	 * Load the text domain for the plugin in order to work with localzation.
	 *
	 */
	public static function add_text_domain()
	{
		$domain = 'versions';
		$path 	= dirname( plugin_basename( __FILE__ ) ) . '/languages/';
		
		load_plugin_textdomain( $domain, false, $path );
	}
	
	public static function is_login_page()
	{
	    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
	}
}

Versions::init();