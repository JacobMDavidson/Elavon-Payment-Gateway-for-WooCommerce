<?php
/*
Plugin Name: WooCommerce Virtual Merchant Gateway
Description: Virtual Merchant/Elavon payment gateway plugin for WooCommerce. A Virtual Merchant account through Elavon, and a server with SSL support and an SSL certificate is required (for security reasons) for this gateway to function. This gateway is configured for US purchases in US Dollars only.
Version: 1.0.6
Author: Jacob Davidson
Author URI: http://jacobmdavidson.wordpress.com//
*/

add_action( 'plugins_loaded', 'woocommerce_virtualmerchant_init', 0 );

function woocommerce_virtualmerchant_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { 
		return; 
	}

	/**
	 * Gateway Class
	 */
	class WC_Gateway_Virtualmerchant extends WC_Payment_Gateway {

		/**
		 *Define Virtual Merchant Variables
		 */

		public $merchant_id;
		public $user_id;
		public $pin;
		public $liveurl = 'https://www.myvirtualmerchant.com/VirtualMerchant/process.do';
		public $testurl = 'https://demo.myvirtualmerchant.com/VirtualMerchantDemo/process.do';
		public $testmode;
		public $cvv_enabled;
		public $avs_options;
		public $transactionid;

		/**
		 * Constructor
		 */
		function __construct() { 

			$this->id 				= 'virtualmerchant';
			$this->method_title		= __('VirtualMerchant', 'woothemes');
			$this->has_fields 		= true;
			$this->supports[]		= 'default_credit_card_form';

			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Get setting values
			$this->title 					= $this->settings['title'];
			$this->description 				= $this->settings['description'];
			$this->enabled					= $this->settings['enabled'];
			$this->merchant_id				= $this->settings['merchant_id'];
			$this->user_id					= $this->settings['user_id'];
			$this->pin						= $this->settings['pin'];
			$this->testmode 				= $this->settings['testmode'];
			$this->cvv_enabled				= $this->settings['cvv_enabled'];
			$this->avs_options				= $this->settings['avs_options'];
		
			// SSL check hook used on admin options to determine if SSL is enabled
			add_action( 'admin_notices', array( &$this, 'ssl_check' ) );

			// Save admin options
			add_action( 'woocommerce_update_options_payment_gateways',              array( $this, 'process_admin_options' ) );  // WC < 2.0
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );  // WC >= 2.0
		}

		/**
		 * Check if SSL is enabled and notify the user if SSL is not enabled
		 */
	 	function ssl_check() {

			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				echo '<div class="error"><p>'.sprintf(__('VirtualMerchant is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate - VirtualMerchant will only work in test mode.', 'woothemes'), admin_url('admin.php?page=woocommerce')).'</p></div>';
			}
		}

		/**
		 *Initialize Gateway Settings Form Fields
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'title' => array(
								'title' => __( 'Title', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ), 
								'default' => __( 'Credit Card / Debit Card', 'woothemes' )
							), 
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woothemes' ), 
								'label' => __( 'Enable VirtualMerchant', 'woothemes' ), 
								'type' => 'checkbox', 
								'description' => '', 
								'default' => 'no'
							), 			
				'description' => array(
								'title' => __( 'Description', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ), 
								'default' => 'Pay with your credit card or debit card.'
							),  
				'testmode' => array(
								'title' => __( 'VirtualMerchant Test', 'woothemes' ), 
								'label' => __( 'Enable VirtualMerchant Test', 'woothemes' ), 
								'type' => 'checkbox', 
								'description' => __( 'Process transactions in Test Mode via the VirtualMerchant Test account (demo.myvirtualmerchant.com). Contact Virtual Merchant at 1-800-377-3962 to request a unique test account', 'woothemes' ), 
								'default' => 'no'
							), 
				'merchant_id' => array(
								'title' => __( 'Merchant ID', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'Merchant ID provided by VirtualMerchant.', 'woothemes' ), 
								'default' => ''
							), 
				'user_id' => array(
								'title' => __( 'User ID', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'User ID provided by VirtualMerchant.', 'woothemes' ), 
								'default' => ''
							),
				'pin' => array(
								'title' => __( 'pin', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'pin provided by VirtualMerchant.', 'woothemes' ), 
								'default' => ''
							),
				'cvv_enabled' => array(
								'title' => __( 'Enable CSC Authentication', 'woothemes' ), 
								'label' => __( 'Enable CSC Authentication', 'woothemes' ), 
								'type' => 'checkbox', 
								//'description' => __( 'This option must also be enabled on your VirtualMerchant account as a Post-Processing Rule under Business Rules. Contact VirtualMerchant if you have any questions.', 'woothemes' ), 
								'default' => 'no'
							),	
				'avs_options' => array(
								'title' => __( 'Enable AVS Authentication', 'woothemes' ), 
								'description' => __( 'AVS Authentication Level', 'woothemes' ), 
     							'type' => 'select',
     							'default' => 'None',
     							'options' => array(
     									'none' => 'Disable AVS Verification',
     									'address_or_zip' => 'Low - Either Address or Zip Match',
     									'zip' => 'Medium - Zip Must Match',
     									'address' => 'Medium - Address Must Match',
     									'strict' => 'Strict - Both Address and Zip Must Match'
     								) 
     						)			
				);
		}

		/**
		 * Admin panel options
		 */
		function admin_options() {
		
			?>
			<h3><?php _e( 'VirtualMerchant', 'woothemes' ); ?></h3>
			<p>	
				<?php _e( 'VirtualMerchant works by adding credit card fields on the checkout and then sending the details to VirtualMerchant for verification.', 'woothemes' ); ?><br />
				<?php _e( "<strong><u>WARNING:</u></strong> WooCommerce is currently set to use '" . get_woocommerce_currency() . "' currency.", 'woothemes'); ?>
				<?php _e( ' Please make sure your Virtual Merchant account is set to accept this currency.', 'woothemes'); ?>
			</p>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
			<?php
		}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_available() {

			if ($this->enabled=="yes") {

				if (get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->testmode == 'no' ) {
					return false;
				}
				return true;
			} else {
				return false;
			}
		}

		/*
		 * Get the users country either from their order, or from their customer data
		 */
		function get_country_code() {

			global $woocommerce;

			if ( isset( $_GET['order_id'] ) ) {
				$order = new WC_Order( $_GET['order_id'] );
				return $order->billing_country;
			} elseif ( $woocommerce->customer->get_country() ) {
				return $woocommerce->customer->get_country();
			} else {
			return NULL;
			}
		}

		/*
		 * Payment form on checkout page
		 */
		function payment_fields() {

			$user_country = $this->get_country_code();

			if( empty($user_country) ) {
				echo __('Select a country to see the payment form', 'woothemes');
				return;
			}
			
			$description = $this->get_description();
			if ( 'yes' == $this->testmode ) {
				$description .= ' TEST MODE ENABLED.';
			}
			
			if ( $description ) {
				echo wpautop( wptexturize( trim( $description ) ) );
			}
			$this->credit_card_form();

		}

		/**
		 * Process the payment, receive and validate the results, and redirect to the thank you page upon a successful transaction
		 */
		function process_payment( $order_id ) {

			global $woocommerce;
			$order = new WC_Order( $order_id );
			$card_number		= str_replace( array(' ', '-'), '', $_POST['virtualmerchant-card-number']);
			$card_csc			= isset( $_POST['virtualmerchant-card-cvc'] ) ? $_POST['virtualmerchant-card-cvc'] : '';
			$card_expiration	= str_replace( array( '/', ' '), '', $_POST['virtualmerchant-card-expiry'] );

			// Validate plugin settings
			if ( ! $this->validate_settings() ) {
				$cancelNote = __('Order was cancelled due to invalid settings (check your credentials).', 'woothemes');
				$order->add_order_note( $cancelNote );
				wc_add_notice(__('Payment was rejected due to configuration error.', 'woothemes'), $notice_type = 'error');
				return false;
			}

			// Send request to virtualmerchant
			$url = $this->liveurl;

			//Determine if testmode is enabled and set the URL accordingly
			if ( $this->testmode == 'yes' ) {
				$url = $this->testurl;
			}

			$cvv_enabled = $this->cvv_enabled;
			
			$avs_options = $this->avs_options;
			
			$authorization = array(
					'ssl_merchant_id'			=> $this->merchant_id,
					'ssl_user_id'				=> $this->user_id,
					'ssl_pin'					=> $this->pin,
					'ssl_show_form'				=> "false",
					'ssl_transaction_type'		=> "ccverify",
					'ssl_card_number'			=> $card_number,
					'ssl_exp_date'				=> $card_expiration,
					'ssl_cvv2cvc2'				=> $card_csc,
					'ssl_avs_zip'				=> $order->billing_postcode,
					'ssl_avs_address'			=> $order->billing_address_1,
					'ssl_result_format'			=> 'ascii'
				);
					
			
			$fields = array(
					'ssl_merchant_id'			=> $this->merchant_id,
					'ssl_user_id'				=> $this->user_id,
					'ssl_pin'					=> $this->pin,
					'ssl_transaction_type'		=> "ccsale",
					'ssl_show_form'				=> "false",
					'ssl_card_number'			=> $card_number,
					'ssl_exp_date'				=> $card_expiration,
					'ssl_amount'				=> $order->order_total,
					'ssl_salestax'				=> $order->get_total_tax(),
					'ssl_cvv2cvc2_indicator'	=> '1',
					'ssl_cvv2cvc2'				=> $card_csc,
					'ssl_avs_zip'				=> $order->billing_postcode,
					'ssl_invoice_number'		=> $order_id,
					'ssl_avs_address'			=> $order->billing_address_1,
					'ssl_first_name'			=> $order->billing_first_name,
					'ssl_last_name'				=> $order->billing_last_name,
					'ssl_city'					=> $order->billing_city,
					'ssl_state'					=> $order->billing_state,
					'ssl_result_format'			=> 'ascii',
					'ssl_test_mode' 			=>'false',
				);
									
			// Verify the transaction (CVV and AVS checks)
			try{
				//execute wp_remote_post
				$authorization_result = wp_remote_post( $url, array (
						'method'	=> 'POST',
						'timeout'	=> 90,
						'sslverify'	=> false,
						'body'		=> http_build_query($authorization)
					)
				);

				//Check for wp_remote_post errors
				if ( is_wp_error( $authorization_result ) ) 
					throw new Exception( 'There was an error during authorization' );
				if ( empty( $authorization_result['body'] ) ) 
					throw new Exception( 'Empty VirtualMerchant Output during authorization.' );

				//parse the resulting array
				parse_str( str_replace( array( "\n", "\r" ), '&', $authorization_result['body'] ), $authorization_output );
				
				// Used for Testing

				//parse_str("ssl_result=0&ssl_result_message=ssl_result_message&ssl_avs_response=A&ssl_cvv2_response=M&errorCode=errorCode&errorName=errorName", $authorization_output);
				//parse_str("ssl_result=0&ssl_result_message=ssl_result_message&ssl_avs_response=X&ssl_cvv2_response=M&errorCode=A&errorMessage=this is a test message&errorName=Error", $authorization_output);

			}

			//Catch any errors caused by wp_remote_post
			catch( Exception $e ) {
				wc_add_notice(__( 'There was a connection error', 'woothemes' ) . ': "' . $e->getMessage() . '"', $notice_type = 'error' );
				return;
			}
						
			/**
			 *Check for Valid CVV in the wp_remote_post results
			 */
			function cvv_check( $cvv_check, $cvv_enabled_check ) {
			
				if ( $cvv_enabled_check == 'yes' ) {

					if( $cvv_check == 'M' ) {
						return true;
					} else {
						return false;
					}

				} else {
					return true;
				}
			}

			/**
			 *Check for Valid AVS in the wp_remote_post results
			 */
			function avs_check( $avs_response, $avs_options ) {
			
				if ( $avs_options != "none" ) {
					if( $avs_options == "strict") {
						$compare_array = array("X", "Y", "F", "D", "M");		
					} else if ( $avs_options == "address") {
						$compare_array = array("X", "Y", "F", "D", "M", "A", "B");
					} else if ( $avs_options == "zip") {
						$compare_array = array("X", "Y", "F", "D", "M", "W", "Z", "P");
					} else {
						$compare_array = array("X", "Y", "F", "D", "M", "A", "B", "W", "Z", "P");
					}

					if( in_array($avs_response, $compare_array) ) {
						return true;
					} else {
						return false;
					}

				} else {
					return true;
				}
			}

			//determine if the authorization was successful
			if ( cvv_check( $authorization_output['ssl_cvv2_response'], $cvv_enabled )  && 
				avs_check( $authorization_output['ssl_avs_response'], $avs_options ) ) {
				
				//Execute the actual payment
				try{
					
					//execute wp_remote_post
					$result = wp_remote_post( $url, array (
							'method'	=> 'POST',
							'timeout'	=> 90,
							'sslverify'	=> false,
							'body'		=> http_build_query( $fields )
						)
					);

					//Check for wp_remote_post errors
					if ( is_wp_error( $result ) ) throw new Exception( 'There was an error' );
					if ( empty( $result['body'] ) ) throw new Exception( 'Empty VirtualMerchant Output.' );

					//parse the resulting array
					parse_str( str_replace( array( "\n", "\r" ), '&', $result['body'] ), $output );
					
					// Used for testing
					// parse_str("ssl_result=1&ssl_result_message=ssl_result_message&errorCode=error core&errorMessage=error message&errorName=payment Error", $output);
				}

				//Catch any errors caused by wp_remote_post
				catch( Exception $e ) {
					
					wc_add_notice(__( 'There was a connection error', 'woothemes' ) . ': "' . $e->getMessage() . '"', $notice_type = 'error' );
					return;
				}

				//Assign transactionid if it is set in the wp_remote_post results
				if ( isset( $output['ssl_txn_id'] ) ) {
					$transactionid = $output['ssl_txn_id'];
				} else {
					$transactionid = '';
				} 
			
				//determine if the transaction was successful
				if ( isset( $output['ssl_result'] ) && ( $output['ssl_result'] == 0 ) ) {

					//add transaction id to payment complete message, update woocommerce order and cart
					$order->add_order_note( __( 'VirtualMerchant payment completed', 'woothemes' ) . '(Transaction ID: ' . $transactionid . ')' );
					$order->payment_complete();
					$woocommerce->cart->empty_cart();
			
					//redirect to the woocommerce thank you page
					return array(
						'result' => 'success',
						//'redirect' => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( get_option( 'woocommerce_thanks_page_id' ) ) ) )
						'redirect' => $order->get_checkout_order_received_url()
					);
				
				// There was an error during the payment process
				} else {
					if( isset( $output['ssl_result'] ) && ( $output['ssl_result'] != 0 ) ) {
						$responsemessage = 'Payment was declined for the following reason: '. $output['ssl_result_message'] . '. Try again, or select a different card.';
						if( isset( $output['errorCode'] ) ) {
							$responsemessage .= '<br />(An error occured - ' . $output['errorName'] . ': ' . $output['errorMessage'] . ')';
						}
					} else if ( isset( $output['errorCode'] ) ) {
						$responsemessage = 'An error occured - ' . $output['errorName'] . ': ' . $output['errorMessage'] . '';
					} else {
						$responsemessage =  "Unidentified Error. Try again, or select a different card.";
					}
					$cancelNote = __( 'VirtualMerchant payment failed', 'woothemes' ) . '(Transaction ID: ' . $transactionid . '). ' . __( 'Payment was rejected due to an error', 'woothemes' ) . ': "' . $responsemessage . '". ';
					$order->add_order_note( $cancelNote );
					$order->update_status( 'Failed',__( 'Payment method was declined.', 'woothemes' ) );
					wc_add_notice(__( 'Payment Error', 'woothemes' ) . ': ' . $responsemessage . '', $notice_type = 'error');
				}
				
			// There was an error during the verification process	
			} else {	
				if( isset( $authorization_output['ssl_result'] ) && ( $authorization_output['ssl_result'] != 0 ) && 
					cvv_check( $authorization_output['ssl_cvv2_response'], $cvv_enabled ) && 
					avs_check( $authorization_output['ssl_avs_response'], $avs_options )) {
					$responsemessage = 'Payment was declined for the following reason: '. $authorization_output['ssl_result_message'] . '. Try again, or select a different card.';
					if( isset( $authorization_output['errorCode'] ) ) {
						$responsemessage .=  '<br />(An error occured - ' . $authorization_output['errorName'] . ': ' . $authorization_output['errorMessage'] . ')';
					}
				} else if ( isset( $authorization_output['errorCode'] ) ) {
					$responsemessage = 'An error occured - ' . $authorization_output['errorName'] . ': ' . $authorization_output['errorMessage'] . '';
				} else if ( ! cvv_check( $authorization_output['ssl_cvv2_response'], $cvv_enabled ) ) {
					$responsemessage = "Payment was declined because the Card Security Code is not correct. Try again, or select a different card.";
				} else if ( ! avs_check( $authorization_output['ssl_avs_response'], $avs_options ) ) {
				$responsemessage =  "Payment was declined because the address could not be verified (AVS Response Code " . $authorization_output['ssl_avs_response'] .
									"). Try again, or select a different card.";
				} else {
				$responsemessage =  "Unidentified Error. Try again, or select a different card.";
				}
				
				$cancelNote = __( 'VirtualMerchant payment failed', 'woothemes' ) . '(Transaction ID: ' . $transactionid . '). ' . __( 'Payment was rejected due to an error', 'woothemes' ) . ': "' . $responsemessage . '". ';
				$order->add_order_note( $cancelNote );
				$order->update_status( 'Failed',__( 'Payment method was declined.', 'woothemes' ) );
				wc_add_notice(__( 'Payment Error', 'woothemes' ) . ': ' . $responsemessage . '', $notice_type = 'error');
			}
		}


		/**
		 * Validate plugin settings
		 */
		function validate_settings() {

			//Check for the Virtual Merchant merchant id, pin, and user id
			if ( ! $this->merchant_id || !$this->pin || ! $this->user_id ) {
				return false;
			}

			return true;
		}
	}

	/**
	 * Add the Virtual Merchant Gateway to WooCommerce
	 */
	function add_virtualmerchant_gateway( $methods ) {
		$methods[] = 'WC_Gateway_virtualmerchant';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_virtualmerchant_gateway' );
} 
