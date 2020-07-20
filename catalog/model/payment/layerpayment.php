<?php

class ModelPaymentLayerpayment extends Model
{
    public function getMethod($address, $total)
    {
        $this->language->load('payment/layerpayment');

        $method_data = array(
            'code' => 'layerpayment',
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => $this->config->get('layerpayment_sort_order'),
        );

        return $method_data;
    }
}
