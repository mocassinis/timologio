<?php

/**
 * Set namespace.
 */
namespace Nvm\Timologio;

/**
 * Prevent direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you cannot directly access this file.' );
}

// other plugins
// expert
// billing_vat_id
// billing_tax_office
// billing_company
// billing_activity


/**
 * Class Checkout
 */
class Checkout {

	// Constants
	const FIELD_TYPE_ORDER = 'type_of_order';
	const TYPE_TIMOLOGIO   = 'timologio';
	const TYPE_APODEIXI    = 'apodeixi';

	/**
	 * Required fields for timologio (invoice) form.
	 *
	 * @var array
	 */
	private $required_timologio_fields;

	/**
	 * Required fields for timologio (invoice) form.
	 *
	 * @var array
	 */
	private $required_timologio_keys;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->required_timologio_fields = array(
			'billing_vat'      => __( 'ΑΦΜ', 'nevma' ),
			'billing_irs'      => __( 'ΔΟΥ', 'nevma' ),
			'billing_company'  => __( 'Επωνυμία εταιρίας', 'nevma' ),
			'billing_activity' => __( 'Δραστηριότητα', 'nevma' ),
		);

		$this->required_timologio_keys = array(
			'billing_vat',
			'billing_irs',
			'billing_company',
			'billing_activity',
		);

