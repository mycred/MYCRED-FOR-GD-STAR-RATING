<?php
/**
 * Plugin Name: myCRED for GD Star Rating
 * Plugin URI: http://mycred.me
 * Description: Allows you to reward users with points for rating content.
 * Version: 1.0.1
 * Tags: mycred, points, gd-star, rating
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.6.1
 * Text Domain: mycred_gd_star
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
if ( ! class_exists( 'myCRED_GD_Star_Rating' ) ) :
	final class myCRED_GD_Star_Rating {

		// Plugin Version
		public $version             = '1.0';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-gd-star-rating';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_gd_star';
			$this->plugin_name = 'myCRED for GD Star Rating';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_setup_hooks',    array( $this, 'register_hook' ) );
			add_action( 'mycred_init',           array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references', array( $this, 'add_badge_support' ) );
			add_action( 'mycred_load_hooks',     'mycred_load_gd_star_rating_hook' );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_GD_STAR_SLUG',     $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY', 'mycred_default' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 320 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 320, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 320, 3 );

		}

		/**
		 * Register Hook
		 * @since 1.0
		 * @version 1.0.1
		 */
		public function register_hook( $installed ) {

			if ( ! function_exists( 'gdrts' ) ) return $installed;

			$installed['gdstars'] = array(
				'title'       => __( 'GD Star Rating', 'mycred_gd_star' ),
				'description' => __( 'Awards %_plural% for users rate items using the GD Star Rating plugin.', 'mycred_gd_star' ),
				'callback'    => array( 'myCRED_Hook_GD_Star_Rating' )
			);

			return $installed;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_badge_support( $references ) {

			if ( ! function_exists( 'gdrts' ) ) return $references;

			$references['star_rating'] = __( 'Rating Content (GD Star Rating)', 'mycred_gd_star' );
			$references['star_rated']  = __( 'Receiving a Rating (GD Star Rating)', 'mycred_gd_star' );

			return $references;

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', 'mycred_gd_star' ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', 'mycred_gd_star' )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_gd_star_rating_plugin() {
	return myCRED_GD_Star_Rating::instance();
}
mycred_gd_star_rating_plugin();

/**
 * GD Star Rating Hook
 * @since 1.0
 * @version 1.0
 */
if ( ! function_exists( 'mycred_load_gd_star_rating_hook' ) ) :
	function mycred_load_gd_star_rating_hook() {

		if ( class_exists( 'myCRED_Hook_GD_Star_Rating' ) || ! function_exists( 'gdrts' ) ) return;

		class myCRED_Hook_GD_Star_Rating extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'gdstars',
					'defaults' => array(
						'star_rating' => array(
							'creds'       => 1,
							'log'         => '%plural% for rating',
							'limit'       => '0/x'
						),
						'star_rated'  => array(
							'creds'       => 1,
							'log'         => '%plural% for rated content',
							'limit'       => '0/x'
						)
					)
				), $hook_prefs, $type );

			}

			/**
			 * Run
			 * @since 1.0
			 * @version 1.0
			 */
			public function run() {

				add_action( 'gdrts_db_vote_logged', array( $this, 'new_rating' ), 10, 3 );

			}

			/**
			 * Vote
			 * @since 1.0
			 * @version 1.0.1
			 */
			public function new_rating( $log_id, $data, $meta ) {

				if ( ! is_user_logged_in() ) return;

				extract( $data );

				$run  = true;
				$item = get_post( $item_id );
				if ( ! isset( $item->post_author ) || $user_id == $item->post_author )
					$run = false;

				// Reward the rating
				if ( $run && $this->prefs['star_rating']['creds'] != 0 ) {

					if ( ! $this->over_hook_limit( 'star_rating', 'star_rating', $user_id ) )
						$this->core->add_creds(
							'star_rating',
							$user_id,
							$this->prefs['star_rating']['creds'],
							$this->prefs['star_rating']['log'],
							$item_id,
							array( 'ref_type' => 'post', 'rating' => $meta['vote'] ),
							$this->mycred_type
						);

				}

				// Reward getting rated
				if ( $run && $this->prefs['star_rated']['creds'] != 0 ) {

					if ( ! $this->over_hook_limit( 'star_rated', 'star_rated', $user_id ) )
						$this->core->add_creds(
							'star_rated',
							$item->post_author,
							$this->prefs['star_rated']['creds'],
							$this->prefs['star_rated']['log'],
							$item_id,
							array( 'ref_type' => 'post', 'rating' => $meta['vote'] ),
							$this->mycred_type
						);

				}

			}

			/**
			 * Preferences
			 * @since 1.0
			 * @version 1.0.1
			 */
			public function preferences() {

				$prefs = $this->prefs;

?>
<label class="subheader" for="<?php echo $this->field_id( array( 'star_rating' => 'creds' ) ); ?>"><?php _e( 'Rating', 'mycred_gd_star' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'star_rating' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'star_rating' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['star_rating']['creds'] ); ?>" size="8" /></div>
		<span class="description"><?php _e( 'Authors rating their own content will NOT receive points!', 'mycred_gd_star' ); ?></span>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'star_rating' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred_gd_star' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'star_rating' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'star_rating' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['star_rating']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<label class="subheader"><?php _e( 'Limit', 'mycred_gd_star' ); ?></label>
<ol>
	<li>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'star_rating' => 'limit' ) ), $this->field_id( array( 'star_rating' => 'limit' ) ), $prefs['star_rating']['limit'] ); ?>
		<span class="description"><?php _e( 'The number of times a user can receive points for rating is based on your rating setup. If you only allow users to rate content once, then they can only gain points once. If they can vote multiple times, they can also receive points multiple times. The limit settings in this hook is only applicable if users can vote more then once.', 'mycred_gd_star' ); ?></span>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'star_rated' => 'creds' ) ); ?>"><?php _e( 'Receiving a Rating', 'mycred_gd_star' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'star_rated' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'star_rated' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['star_rated']['creds'] ); ?>" size="8" /></div>
		<span class="description"><?php _e( 'Authors rating their own content will NOT receive points!', 'mycred_gd_star' ); ?></span>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'star_rated' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred_gd_star' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'star_rated' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'star_rated' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['star_rated']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<label class="subheader"><?php _e( 'Limit', 'mycred_gd_star' ); ?></label>
<ol>
	<li>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'star_rated' => 'limit' ) ), $this->field_id( array( 'star_rated' => 'limit' ) ), $prefs['star_rated']['limit'] ); ?>
	</li>
</ol>
<?php

			}

			/**
			 * Sanitise Preferences
			 * @since 1.0.1
			 * @version 1.0
			 */
			function sanitise_preferences( $data ) {

				if ( isset( $data['star_rating']['limit'] ) && isset( $data['star_rating']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['star_rating']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['star_rating']['limit'] = $limit . '/' . $data['star_rating']['limit_by'];
					unset( $data['star_rating']['limit_by'] );
				}

				if ( isset( $data['star_rated']['limit'] ) && isset( $data['star_rated']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['star_rated']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['star_rated']['limit'] = $limit . '/' . $data['star_rated']['limit_by'];
					unset( $data['star_rated']['limit_by'] );
				}

				return $data;

			}

		}

	}
endif;
