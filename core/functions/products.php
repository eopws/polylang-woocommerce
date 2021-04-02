<?php

// just handy functions to work with products

/**
 * Method to delete Woo Product
 *
 * @param WC_Product|int $product the product or the product ID.
 * @param bool $force true to permanently delete product, false to move to trash.
 *
 * @return WP_Error|boolean
 */
function polywoo_delete_product( $product, $force = false ) {
    $product = is_int($product) ? wc_get_product($product) : $product;

    if ( empty($product) )
        return new WP_Error(999, sprintf(__('No %s is associated with #%d', 'woocommerce'), 'product', $id));

    // If we're forcing, then delete permanently.
    if ( $force ) {
        if ( $product->is_type('variable') ) {
            foreach ($product->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                $child->delete(true);
            }
        } elseif ( $product->is_type('grouped') ) {
            foreach ($product->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                $child->set_parent_id(0);
                $child->save();
            }
        }

        $product->delete(true);
        $result = $product->get_id() > 0 ? false : true;
    } else {
        $product->delete();
        $result = 'trash' === $product->get_status();
    }

    if ( !$result ) {
        return new WP_Error(999, sprintf(__('This %s cannot be deleted', 'woocommerce'), 'product'));
    }

    // Delete parent product transients.
    if ( $parent_id = wp_get_post_parent_id($id) ) {
        wc_delete_product_transients($parent_id);
    }

    return true;
}

/**
 * Copies all meta info (price, sku etc.) from one product to another
 *
 * @param WC_Product $product_from product to take info from
 * @param WC_Product $product_to product to set info to
 *
 * @return void
 */
function polywoo_copy_product_meta_properties( WC_Product $product_from, WC_Product $product_to ) {
    if ( !empty($product_from->get_sku()) )
        $product_to->set_sku( $product_from->get_sku() );

    if ( empty($product_from->get_sale_price()) ) {
        $product_to->set_price( $product_from->get_regular_price() );
    } else {
        $product_to->set_price( $product_from->get_sale_price() );
        $product_to->set_sale_price( $product_from->get_sale_price() );
    }
    $product_to->set_regular_price( $product_from->get_regular_price() );

    if ( ! empty($product_from->get_stock_quantity()) ) {
        $product_to->set_stock_quantity( $product_from->get_stock_quantity() );
        $product_to->set_manage_stock( $product_from->get_manage_stock() );
        $product_to->set_stock_status( $product_from->get_stock_status() );
    } else {
        $product_to->set_manage_stock(false);
    }

    $product_to->set_catalog_visibility( $product_from->get_catalog_visibility() );
    $product_to->set_featured( $product_from->get_featured() );
    $product_to->set_status( $product_from->get_status() );
    $product_to->set_date_on_sale_from( $product_from->get_date_on_sale_from() );
    $product_to->set_date_on_sale_to( $product_from->get_date_on_sale_to() );
    $product_to->set_total_sales( $product_from->get_total_sales() );
    $product_to->set_tax_status( $product_from->get_tax_status() );
    $product_to->set_tax_class( $product_from->get_tax_class() );
    $product_to->set_backorders( $product_from->get_backorders() );
    $product_to->set_low_stock_amount( $product_from->get_low_stock_amount() );
    $product_to->set_sold_individually( $product_from->get_sold_individually() );
    $product_to->set_weight( $product_from->get_weight() );
    $product_to->set_length( $product_from->get_length() );
    $product_to->set_width( $product_from->get_width() );
    $product_to->set_height( $product_from->get_height() );
    $product_to->set_reviews_allowed( $product_from->get_reviews_allowed() );

    if ( !$product_to->get_gallery_image_ids() )
        $product_to->set_gallery_image_ids( $product_from->get_gallery_image_ids() );

    if ( !$product_to->get_image_id() )
        $product_to->set_image_id( $product_from->get_image_id() );

    $product_to->set_shipping_class_id( $product_from->get_shipping_class_id() );

    // product_to language
    $product_to_lang = pll_get_post_language($product_to->get_id(), 'slug');

    // set product categories
    if ( pll_is_translated_taxonomy('product_cat') ) {
        $category_ids = [];

        foreach ( $product_from->get_category_ids() as $cat_id ) {
            $category_ids[] = pll_get_term($cat_id, $product_to_lang);
        }

        $product_to->set_category_ids($category_ids);
    } else {
        $product_to->set_category_ids( $product_from->get_category_ids() );
    }

    // set upsells translations as upsells for the product_to
    $upsell_ids = [];

    foreach ($product_from->get_upsell_ids() as $upsell_id) {
        $upsell = pll_get_post($upsell_id, $product_to_lang);

        if ($upsell)
            $upsell_ids[] = $upsell;
    }

    $product_to->set_upsell_ids($upsell_ids);

    // set crosssell translations as crosssell for the product_to
    $cross_sell_ids = [];

    foreach ($product_from->get_cross_sell_ids() as $cross_sell_id) {
        $cross_sell = pll_get_post($cross_sell_id, $product_to_lang);

        if ($cross_sell)
            $cross_sell_ids[] = $cross_sell;
    }

    $product_to->set_cross_sell_ids($cross_sell_ids);
}


/**
 * Copies all text properties (title, description etc.) from one product to another
 *
 * @param WC_Product $product_from product to take info from
 * @param WC_Product $product_to product to set info to
 *
 * @return void
 */
function polywoo_copy_product_text_properties( WC_Product $product_from, WC_Product $product_to ) {
    $product_to->set_name($product_from->get_title());
    $product_to->set_description($product_from->get_description());
    $product_to->set_short_description($product_from->get_short_description());
    $product_to->set_purchase_note($product_from->get_purchase_note());
}

/**
 * Sets a post as a translation to another post in given language
 *
 * @param WC_Product $product_child the product to set as translation
 * @param WC_Product $product_parent the product to set translation to
 * @param string $as_lang language in wich set product translation
 *
 * @return void
 */
function polywoo_set_product_as_translation_to( WC_Product $product_child, WC_Product $product_parent, string $as_lang ) {
    pll_set_post_language($product_child->get_id(), $as_lang);

    $product_parent_translations = pll_get_post_translations($product_parent->get_id());
    $product_parent_translations[$as_lang] = $product_child->get_id();
    pll_save_post_translations( $product_parent_translations );
}