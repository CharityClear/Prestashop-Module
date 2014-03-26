<?php

class charityclear extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'charityclear';
        $this->tab = 'payments_gateways';
        $this->version = '1.0';
        $this->author = 'CharityClear';

        parent::__construct();

        $this->displayName = 'CharityClear Hosted Form';
        $this->description = $this->l('Process payments with CharityClear');
    }

    public function install()
    {
        return (parent::install());
    }

    public function uninstall()
    {
        Configuration::deleteByName('CHARITYCLEAR_MERCHANT_ID');
        Configuration::deleteByName('CHARITYCLEAR_CURRENCY_ID');
        Configuration::deleteByName('CHARITYCLEAR_COUNTRY_ID');
        Configuration::deleteByName('CHARITYCLEAR_FRONTEND');
        Configuration::deleteByName('CHARITYCLEAR_MERCHANT_PASSPHRASE');

        return parent::uninstall();
    }

    public function hookOrderConfirmation($params)
    {
        global $smarty;

        if ($params['objOrder']->module != $this->name)
            return "";

        if ($params['objOrder']->getCurrentState() != _PS_OS_ERROR_)
            $smarty->assign(array('status' => 'ok', 'id_order' => intval($params['objOrder']->id)));
        else
            $smarty->assign('status', 'failed');

        return $this->display(__FILE__, 'hookorderconfirmation.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitModule')) {
            Configuration::updateValue('CHARITYCLEAR_MERCHANT_ID', Tools::getvalue('charityclear_merchant_id'));
            Configuration::updateValue('CHARITYCLEAR_CURRENCY_ID', Tools::getvalue('charityclear_currency_id'));
            Configuration::updateValue('CHARITYCLEAR_COUNTRY_ID', Tools::getvalue('charityclear_country_id'));
            Configuration::updateValue('CHARITYCLEAR_FRONTEND', Tools::getvalue('charityclear_frontend'));
            Configuration::updateValue('CHARITYCLEAR_MERCHANT_PASSPHRASE', Tools::getvalue('charityclear_passphrase'));

            echo $this->displayConfirmation($this->l('Configuration updated'));
        }

        return '
		<h2>' . $this->displayName . '</h2>
		<form action="' . Tools::htmlentitiesutf8($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset class="width2">
				<legend><img src="../img/admin/contact.gif" alt="" />' . $this->l('Settings') . '</legend>
				<label for="charityclear_merchant_id">' . $this->l('Merchant ID') . '</label>
				<div class="margin-form"><input type="text" size="20" id="charityclear_merchant_id" name="charityclear_merchant_id" value="' . Configuration::get('CHARITYCLEAR_MERCHANT_ID') . '" /></div>
				<label for="charityclear_currency_id">' . $this->l('Currency Code') . '</label>
				<div class="margin-form"><input type="text" size="20" id="charityclear_currency_id" name="charityclear_currency_id" value="' . Configuration::get('CHARITYCLEAR_CURRENCY_ID') . '" /></div>
				<label for="charityclear_country_id">' . $this->l('Country ID') . '</label>
                                <div class="margin-form"><input type="text" size="20" id="charityclear_country_id" name="charityclear_country_id" value="' . Configuration::get('CHARITYCLEAR_COUNTRY_ID') . '" /></div>
				<label for="charityclear_passphrase">' . $this->l('Passphrase') . '</label>
                                <div class="margin-form"><input type="text" size="20" id="charityclear_passphrase" name="charityclear_passphrase" value="' . Configuration::get('CHARITYCLEAR_MERCHANT_PASSPHRASE') . '" /></div>
				<label for="charityclear_frontend">' . $this->l('Frontend Name') . '</label>
                                <div class="margin-form"><input type="text" size="20" id="charityclear_frontend" name="charityclear_frontend" value="' . Configuration::get('CHARITYCLEAR_FRONTEND') . '" /></div>
				<br /><center><input type="submit" name="submitModule" value="' . $this->l('Update settings') . '" class="button" /></center>
			</fieldset>
		</form>';
    }

    public function hookPayment($params)
    {
        global $smarty;

        $invoiceAddress = new Address((int)$params['cart']->id_address_invoice);
	$currency = new Currency((int)($params['cart']->id_currency));

        $charityclearparams = array();
        $charityclearparams['merchantID'] = Configuration::get('CHARITYCLEAR_MERCHANT_ID');
        
        $charityclearparams['currencyCode'] = is_numeric(Configuration::get('CHARITYCLEAR_CURRENCY_ID')) ? Configuration::get('CHARITYCLEAR_CURRENCY_ID') : $currency->iso_code_num;
        //$charityclearparams['currencyCode'] = Configuration::get('CHARITYCLEAR_CURRENCY_ID');
        
        $charityclearparams['countryCode'] = Configuration::get('CHARITYCLEAR_COUNTRY_ID');
        $charityclearparams['action'] = "SALE";
        $charityclearparams['type'] = 1;
        $charityclearparams['orderRef'] = $params['cart']->id;
        $charityclearparams['transactionUnique'] = (int)($params['cart']->id) . '_' . date('YmdHis') . '_' . $params['cart']->secure_key;
        $charityclearparams['amount'] = number_format($params['cart']->getOrderTotal(), 2, '', '');

        $charityclearparams['redirectURL'] = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/" . $this->name . "/validation.php";
        $charityclearparams['customerName'] = $invoiceAddress->firstname . ' ' . $invoiceAddress->lastname;
        $charityclearparams['customerAddress'] = $invoiceAddress->address1 . "\n" . $invoiceAddress->address2 . "\n" . $invoiceAddress->city;
        $charityclearparams['customerPostCode'] = $invoiceAddress->postcode;
        $charityclearparams['merchantData'] = "PrestaShop " . $this->name . ' ' . $this->version;
        
        $signing = "merchantID,currencyCode,countryCode,action,type,orderRef,transactionUnique,amount,redirectURL,customerName,customerAddress,customerPostCode,merchantData";
        
        if(!empty($invoiceAddress->phone)){
        	$charityclearparams['customerPhone'] = $invoiceAddress->phone;
        	$signing .= ",customerPhone";
        }
                
        if (Configuration::get('CHARITYCLEAR_MERCHANT_PASSPHRASE')) {
            ksort($charityclearparams);
            $sig_fields = http_build_query($charityclearparams) . Configuration::get('CHARITYCLEAR_MERCHANT_PASSPHRASE');
            $charityclearparams['signature'] = hash('SHA512', $sig_fields) . "|" . $signing;
        }

        $smarty->assign('p', $charityclearparams);
       // $smarty->assign('isFailed', $isFailed);
        $smarty->assign('frontend', Configuration::get('CHARITYCLEAR_FRONTEND'));

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_charityclear' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'));

        return $this->display(__FILE__, 'charityclear.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
            return;

        global $smarty;

        if ($params['objOrder']->module != $this->name) {
            return "";
        }

        if ($params['objOrder']->getCurrentState() != _PS_OS_ERROR_) {
            $smarty->assign(array('status' => 'ok', 'id_order' => intval($params['objOrder']->id)));
        } else {
            $smarty->assign('status', 'failed');
        }
        return $this->display(__FILE__, 'hookorderconfirmation.tpl');
    }

}

?>
