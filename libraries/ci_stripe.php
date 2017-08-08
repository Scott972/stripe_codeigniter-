<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Created by PhpStorm.
 * User: scott
 * Date: 5/19/2016
 * Time: 8:28 AM
 * 
 * Wrapper around stripe PHP library 
 */

require_once(BASEPATH . '../application/libraries/stripe/init.php');


class ci_stripe{
    
    public $ci;
    
    public function __construct()
    {
        $this->ci = &get_instance();
        
        \Stripe\Stripe::setApiKey('your_key_here');
    }


    /**
     * @param $limit
     * @param array $criteria
     * @param $customer
     * 
     * @return \Stripe\Collection
     *
     * Returns last number of charges
     */
    public function get_charges($limit, $criteria = array(), $customer = NULL)
    {
        $charges = \Stripe\Charge::all(
            array(
                'limit' => $limit,
                array(
                    'created'=> array(
                        'gte' => $criteria['start_date'], 
                        'lte' => $criteria['end_date']
                    ),
                ),
                'starting_after' => $criteria['starting_after'],
                'customer' => $customer
            )
        );

        return $charges;
    }


    /**
     * @param $charge_id
     * 
     * @return \Stripe\Charge
     * 
     * Returns a single charge object for the $charged_id passed
     */
    public function retrieve_charge($charge_id)
    {
        $charge = \Stripe\Charge::retrieve($charge_id);
        
        return $charge;
    }
    /**
     * @param $limit
     *
     * @param array $criteria
     *
     * @return \Stripe\Collection
     *
     * Returns stripe refunds
     *
     */
    public function get_stripe_refunds($limit, $criteria = array())
    {
        $refunds= \Stripe\BalanceTransaction::all( /** uses the BalanceTransactions class in order to avoid having to check each individual charge for a refund */
            array(
                'limit' => $limit,
                array(
                    'created'=> array(
                        'gte' => $criteria['start_date'],
                        'lte' => $criteria['end_date']
                    ),
                ),
                'starting_after' => $criteria['starting_after'],
                'type' => 'refund'
            )
        );

        return $refunds;
    }

    /**
     * @param $stripe_customer_id
     * 
     * @return \Stripe\Customer
     * 
     * Returns ENTIRE stripe customer object
     */
    public function get_stripe_customer($stripe_customer_id)
    {
        $customer = \Stripe\Customer::retrieve(
            $stripe_customer_id
        );

        return $customer; 
    }

    /**
     * @param $limit
     * @param $starting_after
     * @return \Stripe\Collection
     * 
     * Returns All stripe customers. 
     * Max $limit of 100 
     * Use $starting_after to paginate ($starting_after represents Stripe customer id here)
     */
    public function get_all_stripe_customers($limit, $starting_after = NULL)
    {
        $customers = \Stripe\Customer::all(
            array('limit' => $limit, 'starting_after' => $starting_after)
        );

        return $customers; 
    }

    /**
     * @param $card_token
     * 
     * @param $stripe_customer_id
     * 
     * @return mixed
     * 
     * Creates a new card for a customer source , returns card object
     */
    public function add_card_to_customer($card_token, $stripe_customer_id)
    {
        $customer_object = $this->get_stripe_customer($stripe_customer_id);
        $new_card = $customer_object->sources->create(
            array(
                "source"=> $card_token
            )
        );
        
        return $new_card; 
    }


    /**
     * @param $charge_amount
     * 
     * @param $stripe_customer_id
     * 
     * @param $stripe_card_id
     * 
     * @param $charge_description
     * 
     * @return \Stripe\Charge
     * 
     * Creates a single charge in stripe, must multiply charge amount by 100 BEFORE passed to method 
     */
    public function create_charge($charge_amount, $stripe_customer_id, $stripe_card_id, $charge_description)
    {
        $charge = \Stripe\Charge::create(array(
                "amount" => $charge_amount,
                "currency" => "usd",
                "customer" => $stripe_customer_id,
                "card" => $stripe_card_id,
                "description" =>$charge_description
            )
        );
        
        return $charge;
    }


    /**
     * @param $limit
     * @param null $staring_after
     * @param null $status
     * @return \Stripe\Collection
     * 
     * Patched \Stripe\Subscriptions 
     * Patched \Stripe\ApiResources
     * 
     * Returns list of all subscriptions by status 
     */
    public function get_all_subscriptions($limit, $staring_after = NULL, $status = NULL)
    {
        $subscriptions = \Stripe\Subscription::all(
            array('limit'=>$limit, 'starting_after' => $staring_after, 'status' => $status)
        );
        
        return $subscriptions; 
    }

    /**
     * @param $customer
     * @return mixed
     *
     * Returns a Stripe Customer Objects default source information
     */
    public function get_customer_default_sources($customer)
    {
        if($customer->default_source) {
            return $customer->cards->retrieve($customer->default_source);
        }
    }

    /**
     * @param $stripe_customer
     * @return mixed
     * 
     * Gets  bank account source if available 
     */
    public function get_customer_bank_account($stripe_customer)
    {
        foreach($stripe_customer->sources->data as $customer_payment_sources){
            if($customer_payment_sources->object == 'bank_account'){
                return $stripe_customer->sources->retrieve($customer_payment_sources->id);
            }
        }
    }

    /**
     * @param $bank_account
     * @return mixed
     * 
     * Verifies the deposit values for a pending verification bank source
     */
    public function verify_account($bank_account)
    {
        return $bank_account->verify(
            array(
                'amounts'=>array(
                    $this->ci->input->post('first_deposit'),
                    $this->ci->input->post('second_deposit')
                )
            )
        );
    }


    /**
     * @param $invoice_id
     * @return \Stripe\Invoice
     * 
     * Retrieves subscription invoice object
     */
    public function get_subscription_invoice($invoice_id)
    {
        $invoice = \Stripe\Invoice::retrieve(
            $invoice_id
        );
        return $invoice;
    }
    
    public function list_invoice($customer_id, $limit)
    {
        $invoice = \Stripe\Invoice::all(
            array(
                'customer'=>$customer_id, 
                'limit' => $limit
            )
        );

        return $invoice;
    }

    /**
     * @param $start_date
     * @param $end_date
     * @param $limit
     * @param $starting_after
     * @return array
     * 
     * Retrieves disputes between date range 
     */
    public function list_disputes($start_date = NULL, $end_date = NULL, $starting_after = NULL, $limit)
    {
        $disputes = \Stripe\Dispute::all(
            array(
                'limit' => $limit,
                'created' => array(
                    'gt' => $start_date,
                    'lt' => $end_date,
                    ),
                'starting_after' => $starting_after
            )
        );
        return $disputes; 
    }
}