		$this->register_hooks();
	}

	/**
	 * Register hooks and filters.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// add_action( 'template_redirect', array( $this, 'initiate_checkout_actions' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'order_show_timologio_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_timologio_data' ) );

		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_timologio_fields' ) );
		add_filter( 'woocommerce_form_field', array( $this, 'customize_form_field' ), 10, 4 );

		add_action( 'woocommerce_init', array( $this, 'block_checkout' ) );
	}

	/**
	 * Initialize actions for the checkout page.
	 *
	 * @return void
	 */
	public function initiate_checkout_actions() {
	}

	/**
	 * Customize checkout fields.
	 *
	 * @param array $fields The existing fields.
	 *
	 * @return array
	 */
	public function customize_checkout_fields( $fields ) {
		// Get the selected value for the tax_type field.
		$chosen = WC()->session->get( 'tax_type' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'tax_type' ) : $chosen;
		$chosen = empty( $chosen ) ? self::TYPE_APODEIXI : $chosen;

		// Add the custom radio field directly to the billing section.
		$fields['billing'][ self::FIELD_TYPE_ORDER ] = array(
			'type'     => 'radio',
			'class'    => array( 'form-row-wide', self::FIELD_TYPE_ORDER ),
			'options'  => array(
				self::TYPE_APODEIXI  => __( 'Απόδειξη', 'nevma' ),
				self::TYPE_TIMOLOGIO => __( 'Τιμολόγιο', 'nevma' ),
			),
			'default'  => $chosen,
			'priority' => 27, // Ensure it appears after "Last Name".
		);

		// Define default values for Timologio fields.
		$billing_defaults = array(
			'billing_vat'      => WC()->checkout->get_value( 'billing_vat' ),
			'billing_irs'      => WC()->checkout->get_value( 'billing_irs' ),
			'billing_company'  => WC()->checkout->get_value( 'billing_company' ),
			'billing_activity' => WC()->checkout->get_value( 'billing_activity' ),
		);

		// Add additional fields for Timologio (invoice).
		$timologia_fields = array(
			'billing_vat'      => $this->get_field_config(
				__( 'ΑΦΜ', 'nevma' ),
				array( 'form-row-first' ),
				28,
				$billing_defaults['billing_vat']
			),
			'billing_irs'      => $this->get_field_config(
				__( 'ΔΟΥ', 'nevma' ),
				array( 'form-row-last' ),
				29,
				$billing_defaults['billing_irs']
			),
			'billing_company'  => $this->get_field_config(
				__( 'Επωνυμία Εταιρίας', 'nevma' ),
				array( 'form-row-wide' ),
				30,
				$billing_defaults['billing_company']
			),
			'billing_activity' => $this->get_field_config(
				__( 'Δραστηριότητα', 'nevma' ),
				array( 'form-row-wide' ),
				31,
				$billing_defaults['billing_activity']
			),
		);

		// Merge custom fields with existing billing fields, preserving order.
		$fields['billing'] = array_merge( $fields['billing'], $timologia_fields );

		return $fields;
	}

	// Remove the Optional note and show it with a star
	public function customize_form_field( $field, $key, $args, $value ) {

		$keys = $this->required_timologio_keys;

		if ( in_array( $key, $keys, true ) ) {

			$field = preg_replace( '/<span class="optional">.*?<\/span>/', '', $field );

			$field = preg_replace(
				'/<label(.*?)>(.*?)<\/label>/',
				'<label$1>$2 <abbr class="required" title="required">*</abbr></label>',
				$field
			);
		}
		return $field;
	}


	/**
	 * Get field configuration for billing fields.
	 *
	 * @param string $label     The field label.
	 * @param array  $css_class Additional CSS classes for the field.
	 * @param int    $priority  The field priority.
	 * @param mixed  $default   The default value for the field.
	 *
	 * @return array
	 */
	private function get_field_config( $label, $css_class = array(), $priority = 28, $default = '' ) {
		$pre_class = array( 'form-row', 'timologio' );

		if ( ! empty( $css_class ) ) {
			$pre_class = array_merge( $css_class, $pre_class );
		}

		return array(
			'label'    => $label,
			'required' => false,
			'type'     => 'text',
			'class'    => $pre_class,
			'priority' => $priority,
			'default'  => $default,
		);
	}

	/**
	 * Validate required fields for timologio.
	 *
	 * @return void
	 */
	public function validate_timologio_fields() {
		if ( isset( $_POST[ self::FIELD_TYPE_ORDER ] ) && self::TYPE_TIMOLOGIO === $_POST[ self::FIELD_TYPE_ORDER ] ) {
			foreach ( $this->required_timologio_fields as $field => $label ) {
				if ( empty( $_POST[ $field ] ) ) {
					wc_add_notice( sprintf( __( 'Please fill in the %s field.', 'nevma' ), esc_html( $label ) ), 'error' );
				}
			}
		}
	}

	/**
	 * Save timologio data to the order and the user profile.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	public function save_timologio_data( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order ) {
			$user_id        = $order->get_user_id();
			$fields_to_save = $this->required_timologio_keys;

			foreach ( $fields_to_save as $post_key ) {
				if ( isset( $_POST[ $post_key ] ) ) {
					$sanitized_value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );

					// Save to order meta.
					$order->update_meta_data( $post_key, $sanitized_value );

					// Save to user meta if user is logged in.
					if ( $user_id ) {
						update_user_meta( $user_id, $post_key, $sanitized_value );
					}
				}
			}

			$order->save();
		}
	}


	/**
	 * Show timologio fields in the admin order view.
	 *
	 * @param \WC_Order $order The order object.
	 *
	 * @return void
	 */
	public function order_show_timologio_fields( $order ) {
		$fields_to_display = $this->required_timologio_fields;

		foreach ( $fields_to_display as $meta_key => $label ) {
			$value = $order->get_meta( $meta_key );

			if ( ! empty( $value ) ) {
				printf( '<p><strong>%s:</strong> %s</p>', esc_html( $label ), esc_html( $value ) );
			}
		}
	}

	/**
	 * Show timologio fields in the admin order view.
	 *
	 * @param \WC_Order $order The order object.
	 *
	 * @return void
	 */
	public function user_show_timologio_fields( $order ) {
		// Fields to display for the order.
		$fields_to_display = $this->required_timologio_fields;

		foreach ( $fields_to_display as $meta_key => $label ) {
			$value = $order->get_meta( $meta_key );

			if ( ! empty( $value ) ) {
				printf( '<p><strong>%s:</strong> %s</p>', esc_html( $label ), esc_html( $value ) );
			}
		}

		// Fetch and display user-specific timologio fields.
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			echo '<h4>' . esc_html__( 'User Timologio Fields', 'nevma' ) . '</h4>';

			foreach ( $fields_to_display as $meta_key => $label ) {
				$user_value = get_user_meta( $user_id, $meta_key, true );

				if ( ! empty( $user_value ) ) {
					printf( '<p><strong>%s:</strong> %s</p>', esc_html( $label ), esc_html( $user_value ) );
				}
			}
		}
	}

	public function block_checkout() {

		woocommerce_register_additional_checkout_field(
			array(
				'id'         => 'nvm/invoice_or_timologio',
				'label'      => __( 'Απόδειξη ή Τιμολόγιο', 'nevma' ),
				'location'   => 'contact',
				'type'       => 'checkbox',
				'attributes' => array(
					'data-nvm' => 'nvm-checkbox',
				),
			),
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'         => 'nvm/billing_vat',
				'label'      => __( 'ΑΦΜ', 'nevma' ),
				'location'   => 'contact',
				'type'       => 'text',
				'attributes' => array(
					'data-nvm' => 'nvm-first-row timologio',
				),
				// 'required'   => true,
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'         => 'nvm/billing_irs',
				'label'      => __( 'ΔΟΥ', 'nevma' ),
				'location'   => 'contact',
				'type'       => 'text',
				'attributes' => array(
					'data-nvm' => 'nvm-last-row timologio',
				),
				// 'required'   => true,
			),
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'         => 'nvm/billing_company',
				'label'      => __( 'Επωνυμία εταιρίας', 'nevma' ),
				'location'   => 'contact',
				'type'       => 'text',
				'attributes' => array(
					'data-nvm' => 'timologio',
				),
				// 'required'   => true,
			),
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'         => 'nvm/billing_activity',
				'label'      => __( 'Δραστηριότητα', 'nevma' ),
				'location'   => 'contact',
				'type'       => 'text',
				'attributes' => array(
					'data-nvm' => 'timologio',
				),
				// 'required'   => true,
			),
		);
	}
}
