<?php
class WC_Mollie_Helper_Data
{
    /**
     * @var Mollie_API_Object_Method[]|Mollie_API_Object_List|array
     */
    protected static $api_methods;

    /**
     * @var Mollie_API_Object_Issuer[]|Mollie_API_Object_List|array
     */
    protected static $api_issuers;

    /**
     * @var WC_Mollie_Helper_Api
     */
    protected $api_helper;

    /**
     * @param WC_Mollie_Helper_Api $api_helper
     */
    public function __construct (WC_Mollie_Helper_Api $api_helper)
    {
        $this->api_helper = $api_helper;
    }

    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $payment_id
     * @param bool   $use_cache (default true)
     * @return Mollie_API_Object_Payment|null
     */
    public function getPayment ($payment_id, $use_cache = true)
    {
        try
        {
            $transient_id = WC_Mollie::PLUGIN_ID . '_payment_' . $payment_id;

            if ($use_cache)
            {
                $payment = @unserialize(get_transient($transient_id));

                if ($payment && $payment instanceof Mollie_API_Object_Payment)
                {
                    return $payment;
                }
            }

            $payment = $this->api_helper->getApiClient()->payments->get($payment_id);

            set_transient($transient_id, $payment, MINUTE_IN_SECONDS * 5);

            return $payment;
        }
        catch (Exception $e)
        {
            WC_Mollie::debug(__METHOD__ . ": Could not load payment $payment_id: " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return NULL;
    }

    /**
     * @return Mollie_API_Object_Method[]|Mollie_API_Object_List|array
     * @throws WC_Mollie_Exception_InvalidApiKey
     */
    public function getPaymentMethods ()
    {
        try
        {
            $transient_id = WC_Mollie::PLUGIN_ID . '_api_methods';

            if (empty(self::$api_methods))
            {
                $cached = @unserialize(get_transient($transient_id));

                if ($cached && $cached instanceof Mollie_API_Object_List)
                {
                    self::$api_methods = $cached;
                }
                else
                {
                    self::$api_methods = $this->api_helper->getApiClient()->methods->all();

                    set_transient($transient_id, self::$api_methods, MINUTE_IN_SECONDS * 5);
                }
            }

            return self::$api_methods;
        }
        catch (Mollie_API_Exception $e)
        {
            // add log message
            return array();
        }
    }

    /**
     * @param string $method
     * @return Mollie_API_Object_Method|null
     */
    public function getPaymentMethod ($method)
    {
        $payment_methods = $this->getPaymentMethods();

        foreach ($payment_methods as $payment_method)
        {
            if ($payment_method->id == $method)
            {
                return $payment_method;
            }
        }

        return null;
    }

    /**
     * @param string|null $method
     * @return Mollie_API_Object_Issuer[]|Mollie_API_Object_List|array
     */
    public function getIssuers ($method = NULL)
    {
        try
        {
            $transient_id = WC_Mollie::PLUGIN_ID . '_api_issuers';

            if (empty(self::$api_issuers))
            {
                $cached = @unserialize(get_transient($transient_id));

                if ($cached && $cached instanceof Mollie_API_Object_List)
                {
                    self::$api_issuers = $cached;
                }
                else
                {
                    self::$api_issuers = $this->api_helper->getApiClient()->issuers->all();

                    set_transient($transient_id, self::$api_issuers, MINUTE_IN_SECONDS * 5);
                }
            }

            // Filter issuers by method
            if ($method !== NULL)
            {
                $method_issuers = array();

                foreach(self::$api_issuers AS $issuer)
                {
                    if ($issuer->method === $method)
                    {
                        $method_issuers[] = $issuer;
                    }
                }

                return $method_issuers;
            }

            return self::$api_issuers;
        }
        catch (Mollie_API_Exception $e)
        {
            WC_Mollie::debug(__METHOD__ . ': Failed to retrieve issuers: ' . $e->getMessage());
        }

        return array();
    }

    /**
     * Save active Mollie payment id for order
     *
     * @param int    $order_id
     * @param string $mollie_payment_id
     * @return $this
     */
    public function setActiveMolliePaymentId ($order_id, $mollie_payment_id)
    {
        add_post_meta($order_id, '_mollie_transaction_id', $mollie_payment_id, $single = true);
        delete_post_meta($order_id, '_mollie_cancelled_payment_id');

        return $this;
    }

    /**
     * Delete active Mollie payment id for order
     * @param int $order_id
     * @return $this
     */
    public function unsetActiveMolliePaymentId ($order_id)
    {
        delete_post_meta($order_id, '_mollie_transaction_id');

        return $this;
    }

    /**
     * Get active Mollie payment id for order
     *
     * @param int $order_id
     * @return string
     */
    public function getActiveMolliePaymentId ($order_id)
    {
        return get_post_meta($order_id, '_mollie_transaction_id', $single = true);
    }

    /**
     * @param int  $order_id
     * @param bool $use_cache
     * @return Mollie_API_Object_Payment|null
     */
    public function getActiveMolliePayment ($order_id, $use_cache = true)
    {
        if ($this->hasActiveMolliePayment($order_id))
        {
            return $this->getPayment(
                $this->getActiveMolliePaymentId($order_id),
                $use_cache
            );
        }

        return null;
    }

    /**
     * Check if the order has an active Mollie payment
     *
     * @param int $order_id
     * @return bool
     */
    public function hasActiveMolliePayment ($order_id)
    {
        $mollie_payment_id = $this->getActiveMolliePaymentId($order_id);

        return !empty($mollie_payment_id);
    }

    /**
     * @param int $order_id
     * @param string $payment_id
     * @return $this
     */
    public function setCancelledMolliePaymentId ($order_id, $payment_id)
    {
        add_post_meta($order_id, '_mollie_cancelled_payment_id', $payment_id, $single = true);

        return $this;
    }

    /**
     * @param int $order_id
     * @return string|false
     */
    public function getCancelledMolliePaymentId ($order_id)
    {
        return get_post_meta($order_id, '_mollie_cancelled_payment_id', $single = true);
    }

    /**
     * Check if the order has been cancelled
     *
     * @param int $order_id
     * @return bool
     */
    public function hasCancelledMolliePayment ($order_id)
    {
        $cancelled_payment_id = $this->getCancelledMolliePaymentId($order_id);

        return !empty($cancelled_payment_id);
    }
}
