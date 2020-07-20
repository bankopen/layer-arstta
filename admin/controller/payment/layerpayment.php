<?php

class ControllerPaymentLayerpayment extends Controller
{
    private $error = array();

    public function index()
    {
        $this->language->load('payment/layerpayment');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('layerpayment', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/payment', 'token='.$this->session->data['token'], 'SSL'));
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
		$data['text_test'] = $this->language->get('text_test');
		$data['text_live'] = $this->language->get('text_live');
		
		$data['entry_mode'] = $this->language->get('entry_mode');
        $data['entry_apikey'] = $this->language->get('entry_apikey');
        $data['entry_secretkey'] = $this->language->get('entry_secretkey');
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_order_fail_status'] = $this->language->get('entry_order_fail_status');		
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        $data['help_mode'] = $this->language->get('help_mode');
		$data['help_apikey'] = $this->language->get('help_apikey');
		$data['help_secretkey'] = $this->language->get('help_secretkey');
        $data['help_order_status'] = $this->language->get('help_order_status');
		$data['help_order_fail_status'] = $this->language->get('help_order_fail_status');        

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['error_apikey'])) {
            $data['error_apikey'] = $this->error['error_apikey'];
        } else {
            $data['error_apikey'] = '';
        }

        if (isset($this->error['error_secretkey'])) {
            $data['error_secretkey'] = $this->error['error_secretkey'];
        } else {
            $data['error_secretkey'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'token='.$this->session->data['token'], 'SSL'),
            'separator' => false,
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('extension/payment', 'token='.$this->session->data['token'], 'SSL'),
            'separator' => ' :: ',
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('payment/layerpayment', 'token='.$this->session->data['token'], 'SSL'),
            'separator' => ' :: ',
        );

        $data['action'] = $this->url->link('payment/layerpayment', 'token='.$this->session->data['token'], 'SSL');

        $data['cancel'] = $this->url->link('extension/layerpayment', 'token='.$this->session->data['token'], 'SSL');

        if (isset($this->request->post['layerpayment_mode'])) {
            $data['layerpayment_mode'] = $this->request->post['layerpayment_mode'];
        } else {
            $data['layerpayment_mode'] = $this->config->get('layerpayment_mode');
        }
		
		if (isset($this->request->post['layerpayment_apikey'])) {
            $data['layerpayment_apikey'] = $this->request->post['layerpayment_apikey'];
        } else {
            $data['layerpayment_apikey'] = $this->config->get('layerpayment_apikey');
        }

        if (isset($this->request->post['layerpayment_secretkey'])) {
            $data['layerpayment_secretkey'] = $this->request->post['layerpayment_secretkey'];
        } else {
            $data['layerpayment_secretkey'] = $this->config->get('layerpayment_secretkey');
        }

        if (isset($this->request->post['layerpayment_order_status'])) {
            $data['layerpayment_order_status'] = $this->request->post['layerpayment_order_status'];
        } else {
            $data['layerpayment_order_status'] = $this->config->get('layerpayment_order_status');
        }
		
		if (isset($this->request->post['layerpayment_order_fail_status'])) {
            $data['layerpayment_order_fail_status'] = $this->request->post['layerpayment_order_fail_status'];
        } else {
            $data['layerpayment_order_fail_status'] = $this->config->get('layerpayment_order_fail_status');
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['layerpayment_status'])) {
            $data['layerpayment_status'] = $this->request->post['layerpayment_status'];
        } else {
            $data['layerpayment_status'] = $this->config->get('layerpayment_status');
        }

        if (isset($this->request->post['layerpayment_sort_order'])) {
            $data['layerpayment_sort_order'] = $this->request->post['layerpayment_sort_order'];
        } else {
            $data['layerpayment_sort_order'] = $this->config->get('layerpayment_sort_order');
        }

        $this->template = 'payment/layerpayment.tpl';
        $this->children = array(
            'common/header',
            'common/footer',
        );
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('payment/layerpayment.tpl', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'payment/layerpayment')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['layerpayment_apikey']) {
            $this->error['error_apikey'] = $this->language->get('error_apikey');
        }

        if (!$this->request->post['layerpayment_secretkey']) {
            $this->error['error_secretkey'] = $this->language->get('error_secretkey');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }
}
