<?php
/*
  Plugin Name: National Bank of Greece Payment Gateway
  Description: Take payments through various bank cards, such as Maestro, Mastercard and Visa.
  Version: 1.0.2
  Author: emspace.gr
  Author URI: https://emspace.gr
  Text Domain: woocommerce-nbg-payment-gateway
  Domain Path: /languages/
  License: GPL-3.0+
  License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 */

if( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'woocommerce_nbg_init', 0 );

require_once( 'simplexml.php' );

function woocommerce_nbg_init() {
	if( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	load_plugin_textdomain( 'woocommerce-nbg-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Gateway class
	 */
	class WC_NBG_Gateway extends WC_Payment_Gateway {

		function __construct() {
			global $wpdb;

			$this->id = 'nbg_gateway';
			$this->icon = apply_filters( 'nbg_icon', plugins_url( 'assets/nbg.png', __FILE__ ) );
			$this->has_fields = false;
			$this->notify_url = WC()->api_request_url( 'WC_NBG_Gateway' );
			$this->method_title = __( 'National Bank of Greece Payment Gateway', 'woocommerce-nbg-payment-gateway' );
			$this->method_description = __( 'Take payments through various bank cards, such as Maestro, Mastercard and Visa.', 'woocommerce-nbg-payment-gateway' );

			// Load the form fields
			$this->init_form_fields();

			// Create the table
			if( $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "nbg_transactions'" ) === $wpdb->prefix . 'nbg_transactions' ) {
				// Table exists
			} else {
				// Table does not exist
				$query = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'nbg_transactions (id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, merchantreference VARCHAR(30) NOT NULL, reference VARCHAR(100) NOT NULL, orderid VARCHAR(100) NOT NULL, timestamp DATETIME DEFAULT NULL, PRIMARY KEY (id))';
				$wpdb->query( $query );
			}

			// Load the settings
			$this->init_settings();

			// Define user-set variables
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			$this->nbg_username = $this->get_option( 'nbg_username' );
			$this->nbg_password = $this->get_option( 'nbg_password' );

			$this->nbg_installments = $this->get_option( 'nbg_installments' );
			$this->nbg_minimum = $this->get_option( 'nbg_minimum' );

			$this->nbg_mode = $this->get_option( 'nbg_mode' );
			$this->nbg_description = $this->get_option( 'nbg_description' ) != '' ? $this->get_option( 'nbg_description' ) : $this->nbg_get_purchase_description();
			$this->nbg_method = $this->get_option( 'nbg_method' );
			$this->nbg_redirect = $this->get_option( 'nbg_redirect' );

			// Define other variables
			if( $this->nbg_mode == 'test' ) {
				$this->nbg_pageset = '243';
				$this->nbg_url = 'https://accreditation.datacash.com/Transaction/acq_a';
			} else {
				$this->nbg_pageset = '3366';
				$this->nbg_url = 'https://mars.transaction.datacash.com/Transaction';
			}

			// Action hooks
			add_action( 'woocommerce_receipt_nbg_gateway', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_nbg_gateway', array( $this, 'check_nbg_response' ) );
		}

		/**
		 * Admin panel options
		 */
		function admin_options() {
			echo '<h3>' . __( 'National Bank of Greece Payment Gateway', 'woocommerce-nbg-payment-gateway' ) . '</h3>';
			echo '<p>' . __( 'Take payments through various bank cards, such as Maestro, Mastercard and Visa.', 'woocommerce-nbg-payment-gateway' ) . '</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		/**
		 * Initialise gateway settings form fields
		 */
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable National Bank of Greece payment gateway', 'woocommerce-nbg-payment-gateway' ),
					'default'     => 'no'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true,
					'default'     => __( 'National Bank of Greece Payment Gateway', 'woocommerce-nbg-payment-gateway' )
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true,
					'default'     => __( 'Pay via National Bank of Greece; accepts Mastercard, Visa, etc.', 'woocommerce-nbg-payment-gateway' )
				),
				'credentials_group' => array(
					'title'       => __( 'Gateway credentials', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'title',
					'description' => '',
				),
				'nbg_username' => array(
					'title'       => __( 'vTID (Client)', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'Gateway credentials given to you by the bank.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true,
					'default'     => ''
				),
				'nbg_password' => array(
					'title'       => __( 'Processing Password', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'Gateway credentials given to you by the bank.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true,
					'default'     => ''
				),
				'installments_group' => array(
					'title'       => __( 'Installment configuration', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'title',
					'description' => '',
				),
				'nbg_installments' => array(
					'title'       => __( 'Installments', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'select',
					'options'     => $this->nbg_get_installments(),
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Number of installments; select between 1 to 36 (1 is for one-time payment and disables installments). Note: in order to avoid transactions being rejected, please ensure the installments number selected matches the agreed number by the bank.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => false
				),
				'nbg_minimum' => array(
					'title'       => __( 'Minimum Order Total', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'Minimum order total during checkout for which paying in installments will be available. Note: in order to avoid transactions being rejected, please ensure the minimum order total typed matches the agreed minimum total by the bank.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => false,
				),
				'advanced_group' => array(
					'title'       => __( 'Advanced options', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'title',
					'description' => '',
				),
				'nbg_mode' => array(
					'title'       => __( 'Gateway Environment', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'select',
					'options'     => array(
						'test'    => __( 'Accreditation', 'woocommerce-nbg-payment-gateway' ),
						'live'     => __( 'Production', 'woocommerce-nbg-payment-gateway' )
					),
					'class'       => 'wc-enhanced-select',
					'description' => __( 'This sets the payment gateway to operate in either Accreditation (TEST) or Production (LIVE) mode.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true,
					'default'     => 'test'
				),
				'nbg_description' => array(
					'title'       => __( 'Purchase Description', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'Type a generic purchase description, up to 125 characters, for the 3D-Secure verification of the bank. If you prefer to use a description specific to what has been purchased, leave the field blank and the system will automatically generate one based on the items in the cart instead.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true,
					'default'     => ''
				),
				'nbg_method' => array(
					'title'       => __( 'Payment Method', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'select',
					'options'     => array(
						'auth'    => __( 'Authorize', 'woocommerce-nbg-payment-gateway' ),
						'pre'     => __( 'Pre-Authorize', 'woocommerce-nbg-payment-gateway' )
					),
					'class'       => 'wc-enhanced-select',
					'description' => sprintf( __( "Select whether you wish to authorize capture of funds immediately or pre-authorize it for manual fulfillment through the bank's <a href='%s' target='_blank' rel='noopener noreferrer'>Merchant Reporting Portal</a>. Note: before selecting Pre-Authorize, please ensure that you meet specific requirements set by the bank, such as a declared static IP to access the bank's portal, etc.", 'woocommerce-nbg-payment-gateway' ), 'https://reporting.datacash.com/NBG/login' ),
					'desc_tip'    => false,
					'default'     => 'auth'
				),
				'nbg_redirect' => array(
					'title'       => __( 'Return Page', 'woocommerce-nbg-payment-gateway' ),
					'type'        => 'select',
					'options'     => $this->nbg_get_pages( __( 'Select Page', 'woocommerce-nbg-payment-gateway' ), true ),
					'class'       => 'wc-enhanced-select',
					'description' => __( 'This controls the page where the user is redirected to after successful payment.', 'woocommerce-nbg-payment-gateway' ),
					'desc_tip'    => true
				)
			);
		}

		function nbg_get_pages( $title = false, $indent = true ) {
			$wp_pages = get_pages( 'sort_column=menu_order' );
			$page_list = array();

			if( $title ) $page_list[] = $title;

			foreach( $wp_pages as $page ) {
				$prefix = '';

				// Show indented child pages?
				if( $indent ) {
					$has_parent = $page->post_parent;

					while( $has_parent ) {
						$prefix .= ' - ';
						$next_page = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}

				// Add to page list array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}

			$page_list[-1] = __( 'Thank you page', 'woocommerce-nbg-payment-gateway' );

			return $page_list;
		}

		function nbg_get_installments() {
			for( $i = 1; $i <= 36; $i++ ) {
				$installment_list[$i] = $i;
			}

			return $installment_list;
		}

		function nbg_get_purchase_description() {
			$products = WC()->cart->cart_contents;

			if( is_array( $products ) ) {
				foreach( $products as $product ) {
					$prods[] = $product['data']->get_title();
				}

				return substr( implode( ' / ', $prods ), 0, 125 );
			} else {
				return '';
			}
		}

		function nbg_get_dyn_data( $cart_total ) {
			global $woocommerce;

			$dyn_data = array(
				'lang'         => defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : 'el',
				'merchantName' => get_bloginfo( 'name' ),
				'backUrl'      => $woocommerce->cart->get_checkout_url()
			);

			if( $this->nbg_installments > 1 && is_numeric( $this->nbg_minimum ) && $cart_total >= $this->nbg_minimum && $this->nbg_minimum >= 15 ) {
				$dyn_data = array_merge( $dyn_data, array(
					'enableInstallments'   => true,
					'installmentsRequired' => false,
					'installmentsMin'      => 2,
					'installmentsMax'      => $this->nbg_installments
				) );
			}

			return json_encode( $dyn_data, JSON_UNESCAPED_SLASHES );
		}

		function generate_nbg_form( $order_id ) {
			global $wpdb;

			$order = new WC_Order( $order_id );

			$merchantreference = strtoupper( substr( sha1( rand() ), 0, 30 ) );

			// Make the XML
			$xml = new nbg_SimpleXMLExtended( '<?xml version="1.0" encoding="utf-8"?><Request version="2"/>' );

			$authentication = $xml->addChild( 'Authentication' );
			$authentication->addChild( 'password', $this->nbg_password );
			$authentication->addChild( 'client', $this->nbg_username );
			$transaction = $xml->addChild( 'Transaction' );
			$TxnDetails = $transaction->addChild( 'TxnDetails' );
			$TxnDetails->addChild( 'merchantreference', $merchantreference );
			$ThreeDSecure = $TxnDetails->addChild( 'ThreeDSecure' );
			$Browser = $ThreeDSecure->addChild( 'Browser' );
			$Browser->addChild( 'device_category', '0' );
			$Browser->addChild( 'accept_headers', '*/*' );
			$Browser->addChild( 'user_agent', $_SERVER['HTTP_USER_AGENT'] );
			$ThreeDSecure->addChild( 'purchase_datetime', current_time( 'Ymd h:i:s' ) );
			$ThreeDSecure->addChild( 'merchant_url', get_site_url() );
			$ThreeDSecure->addChild( 'purchase_desc', $this->nbg_description );
			$ThreeDSecure->addChild( 'verify', 'yes' );
			$TxnDetails->addChild( 'capturemethod', 'ecomm' );
			$amount = $TxnDetails->addChild( 'amount', $order->get_total() );
			$amount->addAttribute( 'currency', 'EUR' );
			$HpsTxn = $transaction->addChild( 'HpsTxn' );
			$HpsTxn->addChild( 'page_set_id', $this->nbg_pageset );
			$DynamicData = $HpsTxn->addChild( 'DynamicData' );
			$DynamicData->dyn_data_2 = NULL;
			$DynamicData->dyn_data_2->addCData( $this->nbg_get_dyn_data( $order->get_total() ) );
			$HpsTxn->addChild( 'method', 'setup_full' );
			$HpsTxn->addChild( 'return_url', get_site_url() . "?wc-api=WC_NBG_gateway&amp;nbg=success&amp;MerchantReference=" . $merchantreference );
			$HpsTxn->addChild( 'expiry_url', get_site_url() . "?wc-api=WC_NBG_gateway&amp;nbg=cancel" );
			$HpsTxn->addChild( 'error_url', get_site_url() . "?wc-api=WC_NBG_gateway&amp;nbg=error" );
			$CardTxn = $transaction->addChild( 'CardTxn' );
			$CardTxn->addChild( 'method', $this->nbg_method );

			// Make the XML CURL call
			$ch = curl_init( $this->nbg_url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml->asXML() );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Content-Type: text/xml" ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_NOPROGRESS, 0 );

			// Get the XML reply
			$result = curl_exec( $ch );
			curl_close( $ch );

			if( $result == false ) return __( 'Could not connect to the Payment Gateway, please contact the administrator.', 'woocommerce-nbg-payment-gateway' );

			// Parse the XML reply
			$response = simplexml_load_string( $result );

			if( $response->status != 1 ) {
				// An error occurred
				return __( 'An error occurred, please contact the administrator.', 'woocommerce-nbg-payment-gateway' );
			} else {
				// Save data in DB and redirect to the Payment Gateway
				$wpdb->insert( $wpdb->prefix . 'nbg_transactions', array(
					'reference'         => $response->datacash_reference,
					'merchantreference' => $merchantreference,
					'orderid'           => $order_id,
					'timestamp'         => current_time( 'mysql', 1 )
				) );

				$requesturl = $response->HpsTxn->hps_url . '?HPS_SessionID=' . $response->HpsTxn->session_id;

				wc_enqueue_js( '$.blockUI({
					message:"' . esc_js( __( 'Thank you for your order. We are now redirecting you to the Payment Gateway.', 'woocommerce-nbg-payment-gateway' ) ) . '",
					baseZ:99999,
					overlayCSS:{
						background:"#fff",
						opacity:0.6
					},
					css:{
						padding:"20px",
						zindex:"9999999",
						textAlign:"center",
						color:"#555",
						border:"3px solid #aaa",
						backgroundColor:"#fff",
						cursor:"wait",
						lineHeight:"24px",
					}
				});
				setTimeout(function(){jQuery("#submit_nbg_payment_form").click();},3000);' );

				return '<form action="' . $requesturl . '" method="post" id="nbg_payment_form" target="_top">
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_nbg_payment_form" value="' . __( 'Pay via National Bank of Greece', 'woocommerce-nbg-payment-gateway' ) . '" />
						<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order & restore cart', 'woocommerce-nbg-payment-gateway' ) . '</a>
					</div>
					<script type="text/javascript">
						jQuery(".payment_buttons").hide();
					</script>
				</form>';
			}
		}

		/**
		 * Process the payment and return the result
		 */
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );

			return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );
		}

		/**
		 * Output for the order received page.
		 */
		function receipt_page( $order ) {
			echo '<p>' . __( 'Your order is pending payment. You should be automatically redirected to the National Bank of Greece to make the payment.', 'woocommerce-nbg-payment-gateway' ) . '</p>';
			echo $this->generate_nbg_form( $order );
		}

		/**
		 * Verify a successful payment
		 */
		function check_nbg_response() {
			global $wpdb;
			global $woocommerce;

			if( isset( $_GET['nbg'] ) && ( $_GET['nbg'] === 'success' ) ) {
				$merchantreference = $_GET['MerchantReference'];

				// Query the DB
				$ttquery = 'SELECT * FROM `' . $wpdb->prefix . 'nbg_transactions` WHERE `merchantreference` LIKE "' . $merchantreference . '";';
				$ref = $wpdb->get_results( $ttquery );
				$orderid = $ref['0']->orderid;

				// Make the XML
				$xml = new nbg_SimpleXMLExtended( '<?xml version="1.0" encoding="utf-8"?><Request version="2"/>' );

				$authentication = $xml->addChild( 'Authentication' );
				$authentication->addChild( 'password', $this->nbg_password );
				$authentication->addChild( 'client', $this->nbg_username );
				$transaction = $xml->addChild( 'Transaction' );
				$HistoricTxn = $transaction->addChild( 'HistoricTxn' );
				$HistoricTxn->addChild( 'reference', $ref['0']->reference );
				$HistoricTxn->addChild( 'method', 'query' );

				// Make the XML CURL call
				$ch = curl_init( $this->nbg_url );
				curl_setopt( $ch, CURLOPT_POST, 1 );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml->asXML() );
				curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Content-Type: text/xml" ) );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_NOPROGRESS, 0 );

				// Get the XML reply
				$result = curl_exec( $ch );
				curl_close( $ch );

				if( $result == false ) return __( 'Could not connect to the Payment Gateway, please contact the administrator.', 'woocommerce-nbg-payment-gateway' );

				// Parse the XML reply
				$response = simplexml_load_string( $result );

				$trans_id = $response -> merchantreference;
				
				$order = new WC_Order( $orderid );

				if( $response->status == 1 ) {
					if( strcmp( $response->reason, 'ACCEPTED' ) == 0 ) {
						// Verified - Successful payment - Complete the order
						if( $order->get_status() == 'processing' ) {
							// Add admin order note
							$order->add_order_note( __( 'Payment via National Bank of Greece<br />Transaction ID: ', 'woocommerce-nbg-payment-gateway' ) . $trans_id );

							// Add customer order note
							$order->add_order_note( __( 'Payment received.<br />Your order is currently being processed.<br />We will be shipping your order soon.<br />Transaction ID: ', 'woocommerce-nbg-payment-gateway' ) . $trans_id, 1 );

							// Reduce stock levels
							$order->reduce_order_stock();

							// Empty cart
							WC()->cart->empty_cart();

							$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'woocommerce-nbg-payment-gateway' );
							$message_type = 'success';
						} else {
							if( $order->has_downloadable_item() ) {
								// Update order status
								$order->update_status( 'completed', __( 'Payment received, your order is now complete.', 'woocommerce-nbg-payment-gateway' ) );

								// Add admin order note
								$order->add_order_note( __( 'Payment via National Bank of Greece<br />Transaction ID: ', 'woocommerce-nbg-payment-gateway' ) . $trans_id );

								// Add customer order note
								$order->add_order_note( __( 'Payment received.<br />Your order is now complete.<br />Transaction ID: ', 'woocommerce-nbg-payment-gateway' ) . $trans_id, 1 );

								$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'woocommerce-nbg-payment-gateway' );
								$message_type = 'success';
							} else {
								// Update order status
								$order->update_status( 'processing', __( 'Payment received, your order is currently being processed.', 'woocommerce-nbg-payment-gateway' ) );

								// Add admin order note
								$order->add_order_note( __( 'Payment via National Bank of Greece<br />Transaction ID: ', 'woocommerce-nbg-payment-gateway' ) . $trans_id );

								// Add customer order note
								$order->add_order_note( __( 'Payment received.<br />Your order is currently being processed.<br />We will be shipping your order soon.<br />Transaction ID: ', 'woocommerce-nbg-payment-gateway' ) . $trans_id, 1 );

								$message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'woocommerce-nbg-payment-gateway' );
								$message_type = 'success';
							}

							$nbg_message = array( 'message' => $message, 'message_type' => $message_type );

							update_post_meta( $order_id, '_nbg_message', $nbg_message );

							// Reduce stock levels
							$order->reduce_order_stock();

							// Empty cart
							WC()->cart->empty_cart();
						}
					} else {
						// Payment has failed - Retry
						$message = __( 'Thank you for shopping with us.<br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'woocommerce-nbg-payment-gateway' );
						$message_type = 'error';

						$nbg_message = array( 'message' => $message, 'message_type' => $message_type );

						update_post_meta( $order_id, '_nbg_message', $nbg_message );

						// Update order status
						$order->update_status( 'failed', '' );

						// Redirect and exit
						$checkout_url = $woocommerce->cart->get_checkout_url();
						wp_redirect( $checkout_url );
						exit;
					}
				} else {
					// An error occurred
					$message = __( 'Thank you for shopping with us.<br />However, an error occurred and the transaction wasn\'t successful, payment wasn\'t received.', 'woocommerce-nbg-payment-gateway' );
					$message_type = 'error';

					$nbg_message = array( 'message' => $message, 'message_type' => $message_type );

					update_post_meta( $order_id, '_nbg_message', $nbg_message );

					// Update order status
					$order->update_status( 'failed', '' );

					// Redirect and exit
					$checkout_url = $woocommerce->cart->get_checkout_url();
					wp_redirect( $checkout_url );
					exit;
				}

				if( $this->nbg_redirect == "-1" ) {
					$redirect_url = $this->get_return_url( $order );
				} else {
					$redirect_url = ( $this->nbg_redirect == "" || $this->nbg_redirect == 0 ) ? get_site_url() . "/" : get_permalink( $this->nbg_redirect );

					// For WooCoomerce 2.0
					$redirect_url = add_query_arg( array( 'msg' => urlencode( $this->msg['message'] ), 'type' => $this->msg['class'] ), $redirect_url );
				}

				wp_redirect( $redirect_url );
				exit;
			}

			if( isset( $_GET['nbg'] ) && ( $_GET['nbg'] === 'cancel' ) ) {
				// Redirect and exit
				$checkout_url = $woocommerce->cart->get_checkout_url();
				wp_redirect( $checkout_url );
				exit;
			}

			if( isset( $_GET['nbg'] ) && ( $_GET['nbg'] === 'error' ) ) {
				// An error occurred
				$message = __( 'Thank you for shopping with us.<br />However, an error occurred and the transaction wasn\'t successful, payment wasn\'t received.', 'woocommerce-nbg-payment-gateway' );
				$message_type = 'error';

				$nbg_message = array( 'message' => $message, 'message_type' => $message_type );

				update_post_meta( $order_id, '_nbg_message', $nbg_message );

				// Update order status
				$order->update_status( 'failed', '' );

				// Redirect and exit
				$checkout_url = $woocommerce->cart->get_checkout_url();
				wp_redirect( $checkout_url );
				exit;
			}
		}
	}

	add_action( 'wp', 'nbg_message' );

	function nbg_message() {
		$order_id = absint( get_query_var( 'order-received' ) );
		$payment_method = wc_get_payment_gateway_by_order($order_id);

		if( is_order_received_page() && ( 'nbg_gateway' == $payment_method ) ) {
			$nbg_message = get_post_meta( $order_id, '_nbg_message', true );

			$message = $nbg_message['message'];
			$message_type = $nbg_message['message_type'];

			delete_post_meta( $order_id, '_nbg_message' );

			if( ! empty( $nbg_message ) ) {
				wc_add_notice( $message, $message_type );
			}
		}
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_nbg_gateway' );

	function woocommerce_add_nbg_gateway( $methods ) {
		$methods[] = 'WC_NBG_Gateway';

		return $methods;
	}

	if( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {
		add_filter( 'plugin_action_links', 'nbg_plugin_action_links', 10, 2 );

		function nbg_plugin_action_links( $links, $file ) {
			static $this_plugin;

			if( ! $this_plugin ) {
				$this_plugin = plugin_basename( __FILE__ );
			}

			if( $file == $this_plugin ) {
				$settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_NBG_Gateway">Settings</a>';
				array_unshift( $links, $settings_link );
			}

			return $links;
		}
	} else {
		add_filter( 'plugin_action_links', 'nbg_plugin_action_links', 10, 2 );

		function nbg_plugin_action_links( $links, $file ) {
			static $this_plugin;

			if( ! $this_plugin ) {
				$this_plugin = plugin_basename( __FILE__ );
			}

			if( $file == $this_plugin ) {
				$settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_NBG_Gateway">Settings</a>';
				array_unshift( $links, $settings_link );
			}

			return $links;
		}
	}
}