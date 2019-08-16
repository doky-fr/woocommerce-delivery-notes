<?php
/**
 * Theme class
 *
 * @package woocommerce-print-invoice-delivery-notes
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Theme class.
 */
if ( ! class_exists( 'WCDN_Theme' ) ) {

	/**
	 * Theme class
	 */
	class WCDN_Theme {

		/**
		 * Constructor
		 */
		public function __construct() {
			// Load the hooks.
			add_action( 'wp_loaded', array( $this, 'load_hooks' ) );
		}

		/**
		 * Load the hooks at the end when
		 * the theme and plugins are ready.
		 */
		public function load_hooks() {
			// hooks.
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'create_print_button_account_page' ), 10, 2 );
			add_action( 'woocommerce_view_order', array( $this, 'create_print_button_order_page' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'create_print_button_order_page' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'add_email_print_url' ), 100, 3 );
		}

		/**
		 * Add the scripts
		 */
		public function add_scripts() {
			if ( is_account_page() || is_order_received_page() || $this->is_woocommerce_tracking_page() ) {
				wp_enqueue_script( 'woocommerce-delivery-notes-print-link', WooCommerce_Delivery_Notes::$plugin_url . 'js/jquery.print-link.js', array( 'jquery' ), WooCommerce_Delivery_Notes::$plugin_version, false );
				wp_enqueue_script( 'woocommerce-delivery-notes-theme', WooCommerce_Delivery_Notes::$plugin_url . 'js/theme.js', array( 'jquery', 'woocommerce-delivery-notes-print-link' ), WooCommerce_Delivery_Notes::$plugin_version, false );
			}
		}

		/**
		 * Create a print button for the 'My Account' page.
		 *
		 * @param array  $actions My Account page actions.
		 * @param object $order Order object.
		 */
		public function create_print_button_account_page( $actions, $order ) {
			if ( 'yes' === get_option( 'wcdn_print_button_on_my_account_page' ) ) {
				// Add the print button.
				$wdn_order_id     = ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) ? $order->get_id() : $order->id;
				$actions['print'] = array(
					'url'  => wcdn_get_print_link( $wdn_order_id, $this->get_template_type( $order ) ),
					'name' => apply_filters( 'wcdn_print_button_name_on_my_account_page', __( 'Print', 'woocommerce-delivery-notes' ), $order )
				);
			}
			return $actions;
		}

		/**
		 * Create a print button for the 'View Order' page.
		 *
		 * @param int $order_id Order ID.
		 */
		public function create_print_button_order_page( $order_id ) {
			$order                = new WC_Order( $order_id );
			$wdn_order_billing_id = ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) ? $order->get_billing_email() : $order->billing_email;
			// Output the button only when the option is enabled.
			if ( 'yes' === get_option( 'wcdn_print_button_on_view_order_page' ) ) {
				// Default button for all pages and logged in users.
				$print_url = wcdn_get_print_link( $order_id, $this->get_template_type( $order ) );

				// Pass the email to the url for the tracking and thank you page. This allows to view the print page without logging in.
				if ( $this->is_woocommerce_tracking_page() ) {
					// changed.
					$wdn_order_email = isset( $_REQUEST['order_email'] ) ? sanitize_email( wp_unslash( $_REQUEST['order_email'] ) ) : '';
					$print_url       = wcdn_get_print_link( $order_id, $this->get_template_type( $order ), $wdn_order_email );
				}

				// Thank you page.
				if ( is_order_received_page() && ! is_user_logged_in() ) {
					// Don't output the butten when there is no email.
					if ( ! $wdn_order_billing_id ) {
						return;
					}
					$print_url = wcdn_get_print_link( $order_id, $this->get_template_type( $order ), $wdn_order_billing_id );
				}

				?>
				<p class="order-print">
					<a href="<?php echo esc_url( $print_url ); ?>" class="button print"><?php echo apply_filters( 'wcdn_print_button_name_order_page', __( 'Print', 'woocommerce-delivery-notes' ), $order ); ?></a>
				</p>
				<?php
			}
		}

		/**
		 * Add a print url to the emails that are sent to the customer
		 *
		 * @param object  $order Order Object.
		 * @param boolean $sent_to_admin Sent to admin true or false.
		 * @param boolean $plain_text Whether only to send plain text email or not.
		 */
		public function add_email_print_url( $order, $sent_to_admin = true, $plain_text = false ) {
			if ( 'yes' === get_option( 'wcdn_email_print_link' ) ) {
				$wdn_order_billing_id = ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) ? $order->get_billing_email() : $order->billing_email;
				if ( $wdn_order_billing_id && ! $sent_to_admin ) {
					$wdn_order_id = ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) ? $order->get_id() : $order->id;

					$url = wcdn_get_print_link( $wdn_order_id, $this->get_template_type( $order ), $wdn_order_billing_id, true );

					if ( $plain_text ) :
						echo esc_html_e( 'Print your order', 'woocommerce-delivery-notes' ) . "\n\n";

						echo esc_url( $url ) . "\n";

						echo "\n****************************************************\n\n";
					else :
						?>
						<p><strong><?php esc_attr_e( 'Print:', 'woocommerce-delivery-notes' ); ?></strong> <a href="<?php echo esc_url_raw( $url ); ?>"><?php esc_attr_e( 'Open print view in browser', 'woocommerce-delivery-notes' ); ?></a></p>
					<?php endif;
				}
			}
		}

		/**
		 * Get the print button template type depnding on order status
		 *
		 * @param object $order Order object.
		 */
		public function get_template_type( $order ) {

			$wdn_order_status = ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) ? $order->get_status() : $order->status;

			if ( 'completed' === $wdn_order_status ) {
				$type = apply_filters( 'wcdn_theme_print_button_template_type_complete_status', 'invoice' );
			} else {
				$type = apply_filters( 'wcdn_theme_print_button_template_type', 'order' );
			}

			$type = apply_filters( 'wcdn_theme_print_button_template_type_arbitrary', $type, $order );

			return $type;
		}

		/**
		 * Is WooCommerce 'Order Tracking' page
		 */
		public function is_woocommerce_tracking_page() {
			return ( is_page( wc_get_page_id( 'order_tracking' ) ) && isset( $_REQUEST['order_email'] ) ) ? true : false;
		}

	}

}

?>
