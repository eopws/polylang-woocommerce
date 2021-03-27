<?php

/**
 * Synchronize stock of product translations
 */
class Polywoo_stock_sync
{

	public function __construct() {
		add_action( 'woocommerce_product_set_stock', [$this, 'sync_stock'] );
		add_action( 'woocommerce_variation_set_stock', [$this, 'sync_stock'] );
	}

	/**
	 * Synchronize product stock with its translations
	 *
	 * @param WC_Product $product product to sync stock
	 *
	 * @return void
	 */
	public function sync_stock(WC_Product $product) {
		remove_action( 'woocommerce_product_set_stock', [$this, __FUNCTION__] );
		remove_action( 'woocommerce_variation_set_stock', [$this, __FUNCTION__] );

		$stock_quantity = $product->get_stock_quantity();

		$product_id_with_stock = $product->get_stock_managed_by_id();
		$product_with_stock    = $product_id_with_stock !== $product->get_id() ? wc_get_product( $product_id_with_stock ) : $product;
		$data_store            = WC_Data_Store::load( 'product' );

		$languages = pll_languages_list( ['hide_empty' => 1] );

		foreach ( $languages as $lang ) {
			$product_translation_id_with_stock = pll_get_post($product_id_with_stock, $lang);

			$product_translation_with_stock = wc_get_product($product_translation_id_with_stock);

			$product_translation_with_stock->set_manage_stock(true);

			if ( $product_translation_id_with_stock )
				$new_stock = $data_store->update_product_stock($product_translation_id_with_stock, $stock_quantity, 'set' );
		}

		add_action( 'woocommerce_product_set_stock', [$this, __FUNCTION__] );
		add_action( 'woocommerce_variation_set_stock', [$this, __FUNCTION__] );
	}
}