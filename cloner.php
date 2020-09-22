<?php
	/**
	 * Plugin Name: Cloner
	 * Description: Clone posts, pages and custom post types easily
	 * Author: biohzrdmx
	 * Version: 1.0
	 * Plugin URI: http://github.com/biohzrdmx/wp-cloner
	 * Author URI: http://github.com/biohzrdmx/
	 */

	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	if( ! class_exists('Cloner') ) {

		/**
		 * Cloner class
		 */
		class Cloner {

			public static function init() {
				$folder = dirname( plugin_basename(__FILE__) );
				$ret = load_plugin_textdomain('cloner', false, "{$folder}/lang");
			}

			public static function actionAdminMenu() {
				add_menu_page('Cloner', 'Cloner', 'manage_options', 'cloner', 'Cloner::callbackAdminPage', 'dashicons-welcome-add-page');
			}

			public static function actionEnqueueScripts($hook) {
				if( $hook != 'toplevel_page_cloner' ) {
					return;
				}
				wp_enqueue_style( 'cloner_admin_css', plugins_url('cloner.css', __FILE__) );
				wp_enqueue_script( 'cloner_admin_js', plugins_url('cloner.js', __FILE__), array('jquery') );
			}

			public static function actionAdminInit() {
				register_setting( 'cloner', 'cloner_options' );
				add_settings_section( 'cloner_settings', __( 'Supported post types', 'cloner' ), 'Cloner::callbackSettings', 'cloner' );
				add_settings_field( "cloner_field_post", __('Posts', 'cloner'), 'Cloner::fieldToggle', 'cloner', 'cloner_settings', [ 'label_for' => "cloner_field_post", 'class' => 'cloner_row' ] );
				add_settings_field( "cloner_field_page", __('Pages', 'cloner'), 'Cloner::fieldToggle', 'cloner', 'cloner_settings', [ 'label_for' => "cloner_field_page", 'class' => 'cloner_row' ] );
				$args = array(
					'public'   => true,
					'_builtin' => false
				);
				$types = get_post_types($args, 'objects', 'AND');
				if ($types) {
					foreach ($types as $type) {
						add_settings_field( "cloner_field_{$type->name}", __($type->label, 'cloner'), 'Cloner::fieldToggle', 'cloner', 'cloner_settings', [ 'label_for' => "cloner_field_{$type->name}", 'class' => 'cloner_row' ] );
					}
				}
			}

			public static function adminSettingsLink($links, $file) {
				$folder = dirname( plugin_basename(__FILE__) );
				$links = (array) $links;
				if ( $file === "{$folder}/cloner.php" && current_user_can( 'manage_options' ) ) {
					$url = admin_url('admin.php?page=cloner');
					$link = sprintf( '<a href="%s">%s</a>', $url, __( 'Settings', 'cloner' ) );
					array_unshift($links, $link);
				}
				return $links;
			}

			public static function fieldToggle($args) {
				$options = get_option( 'cloner_options' );
				?>
					<input type="checkbox" class="js-toggle-switch" id="<?php echo esc_attr( $args['label_for'] ); ?>" <?php echo ( isset( $options[ $args['label_for'] ] ) ? 'checked="checked"' : '' ); ?> name="cloner_options[<?php echo esc_attr( $args['label_for'] ); ?>]" value="1">
				<?php
			}

			public static function callbackAdminPage() {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				if ( isset( $_GET['post'] ) ) {
					$post_id = $_GET['post'];
					$clone_id = self::duplicate($post_id);
					$clone = get_post( $clone_id );
					#
					add_settings_error( 'cloner_messages', 'cloner_message', __( 'Post cloned successfully', 'cloner' ), 'updated' );
					#
					settings_errors( 'cloner_messages' );
					?>
						<div class="wrap">
							<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
							<p><?php printf( __( 'The post "%s" has been successfully cloned.', 'cloner' ), $clone->post_title ); ?></p>
							<p><?php _e( 'Please wait while you are being be redirected or ', 'cloner' ); ?> <a href="<?php echo admin_url("post.php?post={$clone->ID}&amp;action=edit") ?>"><?php _e( 'click here to edit the clone.', 'cloner' ); ?></a></p>
							<script type="text/javascript">
								(function(w) {
									setTimeout(function() {
										w.location.href = "<?php echo admin_url("post.php?post={$clone->ID}&action=edit") ?>";
									}, 2000);
								})(window);
							</script>
						</div>
					<?php
				} else {
					if ( isset( $_GET['settings-updated'] ) ) {
						add_settings_error( 'cloner_messages', 'cloner_message', __( 'Settings Saved', 'cloner' ), 'updated' );
					}
					settings_errors( 'cloner_messages' );
					?>
						<div class="wrap">
							<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
							<form action="options.php" method="post">
								<?php
								settings_fields( 'cloner' );
								do_settings_sections( 'cloner' );
								submit_button( __('Save Settings', 'cloner') );
								?>
							</form>
						</div>
					<?php
				}
			}

			public static function callbackSettings() {
				?>
					<p><?php _e('The Clone action will be shown for the following post types:', 'cloner'); ?></p>
				<?php
			}

			public static function actionRowItems($actions, $post) {
				$options = get_option( 'cloner_options' );
				$key = "cloner_field_{$post->post_type}";
				if ( self::getItem($options, $key) == 1 ) {
					$label = __('Clone', 'cloner');
					$url = admin_url("admin.php?page=cloner&amp;post={$post->ID}");
					$action = "<a href=\"{$url}\">{$label}</a>";
					$actions['clone'] = $action;
				}
				return $actions;
			}

			/**
			 * Get an item from an array/object, or a default value if it's not set
			 * @param  mixed $var      Array or object
			 * @param  mixed $key      Key or index, depending on the array/object
			 * @param  mixed $default  A default value to return if the item it's not in the array/object
			 * @return mixed           The requested item (if present) or the default value
			 */
			protected static function getItem($var, $key, $default = '') {
				return is_object($var) ?
					( isset( $var->$key ) ? $var->$key : $default ) :
					( isset( $var[$key] ) ? $var[$key] : $default );
			}

			protected static function duplicate($post_id) {
				$title   = get_the_title($post_id);
				$oldpost = get_post($post_id);
				$post    = array(
					'post_title' => $title,
					'post_status' => $oldpost->post_status,
					'post_type' => $oldpost->post_type,
					'post_content' => $oldpost->post_content,
					'post_excerpt' => $oldpost->post_excerpt,
					'post_parent' => $oldpost->post_parent,
					'post_password' => $oldpost->post_password,
					'comment_status' => $oldpost->comment_status,
					'ping_status' => $oldpost->ping_status,
					'post_author' => get_current_user_id()
				);
				$new_post_id = wp_insert_post($post);
				// Copy post metadata
				$data = get_post_custom($post_id);
				foreach ( $data as $key => $values) {
					foreach ($values as $value) {
						add_post_meta( $new_post_id, $key, $value );
					}
				}

				return $new_post_id;
			}
		}

		add_action( 'init', 'Cloner::init' );
		add_action( 'admin_init', 'Cloner::actionAdminInit' );
		add_action( 'admin_menu', 'Cloner::actionAdminMenu' );
		add_filter( 'post_row_actions','Cloner::actionRowItems', 10, 2 );
		add_filter( 'page_row_actions','Cloner::actionRowItems', 10, 2 );
		add_action( 'admin_enqueue_scripts', 'Cloner::actionEnqueueScripts' );
		add_filter( 'plugin_action_links', 'Cloner::adminSettingsLink', 10, 5 );
	}
?>