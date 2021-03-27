<?php

/**
 * Handle single variation translations
 */
class Polywoo_products_sync
{

    public function __construct() {
        add_action( 'save_post_product', [$this, 'on_save_product'], 150);

        add_action( 'woocommerce_update_product', [$this, 'on_product_updating'] );

        add_filter( 'pll_get_post_types', [$this, 'make_products_translatable'] );
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

        return $types;
    }

    /**
     * Fires whenever a variable product created to handle its translations
     *
     * @param int $product_id id of the product being created or updated
     * @return void
     */
    public function on_save_product( int $product_id ) {
        if ( get_post_status($product_id) !== 'publish' ) return;

        $product = wc_get_product( $product_id );

        if ( !$product ) return;

        $this->product_translations = pll_get_post_translations($product_id);

        if ( strpos( wp_get_raw_referer(), 'post-new' ) > 0 )
            $this->on_translation_creation($product);
    }

    /**
     * Called when creating a product translation to handle its translation
     *
     * @param WC_Product|int $old_product the product or the product id
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
     * Set variations to a product and update translations fields
     *
     * @param WC_Product $product_from product which from take variations
     * @param WC_Product $product_to product to set variations to
     *
     * @return void
     */
    private function set_variations( WC_Product $product_from, WC_Product $product_to ) {
        $variation_to_copy_ids = $product_from->get_children();

        foreach ( $variation_to_copy_ids as $variation_to_copy_id ) {
            $this->create_product_variation_copy($product_to, $variation_to_copy_id);
        }
    }

    /**
     * Creates a copy of variation and sets it to the given product
     *
     * @param WC_Product|int $product product or product id to set variation to
     * @param int $variation_to_copy_id variation to copy info from
     *
     * @return void
     */
    private function create_product_variation_copy( $product, int $variation_to_copy_id ) {
        $product = is_int($product) ? wc_get_product($product) : $product;
        $product_id = $product->get_id();

        $product_variation = array(
            'post_title'  => $product->get_name(),
            'post_name'   => 'product-'.$product_id.'-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'guid'        => $product->get_permalink()
        );

        $variation_id = wp_insert_post($product_variation);

        $variation = new WC_Product_Variation($variation_id);

        $variation_to_copy = wc_get_product($variation_to_copy_id);

        foreach ( $variation_to_copy->get_variation_attributes(false) as $taxonomy => $term_slug ) {
            $attribute = substr($taxonomy, 3);

            // get term translation slug
            if ( pll_is_translated_taxonomy($taxonomy) ) {
                $product_lang = pll_get_post_language($product_id, 'slug');

                $term_id = get_term_by('slug', $term_slug, $taxonomy, 'ARRAY_A')['term_id'];
                $term_translation_id = pll_get_term($term_id, $product_lang);
                $term_slug = get_term_by('id', $term_translation_id, $taxonomy, 'ARRAY_A')['slug'];
            }

            // If taxonomy doesn't exists we create it
            if( !taxonomy_exists($taxonomy) ) {
                register_taxonomy(
                    $taxonomy,
                   'product_variation',
                    array(
                        'hierarchical' => false,
                        'label' => ucfirst( $attribute ),
                        'query_var' => true,
                        'rewrite' => array( 'slug' => sanitize_title($attribute) ), // The base slug
                    ),
                );
            }

            if ( !term_exists($term_slug, $taxonomy) )
                wp_insert_term($term_slug, $taxonomy);

            $term_name = get_term_by('slug', $term_slug, $taxonomy, 'ARRAY_A')['name'];

            $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );

            if( !in_array($term_name, $post_term_names) )
                wp_set_post_terms($product_id, $term_name, $taxonomy, true);

            update_post_meta($variation_id, 'attribute_'.$taxonomy, $term_slug);
        }

        ## Set/save all other data

        polywoo_copy_product_meta_properties($variation_to_copy, $variation);

        $variation->save();

        $lang = pll_get_post_language($product->get_id(), 'slug');

        polywoo_set_product_as_translation_to($variation, $variation_to_copy, $lang);
    }
}