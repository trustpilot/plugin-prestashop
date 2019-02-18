<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

class Trustpilot_Config
{
    protected static $instance = null;

    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->settings_prefix        = 'tp_';
        $this->version                = '2.50.652';
        $this->plugin_url             = 'https://ecommplugins-pluginrepo.trustpilot.com/prestashop/trustpilot.zip';
        $this->apiUrl                 = 'https://invitejs.trustpilot.com/api/';
        $this->script_url             = 'https://invitejs.trustpilot.com/tp.min.js';
        $this->widget_script_url      = '//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js';
        $this->preview_script_url     = '//ecommplugins-scripts.trustpilot.com/v2.1/js/preview.js';
        $this->preview_css_url        = '//ecommplugins-scripts.trustpilot.com/v2.1/css/preview.css';
        $this->integration_app_url    = '//ecommscript-integrationapp.trustpilot.com';
        $this->is_from_marketplace    = 'false';
        $this->trustbox_preview_url   = '//ecommplugins-trustboxpreview.trustpilot.com/v1.0/trustboxpreview.js';
      }

    public function getConfigValues($key, $skipCache = false)
    {
        $config = $skipCache ? $this->get($key) : Configuration::get($this->settings_prefix . $key);
        return $config ? $config : $this->getDefaultConfigValues($key);
    }

    public function get($key, $idShop = null, $idShopGroup = null)
    {
        $sql = '
        SELECT IFNULL(c.`value`, c.`value`) AS value
        FROM `'._DB_PREFIX_.'configuration` c
        WHERE `name` = \''.pSQL($this->settings_prefix . $key).'\'';

        if ($idShop === null || method_exists('Shop', 'isFeatureActive') && !Shop::isFeatureActive()) {
            if (method_exists('Shop', 'getContextShopID') && method_exists('Shop', 'getContextShopGroupID')) {
                $idShop = Shop::getContextShopID(true);
                $idShopGroup = Shop::getContextShopGroupID(true);
            } else if (method_exists('Shop', 'retrieveContext')) {
                $this->getShopFromContext($idShopGroup, $idShop);
            }

            if (!is_null($idShop)) {
                $sql = $sql . ' AND `id_shop` =' . pSQL($idShop);
            }

            if (!is_null($idShopGroup)) {
                $sql = $sql . ' AND `id_shop_group` = '. pSQL($idShopGroup);
            }
        }

        $result = Db::getInstance()->GetRow($sql);
        return $result ? $result['value'] : false;
    }

    public function setConfigValues($key, $value)
    {
        if (Configuration::updateValue($this->settings_prefix . $key, $value)) {
            return $value;
        }
        return false;
    }

    public function getFromMasterSettings($key)
    {
        $config = json_decode($this->getConfigValues('master_settings'));
        return $config->{$key};
    }

    public function deleteConfigValues()
    {
        Configuration::deleteByName($this->settings_prefix . 'master_settings');
        Configuration::deleteByName($this->settings_prefix . 'sync_in_progress');
        Configuration::deleteByName($this->settings_prefix . 'show_past_orders_initial');
        Configuration::deleteByName($this->settings_prefix . 'past_orders');
        Configuration::deleteByName($this->settings_prefix . 'failed_orders');
        Configuration::deleteByName($this->settings_prefix . 'custom_trustboxes');
        Configuration::deleteByName($this->settings_prefix . 'page_urls');
        return true;
    }

    public function getDefaultConfigValues($key)
    {
        $config = array();
        $config['master_settings'] = json_encode(
            array(
                'general' => array(
                    'key' => '',
                    'invitationTrigger' => 'orderConfirmed',
                    'mappedInvitationTrigger' => array(__ORDERCONFIRMEDSTATE__),
                ),
                'trustbox' => array(
                    'trustboxes' => array(),
                ),
                'skuSelector' => 'reference',
                'mpnSelector' => 'none',
                'gtinSelector' => 'none',
                'pastOrderStatuses' => array(2, 4, 5),
            )
        );
        $config['sync_in_progress'] = 'false';
        $config['show_past_orders_initial'] = 'true';
        $config['past_orders'] = '0';
        $config['failed_orders'] = '{}';
        $config['custom_trustboxes'] = '{}';
        $config['page_urls'] = '[]';

        if ($config[$key]) {
            return $config[$key];
        }
        return false;
    }

    private function getShopFromContext(&$id_group_shop, &$id_shop)
    {
        list($shopID, $shopGroupID) = Shop::retrieveContext();
        if (is_null($id_shop)) {
            $id_shop = $shopID;
        }

        if (is_null($id_group_shop)) {
            $id_group_shop = $shopGroupID;
        }

        $id_shop = (int)$id_shop;
        $id_group_shop = (int)$id_group_shop;
    }

}
