<?php

/**
 * Plugin Name: Comments Since Last Visit, Reloaded
 * Description: Highlights new comments since user's last visit, with jQuery scrolling and sorting options, especially useful for busy sites with nested comments and rich conversations
 * Plugin URI: http://www.ckmacleod.com/plugins/comments-since-last-visit/
 * Version:     1.0
 * Author:      CK MacLeod
 * Author URI:  http://www.ckmacleod.com/
 * License:     GPL 2.0
 * Date:        September 11, 2015
 * 
 */

/*
 * Note:        Built on foundation provided by * John Parris, http://www.johnparris.com/wordpress-plugins/comments-since-last-visit/, itself developed from idea by Natko Hasic http://natko.com/highlighting-the-comments-since-your-last-visit/ 
 */

defined( 'ABSPATH' ) or die( 'Plugin file cannot be accessed directly.' );

add_action(
	'plugins_loaded',
	array ( WP_CSLVR::get_instance(), 'plugin_setup' )
);

class WP_CSLVR {

    /**
     * Plugin instance.
     *
     * @see  get_instance()
     * @type object
     */
    protected static $instance = NULL;

    /**
     * URL to this plugin's directory.
     *
     * @type string
     */
    public $plugin_url = '';

    /**
     * Path to this plugin's directory.
     *
     * @type string
     */
    public $plugin_path = '';

    /**
     * Access this plugin's working instance
     *
     * @wp-hook plugins_loaded
     * @since   1.0
     * @return  object of this class
     */
    public static function get_instance()
    {
            NULL === self::$instance and self::$instance = new self;

            return self::$instance;
    }

    /**
     * Used for regular plugin work.
     *
     * @wp-hook plugins_loaded
     * @since   1.0
     * @return  void
     */
    public function plugin_setup()
    {

            $this->plugin_url  = plugins_url( '/', __FILE__ );
            $this->plugin_path = plugin_dir_path( __FILE__ );
            $this->load_language( 'wp-cslvr' );

            // Register actions and filters
            add_action( 'get_header',           array( $this, 'cookie' ) );
            add_filter( 'comment_class',        array( $this, 'comment_class' ) );
            add_filter( 'http_request_args',    array( $this, 'prevent_public_updates' ), 5, 2 );
            add_action( 'wp_enqueue_scripts',   array( $this, 'show_cslvr_only' ) );
            add_filter( 'comment_reply_link',   array( $this, 'gtn_clicker'), 101 );
            add_action( 'wp_enqueue_scripts',   array( $this, 'cslvr_stylesheet' ) ); 
            add_action( 'init',                 array( $this, 'update_cslv_session') );

    }

    /**
     * Constructor. Intentionally left empty and public.
     *
     * @see   plugin_setup()
     * @since 1.0
     */
    public function __construct() {}

    /**
     * Loads translation file.
     *
     * Accessible to other classes to load different language files (admin and
     * front-end for example).
     *
     * @wp-hook init
     * @param   string $domain
     * @since   1.0
     * @return  void
     */
    public function load_language( $domain ) {

        load_plugin_textdomain(
            $domain,
            FALSE,
            $this->plugin_path . 'languages'
        );
    }

    /**
     * 
     * @ sets a session cookie (like a session ID) for set span (15 minute default)
     * @ during which "legacy" highlighting will remain persistent
     * @ unique to each post
     * 
     */
    public function cookie() {

        // We only want this on singular views
        if ( is_singular() ) {

            //key variables defaulted at 600 seconds or 15 minutes for session span,
            //30 days until last visit cookies automatically expire
            $session_span = 900;
            
            $cookie_lookback = 3600*720;

            // Get current time
            $current_time = strtotime( current_time( 'mysql' ) );

            //get the status of key variables

            $lvfb = $_COOKIE['lvfb'];

            $last_visit = $_COOKIE['last_visit'];

            $cur_session = $_COOKIE['cur_session'];
            
            //clear past cookies, weed out browser errors/intrusions

            if (!isset($_COOKIE['lvfb']) | !is_numeric($lvfb ) | !is_numeric($cur_session) | !is_numeric($last_visit) ) {

                setcookie('cur_session', $current_time, time() + $session_span );
                setcookie('last_visit', $current_time, time() + $cookie_lookback );
                setcookie('lvfb', $current_time, time() + $cookie_lookback );

            } else {
                //fallback set and $cur_session unexpired
                if (isset($_COOKIE['cur_session'])) {

                    setcookie('lvfb', $current_time, time() + $cookie_lookback );

                }
            }

            if ( (isset($_COOKIE['lvfb'])) && (!isset($_COOKIE['cur_session'])) ) {

                 //fallback set and $cur_session expired

                    $last_visit = $lvfb;

                    setcookie('last_visit', $last_visit, time() + $cookie_lookback );
                    
                    setcookie('lvfb', $current_time, time() + $cookie_lookback );

                    setcookie('cur_session', $current_time, time() + $session_span );

            } 

        }

    }


