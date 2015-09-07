<?php

/**
 * Plugin Name: Comments Since Last Visit, Reloaded
 * Description: Highlights new comments since users last visit, with fast jQuery scrolling through new comments and display of new comments only
 * Plugin URI: http://www.ckmacleod.com/plugins/comments-since-last-visit/
 * Version:     1.0
 * Author:      CK MacLeod
 * Author URI:  http://www.ckmacleod.com/
 * License:     GPL 2.0
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
     * re-written for persistent highlighting
     * sets a session cookie (like a session ID) for 15 minutes [to be made adjustable]
     * during which "legacy" highlighting will remain persistent
     * 
     */
    public function cookie() {


        /*TESTING VERSION ADDING PRECISE CURRENT TIME/THREAD: 
         * FIRST DRAFT PRODUCES "PURE NEW SINCE LAST VISIT" DOES NOT "PERSIST"
         */


        // We only want this on singular views
        if ( is_singular() ) {

            //key variables defaulted at 600 seconds or 15 minutes for session span,
            //90 days for last visit cookies to automatically expire

            $session_span = 900;
            $cookie_lookback = 3600*2160;


            // Get current post ID //check for use of ID
            $id = get_the_ID();

            // Get current time
            $current_time = strtotime( current_time( 'mysql' ) );

            //get the status of key variables

            $pvfb = json_decode( stripslashes( $_COOKIE['pvfb'] ), true );

            $prev_visit = json_decode( stripslashes( $_COOKIE['prev_visit'] ), true );

            $new_session = json_decode( stripslashes( $_COOKIE['new_session'] ), true );



            //if fallback variable not set, then this is our first time at the thread
            //set all three values at currenttime, with session variable to expire in session time;

            if (!isset($_COOKIE['pvfb'])) {

                $pvfb[$id] = $current_time;
                $new_session[$id] = $current_time;
                $prev_visit[$id] = $current_time;

                setcookie('new_session', json_encode( $new_session), time() + $session_span );
                setcookie('prev_visit', json_encode( $prev_visit ), time() + $cookie_lookback );
                setcookie('pvfb', json_encode( $pvfb ), time() + $cookie_lookback );

            } else {
                //fallback set and $new_session unexpired
                if (isset($_COOKIE['new_session'])) {

                    $pvfb[$id] = $current_time;
                    setcookie('pvfb', json_encode( $pvfb), time() + $cookie_lookback );


                }
            }

            if ( (isset($_COOKIE['pvfb'])) && (!isset($_COOKIE['new_session'])) ) {

                 //fallback set and $new_session expired

                    $prev_visit[$id] = $pvfb[$id];

                    setcookie('prev_visit', json_encode( $prev_visit ), time() + $cookie_lookback );


                    $pvfb[$id] = $current_time;

                    $new_session[$id] = $current_time;

                    setcookie('new_session',json_encode( $new_session ), time() + $session_span );
                    setcookie('pvfb', json_encode( $pvfb), time() + $cookie_lookback );

            } 

        }

    }


    /**
     * Modify comment_class on comments made since last visit
     *
     * @since 1.0
     * @uses comment_class filter
     * @return $classes variable. CSS classes for single comment.
     * got weird "join" error on "new" format for uncommented threads:  so replaced with old logic - have to watch for it
     */
    public function comment_class( $classes ) {
        
        if ( !isset($_POST['mark-all-read']) ) {
                
            $pvfb = json_decode( stripslashes( $_COOKIE['pvfb'] ), true );

            $prev_visit = json_decode( stripslashes( $_COOKIE['prev_visit'] ), true );

            $new_session = json_decode( stripslashes( $_COOKIE['new_session'] ), true );

            $id = get_the_ID();

            // Get time for comment
            $comment_time = strtotime( get_comment_date( 'Y-m-d G:i:s' ) );
            
            if ( !empty($prev_visit[$id])  &&  !empty($new_session[$id]) ) {
            
            //basically: if PREV VIS

            //both prev-visit set AND new session unexpired - here and next:
            //set cookie cleaners
            //if ( isset( $_COOKIE['prev_visit'] ) && isset( $_COOKIE['new_session'] ) ) {
                    $latest_visit = json_decode( stripslashes( $_COOKIE['prev_visit']), true );
                    //attempt to circumvent bad results on certain threads in which lvfb is null for prev-vist
            }

            //pvfb exists but session has expired
            //if ( isset ( $COOKIE['pvfb'] ) && !isset( $_COOKIE['new_session'] ) ) {
            if ( !empty($pvfb[$id])  &&  empty($new_session[$id]) ) {
                    $latest_visit = json_decode( stripslashes( $_COOKIE['pvfb']), true );
            }

            if (empty($latest_visit[$id])) { 

                    $latest_visit[$id] = strtotime( current_time( 'mysql' ) );

            }

            // Add new-comment class if the comment was posted since user's last visit

            // if ( !empty($latest_visit[$id])) { 
                
           //works, now test if deny whole function if ( ($comment_time >= $latest_visit[$id]) && (!isset($_POST['mark-all-read'])) )  {
            //prior version
            if  ($comment_time >= $latest_visit[$id])   {

                $classes[] = 'new-comment';
                
            }
            //}
        }   
        
        return $classes;
        
    }
                

    
    /* 
     * Outputs the Comments Since Last Visit Reloaded heading
     *      
     */ 
    public function cslvr_heading() { 
        
        if ( in_the_loop() ) {
      
                if (isset($_COOKIE['prev_visit']) )  {
            
                    $id = get_the_ID();

                    $prev_visit = json_decode( stripslashes( $_COOKIE['prev_visit']), true );

                    $prev_visit_here = $prev_visit[$id];

                    if ( !isset($_POST['mark-all-read']) ) { 
                        
                        if (!empty($prev_visit_here)) {
                        
                            $xoutput =  '<div id="cslvr-buttons" />';
                            $xoutput .=  '<button type="button" id="show-hide-cslvr-button" onclick="cslvr_only()" class="button" title="Show/Hide New Since Last Visit Comments" />Show New Comments Only</button>';

                            $xoutput .=  '<button type="button" id="go-to-next-top-button" onclick="cslvr_next()" class="button" title="Scroll Through New Comments" alt="Go to Next Clicker" />Go to New Comments &#x21C5;</button>';

                            $xoutput .= '<button type="button" id="cslvr-sort-button" onclick="cslvr_sort()" class="button" title="Sort Chronologically" alt"Sort Chronologically" />Sort by Date/Time</button>';
                            
                            $xoutput .= '<div id="go-to-next-messages"></div>';
                           
                            $xoutput .= '<div id="show-only-messages"></div>';

                            $xoutput .= '</div>';

                            $xoutput .=  '<div id="cslvr-comments-heading">';	

                            $xoutput .=  '<h2>Since ' . date( 'M j, Y @ G:i', $prev_visit_here) . ':</h2>';

                            $xoutput .=  '</div>';   
                        
                        } else {
                        
                            $xoutput =  '<div id="cslvr-first-visit">';	

                            $xoutput .=  '<h2>Session re-started.</h2>'; 

                            $xoutput .=  '</div>';
                        }

                    } else {
                
                        //check for functionality not sure am getting it
                        //not sure if I need following
                        //if (empty($prev_visit_here)) {
                        $xoutput =  '<div id="cslvr-first-visit">';	

                        $xoutput .=  '<h2>Session re-started.</h2>'; 

                        $xoutput .=  '</div>';
                           
                    }
            }         
        
        //COMMENT IN OR OUT FOR DEBUGGING INFO
 //      $xoutput .= WP_CSLVR::debug_cslvr();

        return $xoutput;
        
        }
    
    }
    
    /* 
     * Outputs the Mark All Read Button
     *      
     */ 
    public function mark_all_read_button() {
        
        if (!isset($_POST['mark-all-read'])) {
            
            $id = get_the_ID();
            
            $prev_visit = json_decode( stripslashes( $_COOKIE['prev_visit']), true );
            
            $prev_visit_here = $prev_visit[$id];
        
            if (!empty($prev_visit_here)) {
        
                $marb = '<div id="cslvr-mark-all-read">';

                $marb .= '<form method="post" action="">';

                $marb .= '<input id="cslvr-mark-all-read-button" class="button" type="submit" name="submit" value="Mark All Read" title="Remove New Formatting/Reset Session" />';

                $marb .= '<input type="hidden" name="mark-all-read" value="' . $id . '" />';

                $marb .= '</form>';

                $marb .= '</div>';

                return $marb;
            }
        }
    }
    
    public function update_cslv_session() {
        
        if ( isset($_POST['mark-all-read'])) {
            
            $id = absint($_POST['mark-all-read']);
            $current_time = strtotime( current_time( 'mysql' ) );
            //possibly better set as globals or as admin options since used earlier or wait as sep function
            $session_span = 900;
            $cookie_lookback = 3600*2160;
            
             $pvfb[$id] = $current_time;
             $new_session[$id] = $current_time;
             $prev_visit[$id] = $current_time;

             setcookie('new_session', json_encode( $new_session), time() + $session_span );
             setcookie('prev_visit', json_encode( $prev_visit ), time() + $cookie_lookback );
             setcookie('pvfb', json_encode( $pvfb ), time() + $cookie_lookback );
            
        }
    }

  
    public function debug_cslvr() {
      
//  comment in/out for admin only debugging (also see below)
    //  if ( current_user_can('update_plugins')) {

            $id = get_the_ID();

            $prev_visit = json_decode( stripslashes( $_COOKIE['prev_visit']), true );

            $prev_visit_here = $prev_visit[$id];

            $new_session = json_decode( stripslashes( $_COOKIE['new_session']), true );

            $new_session_here = $new_session[$id];

            $pvfb = json_decode( stripslashes( $_COOKIE['pvfb']), true );

            $pvfb_here = $pvfb[$id];

            $output = '<p>PREV_VISIT[ID]: ' .  date( 'M j, Y @ G:i', $prev_visit_here) . ' ( '. $prev_visit_here . ')</p>';

            if (isset($_COOKIE['prev_visit'])) { $output .= '/ PREV VISIT SET';}
            if (empty($prev_visit[$id])) { $output .= '/ PREV VISIT EMPTY';}

            $output .= '<hr />';

            $output .= '<p>NEW_SESS[ID]: ' .  date( 'M j, Y @ G:i', $new_session_here) . ' ('. $new_session_here . ')</p>';

            if (isset($_COOKIE['new_session'])) { $output .= '/ NEW SESS SET';}
            if (empty($new_session[$id])) { $output .= '/ NEW SESSID EMPTY';}

            $output .= '<hr />';

            $output .= '<p>PVFB[ID]: ' .  date( 'M j, Y @ G:i', $pvfb_here) . ' ('. $pvfb_here . ')</p>';
             if (isset($_COOKIE['pvfb'])) { $output .= '/ PVFB SET';}
            if (empty($pvfb[$id])) { $output .= '/ PVFB EMPTY';}
            
           $output .= '<hr/><p>DEBUG LATEST VISIT</p>';
            
            //both prev-visit set AND new session unexpired
            if ( isset( $_COOKIE['prev_visit'] ) && isset($_COOKIE['new_session'])) {

                $latest_visit = json_decode( stripslashes( $_COOKIE['prev_visit']), true );
                $output .= '<p>PREV VISIT SET, NEW SESSION SET / ';

            }

            //pvfb exists but session has expired
            if ( isset ( $COOKIE['pvfb'] ) && !isset($_COOKIE['new_session']) ) {
                    $latest_visit = json_decode( stripslashes( $_COOKIE['pvfb']), true );
             $output .= '<p>PVFB SET, NEW SESSION UNSET / ';
            }
            
             $output .= 'LV ID: ' . $latest_visit[$id] . '</p>';       
                    
        return $output;
        
// comment in/out for admin-only debugging
//      }        
    }
    
    /**
     * Adds the "clicker" on new comments for scrolling
     * between them
     *
     */ 
    public function gtn_clicker($content) {

        global $comment;

        $gtn_clicker = '<div class="gtn_clicker" title="Scroll Through New Comments" alt="Go to Next Clicker" />&#x21C5;</div>';

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
     * enqueues the plugin stylesheet
     * between them
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


/* add <?php if (function_exists('cslvr_heading') ) { cslvr_heading(); } ?>
 * in appearance/editor/[theme]/comments.php ********************************
 * where clsvr main buttons are to appear (for instance, above "comment list")**
 * **/
function cslvr_heading() {
    $cslvr = new WP_CSLVR(); 
    echo $cslvr->cslvr_heading();
}

/* add <?php if (function_exists('mark_all_read_button') ) { mark_all_read_button(); } ?>
 * in appearance/editor/[theme]/comments.php ********************************
 * where clsvr main button are to appear (for instance, above "comment list")***
 * **/
function mark_all_read_button() {
    $cslvr = new WP_CSLVR();
    echo $cslvr->mark_all_read_button();
}
