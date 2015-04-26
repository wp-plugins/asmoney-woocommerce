<?php

/*
Plugin Name: AsMoney Payment Gateway
Plugin URI: https://www.asmoney.com
Description: Accept AsMoney, Bitcoin, Litecoin, Dogecoin, Darkcoin <a target="_blank" href="https://www.asmoney.com">AsMoney</a>
Version: 1.0
Author: AsMoney
Author URI: https://www.asmoney.com
Copyright: 2013 https://www.asmoney.com , AsMoney
 */

add_action('plugins_loaded', 'woocommerce_asmoney_init', 0);

function woocommerce_asmoney_init()
{
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	if($_GET['msg']!='')
	{
		add_action('the_content', 'showMessageasmoney');
	}

	function showMessageasmoney($content)
	{
		return '<div class="box '.htmlentities($_GET['type']).'-box">'.base64_decode($_GET['msg']).'</div>'.$content;
	}

	class WC_Asmoney extends WC_Payment_Gateway
	{
		protected $msg = array();

		public function __construct()
		{
			$this->id = 'asmoney';
			$this->method_title = 'AsMoney';;
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/logo.png';
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->tid = $this->settings['tid'];
			$this->tun = $this->settings['tun'];
			$this->tpw = $this->settings['tpw'];
			$this->callBackUrl = $this->settings['callBackUrl'];
			$this->currency = $this->settings['currency'];
			$this->shaparak = $this->settings['shaparak'];
			$this->msg['message'] = "";
			$this->msg['class'] = "";

			add_action('woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_asmoney_response' ) );
			add_action('valid-asmoney-request', array($this, 'successful_request'));

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
			{
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}
			else
			{
				add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			}
			add_action('woocommerce_receipt_asmoney', array($this, 'receipt_page'));
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => 'Enable / Disable',
					'type' => 'checkbox',
					'label' => 'Enable / Disable',
					'default' => 'yes'),
				'title' => array(
					'title' => 'Title',
					'type'=> 'text',
					'description' => 'Title',
					'default' => 'AsMoney Payment Gateway'),
				'description' => array(
					'title' => 'Description',
					'type' => 'textarea',
					'description' => 'Description',
					'default' => 'Pay by AsMoney /  Bitcoin and Cryptocoins'),
				'tid' => array(
					'title' => 'Store Name',
					'type' => 'text',
					'description' => 'Store Name'),
				'tun' => array(
					'title' => 'UserName',
					'type' => 'text',
					'description' => 'UserName'),
				'tpw' => array(
					'title' => 'SCI Password',
					'type' => 'text',
					'description' => 'SCI Password'),
				'callBackUrl' => array(
					'title' => 'After Pay Page',
					'type' => 'select',
					'options' => $this->get_pages('Choose Page'),
					'description' => 'After Pay Page'),
				'currency' => array(
					'title' => 'Currency',
					'type' => 'select',
					'options' => array('USD'=>'USD', 'EUR'=>'EUR'),
					'description' => 'Currency')
				);
		}

		public function admin_options()
		{
			echo '<h3>AsMoney Payment Gateway</h3>';
			echo '<p>AsMoney Online Payment Gateway</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		function payment_fields()
		{
			if($this->description) echo wpautop(wptexturize($this->description));
		}

		function receipt_page($order)
		{
			echo '<p>Please click "Pay" button if you are not redirected within a few seconds.</p>';
			echo $this->generate_asmoney_form($order);
       }

		function process_payment($order_id)
		{
			$order = &new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true )); 
		}

		function check_asmoney_response()
		{
			global $woocommerce;
			if (! (isset($_POST['PAYMENT_ID'])) )
			{
				$this->msg['class'] = 'error';
				$this->msg['message'] = 'Hack Attempt. No Post Data';
			}
			else
			{
				$orderID  = (int)$_POST['PAYMENT_ID'];
				$order    = &new WC_Order($orderID);
				if($order->status !='completed')
				{
					$string = $_POST['PAYEE_ACCOUNT'].'|'.$_POST['PAYER_ACCOUNT'].'|'.$_POST['PAYMENT_AMOUNT'].'|'.$_POST['PAYMENT_UNITS'].'|'.$_POST['BATCH_NUM'].'|'.$_POST['PAYMENT_ID'].'|'.strtoupper(md5($this->tpw));
					$hash   = strtoupper(md5($string));
					if($hash==$_POST['MD5_HASH'])
					{
						
						if($_POST['PAYMENT_AMOUNT']==$order->order_total && $_POST['PAYEE_ACCOUNT']==$this->tun && $_POST['PAYMENT_UNITS']==$this->currency)
						{
							if (strtolower($_POST['PAYMENT_STATUS'])=='complete')
							{
								$this->msg['message'] = "Pay Completed. OrderNumber $orderID";
								$this->msg['class'] = 'success';
								$order->payment_complete();
							}
							else
							{
								$this->msg['message'] = "thank you for Pay your order. Please wait for confirmation. OrderNumber $orderID";
								$this->msg['class'] = 'success';
							}
							$order->add_order_note($this->msg['message']);
							$woocommerce->cart->empty_cart();
						}
						else
						{
							$this->msg['class'] = 'error';
							$this->msg['message'] = 'Error in verify Pay. Fake Data';
							$order->add_order_note($this->msg['message']);
						}
					}
					else
					{
							$this->msg['class'] = 'error';
							$this->msg['message'] = 'Error in verify Pay. Bad Hash';
							$order->add_order_note($this->msg['message']);
					}
				}
				else
				{
					$this->msg['class'] = 'error';
					$this->msg['message'] = 'There is no Pay with this data OR Order is completed already';
				}
			}
			$redirect_url = ($this->callBackUrl=="" || $this->callBackUrl==0)?get_site_url() . "/":get_permalink($this->callBackUrl);
			$redirect_url = add_query_arg( array('msg'=> base64_encode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );
			wp_redirect( $redirect_url );
			exit;
		}

		function showMessage($content)
		{
			return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
		}

		public function generate_asmoney_form($order_id)
		{
			global $woocommerce;
			$order                = new WC_Order($order_id);
			$redirect_url         = ($this->callBackUrl=="" || $this->callBackUrl==0)?get_site_url() . "/":get_permalink($this->callBackUrl);
			$redirect_url         = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			$PAYMENT_UNITS        = $this->currency;
			$STORE_NAME           = $this->tid;
			$USER_NAME            = $this->tun;
			$STATUS_URL           = $redirect_url;
			$SUCCESS_URL          = $redirect_url;
			$FAIL_URL             = $redirect_url;
			$PAYMENT_MEMO         = 'Memo';
			$PAYMENT_ID           = $order_id;
			$PAYMENT_AMOUNT       = $order->order_total;
			$PAYMENT_URL_METHOD   = 'LINK';
			$NOPAYMENT_URL_METHOD = 'LINK';
			echo ('<form name="frmPay" method="post" action="https://www.asmoney.com/sci.aspx">
						<input type="hidden" name="PAYMENT_UNITS"        value="'.$PAYMENT_UNITS.'">
						<input type="hidden" name="USER_NAME"            value="'.$USER_NAME.'">
						<input type="hidden" name="STORE_NAME"           value="'.$STORE_NAME.'">
						<input type="hidden" name="CALLBACK_URL"         value="'.$STATUS_URL.'">
						<input type="hidden" name="SUCCESS_URL"          value="'.$SUCCESS_URL.'">
						<input type="hidden" name="FAIL_URL"             value="'.$FAIL_URL.'">
						<input type="hidden" name="PAYMENT_MEMO"         value="'.$PAYMENT_MEMO.'">
						<input type="hidden" name="PAYMENT_ID"           value="'.$PAYMENT_ID.'">
						<input type="hidden" name="PAYMENT_AMOUNT"       value="'.$PAYMENT_AMOUNT.'">
						<input type="hidden" name="PAYMENT_URL_METHOD"   value="'.$PAYMENT_URL_METHOD.'">
						<input type="hidden" name="NOPAYMENT_URL_METHOD" value="'.$NOPAYMENT_URL_METHOD.'">
						<input type="submit" value="Pay" />
					</form>
					<script>document.frmPay.submit();</script>');
		}

		function get_pages($title = false, $indent = true)
		{
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page)
			{
				$prefix = '';
				if ($indent)
				{
					$has_parent = $page->post_parent;
					while($has_parent)
					{
						$prefix .=  ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}

	}

	function woocommerce_add_asmoney_gateway($methods)
	{
		$methods[] = 'WC_Asmoney';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_asmoney_gateway' );
}

?>