<?php

class Polywoo_orders
{

    function __construct() {
        add_filter( 'pll_get_post_types', [$this, 'make_orders_translatable']  );

        add_action( 'woocommerce_checkout_update_order_meta', [$this, 'set_order_language'] );

        add_filter( 'woocommerce_my_account_my_orders_query', [$this, 'merge_orders_query'] );
    }

    /**
     * Merges orders from different languages
     *
     * @param array $query orders query parameters array
     *
     * @return array
     */
    public function merge_orders_query(array $query) {
        $query['lang'] = join(',', pll_languages_list());
        return $query;
    }

    /**
     * Add orders to list of translatable post types
     *
     * @param array $types list of translatable post types
     *
     * @return array
     */
    public function make_orders_translatable(array $types) {
        $options   = get_option('polylang');
        $postTypes = $options['post_types'];

		if ( !in_array('shop_order', $postTypes) ) {
            $options['post_types'][] = 'shop_order';
            update_option('polylang', $options);
        }

        $types[] = 'shop_order';

        return $types;
    }

    /**
     * Set order language to current language
     *
     * @param int $order_id order id
     *
     * @return void
     */
    public function set_order_language(int $order_id) {
        $lang = pll_current_language('slug');
        pll_set_post_language($order_id, $lang);
    }
}