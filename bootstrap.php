<?php

require_once dirname(__FILE__) .'/src/dolibarr.php';
require_once dirname(__FILE__) .'/src/woocommerce.php';

$doli_api = new doli_api('dolibarr_url', 'username', 'subscription_key');
$wc_api = new wc_api('woocommerce_url', 'public_key', 'secret_key');
