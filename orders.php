<?php

require_once dirname(__FILE__) .'/bootstrap.php';

$query = [];

if (isset($_REQUEST['page'])) $query['page'] = $_REQUEST['page'];
if (isset($_REQUEST['per_page'])) $query['per_page'] = $_REQUEST['per_page'];
if (isset($_REQUEST['status'])) $query['status'] = $_REQUEST['status'];
if (isset($_REQUEST['before'])) $query['before'] = $_REQUEST['before'];
if (isset($_REQUEST['after'])) $query['after'] = $_REQUEST['after'];

//get orders from woocommerce
$orders = $wc_api->searchOrder( $query );
$orders_data = [];
foreach ($orders as $order) {
	$orders_data[] = [
        'sku'=>$order->ref,
        'name'=>$order->label,
        'description'=>$order->description,
        'type'=>'simple',
        'regular_price'=>$order->price,
        'weight'=>$order->weight,
        'length'=>$order->length,
        'width'=>$order->width,
        'height'=>$order->height
	];
}

//import orders into dolibarr
$doli_api->importOrders( $orders );

echo json_encode($doli_api->getImportStats());