<?php

/**
 * Handle product translations
 */
class Polywoo_products_sync
{

    public function __construct() {
        add_action( 'save_post_product', [$this, 'on_save_product'], 1);

        add_action( 'woocommerce_update_product', [$this, 'on_product_updating'], 1 );

        add_filter( 'pll_get_post_types', [$this, 'make_products_translatable'] );

        //add_filter( 'woocommerce_product_data_tabs', [$this, 'remove_product_tabs'], 150 );
    }

    /**
     * Hide tabs on product edit page to prevent product translations missynchronization
     *
     * @param array $tabs
     *
     * @return array
     */
    public function remove_product_tabs($tabs) {
        global $post;

        // if editing first product of all product translation don't remove tabs
        if (
            ( (int) min( pll_get_post_translations($post->ID) ) === (int) $post->ID ) &&
            ( strpos( $_SERVER['REQUEST_URI'], 'from_post' ) === false )
        )
            return $tabs;

        // if creating product translation show only advanced tab
        if ( strpos( $_SERVER['REQUEST_URI'], 'from_post' ) !== false )
            return [ $tabs['advanced'] ];

        return [ $tabs['shipping'], $tabs['advanced'] ];
    }

    /**
     * Add product and product_variation types to list of translatable post types
     *
     * @param array $types list of translatable post types
     * 
     * @return array
     */
    public function make_products_translatable(array $types) {
        $options = get_option('polylang');
        $postTypes = $options['post_types'];

        if (!in_array('product', $postTypes)) {
            $options['post_types'][] = 'product';
            update_option('polylang', $options);
        }

        if ( !in_array( 'product_variation', $postTypes ) ) {
            $options['post_types'][] = 'product_variation';
            update_option('polylang', $options);
        }

        $types[] = 'product';
        $types[] = 'product_variation';

        return $types;
    }

    /**
     * Fires whenever a variable product created to handle its translations
     *
     * @param int $product_id id of the product being created or updated
     *
     * @return void
     */
    public function on_save_product( int $product_id ) {
        if ( get_post_status($product_id) !== 'publish' ) return;

        $product = wc_get_product($product_id);

        if ( !$product ) return;

        $this->product_translations = pll_get_post_translations($product_id);

        if ( strpos( wp_get_raw_referer(), 'post-new' ) > 0 )
            $this->on_translation_creation($product);
    }

    /**
     * Called when creating a product translation to handle its translation
     *
     * @param WC_Product|int $old_product the product or the product id
     *
     * @return void
     */
    private function on_translation_creation( $old_product ) {
        $old_product = is_int($old_product) ? wc_get_product($old_product) : $old_product;

        // creating a new product object to prevent different product types

        // used to get initial product type
        $first_product = wc_get_product(min($this->product_translations));

        if ( $first_product->get_type() === 'variable' )
            $product = new WC_Product_Variable();
        elseif ( $first_product->get_type() === 'simple' )
            $product = new WC_Product_Simple();
        else
            return;

        polywoo_copy_product_text_properties($old_product, $product);

        $align_by_product_id = min($this->product_translations);
        $align_by_product = wc_get_product($align_by_product_id);

        // copy all info (prices, sku etc.) from old product to new
        polywoo_copy_product_meta_properties($align_by_product, $product);

        // now set tags
        $product->set_tag_ids($old_product->get_tag_ids());

        // save here to set id
        $product->save();

        // now set translations
        $old_product_lang = pll_get_post_language($old_product->get_id(), 'slug');
        polywoo_set_product_as_translation_to($product, $old_product, $old_product_lang);

        Polywoo_product_attributes::set_attributes($align_by_product, $product);

        // save one more time to save attributes
        $product->save();

        if ( $first_product->get_type() === 'variable' )
            $this->set_variations($align_by_product, $product);

        // delete product duplicate
        polywoo_delete_product($old_product, true);
    }

    /**
     * Fires whenever product or product variation is being updated
     *
     * @param WC_Product|int $product
     *
     * @return void
     */
    public function on_product_updating($product) {
        $product = is_int($product) ? wc_get_product($product) : $product;

        $this->product_translations = pll_get_post_translations($product->get_id());

        // handle only product parent updating
        if ( min($this->product_translations) !== $product->get_id() ) return;

        $product_translation_ids = $this->product_translations;

        // iterate only over the product translations excluding the first product itself
        unset($product_translation_ids[array_search($product->get_id(), $product_translation_ids)]);

        foreach ( $product_translation_ids as $product_translation_id ) {
            $product_translation_object = wc_get_product($product_translation_id);

            if ( !$product_translation_object ) continue;

            polywoo_copy_product_meta_properties($product, $product_translation_object);

            $product_translation_object->save();
        }
    }

    /**
     * Set variations to a product and update translations fields
     *
     * @param WC_Product $product_from product which from take variations
     * @param WC_Product $product_to product to set variations to
     */
    private function set_variations( WC_Product $product_from, WC_Product $product_to ) {
        $variation_to_copy_ids = $product_from->get_children();

        foreach ( $variation_to_copy_ids as $variation_to_copy_id ) {
            Polywoo_variations_sync::create_product_variation_copy($product_to, $variation_to_copy_id);
        }
    }
}