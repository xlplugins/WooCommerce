<?php
class WC_Mollie_Gateway_MisterCash extends WC_Mollie_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id       = 'mollie_mistercash';
        $this->supports = array(
            'products',
            'refunds',
        );

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::MISTERCASH;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('Bancontact / Mister Cash', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
