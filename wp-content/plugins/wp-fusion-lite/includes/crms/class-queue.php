<?php

class WPF_CRM_Queue {

	/**
	 * Holds the current active CRM object
	 */

	private $crm;

	/**
	 * Buffer for queued API calls
	 */

	private $buffer;


	public function __construct( $crm ) {

		$this->crm    = $crm;
		$this->buffer = array();

		// Run shutdown at PHP shutdown
		add_action( 'shutdown', array( $this, 'shutdown' ), 1 );

	}

	/**
	 * Passes get requests to the base CRM class
	 *
	 * @access  public
	 * @return  mixed
	 */

	public function __get( $name ) {

		if ( is_object( $this->crm ) ) {
			return $this->crm->$name;
		} else {
			return false;
		}

	}

	/**
	 * Passes get requests to the base CRM class
	 *
	 * @access  public
	 * @return  void
	 */

	public function __set( $key, $value ) {

		if ( is_object( $this->crm ) ) {
			$this->crm->{$key} = $value;
		}

	}

	/**
	 * Checks for properties in the base CRM class
	 *
	 * @access  public
	 * @return  bool
	 */

	public function __isset( $name ) {

		if ( is_object( $this->crm ) && property_exists( $this->crm, $name ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Routes queue-able API calls to the buffer
	 *
	 * @access  public
	 * @return  mixed
	 */

	public function __call( $method, $args ) {

		$args = apply_filters( 'wpf_api_' . $method . '_args', $args );

		if ( wpf_get_option( 'staging_mode' ) == true || defined( 'WPF_STAGING_MODE' ) ) {

			wpf_log(
				'notice',
				0,
				'Staging mode enabled. Method ' . $method . ':',
				array(
					'source' => $this->crm->slug,
					'args'   => $args,
				)
			);

			require_once WPF_DIR_PATH . 'includes/crms/staging/class-staging.php';
			$staging_crm = new WPF_Staging;

			$result = call_user_func_array( array( $staging_crm, $method ), $args );

			return $result;

		}

		/**
		 * Allows bypassing the API call, for example if a required dependency was deactivated
		 *
		 * @since 3.35.16
		 *
		 * @param bool|WP_Error $error  The error object
		 * @param string        $method The API method to be performed
		 * @param array         $args   The API arguments
		 */

		$error = apply_filters( 'wpf_api_preflight_check', true, $method, $args );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// If the CRM supports custom objects, bypass the queue and call it.

		// This routes calls like wp_fusion()->crm->add_object( $data, 'Lead' ) to
		// wp_fusion()->crm->add_contact( $data, $map_meta_fields = false ), while changing
		// the object type to "Lead".

		if ( false !== strpos( $method, '_object' ) && isset( $this->crm->object_type ) && ! method_exists( $this->crm, $method ) ) {

			$method      = str_replace( '_object', '', $method );
			$object_type = array_pop( $args );

			// Switch the object type for the API call
			add_filter(
				'wpf_crm_object_type',
				function() use ( &$object_type ) {
					return $object_type;
				}
			);

			// Set $map_meta_fields to always false

			if ( 'add' == $method ) {
				$args[1] = false;
			} elseif ( 'update' == $method ) {
				$args[2] = false;
			}

			$method .= '_contact'; // "add" becomes "add_contact"

			$result = call_user_func_array( array( $this->crm, $method ), $args );
			$result = apply_filters( "wpf_api_{$method}_result", $result, $args );

			return $result;

		}

		// You can set the third argument to true to bypass the queue, for example ->update_contact( $contact_id, $data, false, $bypass_queue = true ).
		if ( ( 'apply_tags' === $method || 'remove_tags' === $method || 'update_contact' === $method ) && apply_filters( 'wpf_use_api_queue', true, $method, $args ) ) {

			// Queue sending data and return true.
			$this->add_to_buffer( $method, $args );

			return true;

		} else {

			// Can't be queued, execute right away.

			$result = call_user_func_array( array( $this->crm, $method ), $args );

			$result = apply_filters( "wpf_api_{$method}_result", $result, $args );

			$result = wpf_clean( $result ); // sanitize_text_field recursive.

			return $result;

		}

	}


	/**
	 * Adds API requests to the API buffer
	 *
	 * @access  private
	 * @return  void
	 */

	private function add_to_buffer( $method, $args ) {

		if ( $method == 'apply_tags' || $method == 'remove_tags' ) {

			$cid  = $args[1];
			$data = $args[0];

		} elseif ( $method == 'update_contact' ) {

			// Map meta fields here so calls can be combined

			if ( ! isset( $args[2] ) || $args[2] == true ) {
				$args[1] = wp_fusion()->crm_base->map_meta_fields( $args[1] );
				$args[2] = false;
			}

			$cid  = $args[0];
			$data = $args[1];

			if ( empty( $data ) ) {
				return; // if no fields in the data were enabled, nothing more to do.
			}
		}

		if ( in_array( 'combined_updates', $this->crm->supports ) ) {

			// CRMs that support tags and contact data in the same request

			$update_data = array( $method => $data );

			if ( ! isset( $this->buffer['combined_update'] ) ) {

				$this->buffer['combined_update'] = array( $cid => $update_data );

			} elseif ( ! isset( $this->buffer['combined_update'][ $cid ] ) ) {

				$this->buffer['combined_update'][ $cid ] = $update_data;

			} else {

				if ( ! isset( $this->buffer['combined_update'][ $cid ][ $method ] ) ) {

					$this->buffer['combined_update'][ $cid ][ $method ] = $data;

				}

				if ( $method == 'apply_tags' ) {

					// Prevent tags getting added and removed in the same request

					if ( isset( $this->buffer['combined_update'][ $cid ]['remove_tags'] ) ) {

						foreach ( $data as $tag ) {

							$match = array_search( $tag, $this->buffer['combined_update'][ $cid ]['remove_tags'] );

							if ( $match !== false ) {
								unset( $this->buffer['combined_update'][ $cid ]['remove_tags'][ $match ] );
							}
						}
					}

					$this->buffer['combined_update'][ $cid ]['apply_tags'] = array_unique( array_merge( $this->buffer['combined_update'][ $cid ]['apply_tags'], $data ) );

				} elseif ( $method == 'remove_tags' ) {

					// Prevent tags getting added and removed in the same request

					if ( isset( $this->buffer['combined_update'][ $cid ]['apply_tags'] ) ) {

						foreach ( $data as $tag ) {

							$match = array_search( $tag, $this->buffer['combined_update'][ $cid ]['apply_tags'] );

							if ( $match !== false ) {
								unset( $this->buffer['combined_update'][ $cid ]['apply_tags'][ $match ] );
							}
						}
					}

					$this->buffer['combined_update'][ $cid ]['remove_tags'] = array_unique( array_merge( $this->buffer['combined_update'][ $cid ]['remove_tags'], $data ) );

				} elseif ( $method == 'update_contact' ) {

					$this->buffer['combined_update'][ $cid ]['update_contact'] = array_merge( $this->buffer['combined_update'][ $cid ]['update_contact'], $data );

				}
			}
		} else {

			// CRMs that require separate API calls for tags and contact data.

			if ( ! isset( $this->buffer[ $method ] ) ) {

				$this->buffer[ $method ] = array( $cid => $args );

			} elseif ( ! isset( $this->buffer[ $method ][ $cid ] ) ) {

				$this->buffer[ $method ][ $cid ] = $args;

			}

			if ( 'apply_tags' === $method ) {

				// Prevent tags getting added and removed in the same request.

				if ( isset( $this->buffer['remove_tags'] ) && isset( $this->buffer['remove_tags'][ $cid ] ) ) {
					$this->buffer['remove_tags'][ $cid ][0] = array_diff( $this->buffer['remove_tags'][ $cid ][0], $args[0] );
				}

				$this->buffer['apply_tags'][ $cid ][0] = array_unique( array_merge( $this->buffer['apply_tags'][ $cid ][0], $args[0] ) );

			} elseif ( 'remove_tags' === $method ) {

				// Prevent tags getting added and removed in the same request.

				if ( isset( $this->buffer['apply_tags'] ) && isset( $this->buffer['apply_tags'][ $cid ] ) ) {
					$this->buffer['apply_tags'][ $cid ][0] = array_diff( $this->buffer['apply_tags'][ $cid ][0], $args[0] );
				}

				$this->buffer['remove_tags'][ $cid ][0] = array_unique( array_merge( $this->buffer['remove_tags'][ $cid ][0], $args[0] ) );

			} elseif ( 'update_contact' === $method ) {

				$this->buffer[ $method ][ $cid ][1] = array_merge( $this->buffer[ $method ][ $cid ][1], $args[1] );

			}
		}

	}

	/**
	 * Executes the queued API requests on PHP shutdown
	 *
	 * @access  public
	 * @return  void
	 */

	public function shutdown() {

		if ( empty( $this->buffer ) ) {
			return;
		}

		foreach ( $this->buffer as $method => $contacts ) {

			foreach ( $contacts as $cid => $args ) {

				// Don't send empty data.
				if ( ! empty( $args[0] ) && ! empty( $args[1] ) || $method == 'combined_update' ) {

					if ( $method == 'combined_update' ) {
						$args = array( $cid, $args );
					}

					$result = call_user_func_array( array( $this->crm, $method ), $args );

					// Error handling
					if ( is_wp_error( $result ) ) {

						$user_id = wp_fusion()->user->get_user_id( $cid );

						wpf_log(
							'error',
							$user_id,
							'Error while performing method <strong>' . $method . '</strong>: ' . $result->get_error_message(),
							array(
								'source' => $this->crm->slug,
								'args'   => $args,
							)
						);

						do_action( 'wpf_api_error', $method, $args, $cid, $result );

						do_action( 'wpf_api_error_' . $method, $args, $cid, $result );

					} else {

						do_action( 'wpf_api_did_' . $method, $args, $cid, $result );

					}
				}
			}
		}

		$this->buffer = false; // in case we need to call it again later, clear it out.

	}


}
