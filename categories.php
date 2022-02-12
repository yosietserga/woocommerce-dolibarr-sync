<?php

require_once dirname(__FILE__) .'/bootstrap.php';

$categories = $doli_api->getCategories();
$categories_data = [];
$subcategories = [];
$subcategories_data = [];

//first run, import main categories
foreach ($categories as $k=>$v) {
	if ($v->fk_parent) {
		$subcategories[] = $v;
	} else {
		$categories_data[$k] = [
	        'name'=>$v->label,
	        'description'=>$v->description,
	        'parent'=>(int)$v->fk_parent,
		];
	}
}

$wc_api->importCategories( $categories_data );

//second run, import subcategories
foreach ($subcategories as $k=>$v) {
	//build tree 
	if ($v->fk_parent) {
		$doli_parent = $doli_api->getCategories( ["id"=>$v->fk_parent] ); 
		if ($doli_parent) {
			//generate slug from category name
			$slug = $wc_api->slugify( $doli_parent->label ); 

			//search wc category by slug
			$parent_found = $wc_api->searchCategory( $slug );

			$subcategories_data[$k] = [
		        'name'=>$v->label,
		        'description'=>$v->description,
			];

			if (count( $parent_found ) >= 1) {
				//walk categories and search unique product
				foreach ($parent_found as $c) {
					if ($c->slug === $slug) {
						$subcategories_data[$k]["parent"] = (int)$c->id;
					}
				}
			}
		}
	}
}

$wc_api->importCategories( $subcategories_data );

echo json_encode($wc_api->getImportStats());