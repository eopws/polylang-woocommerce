<?php

/**
 * Here we load all needed classes and files
 */

// temporary for tests
add_filter( 'wc_product_has_unique_sku', '__return_false' );

// loads php file with functions definition
function polywoo_script_load($script) {
    require_once POLYWOO_DIR . 'core/functions/' . $script . '.php';
}

polywoo_script_load('products');

function polywoo_classes_autoload($class) {
    $class = str_replace ('_' , '-' , $class);
    require_once POLYWOO_DIR . 'core/classes/' . $class . '.php';
}
spl_autoload_register('polywoo_classes_autoload', true, true);

new Polywoo_variations_sync;
new Polywoo_pages_urls;
new Polywoo_ajax;
new Polywoo_orders;
new Polywoo_product_attributes;
new Polywoo_products_sync;
new Polywoo_stock_sync;

spl_autoload_unregister('polywoo_classes_autoload');