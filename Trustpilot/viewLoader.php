<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

if (!defined('TP_PATH_ROOT')) {
    define('TP_PATH_ROOT', dirname(__FILE__));
}

include_once TP_PATH_ROOT . '/config.php';
include_once TP_PATH_ROOT . '/pastOrders.php';

class TrustpilotViewLoader
{
    public function __construct($context = null)
    {
        $this->context = $context;
    }

    public function getValues()
    {
        $config = Trustpilot_Config::getInstance();
        $data_past_orders = $this->getPastOrdersInfo();
        $page_urls = $this->getPageUrls();
        $custom_trustboxes = $this->getCustomTrustBoxes();
        return array(
                'module' => array(
                    'class' => get_class($this),
                    'name' => 'trustpilot',
                    'displayName' => 'Trustpilot reviews'
                ),
                'version' => _PS_VERSION_,
                'plugin_version' => $config->version,
                'settings' => base64_encode($config->getConfigValues('master_settings')),
                'sku' => $this->getProductSku(),
                'name' => $this->getProductName(),
                'data_past_orders' => $data_past_orders,
                'integration_app_url' => $this->getDomainName($config->integration_app_url),
                'page_urls' => $page_urls,
                'custom_trustboxes' => $custom_trustboxes,
                'admin_js_dir' => __ASSETS_JS_DIR__ . '/tp_admin.js',
                'product_identification_options' => json_encode(array('none', 'reference', 'ean13', 'upc', 'isbn')),
                'is_from_marketplace' => $config->is_from_marketplace,
                'user_id' => (int)$this->context->employee->id,
                'starting_url' => $this->context->link->getPageLink('index', true),
                'trustbox_preview_url' => $config->trustbox_preview_url,
                'ajax_url' => $this->context->link->getModuleLink('trustpilot', 'trustpilotajax'),
            );
    }

    public function getPastOrdersInfo()
    {
        $past_orders = new Trustpilot_PastOrders($this->context);
        $info = $past_orders->getPastOrdersInfo();
        $info['basis'] = 'plugin';
        return json_encode($info);
    }

    public function getPageUrls()
    {
        $lang = $this->getLanguageId();

        $categories = Category::getHomeCategories($lang);
        $firstCategory = $categories[0];

        $category_id = $firstCategory['id_category'];
        $category = new Category($category_id);

        $product = $this->getFirstProduct();
        if (_PS_VERSION_ >  '1.7') {
            $firstAttributeCombination = count($product->getAttributeCombinations()) > 0
                ? (object) $product->getAttributeCombinations()[0]
                : null;
            $ipa = $firstAttributeCombination ? $firstAttributeCombination->id_product_attribute : 0;
            $link = new Link();
            $productUrl = $link->getProductLink($product, null, null, null, null, null, $ipa);
        } else {
            $productUrl = $product->getLink();
        }
        $urls =  array(
            'landing' => $this->context->link->getPageLink('index', true),
            'category' => $category->getLink(),
            'product' => $productUrl,
        );
        $customPageUrls = json_decode(Trustpilot_Config::getInstance()->getConfigValues('page_urls'));
        $urls = (object) array_merge((array) $customPageUrls, (array) $urls);
        return base64_encode(json_encode($urls));
    }

    public function getCustomTrustBoxes()
    {
        $config = Trustpilot_Config::getInstance();
        $custom_trustboxes = $config->getConfigValues('custom_trustboxes');
        if ($custom_trustboxes) {
            return $custom_trustboxes;
        }
        return "";
    }

    public function getFirstProduct()
    {
        $lang = $this->getLanguageId();
        $products = Product::getProducts($lang, 1, 1, 'id_product', 'ASC', false, true);
        $firstProduct = $products[0];
        $product_id = $firstProduct['id_product'];
        $product = new Product($product_id, false, $lang);
        return $product;
    }

    public function getProductSku()
    {
        $product = $this->getFirstProduct();
        $config = Trustpilot_Config::getInstance();
        $skuSelector = $config->getFromMasterSettings('skuSelector');
        if (!empty($product) && $skuSelector != 'none' && $skuSelector != '') {
            $sku = $product->{$skuSelector};
            return $sku;
        } else {
            return '';
        }
    }

    public function getProductName()
    {
        $product = $this->getFirstProduct();
        if (!empty($product)) {
            return $product->name;
        } else {
            return '';
        }
    }

    private function getDomainName($base_url)
    {
        $protocol = (!empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off' ||
            $_SERVER['SERVER_PORT'] == 443) ||
            Tools::usingSecureMode() ? "https:" : "http:";
        $domainName = $protocol . $base_url;
        return $domainName;
    }

    private function getLanguageId()
    {
        if (!empty($this->context->language) && !empty($this->context->language->id)) {
            return $this->context->language->id;
        } else {
            return $this->context->cookie->id_lang;
        }
    }

}
