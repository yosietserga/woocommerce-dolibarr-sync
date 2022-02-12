<?php

require_once dirname(__FILE__) .'/bootstrap.php';

$products = $doli_api->getProducts();
$products_data = [];
foreach ($products as $product) {
	$products_data[] = [
        'sku'=>$product->ref,
        'name'=>$product->label,
        'description'=>$product->description,
        'type'=>'simple',
        'regular_price'=>$product->price,
        'weight'=>$product->weight,
        'length'=>$product->length,
        'width'=>$product->width,
        'height'=>$product->height
	];
}

$wc_api->importProducts( $products_data );

echo json_encode($wc_api->getImportStats());