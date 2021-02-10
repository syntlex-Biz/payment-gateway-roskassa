<?php
/********************************************************************
Product  : RosKassa
Date  : 1 February 2021
Copyright : © 2021 Syntlex Biz.
Contact  : https://syntlex.biz
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*********************************************************************/
defined('_JEXEC') or die('Restricted Access');

if (!defined('_VALID_MOS') && !defined('_JEXEC'))

	die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');



if (!class_exists('vmPSPlugin')) {

	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

}

ini_set('display_errors',1);

error_reporting (E_ALL);



class plgVMPaymentRKassa extends vmPSPlugin

{

	public static $_this = false;

	public static $flag = false;



	function __construct(& $subject, $config)

	{ 

		parent::__construct($subject, $config);

		$this->_loggable = true;

		$this->tableFields = array_keys($this->getTableSQLFields());

		$this->_tablepkey = 'id'; 

		$this->_tableId = 'id'; 

		$varsToPush = $this->getVarsToPush();

		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);



		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);





		$this->setCryptedFields(array('key'));



	}



	protected function getVmPluginCreateTableSQL()

	{

		return $this->createTableSQL('Платежный стол Роскасса');

	}



	function getTableSQLFields()

	{

		$SQLfields = array(

			'id' 						  => 'int(1) unsigned NOT NULL AUTO_INCREMENT',

			'virtuemart_order_id'         => 'int(11) UNSIGNED',

			'order_number'                => 'char(64)',

			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',

			'payment_name' 				  => 'varchar(5000)',

			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',

			'payment_currency' 			  => 'smallint(1) ',

			'cost_per_transaction' 		  => 'decimal(10,2)',

			'cost_percent_total' 		  => 'decimal(10,2) ',

			'tax_id' 					  => 'smallint(1)'

		);



		return $SQLfields;

	}

    //Подготовка формы 

	function plgVmConfirmedOrder($cart, $order) 

	{

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))

		{

			return null;

		}

		if (!$this->selectedThisElement($method->payment_element))

		{

			return false;

		}

		

		$session = JFactory::getSession();

		$return_context = $session->getId();

		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		

		if (!class_exists('VirtueMartModelOrders'))

		{

			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		}

		if (!class_exists('VirtueMartModelCurrency'))

		{

			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

		}	    

		$new_status = $method->status_pending;		    	

		$this->getPaymentCurrency($method);

		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';

		$db = JFactory::getDBO();

		$db->setQuery($q);

		$currency_code_3 = $db->loadResult();



		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);

		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total,false), 2);

		$totalInPaymentCurrency = number_format( $totalInPaymentCurrency, 2, '.', '' );

		$cnt = count($order['items']);

		if($cnt==1)	$prod = $order['items'][0]->order_item_name;

		else $prod = JText::_ ('VMPAYMENT_RKASSA_ORDER_N') . $order['details']['BT']->order_number;

		$ReturnUrl = JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&method=rkassa';

		$FailUrl = JURI::root();

		$tdop = 'select/';     

		if ($method->sp==='none') $method->sp = '';  

		if ($method->sp==='card'){$tdop=''; $method->sp = ''; }

		$url = 'https://pay.roskassa.net/';

		//Формирование POST заголовка для отправки на roskassa

		$post_variables = Array(

			'shop_id'	=> $method->mrh_id,

			'order_id'		=> $order['details']['BT']->virtuemart_order_id,

			'amount'		=> $totalInPaymentCurrency,

			'currency'		=> $currency_code_3, 

			'test' => 1,

			'ReturnUrl'		=> $ReturnUrl,

			'FailUrl'		=> $FailUrl

		);

		$secret = $method->mrh_psk;

		$signArr = Array(

			'shop_id' => $post_variables['shop_id'],

			'order_id' => $post_variables['order_id'],

			'amount' => $post_variables['amount'],

			'currency' => $post_variables['currency'],

			'test' => $post_variables['test']

		);

		ksort($signArr);

		$str = http_build_query($signArr);

		$post_variables['sign'] = md5($str . $secret);



		$dbValues = array();

		$this->_virtuemart_paymentmethod_id      = $order['details']['BT']->virtuemart_paymentmethod_id;

		$dbValues['payment_name']                = $method->payment_name;

		$dbValues['order_number']                = $order['details']['BT']->order_number;

		$dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;

		$dbValues['cost_per_transaction']        = $method->cost_per_transaction;

		$dbValues['cost_percent_total']          = $method->cost_percent_total;

		$dbValues['payment_currency']            = $currency_code_3 ;

		$dbValues['payment_order_total']         = $totalInPaymentCurrency;

		$dbValues['tax_id']                      = $method->tax_id;

		$this->storePSPluginInternalData($dbValues);

		

		$send_pending = 0;

		if(isset($method->send_pending)) $send_pending=$method->send_pending;

		$html = '';

		$html .= '

		<div>'.JText::_('VMPAYMENT_RKASSA_REDIRECT').'</div>';

		$html .= '<form action="' . $url . '" method="post" name="vm_rkassa_form">';

		$i = 0;
		foreach ($order['items'] as $value) {
			$html .= '<input type="hidden" name="receipt[items]['.$i.'][name]" value="'.$value->product_name.'">
			<input type="hidden" name="receipt[items]['.$i.'][count]" value="'.$value->product_quantity.'">
			<input type="hidden" name="receipt[items]['.$i.'][price]" value="'.$value->product_subtotal_with_tax.'">';
			$i++;
		}

		foreach ($post_variables as $name => $value)

		{

			$html.= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';

		}

		

		$html.= '</form>';

		$html.= ' <script type="text/javascript">';

		$html.= ' document.vm_rkassa_form.submit();';

		$html.= ' </script>';

		

		if($send_pending) {

			$modelOrder = VmModel::getModel ('orders');

			$order['order_status'] = $new_status;

			$order['customer_notified'] = 1;

			$order['comments'] = '';

			$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

			JRequest::setVar ('html', $html);

		} else {

			$this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $method->payment_name, $new_status);

		}

		return null;

	} 



	//Отображение информации о заказе в админке    

	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)

	{

		if (!$this->selectedThisByMethodId($virtuemart_payment_id))

		{

			return null;

		}



		$db = JFactory::getDBO();

		$q = 'SELECT * FROM `' . $this->_tablename . '` '

		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;

		$db->setQuery($q);

		if (!($paymentTable = $db->loadObject()))

		{

			vmWarn(500, $q . " " . $db->getErrorMsg());

			return '';

		}

		$this->getPaymentCurrency($paymentTable);



		$html = '<table class="adminlist">' . "\n";

		$html .=$this->getHtmlHeaderBE();

		$html .= $this->getHtmlRowBE('VMPAYMENT_STT_RKASSA', $paymentTable->payment_name);

		$html .= '</table>' . "\n";

		return $html;

	}



	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {



		if (preg_match ('/%$/', $method->cost_percent_total)) {

			$cost_percent_total = substr ($method->cost_percent_total, 0, -1);

		} else {

			$cost_percent_total = $method->cost_percent_total;

		}

		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));

	}



	protected function checkConditions($cart, $method, $cart_prices)

	{

		return true;

	}



	//Перенаправление после успешной оплаты   

	function plgVmOnPaymentResponseReceived(  &$html)

	{

		if(JRequest::getVar('method','')!='roskassa') {

			return NULL;

		}

		if(self::$flag) return null;

		$jlang = JFactory::getLanguage ();

		$jlang->load ('plg_vmpayment_stt_rkassa', JPATH_ADMINISTRATOR);

		$payment_data = $_REQUEST;

		vmdebug('plgVmOnPaymentResponseReceived', $payment_data);

		/****************************************************************/

		if (!class_exists('VirtueMartModelOrders'))

		{

			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

		}

		$html = JText::_('VMPAYMENT_RKASSA_OK');

		if (!class_exists('VirtueMartCart'))

		{

			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

		}



		$virtuemart_order_id = isset($payment_data['order_id']) ? $payment_data['order_id'] : 0;

		if ($virtuemart_order_id)

		{



			$cart = VirtueMartCart::getCart();

			if (!class_exists('VirtueMartModelOrders'))

			{

				require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

			}

			$order = new VirtueMartModelOrders();

			$orderitems = $order->getOrder($virtuemart_order_id);

//			$cart->sentOrderConfirmedEmail($orderitems);



			$cart->emptyCart();

			self::$flag = true; // а это, чтобы несколько раз одно и то же не делать

			return true;

		}



		$cart = VirtueMartCart::getCart();

		$cart->emptyCart();



		return null;

	}



	function plgVmOnUserPaymentCancel()

	{

		return null;

	}

	

	function plgVmOnPaymentNotification()

	{

		if(JRequest::getVar('method','')!='rkassa') {

			return NULL;

		}

		

		//Изменение статуса заказа на Confirmed после оплаты счета

		if (!class_exists('VirtueMartModelOrders')) {

			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

		}

		

		ob_start();

		$rkassa_data = $_POST;

		if (!array_key_exists ('order_id', $rkassa_data) || !isset($rkassa_data['order_id'])) {

			ob_end_clean();

			return NULL; 

		} 


		$order_id = $rkassa_data['order_id'];

		if(!$order_id) {

			ob_end_clean();

			$this->logInfo('OrderId пустой', 'message');

			return;

		}

		$payment = $this->getDataByOrderId($order_id);

		$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);

		if (!isset($method->payment_currency) || !$method->payment_currency){

			$this->getPaymentCurrency($method);

		}

		if (!class_exists('CurrencyDisplay')){

			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');

		}

		$payment_currency = CurrencyDisplay::getInstance($method->payment_currency);

		$order_model  = new VirtueMartModelOrders();

		$order_info   = $order_model->getOrder($order_id);

		$total_amount = round($payment_currency->convertCurrencyTo($method->payment_currency, $order_info['details']['BT']->order_total, false), 2);	

		$secret_key = $method->mrh_psk;

		$merchant_id = $method->mrh_id;

		$callback_signature = $rkassa_data['sign'];

		unset($rkassa_data['sign']);

		ksort($rkassa_data);
		$str = http_build_query($rkassa_data);
		$signature = md5($str . $secret_key);

		$amount = $rkassa_data['amount'];	

		if($amount != $total_amount) {

			ob_end_clean();

			$this->logInfo('Ошибка суммы '.$amount .' != '.$total_amount, 'уведомление');

			die('Ошибка суммы '.$amount .' != '.$total_amount);

			return;

		}

		if ($signature == $callback_signature) {

			$new_status = $method->status_success;

			$modelOrder = VmModel::getModel('orders');

			$order = array();

			$order['order_status'] = $new_status;

			$order['customer_notified'] = 1;

			$order['comments'] = 'Roskassa '.$order_id;

			$modelOrder->updateStatusForOneOrder($order_id, $order, true);

			if ($method->use_fiscalization == '1') {

				$fiscal_request_body = array(

					'operation' => 'benefit',

					'transactionId' => $roskassa_data['Itemid'],

					'paymentSystemType' => 'card',

					'totalamount' => $total_amount,

					'email' => $order_info['details']['BT']->email,

					'goods' => array()

				);

				foreach ($order_info['items'] as $item) {

					array_push($fiscal_request_body['goods'], array(

						'description' => $item->product_name,

						'quantity' => $item->product_quantity,

						'amount' => round($payment_currency->convertCurrencyTo($method->payment_currency, $item->product_item_price, false), 2),

						'tax' => $method->fiscalization_tax

					));

				}

			}

			ob_end_clean();

			echo 'OK';

			jexit();

		}

		else { 

			ob_end_clean(); 

			$this->logInfo('Ошибка подписи '.$signature .' != '.$callback_signature, 'уведомление');

			die('Ошибка подписи');

		} 

		

		return true;

	}    

	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)

	{

		return $this->OnSelectCheck($cart);

	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)

	{

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))

		{

			return null;

		}

		if (!$this->selectedThisElement($method->payment_element))

		{

			return false;

		}

		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;

	}

	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())

	{

		return $this->onCheckAutomaticSelected($cart, $cart_prices);

	}

	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)

	{

		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);

	}

	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		$this->cart = $cart;

		return $this->displayListFE($cart, $selected, $htmlIn);

	}

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)

	{

		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);

	}

	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		return $this->onStoreInstallPluginTable($jplugin_id);

	}

	function logingActions($params)

	{

		jimport('joomla.error.log');

		$options = array(

			'format' => "{DATE}\t{TIME}\t{ORDER}\t{ACTION}"

		);

		$log = &JLog::getInstance('roskassa_events.log.php', $options);

		$log->addEntry(array('ORDER' => $params['ORDER'],'ACTION' => $params['ACTION']));

	}

	function plgVmonShowOrderPrintPayment($order_number, $method_id)

	{

		return $this->onShowOrderPrint($order_number, $method_id);

	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data)

	{

		return $this->declarePluginParams('payment', $name, $id, $data);

	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) {

		return $this->declarePluginParams('payment', $data);

	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)

	{

		return $this->setOnTablePluginParams($name, $id, $table);

	}

}