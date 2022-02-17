<?php

require dirname(__FILE__).'/../vendor/autoload.php';

// if ( ! defined( 'ABSPATH' ) ) {
//     exit( 'restricted access' );
// }

class doli_api 
{
    private $api_base_uri = '/api/index.php';
    
    private $subscription_key;
	private $url_base;
	private $username;
	private $token;
	private $client;

	private $stats = [];

	public function __construct(String $url_base, String $username, String $subscription_key) {
		$this->url_base = $url_base;
		$this->username = $username;
		$this->subscription_key = $subscription_key;

		$this->client = new GuzzleHttp\Client();

        $this->getAccessToken();
	}

    public function getHeaders($withToken = true) {

		$header = [
	        'Accept' => 'application/json',
	        'DOLAPIKEY' => $this->subscription_key,
		];
		
		if ($withToken) {
			if (!isset($this->token->access_token)) $this->getAccessToken();
			$authorization = "Bearer {$this->token->access_token}";
			$header['authorization'] = $authorization;
		}

		

		return $header;
	}

    private function getAccessToken() {
		return $this->subscription_key;
	}

    public function get($endpoint, $params=[]) {
		return $this->request('GET', $this->url_base.$this->api_base_uri . $endpoint, $params);
	}

    public function post($endpoint, $params) {
		return $this->request('POST', $this->url_base.$this->api_base_uri . $endpoint, $params);
	}

