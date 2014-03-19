<?php

class Ebizmarts_MailChimp_Main extends BClass {

    public $configPath   = 'modules/Ebizmarts_MailChimp/';
    public $moduleActive = false;
    public $mc;

    public function __construct() {
        $this->moduleActive = (bool)BConfig::i()->get($this->configPath . 'active');

        if($this->moduleActive) {
            include_once __DIR__ . '/lib/mailchimp.php';

            $apiKey = BConfig::i()->get('modules/Ebizmarts_MailChimp/api_key');

            $this->mc = new Mailchimp($apiKey);
        }
    }

    /**
     * Handle emailaftersave event.
     *
     * @param $args
     * @return Ebizmarts_MailChimp_Main
     */
    public function onEmailAfterSave($args) {

        if(!$this->moduleActive) {
            return $this;
        }

        $pref = $args['model'];

        //Subscribe email if record is new
        if($pref->isNewRecord()) {

            if(1 === ((int)$pref->get('sub_newsletter'))) {

                $defaultListId = BConfig::i()->get($this->configPath . 'list_id');

                $subscriptionResult = $this->mc->call('lists/subscribe', array(
                    'id' => $defaultListId,
                    'email' => array(
                        'email' => $pref->get('email'),
                    ),
                ));

                //@TODO: Double opt-in handle in Sellvana?

                //If $subscriptionResult is OKAY
                /*Array
                (
                    [email] => pablete1@ebizmarts.com
                    [euid] => 834773b0f7
                    [leid] => 324422413
                )*/

            }

        }
        else {
            //@TODO: Handle other scenarios.
        }

        return $this;

    }

    /**
     * Handle subscriptor deletion.
     *
     * @param $args
     * @return Ebizmarts_MailChimp_Main
     */
    public function onEmailAfterDelete($args) {

        if(!$this->moduleActive) {
            return $this;
        }

        $pref = $args['model'];

        //Get default list ID from config.
        $defaultListId = BConfig::i()->get($this->configPath . 'list_id');

        //Unsubscribe /*/Delete*/
        $this->mc->call('lists/unsubscribe', array(
            'id' => $defaultListId,
            'email' => array(
                'email' => $pref->get('email'),
            ),
        ));

        return $this;
    }

    /**
     * Push frontend order to ecommerce360.
     *
     * @param $args
     * @return Ebizmarts_MailChimp_Main
     */
    public function onCreateFromCartAfter($args) {

        $ecommEnabled =  (bool)BConfig::i()->get($this->configPath . 'ecomm');

        if(!$this->moduleActive or !$ecommEnabled) {
            return $this;
        }

        $order = $args['order'];

        $date = explode(' ', $order->get('create_at'));

        $ecommOrder = array(
            'id'         => $order->get('unique_id'),
            'email'      => $order->get('customer_email'),
            'total'      => $order->get('grandtotal'),
            'order_date' => $date[0],
            'store_id'   => 1,
            'store_name' => BConfig::i()->get('modules/FCom_Core/site_title'),
            'items'      => array(),
        );

        $shippingAmount = $order->get('shipping_amount');
        if($shippingAmount) {
            $ecommOrder['shipping'] = $shippingAmount;
        }

        $taxAmount = $order->get('tax');
        if($taxAmount) {
            $ecommOrder['tax'] = $shippingAmount;
        }

        $items = $order->items();

        foreach($items as $item) {
            $product = json_decode($item->get('product_info'));

            $_item = array(
                'product_id'    => (int)$product->id,
                'sku'           => substr($product->local_sku, 0, 30),
                'product_name'  => $product->product_name,
                'category_id'   => 0,
                'category_name' => 'None',
                'qty'           => $item->get('qty'),
                'cost'          => $product->cost,
            );

            $ecommOrder['items'][] = $_item;
        }

        try {
            $ecommResult = $this->mc->call('ecomm/order-add', array('order' => $ecommOrder));
        }catch(Exception $ex) {
            BDebug::logException($ex);
        }

        return $this;
    }

}