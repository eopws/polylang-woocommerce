<?php

class Polywoo_numbers_localize {

    public function __construct() {
        add_filter( 'wc_get_price_decimal_separator', [$this, 'get_decimal_separator'] );
        add_filter( 'wc_get_price_thousand_separator', [$this, 'get_thousand_separator'] );
    }

    /**
     * Filter decimal separator depending on current locale
     *
     * @param string $separator current separator
     *
     * @return string
     */
    public function get_decimal_separator($separator) {
        $number_formatter = NumberFormatter(pll_current_language('locale'), NumberFormatter::DECIMAL);

        return $number_formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL) ?? $separator;
    }

    /**
     * Filter thousand separator depending on current locale
     *
     * @param string $separator current separator
     *
     * @return string
     */
    public function get_thousand_separator($separator) {
        $number_formatter = NumberFormatter(pll_current_language('locale'), NumberFormatter::DECIMAL);

        return $number_formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL) ?? $separator;
    }
}