    public function request($type, $endpoint, $params) {

		if (!isset($params['headers'])) {
			$withToken = isset($params['withToken']) && $params['withToken'] === false ? false : true;


			if ($withToken && (!isset($this->token->success->token) || !$this->token->success->token)) $this->getAccessToken();

			$params['headers'] = $this->getHeaders( $withToken );
			unset($params['withToken']);
		}

		try {
			$params["DOLAPIKEY"] = $this->subscription_key;
			$response = $this->client->request($type, $endpoint, $params);
			return (int)$response->getStatusCode() === 200 ? json_decode($response->getBody()) : null;
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}

    public function refreshToken() {
		if ($this->token) return $this->token->refresh_token;
		return $this->getAccessToken();
	}

    public function getProducts(array $params = []) {
        $ep = "/products";
		if (isset($params['id']) && !empty((int)$params['id'])) {
			$ep = "/products/{$params['id']}";
		} 

		return $this->get($ep, ["query"=>$params]);
	}

    public function getProductCategories(int $id) {
    	try {
			return $this->get("/products/$id/categories");
		} catch (Exception $e) {
			echo $e->getMessage();
			return false;		
		}
	}

    public function getProductCategoriesID(array $categories) {
    	$categories_ids = [];
    	foreach ($categories as $k=>$v) {
    		$categories_ids[] = ["id"=>$v->id];
    	}
    	return $categories_ids;
	}

    public function getProductImages(int $id) {
    	if (!$id) return false; 

		$query = [
			"id"=>$id,
			"modulepart"=>"product"
		];

		return $this->get("/documents", ["query"=>$query]);
	}

    public function getPublicImagesUrl( array $ecmfiles_infos ) {
    	$images = [];

    	//get current url 
    	$current_url = $this->getCurrentUrl();

		//walk through each image 
    	foreach ($ecmfiles_infos as $k=>$image) {
    		if (!$image->share) continue;

    		$image_content 	= file_get_contents($this->url_base ."/document.php?hashp=". $image->share);
    		$image_ext 		= substr($image->filename, strrpos($image->filename, "."));
    		$image_name 	= $image->share . $image_ext;
    		$image_dir 		= dirname(__FILE__) ."/../public/images/";
			
			if (!file_exists(realpath($image_dir)))
    			mkdir($image_dir, 0777, true);

    		if (file_put_contents(realpath($image_dir) ."/". $image_name, $image_content)) {
	    		$images[] = [
	    			"src"=>$current_url ."/public/images/". $image_name,
	    			"position"=>$k
	    		];
    		}
    	}
    	return $images;
	}

	public function getCurrentUrl() {
		$httpDefaultPath= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] : substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],"/")+1);
		$httpDefaultPath = str_replace('/products.php', "", $httpDefaultPath);

		$protocol = 'http://';
		if (isset($_SERVER['HTTPS']) 
			&& ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) 
			&& $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
			$protocol = 'https://';
		}

		return $protocol . $httpDefaultPath;
	}

    public function getCategories(array $params = []) {
        $ep = "/categories";
		if (isset($params['id']) && !empty((int)$params['id'])) {
			$ep = "/categories/{$params['id']}";
		} 

		return $this->get($ep);
	}


	//get order by ref 
	public function getOrders(array $params = []) {		
        $ep = "/orders";
		if (isset($params['id']) && !empty((int)$params['id'])) {
			$ep = "/orders/{$params['id']}";
		} 

		return $this->get($ep, ["query"=>$params]);
	}

	public function getCustomerByEmail(string $email) {
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $ep = "/thirdparties";

		return $this->get($ep, ["query"=>["sqlfilters" => "(t.email:=:'".$email."')"]]);
    }

	public function importCustomer( array $data ) {
        //getting the thirdparty id 
        $customer = $this->getCustomerByEmail($data['email']);
        if (!$customer || !isset($customer[0]->id)) {
        	//add new customer 
			$customer = $this->post("/thirdparties", ["form_params"=>$data]);
        }
    	return $customer[0]->id;
	}

	public function importOrders(array $data) {
		date_default_timezone_set('Europe/Madrid');

		$orders = [];
		foreach ($data as $j=>$order) {
			$this->stats['processed'] = (int)$this->stats['processed']+1;

			//check if order exists
	        $doli_order = $this->getOrders([ "sqlfilters" => "(t.ref:=:'". $order->order_key ."')" ]);
	        if ($doli_order) {
				$this->stats['already_exists'] = (int)$this->stats['already_exists']+1;
				continue;
	        }

			//prepare customer data
            $customer = [
                'name' => $order->billing->first_name ." ". $order->billing->last_name,
                'email' => $order->billing->email,
                'phone' => $order->billing->phone,
                'adresse' => $order->billing->address_1,
                "country" => $order->billing->country,
                "city" => $order->billing->city,
                "company" => $order->billing->company,
                'client' => '1',
                'code_client' => '-1'
            ];

			//get or create customer
            $customer_id = $this->importCustomer( $customer );

            //prepare order data
            $order_data = [
                "socid" => $customer_id,
                "ref" => $order->order_key,
                "ref_ext" => $order->order_key,
                "ref_client" => $order->billing->email,
                "type" => "0",
                "note_private" => "Order created with Woo-Dolibarr-Sync\n================================\r\r\nWC Order ID: ". $order->id ."\r\nWC Order Ref: ". $order->order_key .".\r\nPayment Method: ".$order->payment_method_title .".\r\nPayment Transaction Id: ". $order->transaction_id,
                "date_commande"=>date('Y-m-d', strtotime($order->date_created)),
                "date"=>date('Y-m-d H:i:s', strtotime($order->date_created)),
            ];

	        //Loop through the order items, then get the products/lines data
  			$lines = [];
	        foreach ($order->line_items as $k=>$item) {
	        	//add new line item to order
				$lines[$k] = [
	                "label" 	=> $item->name,
	                "desc" 		=> $item->name,
	                "subprice" 	=> $item->total + $item->total_tax,
	                "tva_tx" 	=> $item->total_tax,
	                "ref_ext" 	=> $item->sku,
	                "qty" 		=> $item->quantity,
	            ];

	        	//get dolibarr product data 
	        	if ($item->sku) {
	        		$product = $this->getProducts([ "sqlfilters" => "(t.ref:=:'". $item->sku ."')" ]);
	        	}
	        	if ($product[0]) {
	        		$lines[$k]['fk_product'] = (int)$product[0]->id;
	        		$lines[$k]['product_type'] = (int)$product[0]->type;
	        	} else {
	        		$lines[$k]['fk_product'] = 0;
	        		$lines[$k]['product_type'] = 0;
	        	}
	        }
	        $order_data["lines"] = $lines;

			//add new order 
			$doli_order_id = $this->post("/orders", ["form_params"=>$order_data]);
			
			//validate new order created
			if ($doli_order_id) 
				$doli_order_valid = $this->post("/orders/$doli_order_id/validate", ["form_params"=>["idwarehouse"=>"0", "notrigger"=>"0"]]);

			if ($doli_order_valid) {
				$this->stats['imported'] = (int)$this->stats['imported']+1;
			}
		}
	}

	public function getImportStats() {
		return $this->stats;
	}

}