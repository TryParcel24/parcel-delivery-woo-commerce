<?php
/**
 * Parcel Delivery — Native WooCommerce Shipping Method.
 *
 * Adds "Parcel Delivery" as a selectable shipping method inside any zone, so
 * the merchant no longer needs to fall back to "Flat rate" + our filter just
 * to surface the calculated block-based cost at checkout.
 *
 * The actual per-block cost is still stored under the Shipping Calculator tab
 * (pd_api_blocks_info / pd_manual_blocks_info). At checkout, the customer's
 * selected block is written into the session as block_shipping_cost by the
 * checkout module; this method reads that value and emits a single rate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pd_register_shipping_method' ) ) {

	function pd_register_shipping_method_class() {

		if ( ! class_exists( 'WC_Shipping_Method' ) ) {
			return; // WooCommerce not loaded yet.
		}

		if ( class_exists( 'WC_Parcel_Delivery_Shipping_Method' ) ) {
			return;
		}

		class WC_Parcel_Delivery_Shipping_Method extends WC_Shipping_Method {

			public function __construct( $instance_id = 0 ) {
				$this->id                 = 'parcel_delivery';
				$this->instance_id        = absint( $instance_id );
				$this->method_title       = __( 'Parcel Delivery', 'parcel-delivery' );
				$this->method_description = __( 'Calculates shipping cost based on the block selected at checkout. Blocks and per-block charges are configured under WooCommerce → Settings → Parcel Delivery → Shipping Calculator.', 'parcel-delivery' );
				$this->supports           = array(
					'shipping-zones',
					'instance-settings',
					'instance-settings-modal',
				);

				$this->init();
			}

			public function init() {
				$this->init_form_fields();
				$this->init_settings();

				$this->title    = $this->get_option( 'title' );
				$this->tax_status = $this->get_option( 'tax_status' );

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function init_form_fields() {
				$this->instance_form_fields = array(
					'title'         => array(
						'title'       => __( 'Title', 'parcel-delivery' ),
						'type'        => 'text',
						'description' => __( 'Label shown to the customer at checkout.', 'parcel-delivery' ),
						'default'     => __( 'Parcel Delivery', 'parcel-delivery' ),
						'desc_tip'    => true,
					),
					'tax_status'    => array(
						'title'   => __( 'Tax status', 'parcel-delivery' ),
						'type'    => 'select',
						'class'   => 'wc-enhanced-select',
						'default' => 'taxable',
						'options' => array(
							'taxable' => __( 'Taxable', 'parcel-delivery' ),
							'none'    => __( 'None', 'parcel-delivery' ),
						),
					),
					'fallback_cost' => array(
						'title'       => __( 'Fallback cost', 'parcel-delivery' ),
						'type'        => 'number',
						'description' => __( 'Cost displayed before the customer picks a block. Set 0 to show free until a block is chosen.', 'parcel-delivery' ),
						'default'     => '0',
						'desc_tip'    => true,
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '0.01',
						),
					),
				);
			}

			public function calculate_shipping( $package = array() ) {
				$cost = $this->resolve_cost();

				$this->add_rate(
					array(
						'id'        => $this->get_rate_id(),
						'label'     => $this->title ? $this->title : $this->method_title,
						'cost'      => $cost,
						'package'   => $package,
						'taxes'     => ( 'taxable' === $this->tax_status ) ? '' : false,
					)
				);
			}

			/**
			 * Resolve the per-block shipping cost for the current customer.
			 *
			 * Order of precedence:
			 *  1. block_shipping_cost stored in session (set by checkout hooks).
			 *  2. The customer's selected block (Additional Checkout Field
			 *     "parcel-delivery/billing-block" or legacy billing_block) looked
			 *     up against the saved blocks list.
			 *  3. The configured fallback cost.
			 */
			protected function resolve_cost() {
				$log = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

				// ── Priority 1: session value set by checkout hooks ──
				// The checkout module's recalculate_shipping_for_block() computes
				// the correct cost and stashes it in session. This is the most
				// reliable source because it runs in the same request cycle as
				// the customer's block selection.
				if ( function_exists( 'WC' ) && WC() && WC()->session ) {
					$session_cost = WC()->session->get( 'block_shipping_cost' );
					if ( is_numeric( $session_cost ) && $session_cost > 0 ) {
						if ( $log ) { $log->info( '[PD] resolve_cost: using session cost=' . $session_cost, array( 'source' => 'parcel-delivery' ) ); }
						return (float) $session_cost;
					}
				}

				// ── Priority 2: look up the block's del_charge directly ──
				$block_id = $this->get_selected_block_id();
				if ( $log ) {
					$log->info( '[PD] resolve_cost: session empty, trying block_id="' . $block_id . '"', array( 'source' => 'parcel-delivery' ) );
				}
				if ( '' === $block_id ) {
					if ( $log ) { $log->warning( '[PD] resolve_cost: no block selected, returning fallback_cost', array( 'source' => 'parcel-delivery' ) ); }
					return (float) $this->get_option( 'fallback_cost', 0 );
				}

				if ( function_exists( 'global_features_object' ) ) {
					$blocks = global_features_object()->get_blocks_data();
					if ( is_array( $blocks ) && ! empty( $blocks ) ) {
						$key = array_search( $block_id, array_column( $blocks, 'id' ), false );
						if ( false === $key ) {
							// Try loose string comparison (int vs string IDs).
							$key = array_search( (string) $block_id, array_map( 'strval', array_column( $blocks, 'id' ) ), false );
						}
						if ( false !== $key ) {
							$block_obj = $blocks[ $key ];
							$cost = is_object( $block_obj ) && isset( $block_obj->del_charge )
								? (float) $block_obj->del_charge
								: ( is_array( $block_obj ) && isset( $block_obj['del_charge'] ) ? (float) $block_obj['del_charge'] : null );
							if ( null !== $cost ) {
								if ( $log ) { $log->info( '[PD] resolve_cost: found del_charge=' . $cost . ' for block_id="' . $block_id . '"', array( 'source' => 'parcel-delivery' ) ); }
								return $cost;
							}
						} else {
							if ( $log ) { $log->warning( '[PD] resolve_cost: block_id="' . $block_id . '" not found in ' . count( $blocks ) . ' saved blocks', array( 'source' => 'parcel-delivery' ) ); }
						}
					}
				}

				return (float) $this->get_option( 'fallback_cost', 0 );
			}

			/**
			 * Try every WC surface the block id might be stored on by the
			 * Cart/Checkout block (Store API) or the classic checkout.
			 */
			protected function get_selected_block_id() {
				if ( ! function_exists( 'WC' ) || ! WC() ) {
					return '';
				}

				// Additional Checkout Field API (block checkout, WC 8.6+).
				if ( WC()->customer && function_exists( 'wc_get_customer_additional_field_value' ) ) {
					try {
						$val = wc_get_customer_additional_field_value( WC()->customer, 'parcel-delivery/billing-block' );
						if ( ! empty( $val ) ) {
							return (string) $val;
						}
					} catch ( \Throwable $e ) {
						if ( function_exists( 'wc_get_logger' ) ) {
							wc_get_logger()->debug( 'Parcel Delivery: wc_get_customer_additional_field_value failed — ' . $e->getMessage(), array( 'source' => 'parcel-delivery' ) );
						}
					}
				}

				// Persisted on the customer object as meta.
				if ( WC()->customer ) {
					$meta = WC()->customer->get_meta( '_wc_billing/parcel-delivery/billing-block' );
					if ( '' === $meta || null === $meta ) {
						$meta = WC()->customer->get_meta( '_wc_other/parcel-delivery/billing-block' );
					}
					if ( ! empty( $meta ) ) {
						return (string) $meta;
					}
				}

				// Session value set by checkout hooks (works for both classic & block).
				if ( WC()->session ) {
					$session_block = WC()->session->get( 'chosen_billing_block' );
					if ( ! empty( $session_block ) ) {
						return (string) $session_block;
					}
				}

				// Classic checkout: direct POST field.
				if ( isset( $_POST['billing_block'] ) && '' !== $_POST['billing_block'] ) {
					return sanitize_text_field( wp_unslash( $_POST['billing_block'] ) );
				}

				// Classic checkout AJAX: billing_block is inside the serialized
				// post_data string, NOT as a separate $_POST key.
				if ( isset( $_POST['post_data'] ) ) {
					$parsed = array();
					$vars   = explode( '&', (string) wp_unslash( $_POST['post_data'] ) );
					foreach ( $vars as $pair ) {
						$kv = explode( '=', $pair, 2 );
						$k  = isset( $kv[0] ) ? urldecode( $kv[0] ) : '';
						$v  = isset( $kv[1] ) ? urldecode( $kv[1] ) : '';
						if ( '' !== $k ) {
							$parsed[ $k ] = $v;
						}
					}
					if ( ! empty( $parsed['billing_block'] ) ) {
						return sanitize_text_field( $parsed['billing_block'] );
					}
				}

				// Posted as part of the Store API request body (cart/update-customer).
				if ( isset( $_REQUEST['billing_address']['parcel-delivery/billing-block'] ) ) {
					return sanitize_text_field( wp_unslash( $_REQUEST['billing_address']['parcel-delivery/billing-block'] ) );
				}

				return '';
			}
		}
	}

	function pd_register_shipping_method( $methods ) {
		pd_register_shipping_method_class();
		if ( class_exists( 'WC_Parcel_Delivery_Shipping_Method' ) ) {
			$methods['parcel_delivery'] = 'WC_Parcel_Delivery_Shipping_Method';
		}
		return $methods;
	}

	add_action( 'woocommerce_shipping_init', 'pd_register_shipping_method_class' );
	add_filter( 'woocommerce_shipping_methods', 'pd_register_shipping_method' );
}
