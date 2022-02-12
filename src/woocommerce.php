<?php


require __DIR__ . '/../vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class wc_api {

	public $client;

	private $stats = [];

	public function __construct(String $url, String $public_key, String $secret_key) {

		$this->client = new Client(
		    $url,
		    $public_key, 
		    $secret_key,
		    [
		        'wp_api' => true,
		        'version' => 'wc/v3',
		       // 'query_string_auth' => true
		    ]
		);
	}

	public function getAttribute( int $id ) {
		return $this->client->get( "products/attributes/$id" );
	}

	public function searchAttribute(string $slug) {
		return $this->client->get( "products/attributes", ['slug'=>$slug] );
	}

	public function createAttribute( array $data ) {
		return $this->client->post( 'products/attributes', $data );
	}

	public function updateAttribute( int $id, array $data ) {
		return $this->client->put( "products/attributes/$id", $data );
	}

	public function importAttributes(array $attributes) {
		foreach ( $attributes as $attribute_name => $attribute ) {

			//generate slug from attribute name
			$slug = "pa_". $this->slugify( $attribute['name'] ); 

			//search wc attribute by slug
			$attribute_found = $this->searchAttribute( $slug );

			//check if is new attribute or not
			if (!count( $attribute_found )) {
				//attributes not found
				$request_method = "post";
			} else if (count( $attribute_found ) === 1) {
				//unique attribute found
				$request_method = "put";
			} else if (count( $attribute_found ) > 1) {
				//walk categories and search unique attribute
				foreach ($attribute_found as $c) {
					if ($c["slug"] === $slug) $request_method = "put";
				}
			} else {
				//default method
				$request_method = "post";
			}

			$wc_attribute = null;

			//prepare attribute data
			$attribute_data = array(
			    'name' => $attribute["name"],
			    'slug' => $slug,
			    'type' => 'select',
			    'order_by' => 'menu_order',
			    'has_archives' => true
			);

			if ($request_method=="put") {
				//update attribute
				$id = $attribute_found[0]["id"];
				$wc_attribute = $this->updateAttribute( (int)$id, $attribute_data );
			} else {
				//create attribute
				$wc_attribute = $this->createAttribute( $attribute_data );
			}

			if ( $wc_attribute ) {
				status_message( 'Attribute added. ID: '. $wc_attribute['id'] );

				// store attribute ID so that we can use it later for creating products and variations
				$added_attributes[$attribute["name"]]['id'] = $wc_attribute['id'];
				
				// Import Attribute terms
				foreach ( $attribute['terms'] as $term ) {

					$attribute_term_data = array(
						'name' => $term
					);

					$wc_attribute_term = $this->client->post( 'products/attributes/'. $wc_attribute['id'] .'/terms', $attribute_term_data );

					if ( $wc_attribute_term ) {
						status_message( 'Attribute term added. ID: '. $wc_attribute['id'] );

						// store attribute terms so that we can use it later for creating products
						$added_attributes[$attribute["name"]]['terms'][] = $term;
					}
				}
			}
		}
	}

	public function getProduct( int $id ) {
		return $this->client->get( "products/$id" );
	}

	public function searchProduct(string $slug) {
		return $this->client->get( "products", ['slug'=>$slug] );
	}

	public function createProduct( array $data ) {
		return $this->client->post( 'products', $data );
	}

	public function updateProduct( int $id, array $data ) {
		return $this->client->put( "products/$id", $data );
	}

	public function importProducts(array $data) {
		//Import Products
		foreach ( $data as $k => $product ) {
			$this->stats['processed'] = (int)$this->stats['processed']+1;

			if ( isset( $product['variations'] ) ) {
				$_product_variations = $product['variations']; // temporary store variations array

				// Unset and make the $product data correct for importing the product.
				unset($product['variations']);
			}

			//generate slug from product name
			$slug = $this->slugify( $product['name'] ); 

			//search wc product by slug
			$product_found = $this->searchProduct( $slug );

			//check if is new product or not
			$id = 0;
			$request_method = "post";
			if (count( $product_found ) >= 1) {
				//walk categories and search unique product
				foreach ($product_found as $c) {
					if ($c->slug === $slug) {
						$request_method = "put";
						$id = $c->id;
					}
				}
			}

			$wc_product = null;

			if ($request_method=="put") {
				//update product
				$wc_product = $this->updateProduct( (int)$id, $product );
			} else {
				//create product
				$wc_product = $this->createProduct( $product );
			}

			if ( $wc_product ) {
				$this->stats['imported'] = (int)$this->stats['imported']+1;
				if ($request_method=="post") $this->stats['created'] = (int)$this->stats['created']+1;
				if ($request_method=="put") $this->stats['updated'] = (int)$this->stats['updated']+1;
			} else {
				$this->stats['failed'] = (int)$this->stats['failed']+1;
			}

			if ( isset( $_product_variations ) ) {
				// Import Product variations

				// Loop through our temporary stored product variations array and add them
				foreach ( $_product_variations as $variation ) {
					$wc_variation = $this->client->post( 'products/'. $wc_product['id'] .'/variations', $variation );

					if ( $wc_variation ) {
						status_message( 'Product variation added. ID: '. $wc_variation['id'] . ' for product ID: ' . $wc_product['id'] );
					}
				}

				// Don't need it anymore
				unset($_product_variations);
			}

		}
	
	}

	public function getImportStats() {
		return $this->stats;
	}

	public function getCategory( int $id ) {
		return $this->client->get( "products/categories/$id" );
	}

	public function searchCategory(string $slug) {
		return $this->client->get( "products/categories", ['slug'=>$slug] );
	}

	public function createCategory( array $data ) {
		return $this->client->post( 'products/categories', $data );
	}

	public function updateCategory( int $id, array $data ) {
		return $this->client->put( "products/categories/$id", $data );
	}

	public function importCategories(array $data) {
		$valid_params = [
			'name'=>'string',
			'slug'=>'string',
			'parent'=>'int',
			'description'=>'string',
			'display'=>'string',
			'image'=>'array',
		];

		$categories = [];
		foreach ($data as $j=>$l) {
			foreach ($valid_params as $k=>$v) {
				if (isset($data[$j][$k]) && !empty($data[$j][$k])) {
					$categories[$j] = $l;
				}
			}
		}

		// Import
		foreach ( $data as $k => $value ) {
			$this->stats['processed'] = (int)$this->stats['processed']+1;

			if ( empty( $value['name'] ) ) continue;

			//generate slug from category name
			$slug = $this->slugify( $value['name'] ); 

			//search wc category by slug
			$category_found = $this->searchCategory( $slug );

			//check if is new category or not
			$id = 0;
			$request_method = "post";
			if (count( $category_found ) >= 1) {
				//walk categories and search unique product
				foreach ($category_found as $c) {
					if ($c->slug === $slug) {
						$request_method = "put";
						$id = $c->id;
					}
				}
			}

			$wc_category = null;

			if ($request_method=="put") {
				//update category
				$wc_category = $this->updateCategory( (int)$id, $value );
			} else {
				//create category
				$wc_category = $this->createCategory( $value );
			}

			if ( $wc_category ) {
				$this->stats['imported'] = (int)$this->stats['imported']+1;
				if ($request_method=="post") $this->stats['created'] = (int)$this->stats['created']+1;
				if ($request_method=="put") $this->stats['updated'] = (int)$this->stats['updated']+1;
			} else {
				$this->stats['failed'] = (int)$this->stats['failed']+1;
			}
		}
	}

	public function getOrder( int $id ) {
		return $this->client->get( "orders/$id" );
	}

	public function searchOrder(array $params) {
		$query = [];

		if (isset($params['page'])) {
			$query['page'] = (int)$params['page']>0 ? $params['page'] : 1;
		}

		if (isset($params['per_page'])) {
			$query['per_page'] = (int)$params['per_page']>10 ? $params['per_page'] : 10;
		}

		if (isset($params['status'])) {
			if (in_array($params['status'], ['any', 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash'])) $query['status'] = $params['status'];
		}

		date_default_timezone_set('Europe/Madrid');

		if ((isset($params['before']) || isset($params['after'])) && strtotime($params['after']) > strtotime($params['before'])) {
			if (isset($params['before'])) {
				//check or convert ISO8601 date 
				$datetime = new DateTime(date('Y-m-d', strtotime($params['before'])));
				$query['before'] = $datetime->format(DateTime::ATOM);
			}

			if (isset($params['after'])) {
				//check or convert ISO8601 date 
				$datetime = new DateTime(date('Y-m-d', strtotime($params['after'])));
				$query['after'] = $datetime->format(DateTime::ATOM);
			}
		}

		return $this->client->get( "orders", $query );
	}

	public function getCustomer( int $id ) {
		return $this->client->get( "customers/$id" );
	}

	public function searchCustomer(array $params) {
		$query = [];

		if (isset($params['page'])) {
			$query['page'] = (int)$params['page']>0 ? $params['page'] : 1;
		}

		if (isset($params['per_page'])) {
			$query['per_page'] = (int)$params['per_page']>10 ? $params['per_page'] : 10;
		}

		if (isset($params['email'])) {
			//TODO: email validation
			$query['email'] = $params['email'];
		}

		if (isset($params['role'])) {
			if (in_array($params['role'], ['any', 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager'])) $query['role'] = $params['role'];
		}

		return $this->client->get( "customers", $query );
	}

	public function slugify(string $str) {
        if ($str !== mb_convert_encoding(mb_convert_encoding($str, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))
            $str = mb_convert_encoding($str, 'UTF-8', mb_detect_encoding($str));
        $str = htmlentities($str, ENT_NOQUOTES, 'UTF-8');
        $str = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '\1', $str);
        $str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
        $str = preg_replace(array('`[^a-z0-9]`i', '`[-]+`'), '-', $str);
        $str = strtolower(trim($str, '-'));
        return $str;
	}
}

function status_message( $str ) {
	var_dump($str);
}