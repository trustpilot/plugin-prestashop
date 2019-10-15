<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

include_once TP_PATH_ROOT . '/orders.php';

class TrustpilotProducts
{
    public function __construct($context)
    {
        $this->orders = new TrustpilotOrders($context);
        $this->context = $context;
    }

    public function checkSkus($skuSelector)
    {
        $page_id = 0;
        $productObj = new Product();
        $id_lang = (int)$this->context->language->id;
        $products = $productObj->getProducts($id_lang, $page_id, 20, 'id_product', 'DESC');
        $productsWithoutSku = array();
        $page_id = $page_id + 1;

        while (count($products) > 0) {
            set_time_limit(30);
            foreach ($products as $p) {
                $product = new Product((int)$p['id_product'], true, $id_lang);
                $combinations = (isset($p['product_attribute_id']))
                    ? $product->getAttributeCombinationsById((int)$p['product_attribute_id'], $id_lang)
                    : array();
                $combination = count($combinations) > 0 ? (object) $combinations[0] : null;
                $sku = $this->orders->getAttribute($product, $combination, $skuSelector, $id_lang, false);
                if (empty($sku)) {
                    $item = array();
                    $item['id'] = $product->reference;
                    $item['name'] = $product->name;
                    $item['productFrontendUrl'] = $product->getLink();
                    array_push($productsWithoutSku, $item);
                }
                $products = $productObj->getProducts($id_lang, $page_id, 20, 'id_product', 'DESC');
                $page_id = $page_id + 1;
            }
        }
        
        return $productsWithoutSku;
    }
}
