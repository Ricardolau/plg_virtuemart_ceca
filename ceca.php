<?php
/*
 *      TPVV CECA for VirtueMart 2
 *      @package TPVV CECA for VirtueMart 2
 *      @subpackage Content
 *      @author José António Cidre Bardelás
 *      @copyright Copyright (C) 2011-2014 José António Cidre Bardelás and Joomla Empresa. All rights reserved
 *      @license GNU/GPL v3 or later
 *      
 *      Contact us at info@joomlaempresa.com (http://www.joomlaempresa.es)
 *      
 *      This file is part of TPVV CECA for VirtueMart 2.
 *      
 *          TPVV CECA for VirtueMart 2 is free software: you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation, either version 3 of the License, or
 *          (at your option) any later version.
 *      
 *          TPVV CECA for VirtueMart 2 is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *      
 *          You should have received a copy of the GNU General Public License
 *          along with TPVV CECA for VirtueMart 2.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('_JEXEC') or die('Restricted access');


if(!class_exists('vmPSPlugin')) 
	require(JPATH_VM_PLUGINS.DS.'vmpsplugin.php');

class plgVmPaymentCECA extends vmPSPlugin {

	// instance of class

	public static $_this = false;

	function __construct(&$subject, $config) {
		parent::__construct($subject, $config);
		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		
		$varsToPush = $this->getVarsToPush();
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment CECA Table');
	}

	function getTableSQLFields() {
		$SQLfields = array(
			'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(11) UNSIGNED',
			'order_number' => 'char(32)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'char(3) ',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)',
			'ceca_response_date' => 'char(16)',
			'ceca_response_hour' => 'char(8)',
			'ceca_response_order' => 'char(64)', // Num_operacion
			'ceca_response_amount' => 'char(16)', // Importe
			'ceca_response_authorisationcode' => 'char(16)', // Num_aut
			'ceca_response_card_country' => 'char(4)', // Pais
			'ceca_response_language' => 'char(3)', // Idioma
			'ceca_response_currency' => 'char(4)', // TipoMoneda
			'ceca_response_merchantid' => 'char(16)', // MerchantID
			'ceca_response_acquirerbin' => 'char(16)', // AcquirerBIN
			'ceca_response_signature' => 'varchar(280)', // Firma
			'ceca_response_terminal' => 'char(16)', // TerminalID
			'ceca_response_exponent' => 'char(1)', // Exponente
			'ceca_response_reference' => 'char(32)', // Referencia
			'ceca_response_description' => 'varchar(250)' // Descripcion
		);
		return $SQLfields;
	}

	function plgVmConfirmedOrder($cart, $order) {
		if(!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if(!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$chave = $this->obterChave($method, $order['details']['BT']->virtuemart_paymentmethod_id);
		$lang = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);

		$this->_debug = $method->debug;
		$this->logInfo('plgVmConfirmedOrder order number: '.$order['details']['BT']->order_number, 'message');
		
		$html = "";

		if(!class_exists('VirtueMartModelOrders')) 
			require(VMPATH_ADMIN.DS.'models'.DS.'orders.php');
		if(!class_exists('VirtueMartModelCurrency')) 
			require(VMPATH_ADMIN.DS.'models'.DS.'currency.php');
		
		// Double order checking
		if(method_exists($this, 'setInConfirmOrder'))
			$this->setInConfirmOrder($cart);

		$new_status = '';
		$this->getPaymentCurrency($method, true);
		
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'.$method->payment_currency.'" ';
		$db = &JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$montante = $totalInPaymentCurrency * 100;
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
		$endereco = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);
		$idEncomenda = $order['details']['BT']->order_number;
		$urlRespostaBase = 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on='.$order['details']['BT']->order_number.'&pm='.$order['details']['BT']->virtuemart_paymentmethod_id;
		$urlOK = JRoute::_(JURI::root().$urlRespostaBase.'&action=OK');
		$urlKO = JRoute::_(JURI::root().$urlRespostaBase.'&action=KO');

		// SHA1
		$mensagem = $chave.$method->ceca_codigo_loja.$method->ceca_codigo_caixa.$method->ceca_terminal.$idEncomenda.$montante.$method->ceca_divisa.$method->ceca_exponhente.$method->ceca_cifrado.$urlOK.$urlKO;
		$assinatura = sha1($mensagem);
		
		$post_variables = Array(
			'MerchantID' => $method->ceca_codigo_loja,
			'AcquirerBIN' => $method->ceca_codigo_caixa,
			'TerminalID' => $method->ceca_terminal,
			'Num_operacion' => $idEncomenda,
			'Importe' => $montante,
			'TipoMoneda' => $method->ceca_divisa,
			'Exponente' => $method->ceca_exponhente,
			'URL_OK' => $urlOK,
			'URL_NOK' => $urlKO,
			'Firma' => $assinatura,
			'Cifrado' => $method->ceca_cifrado,
			'Idioma' => $method->ceca_idioma,
			'Pago_soportado' => $method->ceca_pagamento_suportado,
			'Descripcion' => $method->ceca_descricom_produtos
		);
		
		$this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData($dbValues);
		
		if(!$method->ceca_tpv_url || !$method->ceca_codigo_loja || !$method->ceca_codigo_caixa || !$method->ceca_terminal || !$method->ceca_divisa || !$method->ceca_exponhente || !$method->ceca_cifrado || !$method->ceca_pagamento_suportado || !$chave) {
			$html = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
			$html = '<img src="'.JURI::root().'plugins/vmpayment/ceca/'.(JVM_VERSION >= 2 ? 'ceca/' : '').'erro.png"><h1>'.JText::_('VMPAYMENT_CECA_NOM_CONFIGURADO_TIT').'</h1>';
			$html .= '<p>'.JText::_('VMPAYMENT_CECA_NOM_CONFIGURADO').'</p>';
			$html .= '</body></html>';
			return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $new_status);
		}
		
		$url = $method->ceca_tpv_url;
		$html = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
		if(!class_exists('VirtueMartModelCurrency')) 
			require(VMPATH_ADMIN.DS.'models'.DS.'currency.php');
		$currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
		$html .= '<p>'.JText::_('VMPAYMENT_CECA_TEXTO_TPV').'</p>';
		$html .= '<p>'.JText::_('VMPAYMENT_CECA_ORDER_NUMBER').' '.$order['details']['BT']->order_number.'<br />';
		$html .= JText::_('VMPAYMENT_CECA_AMOUNT').' '.$currency->priceDisplay($order['details']['BT']->order_total).'</p>';
		$html .= '<form action="'.$url.'" method="post" name="vm_ceca_form" id="vm_ceca_form">';
		$html .= '<input type="image" src="'.JURI::root().'plugins/vmpayment/ceca/'.(JVM_VERSION >= 2 ? 'ceca/' : '').'logo_pagamento.png" name="submit" title="'.($method->ceca_encaminhar ? JText::_('VMPAYMENT_CECA_MENSAGEM_ENCAMINHADO') : JText::_('VMPAYMENT_CECA_MENSAGEM_CLIQUE')).'" alt="'.($method->ceca_encaminhar ? JText::_('VMPAYMENT_CECA_MENSAGEM_ENCAMINHADO') : JText::_('VMPAYMENT_CECA_MENSAGEM_CLIQUE')).'" />';
		foreach($post_variables as $name => $value) {
			$html .= '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars($value).'" />';
		}
		$html .= '</form></div>';
		if($method->ceca_encaminhar) {
			$html .= ' <script type="text/javascript">';
			$html .= ' document.vm_ceca_form.submit();';
			$html .= ' </script>';
		}
		$html .= '</body></html>';

		// 2 = don't delete the cart, don't send email and don't redirect
		return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $dbValues['payment_name'], $new_status);
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
		if(!$this->selectedThisByMethodId($virtuemart_payment_id))
			return null; // Another method was selected, do nothing
		
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `'.$this->_tablename.'` '.'WHERE `virtuemart_order_id` = '.$virtuemart_order_id;
		$db->setQuery($q);
		if(!($paymentTable = $db->loadObject())) {
			vmWarn(500, $q." ".$db->getErrorMsg());
			return false;
		}

		//$this->getPaymentCurrency($paymentTable);
		$html = '<table class="adminlist">'."\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('VMPAYMENT_CECA_NOME_PAGAMENTO', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('VMPAYMENT_CECA_TOTAL_DIVISA', $paymentTable->payment_order_total.' '.$paymentTable->payment_currency);
		$code = "ceca_response_";
		foreach ($paymentTable as $key => $value) {
			if (substr($key, 0, strlen($code)) == $code) {
				$html .= $this->getHtmlRowBE($key, $value);
			}
		}
		$html .= '</table>'."\n";
		return $html;
	}

	function getCosts(VirtueMartCart$cart, $method, $cart_prices) {
		if(preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, - 1);
		}
		else {
			$cost_percent_total = $method->cost_percent_total;
		}
        // Cambio ya que daba una advertencia. A non-numeric value encountered  en 230
        $resultado = floatval($method->cost_per_transaction) + ($cart_prices['salesPrice'] * floatval($cost_percent_total) * 0.01);
		return $resultado;
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices) {

		// 		$params = new JParameter($payment->payment_params);

		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		
		//$amount = $cart_prices['salesPrice'];
		$amount = $this->getCartAmount($cart_prices);
		


		if ( $method->max_amount== null){
			//Compruebo si el maximo no tiene valor, le pongo el valor 0
			$method->max_amount = 0;
		}
		if ( $method->min_amount== null){
			//Compruebo si el mínimo no tiene valor, le pongo el valor 0
			$method->min_amount = 0;	
		}

		$amount_cond = (($amount >= $method->min_amount && $amount <= $method->max_amount) || ($method->min_amount <= $amount && ($method->max_amount == 0)));

		

		if(!$amount_cond) {			
		    error_log('No se muestra forma de pago porque no se cumple una de las condiciones');
			return false;
		}
		$countries = array();
		if(!empty($method->countries)) {
			if(!is_array($method->countries)) {
				$countries[0] = $method->countries;
			}
			else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address

		if(!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}
		
		if(!isset($address['virtuemart_country_id'])) 
			$address['virtuemart_country_id'] = 0;
		if(count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			return true;
		}
		
		return false;
	}

	/*
	 * We must reimplement this triggers for joomla 1.7
	 */
	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart$cart) {
		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart$cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/*
	 * plgVmonSelectedCalculatePricePayment
	 * Calculate the price (value, tax_id) of the selected method
	 * It is called by the calculator
	 * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	 * @author Valerie Isaksen
	 * @cart: VirtueMartCart the current cart
	 * @cart_prices: array the new cart prices
	 * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	 *
	 *
	 */

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart$cart, array&$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
		
		if(!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if(!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);
		
		$paymentCurrencyId = $method->payment_currency;
	}

	// Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	// The plugin must check first if it is the correct type
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	  public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	  return null;
	  }
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added
	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnUpdateOrderPayment(  $_formData) {
	  return null;
	  }

	  /**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnUpdateOrderLine(  $_formData) {
	  return null;
	  }

	  /**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	  return null;
	  }

	  /**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 * @author Oscar van Eijk
	 *
	  public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	  return null;
	  }

	  /**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int $virtuemart_order_id : payment  order id
	 * @param char $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	  public function plgVmOnPaymentNotification() {
		$order_number = vRequest::getVar('Num_operacion', 0);
		if(!isset($order_number) || empty($order_number)) {
			//$this->logInfo('Technical Note: Order number not set or empty: exit ', 'ERROR');
			echo 'Technical Note: Order number not set or empty';
			return false;
		}
		$idEncomenda = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		if (!$idEncomenda) {
			//$this->logInfo('Technical Note: Order id not found: exit ', 'ERROR');
			echo 'Technical Note: Order id not found';
			return false;
		}
		$payment = $this->getDataByOrderId($idEncomenda);
		if (!$payment) {
			//$this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
			echo 'getDataByOrderId payment not found';
			return false;
		}
		$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
		$this->_debug = $method->debug;
		if (!$this->selectedThisElement($method->payment_element)) {
			return null;
		}
		$chave = $this->obterChave($method, $payment->virtuemart_paymentmethod_id);
		if (!$chave || empty($chave)) {
			$this->logInfo('Technical Note: The required transaction key is empty! The payment method settings must be reviewed: exit ', 'ERROR');
			echo 'Technical Note: The required transaction key is empty! The payment method settings must be reviewed';
			return false;
		}
		$ceca_post_data = vRequest::getPost();
		if(!$ceca_post_data || empty($ceca_post_data)) {
			$this->logInfo('Technical Note: No post data received: exit ', 'ERROR');
			echo 'Technical Note: No post data received';
			return false;
		}
		if(strcmp($ceca_post_data['Num_operacion'], $order_number)) {
			$this->logInfo('Technical Note: Order number don\'t match with that used in the POS order ID generation ('.$ceca_post_data['Num_operacion'].' != '.$order_number.'): exit ', 'ERROR');
			echo 'Technical Note: Order number don\'t match with that used in the POS order ID generation';
			return false;
		}
		//if($ceca_post_data['Importe'] != ($payment->payment_order_total*100)) {
		if((string)$ceca_post_data['Importe'] != (string)(number_format($payment->payment_order_total*100, 0, '.', ''))) {
			$this->logInfo('Technical Note: Amount in DB and received don\'t match: exit ('.(string)$ceca_post_data['Importe'] != (string)(number_format($payment->payment_order_total*100, 0, '.', '')).')', 'ERROR');
			echo 'Technical Note: Amount in DB and received don\'t match ('.(string)$ceca_post_data['Importe'] != (string)(number_format($payment->payment_order_total*100, 0, '.', '')).')';
			return false;
		}
		$mensagem = $chave.$ceca_post_data['MerchantID'].$ceca_post_data['AcquirerBIN'].$ceca_post_data['TerminalID'].$ceca_post_data['Num_operacion'].$ceca_post_data['Importe'].$ceca_post_data['TipoMoneda'].$ceca_post_data['Exponente'].$ceca_post_data['Referencia'];
		$assinatura_calc = sha1($mensagem);
		//dump($ceca_post_data['Firma'].' - '.$assinatura_calc);
		if($ceca_post_data['Firma'] != $assinatura_calc) {
			$this->logInfo('Technical Note: The verification signatures don\'t match ('.$ceca_post_data['Firma'].' != '.$assinatura_calc.'): exit ', 'ERROR');
			echo 'Technical Note: The verification signatures don\'t match';
			return false;
		}
		if (!class_exists('VirtueMartModelOrders')) require_once( VMPATH_ADMIN . DS . 'models' . DS . 'orders.php' );
		$this->logInfo('ceca_post_data '.implode('   ', $ceca_post_data), 'MESSAGE');
		$this->_storeCECAInternalData($method, $ceca_post_data, $idEncomenda, $order_number);
		$modelOrder = VmModel::getModel('orders');
		$order = array();
		if(($ceca_post_data['Referencia'] != '') && $ceca_post_data['Num_aut'] != '') {
			$new_status = $method->status_success;
			$order['comments'] = JText::sprintf('VMPAYMENT_CECA_PAGAMENTO_ACEITE', $order_number, $ceca_post_data['Referencia'], urldecode($ceca_post_data['Num_aut']));
			echo '$*$OKY$*$';
		}
		else {
			$new_status = $method->status_canceled;
			$order['comments'] = JText::sprintf('VMPAYMENT_CECA_PAGAMENTO_REJEITADO', $order_number);
			$this->logInfo('Payment not authorised. Status: '. $new_status, 'ERROR');
			echo 'Payment not authorised';
		}
		$order['order_status'] = $new_status;
		$order['customer_notified'] = 1;
		$modelOrder->updateStatusForOneOrder($idEncomenda, $order, true);
		$this->logInfo('Notification, sentOrderConfirmedEmail ' . $order_number . ' ' . $new_status, 'MESSAGE');
		return true;
	  }

	  /**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 */
	  function plgVmOnPaymentResponseReceived(&$html) {
		$itemId = vRequest::getInt('Itemid', '');
		$virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
		$order_number = vRequest::getVar('on', 0);
		$acom = vRequest::getVar('action');
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		if(!isset($order_number) || empty($order_number)) {
			//vmError(JText::_('VMPAYMENT_CECA_NOM_HA_NUM_ENCOMENDA'));
			$html = '<img src="'.JURI::root().'plugins/vmpayment/ceca/'.(JVM_VERSION >= 2 ? 'ceca/' : '').'erro.png"><h1>'.JText::_('VMPAYMENT_CECA_NOM_HA_NUM_ENCOMENDA_TIT').'</h1>';
			$html .= '<p>'.JText::_('VMPAYMENT_CECA_NOM_HA_NUM_ENCOMENDA').'</p>';
			return false;
		}

		if(!class_exists('VirtueMartCart')) 
			require(VMPATH_SITE.DS.'helpers'.DS.'cart.php');
		if(!class_exists('VirtueMartModelOrders')) 
			require(VMPATH_ADMIN.DS.'models'.DS.'orders.php');
		$html = '<img src="'.JURI::root().'plugins/vmpayment/ceca/'.(JVM_VERSION >= 2 ? 'ceca/' : '').($acom == 'OK' ? 'correto.png' : 'incorreto.png').'" alt="'.($acom == 'OK' ? JText::_('VMPAYMENT_CECA_PAGAMENTO_CORRETO_TIT') : JText::_('VMPAYMENT_CECA_PAGAMENTO_INCORRETO_TIT')).'" border="0" />
<h1>'.($acom == 'OK' ? JText::_('VMPAYMENT_CECA_PAGAMENTO_CORRETO_TIT') : JText::_('VMPAYMENT_CECA_PAGAMENTO_INCORRETO_TIT')).'</h1>';
		$html .= '<p>'.($acom == 'OK' ? JText::_('VMPAYMENT_CECA_PAGAMENTO_CORRETO') : JText::_('VMPAYMENT_CECA_PAGAMENTO_INCORRETO')).'</p>';
		//$html .= JHTML::_('link', JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$order_number.($itemId == '' ? '' : '&Itemid='.$itemId)), JText::_('VMPAYMENT_CECA_CONSULTA_ENCOMENDA'));
		$idEncomenda = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		$modelOrder = VmModel::getModel('orders');
		$order = $modelOrder->getOrder($idEncomenda);
		$tplData = array(
			'errorText' => $errorText ? $errorText : '',
			'imgSrc' => JURI::root().'plugins/vmpayment/ceca/'.(JVM_VERSION >= 2 ? 'ceca/' : '').($acom == 'OK' ? 'correto.png' : 'incorreto.png'),
			'imgAlt' => ($acom == 'OK' ? JText::_('VMPAYMENT_CECA_PAGAMENTO_CORRETO_TIT') : JText::_('VMPAYMENT_CECA_PAGAMENTO_INCORRETO_TIT')),
			'title' => ($acom == 'OK' ? JText::_('VMPAYMENT_CECA_PAGAMENTO_CORRETO_TIT') : JText::_('VMPAYMENT_CECA_PAGAMENTO_INCORRETO_TIT')),
			'text' => ($acom == 'OK' ? JText::_('VMPAYMENT_CECA_PAGAMENTO_CORRETO') : JText::_('VMPAYMENT_CECA_PAGAMENTO_INCORRETO')),
			'linkOrder' => JURI::root().'index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$order_number.'&order_pass='.$order['details']['BT']->order_pass.($itemId == '' ? '' : '&Itemid='.$itemId)
			);
		if($acom == 'OK') {
			$cart = VirtueMartCart::getCart();
			$cart->emptyCart();
		}
		$html = $this->renderByLayout(($acom == 'OK' ? 'url_ok' : 'url_ko'), $tplData);
		return true;
	  }
	
	function _storeCECAInternalData($method, $ceca_post_data, $virtuemart_order_id, $order_number) {
		$bd = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE '
		. " `virtuemart_order_id` = '" . $virtuemart_order_id . "'";
		$bd->setQuery($q);
		$data = date("d-m-Y");
		$hora = date("H:i:s");
		$guardado = $bd->loadObject();
		$getEscaped = version_compare(JVERSION, '3.0.0','ge') ? 'escape' : 'getEscaped';
		$resposta['virtuemart_order_id'] = $bd->{$getEscaped}($virtuemart_order_id);
		$resposta['order_number'] = $bd->{$getEscaped}($order_number);
		$resposta['virtuemart_paymentmethod_id'] = $guardado->virtuemart_paymentmethod_id;
		$resposta['payment_name'] = $this->renderPluginName($method);
		$resposta['payment_order_total'] = $guardado->payment_order_total;
		$resposta['payment_currency'] = $guardado->payment_currency;
		$resposta['cost_per_transaction'] = $guardado->cost_per_transaction;
		$resposta['cost_percent_total'] = $guardado->cost_percent_total;
		$resposta['tax_id'] = $guardado->tax_id;
		$resposta['ceca_response_date'] = $bd->{$getEscaped}($data);
		$resposta['ceca_response_hour'] = $bd->{$getEscaped}($hora);
		$resposta['ceca_response_order'] = $bd->{$getEscaped}($ceca_post_data['Num_operacion']);
		$resposta['ceca_response_amount'] = $bd->{$getEscaped}($ceca_post_data['Importe']);
		$resposta['ceca_response_authorisationcode'] = $bd->{$getEscaped}($ceca_post_data['Num_aut']);
		$resposta['ceca_response_card_country'] = $bd->{$getEscaped}($ceca_post_data['Pais']);
		$resposta['ceca_response_language'] = $bd->{$getEscaped}($ceca_post_data['Idioma']);
		$resposta['ceca_response_currency'] = $bd->{$getEscaped}($ceca_post_data['TipoMoneda']);
		$resposta['ceca_response_merchantid'] = $bd->{$getEscaped}($ceca_post_data['MerchantID']);
		$resposta['ceca_response_acquirerbin'] = $bd->{$getEscaped}($ceca_post_data['AcquirerBIN']);
		$resposta['ceca_response_signature'] = $bd->{$getEscaped}($ceca_post_data['Firma']);
		$resposta['ceca_response_terminal'] = $bd->{$getEscaped}($ceca_post_data['TerminalID']);
		$resposta['ceca_response_exponent'] = $bd->{$getEscaped}($ceca_post_data['Exponente']);
		$resposta['ceca_response_reference'] = $bd->{$getEscaped}($ceca_post_data['Referencia']);
		$resposta['ceca_response_description'] = $bd->{$getEscaped}($ceca_post_data['Descripcion']);
		//$preload=true   preload the data here to preserve not updated data -> actually not working
		$this->storePSPluginInternalData($resposta, 'virtuemart_order_id', true);
	}
	
	function obterChave($method, $idPagamento) {
		if(!file_exists(JPATH_ADMINISTRATOR.'/components/com_jetpvvcommon/versom.php')) {
			$chave = $method->ceca_chave;
		}
		elseif(!JComponentHelper::isEnabled('com_jetpvvcommon', true))
			$chave = $method->ceca_chave;
		else {
			$funcomDescifrado = 'AES_DECRYPT';
			$config = &JFactory::getConfig();
			$chaveJ = version_compare(JVERSION, '3.0.0','ge') ? $config->get('config.secret') : $config->getValue('config.secret');
			$bd = JFactory::getDBO();
			$q = "SELECT ".$funcomDescifrado."(payment_value,'".$chaveJ."') AS `chave` FROM #__je_tpvv_common WHERE payment_key='ceca_chave' AND virtuemart_payment_id='$idPagamento'";
			$bd->setQuery($q);
			$chave = isset($bd->loadObject()->chave) ? $bd->loadObject()->chave : '';
		}
	return $chave;
	}
}
