<?php
/**
 * Upgrader
 *
 * @since 1.2.0
 */
namespace wpCloud\StatelessMedia {

  if( !class_exists( 'wpCloud\StatelessMedia\Upgrader' ) ) {

    final class Upgrader {

      /**
       * Upgrades data if needed.
       *
       */
      public static function call() {
        $version = get_site_option( 'wp_sm_version', false );
        if(!$version){
          self::fresh_install();
        }

        /* Maybe upgrade blog ( single site ) */
        self::upgrade();
        /* Maybe upgrade network options */
        if( ud_get_stateless_media()->is_network_detected(true) ) {
          self::upgrade_network();
        }

      }

      /**
       * Upgrade current blog ( or single site )
       *
       */
      private static function upgrade() {
        global $wpdb;

        $version = get_option( 'wp_sm_version', false );

        /**
         * Upgrade to v.1.2.0 requirements
         */
        if ( !$version || version_compare( $version, '1.2.0', '<' ) ) {

          if( $v = get_option( 'sm.mode' ) ) {
            update_option( 'sm_mode', $v );
            delete_option( 'sm.mode' );
          }

          $v = get_option( 'sm_mode' );
          if( $v == '0' ) update_option( 'sm_mode', 'disabled' );
          elseif( $v == '1' ) update_option( 'sm_mode', 'backup' );
          elseif( $v == '2' ) update_option( 'sm_mode', 'cdn' );

          if( $v = get_option( 'sm.service_account_name' ) ) {
            update_option( 'sm_service_account_name', $v );
            delete_option( 'sm.service_account_name' );
          }

          if( $v = get_option( 'sm.key_file_path' ) ) {
            update_option( 'sm_key_file_path', $v );
            delete_option( 'sm.key_file_path' );
          }

          if( $v = get_option( 'sm.bucket' ) ) {
            update_option( 'sm_bucket', $v );
            delete_option( 'sm.bucket' );
          }

          delete_option( 'sm.app_name' );
          delete_option( 'sm.body_rewrite' );
          delete_option( 'sm.bucket_url_path' );
          delete_option( 'sm.post_content_rewrite' );

        }
        
        update_option('dismissed_notice_stateless_cache_busting', true);
        if ( !$version || version_compare( $version, '2.1.7', '<' ) ){
          $sm_mode = get_option('sm_mode', null);
          $hashify_file_name = get_option('sm_hashify_file_name', null);
          if($version && $sm_mode == 'stateless' && $hashify_file_name == 'true'){
            delete_option('dismissed_notice_stateless_cache_busting');
          }
        }

        if ( !$version || version_compare( $version, '2.2.0', '<' ) ){
          $sm_synced_files = get_option('sm_synced_files', array());
          if(!empty($sm_synced_files) && is_array($sm_synced_files)){
            $table_name = $wpdb->prefix . SyncNonMedia::table;
            // Backing up the old data with autoload false.
            // @todo delete in future release.
            add_option('__sm_synced_files', $sm_synced_files, null, false);
  
            $files = array();
            $place_holders = array();
            $query = "INSERT INTO $table_name (file, status) VALUES ";
  
            $sm_synced_files = array_unique($sm_synced_files);
            foreach ($sm_synced_files as $key => $file) {
              array_push($files, $file, 'synced');
              $place_holders[] = "('%s', '%s')"; /* In my case, i know they will always be integers */
            }
            $query .= implode(', ', $place_holders);
            $query = $wpdb->prepare("$query ", $files);
            $wpdb->query( $query );

            delete_option('sm_synced_files');
          }
        }

        if ( $version && version_compare( $version, '2.4.0', '<' ) ){
          $sm_root_dir    = self::migrate_root_dir();
        }

        update_option( 'wp_sm_version', ud_get_stateless_media()->args[ 'version' ]  );

      }

      /**
       * Upgrade Network Enabled
       *
       */
      private static function upgrade_network() {

        $version = get_site_option( 'wp_sm_version', false );

        /**
         * Upgrade to v.1.2.0 requirements
         */
        if ( !$version || version_compare( $version, '1.2.0', '<' ) ) {

          if( $v = get_site_option( 'sm.key_file_path' ) ) {
            update_site_option( 'sm_key_file_path', $v );
            delete_site_option( 'sm.key_file_path' );
          }

          if( $v = get_option( 'sm.service_account_name' ) ) {
            update_site_option( 'sm_service_account_name', $v );
            delete_site_option( 'sm.service_account_name' );
          }

        }

        /**
         * Upgrade to v.2.4.0 requirements
         */
        if ( $version && version_compare( $version, '2.4.0', '<' ) ){

          if ( is_multisite() ) {
            $sites = get_sites();
            foreach ( $sites as $site ) {
              switch_to_blog( $site->blog_id );
              self::migrate_root_dir(true);
              restore_current_blog();
            }

          }
        }

        update_site_option( 'wp_sm_version', ud_get_stateless_media()->args[ 'version' ]  );

      }

      /**
       * Fresh install
       */
      private static function fresh_install(){
        if ( is_multisite() ) {
          $sites = get_sites();
          foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            self::migrate_root_dir(true);
            restore_current_blog();
          }
        }

      }

      /**
       * 
       * @param bool | int $multisite pass true to use multisite prefix and 2 to use site option instead of get_option.
       */
      private static function migrate_root_dir($multisite = false){
        $sm_root_dir    = get_option('sm_root_dir', '');
        $organize_media = get_option('uploads_use_yearmonth_folders');

        if( !empty( $organize_media ) && empty( $sm_root_dir ) ){
          $sm_root_dir  =  '/%date_year%/%date_month%/';
        } elseif ( !empty( $sm_root_dir ) && !empty( $organize_media ) ) {
          $sm_root_dir  =  trim($sm_root_dir, ' /') . '/%date_year%/%date_month%/';
        } elseif ( !empty( $sm_root_dir ) ) {
          // $sm_root_dir  =  $sm_root_dir;
        } elseif ( empty( $organize_media ) ) {
          $sm_root_dir  =  '';
        }

        if($multisite){
          if ( empty( $sm_root_dir ) || $sm_root_dir[0] !== '/' ) {
            $sm_root_dir = "/" . $sm_root_dir;
          }
          if(false === strpos($sm_root_dir, '/sites/%site_id%')){
            $sm_root_dir  =  '/sites/%site_id%' . $sm_root_dir;
          }
        }

        update_option( 'sm_root_dir', $sm_root_dir  );
        //managed in WP-Stateless settings (via Bucket Folder control)
        update_option( 'uploads_use_yearmonth_folders', '0'  );

        return $sm_root_dir;
      }

    }

  }

}