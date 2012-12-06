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
        add_action( 'admin_init', array( __CLASS__, 'plugin_admin_init' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_menus' ) );

        self::add_text_domain();

        if(!is_admin()){
            $activation = (array) get_option( 'versions_activation_setting_name' );
            if( strcmp ( $activation[0] , 'yes' ) == 0 ) {
                $filter = (array) get_option( 'versions_filter_setting_name' );
                if( strcmp ( $filter[0] , 'default' ) == 0 ) {
                    add_action( 'plugins_loaded', array( __CLASS__, 'start_html_buffer' ) );
                    add_action( 'wp_footer', array( __CLASS__, 'stop_html_buffer' ) );
                }
                else if( strcmp ( $filter[0] , 'advanced' ) == 0 ) {
                    add_action( 'wp_print_scripts', array( __CLASS__, 'handle_enqueued_scripts' ), 999);
                    add_action( 'wp_print_styles', array( __CLASS__, 'handle_enqueued_styles' ), 999);
                }
            }
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

    /**
     * Initialize the admin setting for Versions
     *
     * @access	public
     */
	public static function plugin_admin_init()
	{
        register_setting( 'versions_group', 'versions_activation_setting_name' );
        register_setting( 'versions_group', 'versions_filter_setting_name' );

	    add_settings_section( 'versions_settings_section', __( 'Versions Settings', 'versions' ), array( __CLASS__, 'versions_settings_section_callback') , 'versions' );
        add_settings_field( 'versions_activation_setting_name', __( 'Activate Versions', 'versions' ), array( __CLASS__, 'versions_activation_setting_callback') , 'versions', 'versions_settings_section' );
        add_settings_field( 'versions_filter_setting_name', __( 'Filter method', 'versions' ), array( __CLASS__, 'versions_filter_setting_callback') , 'versions', 'versions_settings_section' );

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
            'id' 		=> 'versions_activation_setting_name',
            'type' 		=> 'radio',
            'desc' 		=> __( 'Activate Versions by selecting \'Yes\' and deactivate by selecting \'No\'.', 'versions' ),
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
            'id' 		=> 'versions_filter_setting_name',
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
    public static function create_section_for_radio($value) {
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
            $output .= '<input type="radio" name="'.$value['id'].'" value="'.$option_name.'"'.$checked.'"/>';
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
}

Versions::init();