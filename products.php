<?php

require_once dirname(__FILE__) .'/bootstrap.php';

//categories caching
$cache_categories = []; 

//get all products from dolibarr
$products = $doli_api->getProducts();

//prepare products data to wc
$products_data = [];
foreach ($products as $product) {
    //all documents linked to this product 
    $images = $doli_api->getProductImages( $product->id );

    //categories var
    $categories = [];
    
    //get doli categories for this product
    $doli_categories = $doli_api->getProductCategories( $product->id );
    
    //get wc categories by his slug 
    foreach ($doli_categories as $dk=>$dv) {

        //generate slug from category name
        $slug = $wc_api->slugify( $dv->label );

        //check cache to optimize 
        if (isset($cache_categories[ $slug ])) {
            $categories[] = $cache_categories[ $slug ];
            continue;
        }

        //search wc category by slug
        $category_found = $wc_api->searchCategory( $slug );

        //check if is new category or not
        if (count( $category_found ) >= 1) {
            //walk categories and search unique product
            foreach ($category_found as $c) {
                if ($c->slug === $slug) {
                    $categories[] = ["id"=>$c->id];

                    //save into cache
                    $cache_categories[ $slug ] = ["id"=>$c->id];
                }
            }
        }
    }

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
        'categories'=>$categories,
        'images'=>$doli_api->getPublicImagesUrl( $images->ecmfiles_infos ) //upload to this server and return images array
	];
}
var_dump($products_data);
//import all products to wc
$wc_api->importProducts( $products_data );

//show import stats
echo json_encode($wc_api->getImportStats());