<?php
/**
 * Plugin Name:       AuthorX
 * Description:       Multiple Authors for Posts in WordPress
 * Version:           0.0.1
 * Requires at least: 4.1
 * Requires PHP:      5.6
 * Author:            WPFraternity
 * Author URI:        https://wpfraternity.com/
 * Text Domain:       authorx
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

 // don't call the file directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The main plugin class
 */
final class Multiple_Authors_On_Single_Post_Authorx {

    const version = '0.0.1';

    /**
     * Class construcotr
     */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Initializes a singleton instance
     *
     * @return \Multiple_Authors_On_Single_Post_Authorx
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'MAOSPAX_CONTRIBUTORS_VERSION', self::version );
        define( 'MAOSPAX_CONTRIBUTORS_FILE', __FILE__ );
        define( 'MAOSPAX_CONTRIBUTORS_PATH', __DIR__ );
        define( 'MAOSPAX_CONTRIBUTORS_URL', plugins_url( '', MAOSPAX_CONTRIBUTORS_FILE ) );
        define( 'MAOSPAX_CONTRIBUTORS_ASSETS', MAOSPAX_CONTRIBUTORS_URL . '/assets' );
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        add_action( 'add_meta_boxes', [ $this, 'maospax_register_meta_box_cb' ] );
        add_action( 'save_post', [ $this, 'maospax_save_authorx_metabox' ] );
        add_action( 'the_content', [ $this, 'maospax_modify_content_with_multiple_post_contributors' ]  );
        add_action( 'wp_enqueue_scripts', [ $this,'maospax_enqueue_style' ] );
    }

    /**
     * Register meta box(es).
     */
    public function maospax_register_meta_box_cb() {
        add_meta_box( 'maospax_meta_box', __( 'AuthorX', 'authorx' ), [ $this, 'maospax_posts_contributors_meta_box' ], 'post', 'side', 'core' );
    }

    /**
     * Meta box display callback.
     *
     * @param WP_Post $post Current post object.
     */
    public function maospax_posts_contributors_meta_box( $post ) {
        //get all user
        $blogwritters = get_users();
        //display user name with checkbox
        if ( !empty ( $blogwritters ) ) {
            foreach ( $blogwritters as $user ) {
                $user_id          = $user->ID;
                $get_meta         = get_post_meta($post->ID, 'maospax_post_author', true);
                //cehck if $get_meta is empty string to convert empty array
                $get_contributorsx = ( ''== $get_meta )  ?  [] : $get_meta;
                $checked          = in_array( $user_id, $get_contributorsx ) ? 'checked' : '';
                //set nonce field
                wp_nonce_field( 'authorx_save_contributor', 'maospax_contributors_nonce');
        ?>
			<input 
                type="checkbox"  
                name="contributors[]" 
                value="<?php echo esc_attr($user_id); ?>"
                <?php echo $checked; ?> 
            />
            <label id="label-<?php echo esc_attr($user_id); ?>"><?php echo esc_html( $user->display_name ) ; ?></label>

        <?php echo '</br>';       
            }
        }
        else {
            echo __( 'No users found.', 'authorx');
        }
    }

    /**
     * [save_post] book callback
     * check user input data and save into db
     *
     * @param array $post_id
     * 
     * @return void
     */
    public function maospax_save_authorx_metabox( $post_id ) {
        // Check if nonce is set
		if (!isset($_POST['maospax_contributors_nonce'])) {
			return $post_id;
		}
		if (!wp_verify_nonce($_POST['maospax_contributors_nonce'], 'authorx_save_contributor')) {
			return $post_id;
		}
		// Check that the logged in user has permission to edit this post
		if (!current_user_can('edit_post')) {
			return $post_id;
		}
        // verify user input data
        $contributors      = isset( $_POST['contributors'] ) ? sanitize_text_field( $_POST['contributors'] ) : [];
        //update data into db
        update_post_meta( $post_id, 'maospax_post_author', $contributors );
    }

    /**
     * display post contributor custom fields for each post
     *
     * @param string $content
     * 
     * @return void
     */
    public function maospax_modify_content_with_multiple_post_contributors( $content ) {
        if ( is_singular('post') ) {
            //get post id
            $post_id  = get_the_id();
            //get contributor data from db using single post id
            $get_meta = get_post_meta($post_id, 'maospax_post_author', true);
            if (! empty($get_meta) ) {
            ob_start();
            ?>
                <div class="authorx-wraper">
                    <h5><?php echo __('Authors:', 'authorx'); ?></h5>
                    <hr>
                    <ul class="authorx-alist">
                    <?php
                        if (is_array($get_meta)) {
                            foreach ($get_meta as $id) {
                                $user = get_user_by('ID', $id);?>
                                <li>
                                    <a href="<?php echo esc_url( get_author_posts_url(get_the_author_meta( 'ID' ) )); ?>"> 
                                    <div class="rt-avatar"><?php echo get_avatar($user, 55); ?></div> 
                                    <div class="contributor-name"><?php echo esc_html( $user->display_name ); ?></div>
                                    </a>
                                </li>
                            <?php
                            }
                        }
            ?>
                    </ul>
                </div>
            
            <?php

            $data = ob_get_clean();

            return $content . $data;
            
            } 
        }
        return $content;
    }
    /**
     * enqueue post crontributors style
     *
     * @return void
     */
    public function maospax_enqueue_style() {
		wp_enqueue_style('wppc-css', plugin_dir_url( __FILE__ ) . 'authorx-styles.css', array());
	}

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installed = get_option( 'maospax_contributors_installed' );

        if ( ! $installed ) {
            update_option( 'maospax_contributors_installed', time() );
        }

        update_option( 'maospax_contributors_version', MAOSPAX_CONTRIBUTORS_VERSION );
    }
}

/**
 * Initializes the main plugin
 *
 * @return \Multiple_Authors_On_Single_Post_Authorx
 */
function Multiple_Authors_On_Single_Post_Authorx() {
    return Multiple_Authors_On_Single_Post_Authorx::init();
}

// kick-off the plugin
Multiple_Authors_On_Single_Post_Authorx();