    /**
     * Modify comment_class on comments made since last visit
     * 
     * @uses comment_class filter
     * @replaces previous visit variable fallback variable set at last visit
     * @return $classes variable. CSS classes for single comment.
     * 
     */
    public function comment_class( $classes ) {
        
        if ( !isset($_GET['mar']) ) {
                
            $lvfb = $_COOKIE['lvfb'];

            $last_visit = $_COOKIE['last_visit'] ;

            $cur_session = $_COOKIE['cur_session'] ;

            // Get time for comment
            $comment_time = strtotime( get_comment_date( 'Y-m-d G:i:s' ) );
            
            if ( !empty($last_visit) && !empty($cur_session) ) {
            
                $latest_visit = $_COOKIE['last_visit'];
            
            }

            //lvfb exists but session has expired
            if ( !empty($lvfb)  &&  empty($cur_session) ) {
                    $latest_visit = $_COOKIE['lvfb'];
            }

            if (empty($latest_visit)) { 

                    $latest_visit = strtotime( current_time( 'mysql' ) );

            }

            // Add new-comment class if the comment was posted since user's last visit

            if  ($comment_time >= $latest_visit)  {

                $classes[] = 'new-comment';
                
            }

        }   
        
        return $classes;
        
    }

    
    /* 
     * Outputs the Comments Since Last Visit Reloaded heading
     * Contains all buttons at top     
     */ 
    public function cslvr_heading() { 
      
        if (isset($_COOKIE['last_visit']) )  {

            $id = get_the_ID();

            $last_visit = $_COOKIE['last_visit'];

            if ( !isset($_GET['mar']) ) { 

                if (!empty($last_visit)) {

                    $xoutput =  '<div id="cslvr-buttons" />';
                    $xoutput .=  '<button type="button" id="show-hide-cslvr-button" onclick="cslvr_only()" class="showhide-button button" title="Show/Hide New Since Last Visit Comments" />Show New Comments Only</button>';

                    $xoutput .=  '<button type="button" id="go-to-next-top-button" onclick="cslvr_next()" class="button" title="Scroll Through New Comments" alt="Go to Next Clicker" />Go to New Comments &#x21F5;</button>';

                    $xoutput .= '<button type="button" id="cslvr-sort-button" onclick="cslvr_sort()" class="button" title="Sort Chronologically" alt"Sort Chronologically" />Sort Newest First</button>';

                    $xoutput .= '<div id="cslvr-top-messages">';

                    $xoutput .= '<div id="go-to-next-messages"></div>';

                    $xoutput .= '<div id="cslvr-sorted-messages"></div>';

                    $xoutput .= '<div id="show-only-messages"></div>';

                    $xoutput .= '</div>';

                    $xoutput .= '</div>';

                    $xoutput .= '<div id="cslvr-comments-heading" class="comment-list" />';	

                    $xoutput .= '<h2>Since ' . date( 'M j, Y @ G:i', $last_visit) . ':</h2>';

                    $xoutput .= '</div>';   

                } else {

                    $xoutput =  '<div id="cslvr-first-visit">';	

                    $xoutput .=  '<h2>Session re-started.</h2>'; 

                    $xoutput .=  '</div>';
                }

            } else {

                //check for functionality not sure am getting it
                //not sure if I need following
                //if (empty($last_visit_here)) {
                $xoutput =  '<div id="cslvr-first-visit">';	

                $xoutput .=  '<h2>Session re-started.</h2>'; 

                $xoutput .=  '</div>';

            }
        }         
        
        //COMMENT IN OR OUT FOR DEBUGGING INFO
 //      $xoutput .= WP_CSLVR::debug_cslvr();

        return $xoutput;
    
    }
    
