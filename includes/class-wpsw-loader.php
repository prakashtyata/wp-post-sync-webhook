<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 * @subpackage WP_Post_Sync_Webhook/includes
 * @author     Prakash Tyata <developer@softart.com.np>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The core plugin class.
 */
class WPSW_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since  1.0.0
     * @access protected
     * @var    array
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since  1.0.0
     * @access protected
     * @var    array
     */
    protected $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since  1.0.0
     * @access private
     */
    private function load_dependencies() {
        require_once WPSW_PLUGIN_PATH . 'admin/class-wpsw-admin.php';
        require_once WPSW_PLUGIN_PATH . 'public/class-wpsw-public.php';
        require_once WPSW_PLUGIN_PATH . 'includes/class-wpsw-sender.php';
        require_once WPSW_PLUGIN_PATH . 'includes/class-wpsw-receiver.php';
        require_once WPSW_PLUGIN_PATH . 'includes/class-wpsw-image-handler.php';
    }

    /**
     * Register all of the hooks related to the admin area.
     *
     * @since  1.0.0
     * @access private
     */
    private function define_admin_hooks() {
        $plugin_admin    = new WPSW_Admin();
        $plugin_settings = new WPSW_Settings();
        $plugin_sender   = new WPSW_Sender();

        // Admin scripts and styles.
        $this->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

        // Settings page.
        $this->add_action( 'admin_menu', $plugin_settings, 'add_settings_page' );
        $this->add_action( 'admin_init', $plugin_settings, 'register_settings' );

        // AJAX handlers.
        $this->add_action( 'wp_ajax_wpsw_test_connection', $plugin_admin, 'ajax_test_connection' );
        $this->add_action( 'wp_ajax_wpsw_sync_post', $plugin_admin, 'ajax_sync_post' );
        $this->add_action( 'wp_ajax_wpsw_sync_post_all', $plugin_admin, 'ajax_sync_post_all' );
        $this->add_action( 'wp_ajax_wpsw_search_posts', $plugin_admin, 'ajax_search_posts' );
        $this->add_action( 'wp_ajax_wpsw_get_post_titles', $plugin_admin, 'ajax_get_post_titles' );
        $this->add_action( 'wp_ajax_wpsw_sync_posts', $plugin_admin, 'ajax_sync_posts' );
        $this->add_action( 'wp_ajax_wpsw_health_check', $plugin_admin, 'ajax_health_check' );
        $this->add_action( 'wp_ajax_wpsw_clear_log', $plugin_admin, 'ajax_clear_log' );
        $this->add_action( 'wp_ajax_wpsw_get_log', $plugin_admin, 'ajax_get_log' );

        // Sender hooks.
        $this->add_action( 'save_post', $plugin_sender, 'schedule_webhook', 999, 3 );
        $this->add_action( 'rest_after_insert_post', $plugin_sender, 'rest_schedule_webhook', 999, 2 );
        $this->add_action( 'added_post_meta', $plugin_sender, 'maybe_send_on_thumbnail', 10, 4 );
        $this->add_action( 'updated_post_meta', $plugin_sender, 'maybe_send_on_thumbnail', 10, 4 );
        $this->add_action( 'shutdown', $plugin_sender, 'send_pending_webhooks' );

        // Plugin action links.
        $this->add_filter( 'plugin_action_links_' . WPSW_PLUGIN_BASENAME, $plugin_admin, 'add_action_links' );
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since  1.0.0
     * @access private
     */
    private function define_public_hooks() {
        $plugin_receiver = new WPSW_Receiver();
        $this->add_action( 'rest_api_init', $plugin_receiver, 'register_routes' );
    }

    /**
     * Add a new action to the collection.
     *
     * @since 1.0.0
     * @param string $hook          The name of the WordPress action.
     * @param object $component     A reference to the instance of the object.
     * @param string $callback      The name of the function.
     * @param int    $priority      Optional. The priority. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments. Default is 1.
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new filter to the collection.
     *
     * @since 1.0.0
     * @param string $hook          The name of the WordPress filter.
     * @param object $component     A reference to the instance of the object.
     * @param string $callback      The name of the function.
     * @param int    $priority      Optional. The priority. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments. Default is 1.
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * A utility function to register hooks.
     *
     * @since  1.0.0
     * @access private
     * @param  array  $hooks         The collection of hooks.
     * @param  string $hook          The name of the WordPress hook.
     * @param  object $component     A reference to the instance of the object.
     * @param  string $callback      The name of the function.
     * @param  int    $priority      The priority.
     * @param  int    $accepted_args The number of arguments.
     * @return array
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since 1.0.0
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}