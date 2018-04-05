<?php
/**
 * Compatibility with other plugins.
 *
 * This class serves as compatibility getway.
 * Initiate all compatibility modules.
 *
 * @class Compatibility
 */

namespace wpCloud\StatelessMedia {
    
    class Module{

        private static $modules = array();

        /**
         * Object initiated on Bootstrap::__construct
         * Save module data on admin_init hook.
         * Initiate all the compatibility modules.
         */
        public function __construct(){
            add_action( 'admin_init', array($this, 'save_modules'), 1 );

            /**
             * Dynamic Image Support
             */
            new DynamicImageSupport();

            /**
             * ACF image crop addons compatibility.
             */
            new CompatibilityAcfImageCrop();
            
            /**
             * Support for Easy Digital Downloads download method
             */
            new EDDDownloadMethod();
            
            /**
             * Support for SiteOrigin widget CSS files
             */
            new SOWidgetCSS();
            
            /**
             * Support for SiteOrigin CSS files
             */
            new SOCSS();
            
            /**
             * Support for Gravity Form file upload field
             */
            new GravityForm();
            
            /**
             * Support for WPBakery Page Builder
             */
            new WPBakeryPageBuilder();
            
            /**
             * Support for Imagify
             */
            new Imagify();
            
            /**
             * Support for ShortPixel Image Optimizer
             */
            new ShortPixel();
        }

        /**
         * Register compatibility modules so that we can ues them in settings page.
         * Called from ICompatibility::init() method.
         */
        public static function register_module($id, $title , $description, $enabled = 'false', $is_constant = false){
            if (is_bool($enabled)) {
                $enabled = $enabled ? 'true' : 'false';
            }
            
            self::$modules[] = array(
                'id'            => $id,
                'title'         => $title,
                'enabled'       => $enabled,
                'description'   => $description,
                'is_constant'   => $is_constant,
            );
        }

        /**
         * Return all the registered modules.
         * Used in admin_init in bootstrap class as localize_script.
         */
        public static function get_modules(){
            return self::$modules;
        }

        /**
         * Handles saving module data.
         * Enable or disable modules from Compatibility tab.
         */
        public function save_modules(){
            if (isset($_POST['action']) && $_POST['action'] == 'stateless_modules' && wp_verify_nonce($_POST['_smnonce'], 'wp-stateless-modules')) {
                $modules = !empty($_POST['stateless-modules']) ? $_POST['stateless-modules'] : array();
                $modules = apply_filters('stateless::modules::save', $modules);
                
                update_option('stateless-modules', $modules, true);
                wp_redirect( $_POST['_wp_http_referer'] );
            }
        }
    }

 }