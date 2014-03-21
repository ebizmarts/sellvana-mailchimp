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

            if($apiKey) {
                $this->mc = new Mailchimp($apiKey);
            }
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

        $sub   = (int)$pref->get('sub_newsletter');
        $unsub = (int)$pref->get('unsub_all');

        $defaultListId = BConfig::i()->get($this->configPath . 'list_id');

        $postData = array(
            'id' => $defaultListId,
            'email' => array(
                'email' => $pref->get('email'),
            )
        );

        //Subscribe email if record is new and subscribe
        if($pref->isNewRecord()) {

            //Check if subscribe flag is ON
            if(1 === $sub) {

                //Frontend, get customer data if logged in.
                $cust = FCom_Customer_Model_Customer::i()->sessionUser();
                if(false !== $cust) {
                    $postData['merge_vars'] = array(
                        'FNAME' => $cust->get('firstname'),
                        'LNAME' => $cust->get('lastname'),
                    );
                }

                try {
                    $this->mc->call('lists/subscribe', $postData);
                }catch(Exception $ex) {
                    BDebug::logException($ex);
                }

                //@TODO: Double opt-in handle in Sellvana?

            }

        }
        else {

            $_postData = $postData;

            $_postData['emails'] = array(
                array('email' => $postData['email']['email']),
            );
            unset($_postData['email']);

            $memberInfo = $this->mc->call('lists/member-info', $_postData);

            $isSubscribed = false;
            if(is_array($memberInfo) and !empty($memberInfo)) {
                if((int)$memberInfo['success_count'] === 1) {
                    $isSubscribed = true;
                }
            }

            if(1 === $unsub) {

                //Unsubscribe email
                if($isSubscribed) {
                    $this->mc->call('lists/unsubscribe', $postData);
                }

            }
            else {

                if(1 === $sub) {
                    //Subscribe email if not subscribed
                    if(!$isSubscribed) {
                        $this->mc->call('lists/subscribe', $postData);
                    }
                }

            }

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