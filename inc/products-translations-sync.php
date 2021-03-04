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
		add_action( 'transition_post_status', [$this, 'product_created'] );
	}

	/**
	 * Synchronize simple product stock when updated with its translations
	 * @param $product_id id of the product is being updated
	 * @return void
	 */
	public function update_product_simple_translations_stock($product_id) {
		# code...
	}

	/**
	 * Does the same as the method above, but for variable product
	 * @param $product_id id of the product is being updated
	 * @return void
	 */
	public function update_product_variable_translations_stock(Type $var = null)
	{
		# code...
	}

	/**
	 * Fires whenever a new product created
	 * @param $new_status new post status
	 * @param $old_status old post status
	 */
	function product_created($new_status, $old_status, $post) {
		if (    $old_status == 'publish'
			|| $new_status != 'publish'
			|| empty($post->ID)
			|| !in_array( $post->post_type, array( 'product') )
			   ) return;

		# code...
	}
}