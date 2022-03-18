<?php

class WPF_MailerLite_Admin {

	private $slug;
	private $name;
	private $crm;

	/**
	 * Get things started
	 *
	 * @since   1.0
	 */

	public function __construct( $slug, $name, $crm ) {

		$this->slug = $slug;
		$this->name = $name;
		$this->crm  = $crm;

		add_filter( 'wpf_configure_settings', array( $this, 'register_connection_settings' ), 15, 2 );
		add_action( 'show_field_mailerlite_header_begin', array( $this, 'show_field_mailerlite_header_begin' ), 10, 2 );

		// AJAX
		add_action( 'wp_ajax_wpf_test_connection_' . $this->slug, array( $this, 'test_connection' ) );

		if ( wpf_get_option( 'crm' ) == $this->slug ) {
			$this->init();
		}

	}

	/**
	 * Hooks to run when this CRM is selected as active
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function init() {

		add_filter( 'wpf_initialize_options_contact_fields', array( $this, 'add_default_fields' ), 10 );
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 10, 2 );
		add_filter( 'validate_field_mailerlite_update_trigger', array( $this, 'validate_update_trigger' ), 10, 2 );
		add_filter( 'validate_field_mailerlite_add_tag', array( $this, 'validate_import_trigger' ), 10, 2 );

		add_action( 'wpf_resetting_options', array( $this, 'delete_webhooks' ) );

	}


	/**
	 * Loads mailerlite connection information on settings page
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function register_connection_settings( $settings, $options ) {

		$new_settings = array();

		$new_settings['mailerlite_header'] = array(
			'title'   => __( 'MailerLite Configuration', 'wp-fusion-lite' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'setup'
		);

		$new_settings['mailerlite_key'] = array(
			'title'       => __( 'API Key', 'wp-fusion-lite' ),
			'desc'        => __( 'You can find your API key in the <a href="https://app.mailerlite.com/integrations/api/" target="_blank">Developer API</a> settings of your MailerLite account.', 'wp-fusion-lite' ),
			'type'        => 'api_validate',
			'section'     => 'setup',
			'class'       => 'api_key',
			'post_fields' => array( 'mailerlite_key' )
		);

		$settings = wp_fusion()->settings->insert_setting_after( 'crm', $settings, $new_settings );

		return $settings;

	}

	/**
	 * Loads MailerLite specific settings fields
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function register_settings( $settings, $options ) {

		$new_settings['contact_copy_header'] = array(
			'title'   => __( 'MailerLite Settings', 'wp-fusion-lite' ),
			'type'    => 'heading',
			'section' => 'advanced'
		);

		$new_settings['email_changes'] = array(
			'title'   => __( 'Email Address Changes', 'wp-fusion-lite' ),
			'type'    => 'select',
			'section' => 'advanced',
			'std'	  => 'ignore',
			'choices' => array(
				'ignore'	=> 'Ignore',
				'duplicate' => 'Duplicate and Delete'
			),
			'desc'    => __( 'MailerLite doesn\'t allow for changing the email address of an existing subscriber. Choose <strong>Ignore</strong> and WP Fusion will continue updating a single subscriber, ignoring email address changes. Choose <strong>Duplicate and Delete</strong> and WP Fusion will attempt to create a new subscriber with the same details when an email address has been changed, and remove the original subscriber.', 'wp-fusion-lite' ),
		);

		$new_settings['email_changes']['desc'] .= '<br /><br />';
		$new_settings['email_changes']['desc'] .= sprintf( __( 'For more information, %1$ssee our documentation%2$s.', 'wp-fusion-lite' ), '<a href="https://wpfusion.com/documentation/crm-specific-docs/email-address-changes-with-mailerlite/" target="_blank">', '</a>' );

		$settings = wp_fusion()->settings->insert_setting_before( 'advanced_header', $settings, $new_settings );

		if( wp_fusion()->is_full_version() ) {

			$settings['access_key_desc'] = array(
				'std'     => 0,
				'type'    => 'paragraph',
				'section' => 'main',
				'desc'    => __( 'Configuring the fields below allows you to add new users to your site and update existing users based on changes in MailerLite. Read our <a href="https://wpfusion.com/documentation/webhooks/mailerlite-webhooks/" target="_blank">documentation</a> for more information.', 'wp-fusion-lite' ),
			);

			if ( isset( $_GET['ml_debug'] ) ) {

				$webhooks = wp_fusion()->crm->get_webhooks();

				if ( isset( $_GET['ml_destroy_all_webhooks'] ) ) {

					foreach ( $webhooks->webhooks as $webhook ) {

						wp_fusion()->crm->destroy_webhook( $webhook->id );

					}

					$webhooks = 'Destroyed ' . count( $webhooks->webhooks ) . ' webhooks.';

				}

				$settings['access_key_desc']['desc'] .= '<pre>' . wpf_print_r( $webhooks, true ) . '</pre>';

			}

			$new_settings['mailerlite_update_trigger'] = array(
				'title' 	=> __( 'Update Trigger', 'wp-fusion-lite' ),
				'desc'		=> __( 'When a subscriber is updated in MailerLite, send their data back to WordPress.', 'wp-fusion-lite' ),
				'std'		=> 0,
				'type'		=> 'checkbox',
				'section'	=> 'main'
				);

			$new_settings['mailerlite_update_trigger_rule_id'] = array(
				'std'		=> false,
				'type'		=> 'hidden',
				'section'	=> 'main'
				);

			$new_settings['mailerlite_update_trigger_group_add_rule_id'] = array(
				'std'		=> false,
				'type'		=> 'hidden',
				'section'	=> 'main'
				);

			$new_settings['mailerlite_update_trigger_group_remove_rule_id'] = array(
				'std'		=> false,
				'type'		=> 'hidden',
				'section'	=> 'main'
				);

			$new_settings['mailerlite_add_tag'] = array(
				'title' 	=> __( 'Import Group', 'wp-fusion-lite' ),
				'desc'		=> __( 'When a contact is added to this group in MailerLite, they will be imported as a new WordPress user.', 'wp-fusion-lite' ),
				'type'		=> 'assign_tags',
				'section'	=> 'main',
				'placeholder' => 'Select a group',
				'limit'		=> 1
				);

			$new_settings['mailerlite_add_tag_rule_id'] = array(
				'std'		=> false,
				'type'		=> 'hidden',
				'section'	=> 'main'
				);

			$new_settings['mailerlite_import_notification'] = array(
				'title'   => __( 'Enable Notifications', 'wp-fusion-lite' ),
				'desc'    => __( 'Send a welcome email to new users containing their username and a password reset link.', 'wp-fusion-lite' ),
				'type'    => 'checkbox',
				'section' => 'main',
				'std'	  => 0
				);

			$settings = wp_fusion()->settings->insert_setting_after( 'access_key', $settings, $new_settings );

		}

		return $settings;

	}

	/**
	 * Creates / destroys / updates webhooks on field changes
	 *
	 * @access public
	 * @return mixed
	 */

