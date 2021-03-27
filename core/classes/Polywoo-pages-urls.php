<?php

class Polywoo_pages_urls {
    public function __construct() {
        $pages = [
            'cart',
            'myaccount',
            'shop',
            'checkout',
            'terms'
        ];

        foreach ($pages as $page) {
            add_filter( 'woocommerce_get_' . $page . '_page_id', [$this, 'get_page_id'] );
        }
    }

    /**
     * Filters page id to set page id in current language
     *
     * @param int $page_id page id
     *
     * @return int
     */
    public function get_page_id($page_id) {
        return is_int($page_id) ? pll_get_post($page_id) : $page_id;
    }
}