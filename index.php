<script src="./src/lib/asset.js"></script>
<?php 

require_once './src/wc_to_dolibarr.php';

function doliCreateProduct(array $data) {

            $newProduct = [
                "ref" => $product->get_title(),
                "label" => $product->get_title(),
                "price_base_type" => "HT",
                "tva_tx" => "20.000",
                "status_buy" => "1",
                "status" => "1",
                "weight" => $product->get_weight(),
                "height" => $product->get_height(),
                'url' => $product->get_permalink(),
                'description' => $product->get_description(),
                'note_public' => $product->get_purchase_note(),
//                $product->get_image()
                "price" => (float)$product->get_regular_price(),

            ];

}




/**
De Dolibarr a WooCommerce:
- Sincronizar Categorias
- Sincronizar productos: incluido fotos, descripciones y stock
De WooCommerce a Dolibarr
- Añadir Clientes nuevos
- Añadir Pedidos nuevos
-*/