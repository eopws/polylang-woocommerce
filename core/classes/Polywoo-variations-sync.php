<?php

class Polywoo_variations_sync
{

    private $post_statuses_to_proceed = ['publish', 'trash'];

    private const VARIATION_TRANSLATION_META_KEY = '_variation_translations';

    public function __construct() {
        add_action( 'save_post_product', [$this, 'sync_variations'] );
        add_action( 'woocommerce_ajax_save_product_variations', [$this, 'sync_variations'] );

        add_action( 'wp_ajax_woocommerce_remove_variations', [$this, 'delete_variations'] );
    }

    public function sync_variations($product_id) {
        global $wpdb;

        remove_action( 'save_post_product', [$this, __FUNCTION__] );
        remove_action( 'woocommerce_ajax_save_product_variations', [$this, __FUNCTION__] );

        $product = wc_get_product($product_id);

        if ( !$product || !$product->is_type('variable') ) {
            add_action( 'save_post_product', [$this, __FUNCTION__] );
            add_action( 'woocommerce_ajax_save_product_variations', [$this, __FUNCTION__] );
            return;
        }

        $product_translations = pll_get_post_translations($product_id);

        unset($product_translations[array_search($product->get_id(), $product_translations)]);

        $product_from_variations = $product->get_children();

        foreach ( $product_translations as $product_translation_id ) {
            $product_translation = wc_get_product($product_translation_id);

            if ( !$product_translation ) continue;

            $product_translation_variations_objects = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}posts` WHERE `post_parent` = " . $product_translation->get_id());

            $product_translation_variations = [];

            foreach ( $product_translation_variations_objects as $product_translation_variations_object ) {
                $product_translation_variations[] = $product_translation_variations_object->ID;
            }

            foreach ( $product_from_variations as $product_from_variation_id ) {
                $product_from_variation_translations = $this->get_variation_translations($product_from_variation_id);

                $product_from_variation_id_translation = array_intersect($product_from_variation_translations, $product_translation_variations);

                if ( count($product_from_variation_id_translation) === 1 ) {
                    $product_from_variation_id_translation = array_shift($product_from_variation_id_translation);

                    $product_from_variation_translation = wc_get_product($product_from_variation_id_translation);
                    $product_from_variation = wc_get_product($product_from_variation_id);

                    polywoo_copy_product_meta_properties($product_from_variation, $product_from_variation_translation);

                    $this->sync_variation_attributes($product_from_variation, $product_from_variation_translation);

                    $product_from_variation_translation->save();
                } elseif ( count($product_from_variation_id_translation) === 0 ) {
                    self::create_product_variation_copy($product_translation_id, $product_from_variation_id);
                }
            }
        }

        add_action( 'save_post_product', [$this, __FUNCTION__] );
        add_action( 'woocommerce_ajax_save_product_variations', [$this, __FUNCTION__] );
    }

    /**
     * Synchronize attributes beetwen two variations with translation
     *
     * @param WC_Product|int $variation_from variation to take attributes from
     * @param WC_Product|int $variation_to variation to set attributes to
     *
     * @return void
     */
    public function sync_variation_attributes($variation_from, $variation_to) {
        $variation_from = is_int($variation_from) ? wc_get_product($variation_from) : $variation_from;
        $variation_to = is_int($variation_to) ? wc_get_product($variation_to) : $variation_to;

        $variation_from_parent = $variation_from->parent_id;
        $variation_to_parent = $variation_to->parent_id;

        $variation_to_lang = pll_get_post_language($variation_to_parent, 'slug');

        $variation_to_new_attributes = [];

        foreach ( $variation_from->get_variation_attributes(false) as $taxonomy => $term_slug ) {
            $term_id = get_term_by('slug', $term_slug, $taxonomy, 'ARRAY_A')['term_id'];

            $term_translation_slug = get_term_by('id', pll_get_term($term_id, $variation_to_lang), $taxonomy, 'ARRAY_A')['slug'];

            $variation_to_new_attributes[$taxonomy] = $term_translation_slug;
        }

        $variation_to->set_attributes($variation_to_new_attributes);
    }

    /**
     * Delete all a product variation with id from $_POST var
     *
     * @return void
     */
    public function delete_variations() {
        global $wpdb;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (!isset($_POST['variation_ids'])) return;

        $variation_ids = isset($_POST['variation_ids']) ? (array) $_POST['variation_ids'] : [];

        foreach ($variation_ids as $variation_id) {
            $variation_translations = $this->get_variation_translations($variation_id) ?? [];

            foreach ( $variation_translations as $variation_translation_id ) {
                $variation_translation_id = intval($variation_translation_id);

                $variation_translation = wc_get_product($variation_translation_id);

                if ( $variation_translation )
                    $variation_translation->delete(true);

                // delete translations info
                $wpdb->query("DELETE FROM `{$wpdb->prefix}postmeta` WHERE `post_id` = $variation_translation_id");
            }
        }
    }

    /**
     * Returns a product variation translations
     *
     * @param $variation_id
     *
     * @return array array of variations translations
     */
    public function get_variation_translations($variation_id) {
        global $wpdb;

        // make sure an integer value passed
        $variation_id = intval($variation_id);

        $meta_key = self::VARIATION_TRANSLATION_META_KEY;

        $translations = @$wpdb->get_results("SELECT * FROM `{$wpdb->prefix}postmeta` WHERE `meta_key`= '$meta_key' AND `post_id` = $variation_id")[0]->meta_value;

        return $translations ? unserialize($translations) : [];
    }

    /**
     * Creates a copy of variation and sets it to the given product
     *
     * @param WC_Product|int $product product or product id to set variation to
     * @param int $variation_to_copy_id variation to copy info from
     * @return void
     */
    public static function create_product_variation_copy( $product, int $variation_to_copy_id ) {
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

        $variation_to_copy_parent_id = wp_get_post_parent_id($variation_to_copy_id);

        $variation_lang         = pll_get_post_language($product->get_id(), 'slug');
        $variation_to_copy_lang = pll_get_post_language($variation_to_copy_parent_id, 'slug');

        $variation_to_copy_translations = get_post_meta($variation_to_copy_id, '_variation_translations', true);

        if ( !$variation_to_copy_translations ) {
            update_post_meta( $variation_to_copy_id, '_variation_translations', [
                $variation_lang => $variation->get_id()
            ] );
        } else {
            $variation_to_copy_translations[$variation_lang] = $variation->get_id();

            update_post_meta( $variation_to_copy_id, '_variation_translations', [
                $variation_lang => $variation->get_id()
            ] );
        }

        update_post_meta( $variation->get_id(), '_variation_translations', [
            $variation_to_copy_lang => $variation_to_copy_id
        ] );
    }
}