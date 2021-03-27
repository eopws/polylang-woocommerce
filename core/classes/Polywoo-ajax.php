<?php

class Polywoo_ajax
{

    public function __construct() {
        add_filter( 'woocommerce_ajax_get_endpoint', [$this, 'filter_woocommerce_ajax_get_endpoint'] );
    }

    /**
     * Get enpoint ajax URL with correct language
     *
     * @param string $url endpoint URL
     *
     * @return string
     */
    public function filter_woocommerce_ajax_get_endpoint(string $url) {
        global $polylang;
        $lang = $polylang->curlang ? $polylang->curlang : $polylang->pref_lang;
        return parse_url($polylang->filters_links->links->get_home_url($lang), PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
    }
}