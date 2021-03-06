<?php

/**
 * Synchronize product translations
 */
class Polywoo_products_sync {

	/**
	 * set event listeners (hooks)
	 * @return void
	 */
	public function __construct()
	{
		// On processed update product stock event
		add_action( 'woocommerce_product_set_stock', [$this, 'update_product_simple_translations_stock'] );
		add_action( 'woocommerce_variation_set_stock', [$this, 'update_product_variable_translations_stock'] );

		// on new product created
		//add_action( 'transition_post_status', [$this, 'product_created'] );
	}

	/**
	 * Synchronize simple product stock when updated with its translations
	 * @param $the_product the product is being updated
	 * @return void
	 */
	public function update_product_simple_translations_stock($the_product)
	{
		if ( !$the_product->get_manage_stock() ) return;

		$product_translations = pll_get_post_translations( $the_product->get_id() );

		$the_product_stock_qty = $the_product->get_stock_quantity();

		remove_action( 'woocommerce_product_set_stock', [$this, __FUNCTION__] );

		foreach ($product_translations as $product_translation) {
			wc_update_product_stock( $product_translation, $the_product_stock_qty );
		}

		add_action( 'woocommerce_product_set_stock', [$this, __FUNCTION__] );
	}

	/**
	 * Does the same as the method above, but for variable product
	 * @param $the_product the product is being updated
	 * @return void
	 */
	public function update_product_variable_translations_stock($the_product)
	{
		# code...
	}

	/**
	 * Fires whenever a new product created
	 * @param $new_status new post status
	 * @param $old_status old post status
	 */
	function product_created($new_status, $old_status, $post)
	{
		if (   $old_status == 'publish'
			|| $new_status != 'publish'
			|| empty($post->ID)
			|| !in_array( $post->post_type, array( 'product') )
			   ) return;

		# code...
	}
}