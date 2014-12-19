<?php
/*
Plugin Name: WooCommerce Virtual Merchant Gateway
Description: Virtual Merchant/Elavon payment gateway plugin for WooCommerce. A Virtual Merchant account through Elavon, and a server with SSL support and an SSL certificate is required (for security reasons) for this gateway to function. This gateway is configured for US purchases in US Dollars only.
Version: 1.0.1
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
		public $transactionid;

		/**
		 * Constructor
		 */
		function __construct() { 

			$this->id 				= 'virtualmerchant';
			$this->method_title		= __('VirtualMerchant', 'woothemes');
			$this->has_fields 		= true;

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
			$this->visa_enabled				= $this->settings['visa_enabled'];
			$this->discover_enabled			= $this->settings['discover_enabled'];
			$this->mastercard_enabled		= $this->settings['mastercard_enabled'];
			$this->americanexpress_enabled	= $this->settings['americanexpress_enabled'];
		
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
				'visa_enabled' => array(
								'title' => __( 'Enable Visa', 'woothemes' ), 
								'label' => __( 'Enable Visa Payments', 'woothemes' ), 
								'type' => 'checkbox',
								'default' => 'yes'
							),			
				'discover_enabled' => array(
								'title' => __( 'Enable Discover', 'woothemes' ), 
								'label' => __( 'Enable Discover Payments', 'woothemes' ), 
								'type' => 'checkbox',
								'default' => 'yes'
							),			
				'mastercard_enabled' => array(
								'title' => __( 'Enable MasterCard', 'woothemes' ), 
								'label' => __( 'Enable MasterCard Payments', 'woothemes' ), 
								'type' => 'checkbox',
								'default' => 'yes'
							),			
				'americanexpress_enabled' => array(
								'title' => __( 'Enable American Express', 'woothemes' ), 
								'label' => __( 'Enable American Express Payments', 'woothemes' ), 
								'type' => 'checkbox',
								'default' => 'yes'
							),
				'cvv_enabled' => array(
								'title' => __( 'Enable CSC Authentication', 'woothemes' ), 
								'label' => __( 'Enable CSC Authentication', 'woothemes' ), 
								'type' => 'checkbox', 
								'description' => __( 'This option must also be enabled on your VirtualMerchant account as a Post-Processing Rule under Business Rules. Contact VirtualMerchant if you have any questions.', 'woothemes' ), 
								'default' => 'no'
							),							
				);
		}

		/**
		 * Admin panel options
		 */
		function admin_options() {
		
			?>
			<h3><?php _e( 'VirtualMerchant', 'woothemes' ); ?></h3>
			<p><?php _e( 'VirtualMerchant works by adding credit card fields on the checkout and then sending the details to VirtualMerchant for verification.', 'woothemes' ); ?></p>
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

			//Build the array of available cards
			$available_cards = array();
			
			if ( $this->visa_enabled=='yes' ){
				$available_cards[] = 'Visa';
			}
			if ( $this->mastercard_enabled=='yes' ){
				$available_cards[] = 'MasterCard';
			}
			if ( $this->discover_enabled=='yes' ){
				$available_cards[] = 'Discover';
			}
			if ( $this->americanexpress_enabled=='yes' ){
				$available_cards[] = 'American Express';
			}

			?>
			<?php if ( $this->testmode == 'yes' ) : ?>
				<p><?php _e( 'TEST MODE/SANDBOX ENABLED', 'woothemes' ); ?></p>
			<?php endif; ?>

			<?php if ( $this->description ) : ?>
				<p><?php echo $this->description; ?></p>
			<?php endif; ?>

			<fieldset>
				<p class="form-row form-row-first">
					<label for="virtualmerchant_card_number"><?php echo __( 'Credit Card number', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" class="input-text" name="virtualmerchant_card_number" maxlength="19"/>
				</p>
				<p class="form-row form-row-last">
					<label for="virtualmerchant_card_type"><?php echo __( 'Card type', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<select id="virtualmerchant_card_type" style="width: 80%" name="virtualmerchant_card_type" onChange="doIt(this.options[this.selectedIndex].value)">
						<?php foreach ( $available_cards as $card ) : ?>
							<option value="<?php echo $card ?>"><?php echo $card; ?></options>
						<?php endforeach; ?>
					</select>
				</p>
				<div class="clear"></div>
				<p class="form-row form-row-first">
					<label for="cc-expire-month"><?php echo __( 'Expiration date', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<select name="virtualmerchant_card_expiration_month" style="width: 45%" id="cc-expire-month">
						<option value=""><?php _e( 'Month', 'woocommerce' ) ?></option>
						<?php
							$months = array();
							for ( $i = 1; $i <= 12; $i++ ) {
								$timestamp = mktime( 0, 0, 0, $i, 1 );
								$months[ date( 'm', $timestamp ) ] = date( 'F', $timestamp );
							}
							foreach ( $months as $num => $name ) {
								printf( '<option value="%s">%s</option>', $num, $name );
							}
						?>
					</select>
					<select name="virtualmerchant_card_expiration_year" id="cc-expire-year">
						<option value=""><?php _e( 'Year', 'woocommerce' ) ?></option>
						<?php
							$years = array();
							for ( $i = date( 'Y' ); $i <= date( 'Y' ) + 15; $i++ ) {
								printf( '<option value="%u">%u</option>', $i, $i );
							}
						?>
					</select>
				</p>
				<p class="form-row form-row-last">
					<label for="virtualmerchant_card_csc"><?php _e( 'Card security code', 'woocommerce' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" class="input-text" id="virtualmerchant_card_csc" name="virtualmerchant_card_csc" maxlength="4" style="width:45px" />
					<br />
					<span id="virtualmerchant_card_csc_description">3 digits usually found on the back of the card.</span>
				</p>
				<div class="clear"></div>
			</fieldset>

			<script type="text/javascript">

				/**
				 * Display credit card security code location message based on credit card type
				 */
				function doIt(objval){
					var cscmessage = objval;
					if (cscmessage == 'American Express'){
						document.getElementById('virtualmerchant_card_csc_description').firstChild.nodeValue='4 digits usually found on the front of the card.';
					} else {
						document.getElementById('virtualmerchant_card_csc_description').firstChild.nodeValue='3 digits usually found on the back of the card.';
					}
				}
			</script>
			<?php
		}

		/**
		 * Process the payment, receive and validate the results, and redirect to the thank you page upon a successful transaction
		 */
		function process_payment( $order_id ) {

			global $woocommerce;
			$order = new WC_Order( $order_id );
			$billing_country	= isset( $_POST['billing-country'] ) ? $_POST['billing-country'] : '';
			$card_type			= isset( $_POST['virtualmerchant_card_type'] ) ? $_POST['virtualmerchant_card_type'] : '';
			$card_number		= isset( $_POST['virtualmerchant_card_number'] ) ? $_POST['virtualmerchant_card_number'] : '';
			$card_csc			= isset( $_POST['virtualmerchant_card_csc'] ) ? $_POST['virtualmerchant_card_csc'] : '';
			$card_exp_month		= isset( $_POST['virtualmerchant_card_expiration_month'] ) ? $_POST['virtualmerchant_card_expiration_month'] : '';
			$card_exp_year		= isset( $_POST['virtualmerchant_card_expiration_year'] ) ? $_POST['virtualmerchant_card_expiration_year'] : '';

			//Format and combine credit card month and year
			$card_expiration = $card_exp_month . substr( $card_exp_year, -2 );

			// Format credit card number
			$card_number = str_replace( array( ' ', '-' ), '', $card_number );

			// Validate plugin settings
			if ( ! $this->validate_settings() ) {
				$cancelNote = __('Order was cancelled due to invalid settings (check your credentials).', 'woothemes');
				$order->add_order_note( $cancelNote );
				$woocommerce->add_error(__('Payment was rejected due to configuration error.', 'woothemes'));
				return false;
			}

			// Send request to virtualmerchant
			$url = $this->liveurl;

			//Determine if testmode is enabled and set the URL accordingly
			if ( $this->testmode == 'yes' ) {
				$url = $this->testurl;
			}

//$order->order_currency
			$cvv_enabled = $this->cvv_enabled;
			$post_data = '';
			$fields = array(
					'ssl_merchant_id'			=> $this->merchant_id,
					'ssl_user_id'				=> $this->user_id,
					'ssl_pin'					=> $this->pin,
					'ssl_transaction_type'		=> "ccsale",
					'ssl_show_form'				=> "false",
					'ssl_card_number'			=> $card_number,
					'ssl_exp_date'				=> $card_expiration,
					'ssl_amount'				=> $order->order_total,
					'ssl_cvv2cvc2_indicator'	=> '1',
					'ssl_cvv2cvc2'				=> $card_csc,
					'ssl_transaction_currency'  => $order->get_order_currency(),
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
//

			//build and format the post string
			foreach ( $fields as $key=>$value ) { 
				$post_data .=$key. '=' .$value. '&'; 
			}

			rtrim( $post_data, "&" );
			
			// For testing
			$woocommerce->add_error(__( 'Payment Error', 'woothemes' ) . ': ' . $post_data . '');
			/*Commented out for testing
			try{
				//execute wp_remote_post
				$result = wp_remote_post( $url, array (
						'method'	=> 'POST',
						'timeout'	=> 30,
						'sslverify'	=> false,
						'body'		=> $post_data
					)
				);

				//Check for wp_remote_post errors
				if ( is_wp_error( $result ) ) throw new Exception( 'There was an error' );
				if ( empty( $result['body'] ) ) throw new Exception( 'Empty VirtualMerchant Output.' );

				//parse the resulting array
				parse_str( str_replace( array( "\n", "\r" ), '&', $result['body'] ), $output );
			}

			//Catch any errors caused by wp_remote_post
			catch( Exception $e ) {
				$woocommerce->add_error(__( 'There was a connection error', 'woothemes' ) . ': "' . $e->getMessage() . '"' );
				return;
			}

			//Assign transactionid if it is set in the wp_remote_post results
			if ( isset( $output['ssl_txn_id'] ) ) {
				$transactionid =  $output['ssl_txn_id'];
			} else {
				$transactionid = '';
			}
			*/
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
			/*Commented out for testing
			//determine if the transaction was successful
			if ( isset( $output['ssl_result'] ) && ( $output['ssl_result'] == 0 ) && cvv_check( $output['ssl_cvv2_response'], $cvv_enabled ) ) {

				//add transaction id to payment complete message, update woocommerce order and cart
				$order->add_order_note( __( 'VirtualMerchant payment completed', 'woothemes' ) . '(Transaction ID: ' . $transactionid . ')' );
				$order->payment_complete();
				$woocommerce->cart->empty_cart();
			
				//redirect to the woocommerce thank you page
				return array(
					'result' => 'success',
					'redirect' => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( get_option( 'woocommerce_thanks_page_id' ) ) ) )
				);
			} else {

				if( isset( $output['ssl_result'] ) && cvv_check( $output['ssl_cvv2_response'], $cvv_enabled ) ) {
					$responsemessage = 'Payment was declined for the following reason: '. $output['ssl_result_message'] . '. Try again, or select a different card.';
				} else if( isset( $output['errorCode'] ) ) {
					$responsemessage =  'Payment was declined for the following reason: ' . $output['errorName'] . ': ' . $output['errorMessage'] . 'Try again, or select a different card.';
				} else if ( ! cvv_check( $output['ssl_cvv2_response'], $cvv_enabled ) ) {
					$responsemessage = "Payment was declined because the Card Security Code is not correct. Try again, or select a different card.";
				} else {
				$responsemessage =  "Unidentified Error. Try again, or select a different card.";
				}

				$cancelNote = __( 'VirtualMerchant payment failed', 'woothemes' ) . '(Transaction ID: ' . $transactionid . '). ' . __( 'Payment was rejected due to an error', 'woothemes' ) . ': "' . $responsemessage . '". ';
				$order->add_order_note( $cancelNote );
				$order->update_status( 'Failed',__( 'Payment method was declined.', 'woothemes' ) );
				$woocommerce->add_error(__( 'Payment Error', 'woothemes' ) . ': ' . $responsemessage . '');
			}
			*/
		}

		/**
		 * Validate the payment form prior to submitting via wp_remote_posts
		 */
		function validate_fields() {

			global $woocommerce;
			$billing_country 	= isset( $_POST['billing_country'] ) ? $_POST['billing_country'] : '';
			$card_type			= isset( $_POST['virtualmerchant_card_type'] ) ? $_POST['virtualmerchant_card_type'] : '';
			$card_number		= isset( $_POST['virtualmerchant_card_number'] ) ? $_POST['virtualmerchant_card_number'] : '';
			$card_csc			= isset( $_POST['virtualmerchant_card_csc'] ) ? $_POST['virtualmerchant_card_csc'] : '';
			$card_exp_month		= isset( $_POST['virtualmerchant_card_expiration_month'] ) ? $_POST['virtualmerchant_card_expiration_month'] : '';
			$card_exp_year		= isset( $_POST['virtualmerchant_card_expiration_year'] ) ? $_POST['virtualmerchant_card_expiration_year'] : '';

			// Determine if provided card security code contains numbers and is the proper length
			if ( ! ctype_digit( $card_csc ) ) {
				$woocommerce->add_error(__( 'Card security code is invalid (only digits are allowed)', 'woothemes' ) );
				return false;
			}

			if ( ( strlen( $card_csc ) != 3 && in_array( $card_type, array('Visa', 'MasterCard', 'Discover') ) ) || ( strlen( $card_csc ) != 4 && $card_type == 'American Express' ) ) {
				$woocommerce->add_error(__( 'Card security code is invalid (wrong length)', 'woothemes' ) );
				return false;
			}

			// Check card expiration date
			if ( ! ctype_digit( $card_exp_month ) || 
				! ctype_digit( $card_exp_year ) ||
				$card_exp_month > 12 ||
				$card_exp_month < 1 ||
				$card_exp_year < date('Y') ||
				$card_exp_year > date('Y') + 20
			) {
				$woocommerce->add_error(__( 'Card expiration date is invalid', 'woothemes' ) );
				return false;
			}

			// Determine if a number was provided for the credit card number
			$card_number = str_replace( array( ' ', '-' ), '', $card_number );
			if( empty( $card_number ) || ! ctype_digit( $card_number ) ) {
				$woocommerce->add_error(__( 'Card number is invalid', 'woothemes' ) );
				return false;
			}

			return true;
		}

		/**
		 * Validate plugin settings
		 */
		function validate_settings() {
			//Client is using plugin for something other than US currency, the US currency check has been
			//removed to accomodate this requirement
			/*
			$currency = get_option( 'woocommerce_currency' );

			//Check if USD is currency selected
			if ( ! in_array( $currency, array('USD') ) ) {
				return false;
			}*/

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
