<?php
namespace EventsChimp {

	use Exception;

	class EventsChimp {

		protected static $instance = false;

		protected static $db_version = '1.0';

		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		protected function __construct() {
			register_activation_hook( \EVENTSCHIMP_PLUGIN_FILE, array( $this, 'activation_hook' ) );

			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'wp_ajax_eventschimp_mailchimp_api_key_test', array( $this, 'eventschimp_mailchimp_api_key_test' ) );
		}

		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'eventschimp', false, \EVENTSCHIMP_PLUGIN_DIR . '/i18n/languages' );
		}

		public function activation_hook() {
			/* placeholder */
		}

		public function admin_init() {
			register_setting( 'eventschimp_settings_group', 'eventschimp_mailchimp_api_key' );
			register_setting(
				'eventschimp_settings_group',
				'eventschimp_enabled',
				array(
					'type'              => 'boolean',
					'default'           => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				)
			);
		}

		public function admin_menu() {
			add_options_page(
				'Event Tracking for Mailchimp® Settings',
				'Event Tracking for Mailchimp®',
				'manage_options',
				'eventschimp',
				array( $this, 'render_settings_page' )
			);
		}

		public function render_settings_page() {
			?>
		<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'eventschimp_settings_group' );
			do_settings_sections( 'eventschimp_settings_group' );
			?>
			<table class="form-table">
			<tr>
				<th scope="row">
				<label for="eventschimp_enabled">Enable Event Tracking for Mailchimp</label>
				</th>
				<td>
				<input type="checkbox" id="eventschimp_enabled" name="eventschimp_enabled" value="1" <?php checked( 1, get_option( 'eventschimp_enabled', true ) ); ?>>
				<label for="eventschimp_enabled">Enable Event Tracking</label>
				<p class="description">Unchecking this lets you temporarily disable event tracking without fully deactivating the plugin. Use this when you want to pause operations while preserving all your settings and API keys.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">API Key</th>
				<td>
				<input type="text" id="eventschimp_mailchimp_api_key" name="eventschimp_mailchimp_api_key" 
					value="<?php echo esc_attr( get_option( 'eventschimp_mailchimp_api_key' ) ); ?>" class="regular-text">
				<p class="description">Enter your Mailchimp API key.</p>
				<div class="eventschimp_mailchimp_api_key_test_container" style="display: flex; align-items:center; gap: 10px;">
					<button type="button" id="eventschimp_mailchimp_api_key_test" class="button button-secondary">Test API Key</button>
					<span id="eventschimp_mailchimp_api_key_test_result"></span>
				</div>
				</td>
			</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<div style="font-style: italic;">Mailchimp® is a registered trademark of The Rocket Science Group.</div>
		</div>

		<script>
		jQuery(function($) {
			$('#eventschimp_mailchimp_api_key_test').on('click', function(e) {
			e.preventDefault();

			var api_key = $('#eventschimp_mailchimp_api_key').val();
			$('#eventschimp_mailchimp_api_key_test_result').html('Testing...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
				action: 'eventschimp_mailchimp_api_key_test',
				api_key: api_key,
				nonce: <?php echo wp_json_encode( wp_create_nonce( 'eventschimp_mailchimp_api_key_test_nonce' ) ); ?>
				},
				success: function(response) {
				$('#eventschimp_mailchimp_api_key_test_result').html(response.data);
				},
				error: function(jqXHR, textStatus, errorThrown) {
				$('#eventschimp_mailchimp_api_key_test_result').html('Error: ' + textStatus + ' (' + errorThrown + ')');
				}
			});
			});
		});
		</script>
			<?php
		}

		public function eventschimp_mailchimp_api_key_test() {
			check_ajax_referer( 'eventschimp_mailchimp_api_key_test_nonce', 'nonce' );
			$api_key = sanitize_text_field( $_POST['api_key'] );

			try {
				$response = $this->call_api( '/3.0/ping', array(), $api_key );
				$code     = wp_remote_retrieve_response_code( $response );

				if ( 200 === $code ) {
					wp_send_json_success( '<span style="color: green;">API key is valid!</span>' );
				} else {
					wp_send_json_error( '<span style="color: red;">API key is invalid. Error code: ' . $code . '</span>' );
				}
			} catch ( Exception $e ) {
				wp_send_json_error( '<span style="color: red;">' . $e->getMessage() . '</span>' );
			}
		}

		public function is_enabled() {
			return get_option( 'eventschimp_enabled', true );
		}

		public function get_mailchimp_api_key() {
			return get_option( 'eventschimp_mailchimp_api_key' );
		}

		public function get_mailchimp_api_token_and_server_prefix( $api_key = null ) {
			return array_pad( explode( '-', is_null( $api_key ) ? $this->get_mailchimp_api_key() : $api_key ), 2, null );
		}

		public function call_api( $path, $args = array(), $api_key = null ) {
			list ( $token, $server_prefix ) = $this->get_mailchimp_api_token_and_server_prefix( $api_key );

			if ( empty( $token ) || empty( $server_prefix ) ) {
				throw new Exception( 'API key is invalid or missing.' );
			}

			if ( ! preg_match( '/^[a-z0-9]+$/i', $server_prefix ) ) {
				throw new Exception( 'Server prefix is invalid.' );
			}

			$defaults = array(
				'method'  => 'GET',
				'timeout' => 2,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			);

			$args = array_replace_recursive( $defaults, $args );

			if ( strpos( $path, '/' ) === 0 ) {
				$path = substr( $path, 1 );
			}

			$response = wp_remote_request(
				'https://' . $server_prefix . '.api.mailchimp.com/' . $path,
				$args
			);

			if ( is_wp_error( $response ) ) {
				throw new Exception( 'API request failed: ' . esc_html( $response->get_error_message() ) );
			}

			return $response;
		}

		public function init() {
			if ( ! $this->is_enabled() ) {
				return;
			}

			if ( is_admin() || strpos( $_SERVER['REQUEST_URI'], '/' . rest_get_url_prefix() . '/' ) === 0 ) {
				return;
			}

			$list_id_map = array();

			if ( isset( $_COOKIE['mc_list_ids'] ) ) {
				$cookie = sanitize_text_field( $_COOKIE['mc_list_ids'] );

				foreach ( explode( '.', $cookie ) as $chip ) {
					list ( $member_id, $list_id ) = array_pad( explode( '_', $chip ), 2, null );

					if ( empty( $member_id ) || empty( $list_id ) ) {
						continue;
					}

					$list_id_map[ $list_id ] = $member_id;
				}
			}

			$unique_email_id = null;
			$list_id         = null;

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['mc_eid'] ) && preg_match( '/^[0-9a-f]+$/', sanitize_text_field( $_GET['mc_eid'] ), $m ) ) {
				$unique_email_id = $m[0];
			}

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['utm_term'] ) && preg_match( '/^0_([0-9a-f]+)-([0-9a-f]+)-([0-9]+)$/', sanitize_text_field( $_GET['utm_term'] ), $m ) ) {
				$list_id = $m[1];
			}

			if ( ! empty( $unique_email_id ) && ! empty( $list_id ) ) {
				try {
					$path = sprintf(
						'/3.0/lists/%s/members?%s',
						$list_id,
						http_build_query(
							array(
								'unique_email_id' => $unique_email_id,
								'fields'          => 'members.id',
							)
						)
					);

					$response = $this->call_api( $path );
					$code     = wp_remote_retrieve_response_code( $response );
					$body     = wp_remote_retrieve_body( $response );

					if ( 200 === $code ) {
								$data = json_decode( $body, true );

						if ( JSON_ERROR_NONE !== json_last_error() ) {
							throw new Exception( 'json_decode() failed: ' . json_last_error_msg() );
						}

						if ( isset( $data['members'] ) && count( $data['members'] ) > 0 && isset( $data['members'][0]['id'] ) ) {
							$list_id_map[ $list_id ] = $data['members'][0]['id'];
						}
					} else {
						throw new Exception( '(' . $code . ') ' . $body );
					}
				} catch ( Exception $e ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( '[%s] %s failed: %s', __METHOD__, $path, $e->getMessage() ) );
				}
			}

			if ( count( $list_id_map ) ) {
				$_COOKIE['mc_list_ids'] = join(
					'.',
					array_map(
						function ( $list_id, $member_id ) {
							return $member_id . '_' . $list_id;
						},
						array_keys( $list_id_map ),
						array_values( $list_id_map )
					)
				);

				setcookie(
					'mc_list_ids',
					$_COOKIE['mc_list_ids'],
					time() + ( 86400 + 30 ),
					'/',
					'',
					false,
					false
				);

				foreach ( $list_id_map as $list_id => $member_id ) {
						$path = sprintf( '/3.0/lists/%s/members/%s/events', $list_id, $member_id );

						$properties = array(
							'url'       => sprintf(
								'http%s://%s%s',
								empty( $_SERVER['HTTPS'] ) ? '' : 's',
								$_SERVER['HTTP_HOST'],
								$_SERVER['REQUEST_URI']
							),
							'page_path' => current( explode( '?', $_SERVER['REQUEST_URI'] ) ),
						);

						if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
							$properties['referrer'] = $_SERVER['HTTP_REFERER'];
						}

						$body = wp_json_encode(
							array(
								'name'       => 'page_view',
								'properties' => $properties,
							)
						);

						$args = array(
							'method'   => 'POST',
							'body'     => $body,
							'blocking' => false,
						);

						try {
							$this->call_api( $path, $args );
						} catch ( Exception $e ) {
						  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( sprintf( '[%s] %s failed: %s', __METHOD__, $path, $e->getMessage() ) );
						}
				}
			}
		}
	}

}
