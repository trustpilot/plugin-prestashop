<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

include_once TP_PATH_ROOT . '/globals.php';

class TrustpilotConfig
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
        $this->version                = TRUSTPILOT_PLUGIN_VERSION;
        $this->plugin_url             = TRUSTPILOT_PLUGIN_URL;
        $this->apiUrl                 = TRUSTPILOT_API_URL;
        $this->script_url             = TRUSTPILOT_SCRIPT_URL;
        $this->widget_script_url      = TRUSTPILOT_WIDGET_SCRIPT_URL;
        $this->preview_script_url     = TRUSTPILOT_PREVIEW_SCRIPT_URL;
        $this->preview_css_url        = TRUSTPILOT_PREVIEW_CSS_URL;
        $this->integration_app_url    = TRUSTPILOT_INTEGRATION_APP_URL;
        $this->is_from_marketplace    = TRUSTPILOT_IS_FROM_MARKETPLACE;
        $this->trustbox_preview_url   = TRUSTPILOT_TRUSTBOX_PREVIEW_URL;
    }

    public function getConfigValues($key, $skipCache = false, $context_scope = Shop::CONTEXT_SHOP)
    {
        switch ((int)$context_scope) {
            case Shop::CONTEXT_ALL:
                $config = Configuration::getGlobalValue($this->settings_prefix . $key);
                break;
            case Shop::CONTEXT_GROUP:
                $config = Configuration::get($this->settings_prefix . $key, null, null, 0);
                break;
            default:
                $config = $skipCache ? $this->get($key) : Configuration::get($this->settings_prefix . $key);
                break;
        }
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
            } else {
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

    public function setConfigValues($key, $value, $context_scope = Shop::CONTEXT_SHOP)
    {
        switch ((int)$context_scope) {
            case Shop::CONTEXT_ALL:
                if (Configuration::updateGlobalValue($this->settings_prefix . $key, $value)) {
                    return $value;
                }
                return false;
            case Shop::CONTEXT_GROUP:
                if (Configuration::updateValue($this->settings_prefix . $key, $value, null, (int)Shop::getContextShopGroupID(), 0)) {
                    return $value;
                }
                return false;
            default:
                if (Configuration::updateValue($this->settings_prefix . $key, $value)) {
                    return $value;
                }
                return false;
        }
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
        $config['plugin_status'] = json_encode(
            array(
                'pluginStatus' => 200,
                'blockedDomains' => array(),
            )
        );

        if ($config[$key]) {
            return $config[$key];
        }
        return false;
    }

    public function getConfigurationScopeTree()
    {
        $shopTree = Shop::getTree();
        $result = array();
        foreach ($shopTree as $group) {
            foreach ($group['shops'] as $shop) {
                if ($shop['active'] == '1') {
                    $languages = Language::getLanguages(true, $shop['id_shop']);
                    foreach ($languages as $lang) {
                        $names = array(
                            'site' => $group['name'],
                            'store' => $shop['name'],
                            'view' => $lang['name'],
                        );
                        $item = array(
                            'ids' => $this->getIdsForConfigurationScope($group['id'], $shop['id_shop'], $lang['id_lang']),
                            'names' => $names,
                            'domain' => preg_replace(array('#^https?://#', '#/?$#'), '', $shop['domain']),
                        );
                        array_push($result, $item);
                    }
                }
            }
        }
        return $result;
    }

    public function getIdsForConfigurationScope($groupId, $shopId, $langId)
    {
        return array((string) $groupId, (string) $shopId, (string) $langId);
    }

    private function getShopFromContext(&$id_group_shop, &$id_shop)
    {
        if (method_exists('Shop', 'retrieveContext')) {
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
}
