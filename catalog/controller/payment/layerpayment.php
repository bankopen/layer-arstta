<?php

class ControllerPaymentLayerpayment extends Controller
{
	const BASE_URL_SANDBOX = "https://sandbox-icp-api.bankopen.co/api";
    const BASE_URL_UAT = "https://icp-api.bankopen.co/api";
	
	private $payment_mode='';
	private $apikey='';
	private $secretkey='';

	public function init()
	{
		$this->payment_mode = $this->config->get('layerpayment_mode');
		$this->apikey = $this->config->get('layerpayment_apikey');
		$this->secretkey = $this->config->get('layerpayment_secretkey');
	}
	
    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		$this->init();
		if($this->payment_mode=='Live') {
			$remote_script = 'https://payments.open.money/layer';
			//$remote_script = '<script id="layer" src="https://payments.open.money/layer"></script>';
		}
		else
		{
			$remote_script = 'https://sandbox-payments.open.money/layer';			
		    //$remote_script = '<script id="layer" src="https://sandbox-payments.open.money/layer"></script>';			
		}
		$txnid = $this->session->data['order_id'];

		$surl = $this->url->link('payment/layerpayment/callback','','SSL');
		
		$layer_payment_token_data = $this->create_payment_token([
                'amount' => (int)$order_info['total'],
                'currency' => $order_info['currency_code'],
                'name'  => $order_info['payment_firstname'].' '.$order_info['payment_lastname'],
                'email_id' => $order_info['email'],
                'contact_number' => $order_info['telephone']                
            ]);
		
		$error="";
		$payment_token_data = "";
		if(empty($error) && isset($layer_payment_token_data['error'])){
			$error = 'E55 Payment error. ' . $layer_payment_token_data['error'];          
		}

		if(empty($error) && (!isset($layer_payment_token_data["id"]) || empty($layer_payment_token_data["id"]))){				
			$error = 'Payment error. ' . 'Layer token ID cannot be empty';        
		}   
    
		if(empty($error))
			$payment_token_data = $this->get_payment_token($layer_payment_token_data["id"]);
    
		if(empty($error) && empty($payment_token_data))
			$error = 'Layer token data is empty...';
		
		if(empty($error) && isset($payment_token_data['error'])){
            $error = 'E56 Payment error. ' . $payment_token_data['error'];            
        }

        if(empty($error) && $payment_token_data['status'] == "paid"){
            $error = "Layer: this order has already been paid";            
        }

        if(empty($error) && $payment_token_data['amount'] != (int)$order_info['total']){
            $error = "Layer: an amount mismatch occurred";
        }
		
    
		if(empty($error) && !empty($payment_token_data)){		
        
			$hash = $this->create_hash(array(
				'layer_pay_token_id'    => $payment_token_data['id'],
				'layer_order_amount'    => $payment_token_data['amount'],
				'woo_order_id'    => $txnid,
				));
			$data['remote_script'] = $remote_script;
			$data['surl'] = $surl;
			$data['payment_token_id'] = $payment_token_data['id'];
			$data['woo_order_id'] = $txnid;
			$data['payment_token_amount'] = $payment_token_data['amount'];
			$data['hash'] = $hash;
			$data['apikey'] = $this->apikey;
			$data['button_confirm'] = $this->language->get('button_confirm');				
		}
		else {
			$data['error']=$error;
		}	