	public function validate_import_trigger( $input, $setting ) {

		$prev_value = wpf_get_option('mailerlite_add_tag');

		// If no changes have been made, quit early
		if($input == $prev_value) {
			return $input;
		}

		// See if we need to destroy an existing webhook before creating a new one
		$rule_id = wpf_get_option('mailerlite_add_tag_rule_id');

		if( ! empty( $rule_id ) ) {
			wp_fusion()->crm->destroy_webhook( $rule_id );
			add_filter( 'validate_field_mailerlite_add_tag_rule_id', function() { return false; } );
		}

		// Abort if tag has been removed and no new one provided
		if( empty( $input ) ) {
			return $input;
		}

		// Add new rule and save
		$rule_ids = wp_fusion()->crm->register_webhooks( 'add' );

		// If there was an error, make the user select the tag again
		if( is_wp_error( $rule_ids ) || empty( $rule_ids ) ) {
			return false;
		}

		add_filter( 'validate_field_mailerlite_add_tag_rule_id', function() use (&$rule_ids) { return $rule_ids[0]; } );

		return $input;

	}

	/**
	 * Creates / destroys / updates webhooks on field changes
	 *
	 * @access public
	 * @return mixed
	 */

	public function validate_update_trigger( $input, $setting ) {

		$prev_value = wpf_get_option('mailerlite_update_trigger');

		// If no changes have been made, quit early
		if( $input == $prev_value ) {
			return $input;
		}

		// See if we need to destroy an existing webhook before creating a new one
		$rule_ids = array();

		$update_rule = wpf_get_option('mailerlite_update_trigger_rule_id');

		if( ! empty( $update_rule ) ) {
			$rule_ids[] = $update_rule;
		}

		$group_add_rule = wpf_get_option('mailerlite_update_trigger_group_add_rule_id');

		if( ! empty( $group_add_rule ) ) {
			$rule_ids[] = $group_add_rule;
		}

		$group_remove_rule = wpf_get_option('mailerlite_update_trigger_group_remove_rule_id');

		if( ! empty( $group_remove_rule ) ) {
			$rule_ids[] = $group_remove_rule;
		}

		if( ! empty( $rule_ids ) ) {

			foreach( $rule_ids as $rule_id ) {
				wp_fusion()->crm->destroy_webhook($rule_id);
			}

			add_filter( 'validate_field_mailerlite_update_trigger_rule_id', function() { return false; } );
			add_filter( 'validate_field_mailerlite_update_trigger_group_add_rule_id', function() { return false; } );
			add_filter( 'validate_field_mailerlite_update_trigger_group_remove_rule_id', function() { return false; } );

		}

		// Abort if tag has been removed and no new one provided
		if( $input == false ) {
			return $input;
		}

		// Add new rule and save
		$rule_ids = wp_fusion()->crm->register_webhooks( 'update' );

		// If there was an error, make the user select the tag again
		if( is_wp_error( $rule_ids ) || empty( $rule_ids ) ) {
			return false;
		}

		$update_rule = $rule_ids[0];
		$group_add_rule = $rule_ids[1];
		$group_remove_rule = $rule_ids[2];

		add_filter( 'validate_field_mailerlite_update_trigger_rule_id', function() use (&$update_rule) { return $update_rule; } );
		add_filter( 'validate_field_mailerlite_update_trigger_group_add_rule_id', function() use (&$group_add_rule) { return $group_add_rule; } );
		add_filter( 'validate_field_mailerlite_update_trigger_group_remove_rule_id', function() use (&$group_remove_rule) { return $group_remove_rule; } );

		return $input;

	}

