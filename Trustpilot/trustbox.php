<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

class Trustpilot_Trustbox
{
    protected static $instance = null;

    public static function getInstance()
    {
        // If the single instance hasn't been set, set it now.
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function isPage($page_name)
    {
        $controller_name = Tools::getValue('controller');
        return $controller_name == $page_name;
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
            }
            if ($this->isPage('index')) {
                $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'landing', $langId), (array)$loadedTrustboxes);
            }
            if (count($loadedTrustboxes) > 0) {
                $settings->trustboxes = $loadedTrustboxes;
                return $settings;
            }
        }
    }

    private function loadPageTrustboxes($settings, $page, $langId, $includeSku = false)
    {
        $data = array();
        $config = Trustpilot_Config::getInstance();
        $skuSelector = $config->getFromMasterSettings('skuSelector');
        $settingsValue = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settingsValue, $queries);

        foreach ($settings->trustboxes as $trustbox) {
            if (rtrim($trustbox->page, '/') == $page && $trustbox->enabled == 'enabled') {
                if ($includeSku) {
                    $product = new Product($queries['id_product'], false, $langId);
                    $trustbox->sku = $skuSelector != 'none' && $skuSelector != '' ? $product->{$skuSelector} : '';
                    $trustbox->name = $product->name;
                }
                array_push($data, $trustbox);
            }
        }
        return $data;
    }

    private function getCurrentUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https:' : 'http:';
        return $protocol . '//' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }
}
