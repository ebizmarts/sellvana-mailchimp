<?php

class Ebizmarts_MailChimp_Admin extends BClass {

    public $mc;

    public function __construct() {

        include_once __DIR__ . '/lib/mailchimp.php';

        $apiKey = BConfig::i()->get('modules/Ebizmarts_MailChimp/api_key');

        if($apiKey) {
            $this->mc = new Mailchimp($apiKey);
        }
    }

    /**
     * Return all MailChimp lists for a given account.
     *
     * @return array
     */
    public function getAllLists() {

        $myLists = array();

        $lists = $this->mc->call('lists/list', array());

        if(is_array($lists) and isset($lists['data'])) {
            for($i=0;$i<count($lists['data']);$i++) {
                $myLists []= array(
                    'id'   => $lists['data'][$i]['id'],
                    'name' => $lists['data'][$i]['name'],
                );
            }
        }

        return $myLists;
    }

    public function accountDetails() {
        $details = $this->mc->call('helper/account-details', array());

        //@TODO: Check for errors on $details: "This method is available for API Keys belonging to users with the following roles: admin, owner"

        $myDetails = array(
            'username'    => $details['username'],
            'plan_type'   => $details['plan_type'],
            'emails_left' => $details['emails_left'],
        );

        return $myDetails;
    }

    public function ecommOptions() {
        return array(
          array(
              'value' => 1,
              'label' => 'Enable',
          ),
            array(
              'value' => 0,
              'label' => 'Disable',
          )
        );
    }

}