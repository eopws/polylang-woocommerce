<?php

/**
 * Here we load all needed classes and files
 */

function woopoly_classes_autoload($class) {
    $class = str_replace ('_' , '-' , $class);
    require_once POLYWOO_DIR . 'core/classes/' . $class . '.php';
}

spl_autoload_register('woopoly_classes_autoload', true, true);

new Polywoo_products_sync;

spl_autoload_unregister('woopoly_classes_autoload');