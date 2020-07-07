<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

include_once TP_PATH_ROOT . '/orders.php';

class TrustpilotTrustbox
{
    protected static $instance = null;

    protected $products;

    public static function getInstance($context)
    {
        // If the single instance hasn't been set, set it now.
        if (null == self::$instance) {
            self::$instance = new self($context);
        }
        return self::$instance;
    }
    
    public function __construct($context)
    {
        $this->orders = new TrustpilotOrders($context);
    }

    public function isPage($page_name)
    {
        $controller_name = Tools::getValue('controller');
        return $controller_name == $page_name;
    }

    public function setProducts($productIds, $langId)
    {
        $this->products = $this->loadCategoryProductData($productIds, $langId);
    }

    public function loadTrustboxes($settings, $langId)
    {
        if ($settings->trustboxes) {
            $currentUrl = $this->getCurrentUrl();
            $loadedTrustboxes = $this->loadPageTrustboxes($settings, $currentUrl, $langId);

            if ($this->isPage('product')) {
                $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'product', $langId, true), (array)$loadedTrustboxes);
            }
            if ($this->isPage('category')) {
                $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'category', $langId), (array)$loadedTrustboxes);
                if ($this->repeatData($loadedTrustboxes)) {
                    $settings->categoryProductsData = $this->products;
                }
            }
            if ($this->isPage('index')) {
                $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'landing', $langId), (array)$loadedTrustboxes);
            }
            if (count($loadedTrustboxes) > 0) {
                $settings->trustboxes = $loadedTrustboxes;
                return $settings;
            }
        }
        $settings->trustboxes = array();
        return $settings;
    }

    private function repeatData($trustBoxes) {
        foreach ($trustBoxes as $trustbox) {
            if (property_exists($trustbox, 'repeat') && $trustbox->repeat) {
                return true;
            }
        }
        return false;
    }

    public function loadCategoryProductData($productIds, $langId)
    {
        $config = TrustpilotConfig::getInstance();
        $skuSelector = $config->getFromMasterSettings('skuSelector');
        $productList = array();
        foreach (json_decode($productIds) as $productId) {
            $variationSkus = $variationIds = array();
            $product = new Product($productId, false, $langId);
            
            $id = $product->id;
            $sku = $this->orders->getAttribute($product, null, 'skuSelector', $langId);
            
            $combinations = $product->getAttributeCombinations($langId);            
            if (isset($combinations)) {
                foreach ($combinations as $combination) {
                    array_push($variationIds, $combination['id_product_attribute']);
                    $sku = $skuSelector != 'none' && $skuSelector != '' && array_key_exists($skuSelector, $combination) ? $combination[$skuSelector] : '';
                    if (isset($sku) && $sku != '') {
                        array_push($variationSkus, $sku);
                    }
                }
            }
            array_push($productList, array(
                "sku" => $sku,
                "id" => $id,
                "variationIds" => $variationIds,
                "variationSkus" => $variationSkus,
                "productUrl" => $product->getLink() ?: '',
                "name" => $product->name,
            ));
        }

        return $productList;
    }

    private function loadPageTrustboxes($settings, $page, $langId, $includeSku = false)
    {
        $data = array();
        $config = TrustpilotConfig::getInstance();
        $skuSelector = $config->getFromMasterSettings('skuSelector');
        foreach ($settings->trustboxes as $trustbox) {
            if ((rtrim($trustbox->page, '/') == $page || $this->checkCustomPage($trustbox->page, $page)) && $trustbox->enabled == 'enabled') {
                if ($includeSku) {
                    $product = new Product($this->getProductId(), false, $langId);
                    $skus = array();
                    array_push($skus, TRUSTPILOT_PRODUCT_ID_PREFIX . $product->id);
                    $sku = $this->orders->getAttribute($product, null, 'skuSelector', $langId);
                    if (isset($sku) && $sku != '') {
                        array_push($skus, $sku);
                    }
                    $combinations = $product->getAttributeCombinations($langId);
                    if (isset($combinations)) {
                        foreach ($combinations as $combination) {
                            array_push($skus, TRUSTPILOT_PRODUCT_ID_PREFIX . $combination['id_product_attribute']);
                            $sku = $skuSelector != 'none' && $skuSelector != '' && array_key_exists($skuSelector, $combination) ? $combination[$skuSelector] : '';
                            if (isset($sku) && $sku != '') {
                                array_push($skus, $sku);
                            }
                        }
                    }
                    $trustbox->sku = implode(',', array_unique($skus, SORT_STRING));
                    $trustbox->name = $product->name;
                }
                array_push($data, $trustbox);
            }
        }
        return $data;
    }

    private function checkCustomPage($tbPage, $page) {
        return (
            $tbPage == strtolower(base64_encode($page . '/')) ||
            $tbPage == strtolower(base64_encode($page)) ||
            $tbPage == strtolower(base64_encode(rtrim($page, '/')))
        );
    }

    private function getProductId()
    {
        $id = Tools::getValue('id_product');
        if (isset($id)) {
            return Tools::getValue('id_product');
        }
        $settings = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settings, $queries);
        if (isset($queries['id_product'])) {
            return $queries['id_product'];
        }
    }

    private function getCurrentUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https:' : 'http:';
        return $protocol . '//' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }
}
