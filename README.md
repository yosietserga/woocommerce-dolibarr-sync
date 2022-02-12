# WooCommerce Dolibarr Sync

Simple PHP script that transfer data between 2 websites deployed with Dolibarr and WooCommerce through Restful API .


## Installation

Use composer to install dependencies, a simple command like **_>composer install**
```sh
composer install
```

## Settings

Open the file named **bootstrap.php** and set your API credentials

## Usage

Just access the files through a web browser to run sync data or use CLI for cronjobs
- **products.php** to sync all products from Dolibarr to WooCommerce
- **categories.php** to sync all categories from Dolibarr to WooCommerce
- **orders.php** to sync all orders and customers from WooCommerce to Dolibarr

## Credits

Developed using WooCommerce API SDK from Automattic and Guzzle HTTP Class from Symfony Bundles.