    /* 
     * Outputs the Mark All Read Button
     * @sets "mar" get parameter - in earlier testing GET proved more stable than POST
     * @could have been feature of prior complexity, but does not harm and may in some 
     * @case induces caches to re-set, too     
     */ 
    public function mark_all_read_button() {
        
        $marb = '<div id="cslvr-mark-all-read">';

        $marb .= '<form method="get" action="">';

        $marb .= '<input id="cslvr-mark-all-read-button" class="button" type="submit" name="submit" value="Mark All Read" title="Remove New Formatting/Reset Session" />';

        $marb .= '<input type="hidden" name="mar" value="1" />';

        $marb .= '</form>';

        $marb .= '</div>';

        return $marb;

    }
    
    
    /* 
     * Outputs both buttons at bottom of comment thread
     *      
     */ 
    public function cslvr_bottom_buttons() {
        
        if (!isset($_GET['mar'])) {
                      
            $last_visit = $_COOKIE['last_visit'];
        
            if (!empty($last_visit)) {
                
                $cbottom = '<div id="cslvr_bottom_buttons">';
                $cbottom .= '<button type="button" id="show-hide-cslvr-button-bottom" class="showhide-button button" onclick="cslvr_only()"  title="Show/Hide New Since Last Visit Comments" />Show New Comments Only</button>';
                $cbottom .= WP_CSLVR::mark_all_read_button();
                $cbottom .= '</div>';
            }
        }
        return $cbottom;
    }
    
    /*
     * Re-sets all session cookies if Mark-All-Read/Session-ReSet 
     * 
     */  
    public function update_cslv_session() {
        
        if ( isset($_GET['mar'])) {
            
            $current_time = strtotime( current_time( 'mysql' ) );
            
            //to be set as admin options
            $session_span = 900;
            
            $cookie_lookback = 3600*720;
            
             setcookie('lvfb', $current_time, time() + $cookie_lookback );
             
             setcookie('cur_session', $current_time, time() + $session_span );
             
             setcookie('last_visit', $current_time, time() + $cookie_lookback );
                    
        }
    }

    
    /**
     * Adds the "clicker" on new comments for scrolling
     * between them
     *
     */ 
    public function gtn_clicker($content) {

        $gtn_clicker = '<div class="gtn_clicker" title="Scroll Through New Comments" alt="Go to Next Clicker" />&#x21F5;</div>';

        return $content.$gtn_clicker;
    }    

    /**
     * Enqueues the jQuery scripts
     *
     */ 
    public function show_cslvr_only() {

        wp_enqueue_script( 
            'cslvr_only', 
                plugins_url('show_cslvr_only.js',__FILE__), 
                array( 'jquery' )
        );

        wp_enqueue_script(
            'cslvr_next',
                plugins_url('show_cslvr_only.js',__FILE__),
                array( 'jquery' )
        );
        
        wp_enqueue_script(
             'cslvr_sort',
                 plugins_url('show_cslvr_only.js',__FILE__),
                array( 'jquery' )
        );
                
    }    

    /**
     * Enqueues the plugin stylesheet
     *
     */ 
    public function cslvr_stylesheet() {
         wp_enqueue_style('cslvr_style', plugins_url('style.css',__FILE__));
} 

    /**
     * Prevents the plugin from being updated from the WP public repo.
     *
     * In case someone adds a plugin to the wordpress.org repo with the same name.
     *
     * @since 1.0.1
     */
    function prevent_public_updates( $r, $url ) {

        if ( 0 === strpos( $url, 'https://api.wordpress.org/plugins/update-check/1.1/' ) ) {

            $plugins = json_decode( $r['body']['plugins'], true );
            unset( $plugins['plugins'][plugin_basename( __FILE__ )] );
            $r['body']['plugins'] = json_encode( $plugins );
        }

        return $r;

    }  

} //class


/* 
 * outside of Class for easy placement in appearance/editor/[theme]/comments.php 
 * add <?php if (function_exists('cslvr_heading') ) { cslvr_heading(); } ?>
 * where clsvr main buttons are to appear (for instance, above "comment list")**
 * **/
function cslvr_heading() {
    $cslvr = new WP_CSLVR(); 
    echo $cslvr->cslvr_heading();
}

/* add <?php if (function_exists('cslvr_bottom_buttons') ) { cslvr_bottom_buttons(); } ?>
 * in appearance/editor/[theme]/comments.php ********************************
 * where Mark All Read button is to appear (for instance, after "comment list")***
 * **/
function cslvr_bottom_buttons() {
    $cslvr = new WP_CSLVR();
    echo $cslvr->cslvr_bottom_buttons();
}