        if (file_exists(DIR_TEMPLATE.$this->config->get('config_template').'/template/payment/layerpayment.tpl')) {
            return $this->load->view($this->config->get('config_template').'/template/payment/layerpayment.tpl', $data);
        } else {
            return $this->load->view('default/template/payment/layerpayment.tpl', $data);
        }
    }

    public function callback()
    {
		$this->init();
		if (isset($this->request->post['layer_payment_id']) || !empty($this->request->post['layer_payment_id'])) {
			$this->language->load('extension/payment/layerpayment');
			$this->load->model('checkout/order');
			
			$pdata = array(
                    'layer_pay_token_id'    => $this->request->post['layer_pay_token_id'],
                    'layer_order_amount'    => $this->request->post['layer_order_amount'],
                    'woo_order_id'     		=> $this->request->post['woo_order_id'],
                );
				
			$orderid = $pdata['woo_order_id'];
			$order_info = $this->model_checkout_order->getOrder($orderid);
			
			$message = '';
			foreach($this->request->post as $k => $val){
				$message .= $k.': ' . $val . "\n";
			}
			
			try {
                if($this->verify_hash($pdata,$this->request->post['hash'])){					

                    if(!empty($order_info)){
						$payment_data = $this->get_payment_details($this->request->post['layer_payment_id']);

                        if(isset($payment_data['error'])){
							$message .=' '.$payment_data['error'];
							$this->session->data['error'] = $message;		
							$this->response->redirect($this->url->link('checkout/checkout', '', true));							
                        }

                        if(isset($payment_data['id']) && !empty($payment_data)){
                            if($payment_data['payment_token']['id'] != $pdata['layer_pay_token_id']){

                                $message .=" Layer: received layer_pay_token_id and collected layer_pay_token_id doesnt match";
								$this->session->data['error'] = $message;		
								$this->response->redirect($this->url->link('checkout/checkout', '', true));
                            }


                            if($pdata['layer_order_amount'] != $payment_data['amount'] || $order_info['total'] !=$payment_data['amount'] ){
								
								$message .=" Layer: received amount and collected amount doesnt match";
								$this->session->data['error'] = $message;		
								$this->response->redirect($this->url->link('checkout/checkout', '', true));
                            }

                            switch ($payment_data['status']){
                                case 'authorized':
								case 'captured': 
									$this->session->data['success'] = "Payment is successful...";
									$this->model_checkout_order->addOrderHistory($orderid, $this->config->get('layerpayment_order_status'),'Payment Successful',true);
									$this->response->redirect($this->url->link('checkout/success', '', true));				
                                    break;
                                case 'failed':								    
                                case 'cancelled':                                    									
									$this->model_checkout_order->addOrderHistory($orderid, $this->config->get('layerpayment_order_fail_status'),$message,true);					
									$this->session->data['error'] = "Payment is cancelled/failed...";
									$this->response->redirect($this->url->link('checkout/checkout', '', true));
									break;
                                default:                                    
                                    exit;
                                break;
                            }
                        } else {
                            $message .=" invalid payment data received E98";
							$this->session->data['error'] = $message;		
							$this->response->redirect($this->url->link('checkout/checkout', '', true));                               
                        }
                    } else {
                        throw new Exception("unable to create order object");
                    }
                } else {
                    throw new Exception("hash validation failed");
                }

            } catch (Throwable $exception){
               
				$message = "Layer: an error occurred " . $exception->getMessage();
				$this->session->data['error'] = $message;		
				$this->response->redirect($this->url->link('checkout/checkout', '', true));
            }							
			
		}
    }

    
	public function create_hash($data){
		ksort($data);
		$hash_string = $this->apikey;
		foreach ($data as $key=>$value){
			$hash_string .= '|'.$value;
		}
		return hash_hmac("sha256",$hash_string,$this->secretkey);
	}
	
	public function verify_hash($data,$rec_hash){
		$gen_hash = $this->create_hash($data);
		if($gen_hash === $rec_hash){
			return true;
		}
		return false;
	}
	
	protected function create_payment_token($data){

        try {
            $pay_token_request_data = array(
                'amount'   			=> (isset($data['amount']))? $data['amount'] : NULL,
                'currency' 			=> (isset($data['currency']))? $data['currency'] : NULL,
                'name'     			=> (isset($data['name']))? $data['name'] : NULL,
                'email_id' 			=> (isset($data['email_id']))? $data['email_id'] : NULL,
                'contact_number' 	=> (isset($data['contact_number']))? $data['contact_number'] : NULL,
                'mtx'    			=> (isset($data['mtx']))? $data['mtx'] : NULL,
                'udf'    			=> (isset($data['udf']))? $data['udf'] : NULL,
            );

            $pay_token_data = $this->http_post($pay_token_request_data,"payment_token");

            return $pay_token_data;
        } catch (Exception $e){			
            return [
                'error' => $e->getMessage()
            ];

        } catch (Throwable $e){
			
			return [
                'error' => $e->getMessage()
            ];
        }
    }

    protected function get_payment_token($payment_token_id){

        if(empty($payment_token_id)){

            throw new Exception("payment_token_id cannot be empty");
        }

        try {

            return $this->http_get("payment_token/".$payment_token_id);

        } catch (Exception $e){

            return [
                'error' => $e->getMessage()
            ];

        } catch (Throwable $e){

            return [
                'error' => $e->getMessage()
            ];
        }

    }

    public function get_payment_details($payment_id){

        if(empty($payment_id)){

            throw new Exception("payment_id cannot be empty");
        }

        try {

            return $this->http_get("payment/".$payment_id);

        } catch (Exception $e){
			
            return [
                'error' => $e->getMessage()
            ];

        } catch (Throwable $e){

            return [
                'error' => $e->getMessage()
            ];
        }

    }


    protected function build_auth($body,$method){

        $time_stamp = trim(time());
        unset($body['udf']);

        if(empty($body)){

            $token_string = $time_stamp.strtoupper($method);

        } else {            
            $token_string = $time_stamp.strtoupper($method).json_encode($body);
        }

        $token = trim(hash_hmac("sha256",$token_string,$this->secretkey));

        return array(                       
            'Content-Type: application/json',                                 
            'Authorization: Bearer '.$this->apikey.':'.$token,
            'X-O-Timestamp: '.$time_stamp
        );

    }


    protected function http_post($data,$route){

        foreach (@$data as $key=>$value){

            if(empty($data[$key])){

                unset($data[$key]);
            }
        }

        if($this->payment_mode == 'Test'){
            $url = self::BASE_URL_SANDBOX."/".$route;
        } else {
            $url = self::BASE_URL_UAT."/".$route;
        }
		
        $header = $this->build_auth($data,"post");
		
        try
        {
            $curl = curl_init();
		    curl_setopt($curl, CURLOPT_URL, $url);
		    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		    curl_setopt($curl, CURLOPT_SSLVERSION, 6);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS,10);
		    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		    curl_setopt($curl, CURLOPT_ENCODING, '');		
		    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_HEX_APOS|JSON_HEX_QUOT ));
            
		    $response = curl_exec($curl);
            $curlerr = curl_error($curl);
            
            if($curlerr != '')
            {
                return [
                    "error" => "Http Post failed",
                    "error_data" => $curlerr,
                ];
            }
			
            return json_decode($response,true);
        }
        catch(Exception $e)
        {
            return [
                "error" => "Http Post failed",
                "error_data" => $e->getMessage(),
            ];
        }           
        
    }

    protected function http_get($route){

        if($this->payment_mode == 'Test'){
			$url = self::BASE_URL_SANDBOX."/".$route;
        } else {			
            $url = self::BASE_URL_UAT."/".$route;
		}

        $header = $this->build_auth($data = [],"get");

        try
        {           
            $curl = curl_init();
		    curl_setopt($curl, CURLOPT_URL, $url);
		    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		    curl_setopt($curl, CURLOPT_SSLVERSION, 6);
		    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		    curl_setopt($curl, CURLOPT_ENCODING, '');		
		    curl_setopt($curl, CURLOPT_TIMEOUT, 60);		   
            $response = curl_exec($curl);
            $curlerr = curl_error($curl);
            if($curlerr != '')
            {
                return [
                    "error" => "Http Get failed",
                    "error_data" => $curlerr,
                ];
            }
            return json_decode($response,true);
        }
        catch(Exception $e)
        {
            return [
                "error" => "Http Get failed",
                "error_data" => $e->getMessage(),
            ];
        }
    }

}