	/**
	 * Delete webhooks when settings are reset
	 *
	 * @access public
	 * @return void
	 */

	public function delete_webhooks( $options ) {

		if ( ! empty( $options['mailerlite_add_tag_rule_id'] ) ) {

			// The add webhook
			$this->crm->destroy_webhook( $options['mailerlite_add_tag_rule_id'] );

		}

		if ( ! empty( $options['mailerlite_update_trigger_rule_id'] ) ) {

			// The three update webhooks

			$this->crm->destroy_webhook( $options['mailerlite_update_trigger_rule_id'] );
			$this->crm->destroy_webhook( $options['mailerlite_update_trigger_group_add_rule_id'] );
			$this->crm->destroy_webhook( $options['mailerlite_update_trigger_group_remove_rule_id'] );

		}

	}

	/**
	 * Loads standard mailerlite field names and attempts to match them up with standard local ones
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function add_default_fields( $options ) {

		if ( $options['connection_configured'] == true ) {

			require_once dirname( __FILE__ ) . '/mailerlite-fields.php';

			foreach ( $options['contact_fields'] as $field => $data ) {

				if ( isset( $mailerlite_fields[ $field ] ) && empty( $options['contact_fields'][ $field ]['crm_field'] ) ) {
					$options['contact_fields'][ $field ] = array_merge( $options['contact_fields'][ $field ], $mailerlite_fields[ $field ] );
				}

			}

		}

		return $options;

	}


	/**
	 * Puts a div around the mailerlite configuration section so it can be toggled
	 *
	 * @access  public
	 * @since   1.0
	 */

	public function show_field_mailerlite_header_begin( $id, $field ) {

		echo '</table>';
		$crm = wpf_get_option( 'crm' );
		echo '<div id="' . esc_attr( $this->slug ) . '" class="crm-config ' . ( $crm == false || $crm != $this->slug ? 'hidden' : 'crm-active' ) . '" data-name="' . esc_attr( $this->name ) . '" data-crm="' . esc_attr( $this->slug ) . '">';

	}


	/**
	 * Verify connection credentials
	 *
	 * @access public
	 * @return bool
	 */

	public function test_connection() {

		check_ajax_referer( 'wpf_settings_nonce' );

		$api_key = sanitize_text_field( wp_unslash( $_POST['mailerlite_key'] ) );

		$connection = $this->crm->connect( $api_key, true );

		if ( is_wp_error( $connection ) ) {

			wp_send_json_error( $connection->get_error_message() );

		} else {

			$options                          = array();
			$options['mailerlite_key']        = $api_key;
			$options['crm']                   = $this->slug;
			$options['connection_configured'] = true;
			wp_fusion()->settings->set_multiple( $options );

			wp_send_json_success();

		}

		die();

	}

}