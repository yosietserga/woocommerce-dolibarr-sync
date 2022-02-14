<?php

require_once dirname(__FILE__) .'/bootstrap.php';

//get all products from dolibarr
$products = $doli_api->getProducts();

//prepare products data to wc
$products_data = [];
foreach ($products as $product) {
    //all documents linked to this product 
    $images = $doli_api->getProductImages( $product->id );

	$products_data[] = [
        'sku'=>$product->ref,
        'name'=>$product->label,
        'description'=>$product->description,
        'type'=>'simple',
        'regular_price'=>$product->price,
        'weight'=>$product->weight,
        'length'=>$product->length,
        'width'=>$product->width,
        'height'=>$product->height,
        'images'=>$doli_api->getPublicImagesUrl( $images->ecmfiles_infos ) //upload to this server and return images array
	];
}

//import all products to wc
$wc_api->importProducts( $products_data );

//show import stats
echo json_encode($wc_api->getImportStats());