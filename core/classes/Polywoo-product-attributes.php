<?php

/**
 * Handle attributes translations
 */
class Polywoo_product_attributes
{

    public function __construct() {
        add_action('woocommerce_register_taxonomy', [$this, 'make_attributes_translatable']);
    }

    /**
     * Allow attributes to have language
     *
     * @return void
     */
    public function make_attributes_translatable() {
        $attribute_taxonomies  = wc_get_attribute_taxonomies();

        if ($attribute_taxonomies) {
            foreach ( $attribute_taxonomies as $tax ) {
                $name = wc_attribute_taxonomy_name( $tax->attribute_name );

                add_filter("woocommerce_taxonomy_args_{$name}", function ($data) {
                    $data['public']             = true;
                    $data['publicly_queryable'] = false;
                    return $data;
                });
            }
        }
    }

    /**
     * Sets attributes to a product
     *
     * @param WC_Product $product_from product which from take attributes
     * @param WC_Product $product_to product to set attributs to
     *
     * @return void
     */
    public static function set_attributes( WC_Product $product_from, WC_Product $product_to ) {
        $product_from_attrs = $product_from->get_attributes();

        $product_to_attrs = [];
        $product_to_default_attrs = [];

        $product_from_lang = pll_get_post_language($product_from->get_id(), 'slug');
        $product_to_lang = pll_get_post_language($product_to->get_id(), 'slug');

        $product_from_default_attrs = $product_from->get_default_attributes();

        foreach ( $product_from_attrs as $taxonomy => $product_from_attr ) {
            if ( $product_from_default_attrs[$taxonomy] ) {
                $term_id = get_term_by('slug', $product_from_default_attrs[$taxonomy], $taxonomy, 'ARRAY_A')['term_id'];
                $term_translation_id = pll_get_term($term_id, $product_to_lang);

                $product_to_default_attrs[$taxonomy] = get_term_by('id', $term_translation_id, $taxonomy, 'ARRAY_A')['slug'];
            }

            if ( !pll_is_translated_taxonomy($taxonomy) ) {
                $product_to_attrs[$taxonomy] = $product_from_attr;
                continue;
            }

            $new_attribute = new WC_Product_Attribute;

            $new_attribute->set_id($product_from_attr->get_id());
            $new_attribute->set_name($product_from_attr->get_name());
            $new_attribute->set_position($product_from_attr->get_position());
            $new_attribute->set_variation($product_from_attr->get_variation());
            $new_attribute->set_visible($product_from_attr->get_visible());

            $translated_options = [];

            foreach ( $product_from_attr->get_options() as $term_id ) {
                $translated_options[] = pll_get_term($term_id, $product_to_lang);
            }

            $new_attribute->set_options($translated_options);

            $product_to_attrs[$taxonomy] = $new_attribute;
        }

        update_post_meta( $product_to->get_id(), '_default_attributes', $product_to_default_attrs );

        $product_to->set_attributes($product_to_attrs);
    }
}