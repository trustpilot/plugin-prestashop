<?php

/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 *
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('TP_PATH_ROOT')) {
    define('TP_PATH_ROOT', dirname(__FILE__));
}

define('__ORDERCONFIRMEDSTATE__', '2');
define('__TRUSTPILOTORDERCONFIRMED__', 'trustpilotOrderConfirmed');
define('__SHIPPEDSTATE__', '4');
define('__DELIVEREDSTATE__', '5');
define('__ACCEPTED__', 202);
define('__ASSETS_JS_DIR__', __PS_BASE_URI__ . 'modules/trustpilot/views/js');

include_once TP_PATH_ROOT . '/orders.php';
include_once TP_PATH_ROOT . '/config.php';
include_once TP_PATH_ROOT . '/pastOrders.php';
include_once TP_PATH_ROOT . '/trustbox.php';
include_once TP_PATH_ROOT . '/viewLoader.php';
include_once TP_PATH_ROOT . '/updater.php';
include_once TP_PATH_ROOT . '/apiClients/TrustpilotHttpClient.php';

class Trustpilot extends Module
{
    public function __construct()
    {
        $this->name                   = 'trustpilot';
        $this->tab                    = 'checkout';
        $this->author                 = 'Trustpilot';
        $this->need_instance          = 0;
        $this->ps_versions_compliancy = array(
            'min' => '1.5',
            'max' => _PS_VERSION_ > '1.7' ? _PS_VERSION_ : '1.7'
        );
        $this->bootstrap  = true;
        $this->module_key = 'd755105a2b739a94b2ba921faac5a546';

        $this->httpClient = new TrustpilotHttpClient(Trustpilot_Config::getInstance()->apiUrl);

        parent::__construct();
        $this->displayName = $this->l('Trustpilot reviews');
        $this->version = '2.50.652';
        $this->description = $this->l('The Trustpilot Review extension makes it simple and easy for merchants to collect reviews from their customers to power their marketing efforts, increase sales conversion, build their online reputation and draw business insights.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function installTab()
    {
        $this->uninstallTabs();

        $tab = new Tab();
        $tab->class_name = 'TrustpilotTab';
        $tab->active = 1;
        $tab->name = array();

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Trustpilot';
        }

        if (_PS_VERSION_ < '1.7') {
            $tab->id_parent = 0;
        } else {
            $tab->id_parent = (int)Tab::getIdFromClassName('AdminTools');
        }

        $tab->module = $this->name;
        $tab->save();

        return true;
    }

    public function hookDisplayBackOfficeHeader($params)
    {
            $this->context->controller->addCSS($this->_path . 'views/css/menuTabIcon.css');
    }

    public function uninstallTabs()
    {
        $moduleTabs = Tab::getCollectionFromModule($this->name);
        if (!empty($moduleTabs)) {
            foreach ($moduleTabs as $moduleTab) {
                $moduleTab->delete();
            }
        }
        return true;
    }

    public function handleSaveChanges()
    {
        $config = Trustpilot_Config::getInstance();
        if (Tools::getIsset('settings')) {
            $settings = base64_decode(Tools::getValue('settings'));
            $queries = array();
            parse_str($settings, $queries);

            if (isset($queries['settings'])) {
                $settings = $queries['settings'];
                $config->setConfigValues('master_settings', $settings);
                return $config->getConfigValues('master_settings');
            }
            if (isset($queries['customTrustboxes'])) {
                $customTrustboxes = $queries['customTrustboxes'];
                $config->setConfigValues('custom_trustboxes', $customTrustboxes);
                return $config->getConfigValues('custom_trustboxes');
            }
            if (isset($queries['pageUrls'])) {
                $pageUrls = $queries['pageUrls'];
                $config->setConfigValues('page_urls', $pageUrls);
                return $config->getConfigValues('page_urls');
            }
        }
    }

    public function sync()
    {
        $settings = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settings, $queries);

        $period_in_days = $queries['sync'];
        $past_orders = new Trustpilot_PastOrders($this->context);
        $past_orders->sync($period_in_days);
        $info = $past_orders->getPastOrdersInfo();
        $info['basis'] = 'plugin';
        return json_encode($info);
    }

    public function resync()
    {
        $past_orders = new Trustpilot_PastOrders($this->context);
        $past_orders->resync();
        $info = $past_orders->getPastOrdersInfo();
        $info['basis'] = 'plugin';
        return json_encode($info);
    }

    public function showPastOrdersInitial()
    {
        $settings = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settings, $queries);

        $value =  $queries['showPastOrdersInitial'];
        $config = Trustpilot_Config::getInstance();
        $config->setConfigValues('show_past_orders_initial', $value);
    }

    public function getPastOrdersInfo()
    {
        $past_orders = new Trustpilot_PastOrders($this->context);
        $info = $past_orders->getPastOrdersInfo();
        $info['basis'] = 'plugin';
        return json_encode($info);
    }

    public function enable($force_all = false) {
        parent::enable($force_all);
        $this->uninstallTabs();
        $this->installTab();
    }

    public function install()
    {
        $this->installTab();
        return parent::install() && $this->registerHooks();
    }

    public function uninstall()
    {
        $this->uninstallTabs();
        $config = Trustpilot_Config::getInstance();
        $data = array(
            'settings'   => Tools::stripslashes(json_encode($config)),
            'event'      => 'Uninstalled',
            'platform'   => 'PrestaShop'

        );
        $this->httpClient->postLog($data);

        return  $config->deleteConfigValues() &&
            parent::uninstall();
    }

    public function getContent()
    {
        $helper = new TrustpilotViewLoader($this->context);
        $this->context->smarty->assign(
            $helper->getValues()
        );

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'trustpilot/views/templates/admin/admin.tpl');
    }

    public function hookDisplayHeader($params)
    {
        if (!$this->active) {
            Logger::addLog('Trustpilot module: Skipping trustpilot script rendering. Module is not active', 2, null, null, null, true);
            return;
        }

        $config = Trustpilot_Config::getInstance();
        $trustbox = Trustpilot_Trustbox::getInstance();
        $trustbox_settings = $config->getFromMasterSettings('trustbox');
        $this->context->smarty->compile_check = true;
        $this->context->smarty->assign(
            array(
                'script_url' => $config->script_url,
                'key' =>  $config->getFromMasterSettings('general')->key,
                'widget_script_url' => $config->widget_script_url,
                'preview_script_url' => $config->preview_script_url,
                'preview_css_url' => $config->preview_css_url,
                'integration_app_url' => $this->getDomainName($config->integration_app_url),
                'trustbox_settings' => $trustbox->loadTrustboxes($trustbox_settings, $this->getLanguageId()),
                'register_js_dir' => __ASSETS_JS_DIR__ . '/tp_register.js',
                'trustbox_js_dir' => __ASSETS_JS_DIR__ . '/tp_trustbox.js',
                'preview_js_dir' => __ASSETS_JS_DIR__ . '/tp_preview.js',
            )
        );

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'trustpilot/views/templates/hook/head.tpl');
    }

    public function hookDisplayOrderConfirmation($params)
    {
        if (!$this->active) {
            Logger::addLog('Trustpilot module: Skipping invitation sending. Module is not active', 2, null, null, null, true);
            return;
        }

        if (!$this->isModuleConfigured()) {
            Logger::addLog('Trustpilot module: Skipping invitation sending. Trustpilot module is not configured.', 2, null, null, null, true);
            return;
        }

        $orders = new Trustpilot_Orders($this->context);
        $invitation = $orders->getInvitation($params, 'displayOrderConfirmation');
        $mapped_invitation_trigger = Trustpilot_Config::getInstance()->getFromMasterSettings('general')->mappedInvitationTrigger;
        if (!in_array(__TRUSTPILOTORDERCONFIRMED__, $mapped_invitation_trigger)) {
            $invitation['payloadType'] = 'OrderStatusUpdate';
        }

        $this->context->smarty->compile_check = true;
        $this->context->smarty->assign(
            array(
                'invitation' => $invitation,
                'invite_js_dir' => __ASSETS_JS_DIR__ . '/tp_invite.js',
            )
        );

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'trustpilot/views/templates/hook/confirmation.tpl');
    }

    public function hookPostUpdateOrderStatus($params)
    {
        if (!$this->active) {
            Logger::addLog('Trustpilot module: Skipping invitation sending. Module is not active', 2, null, null, null, true);
            return;
        }

        if (!$this->isModuleConfigured()) {
            Logger::addLog('Trustpilot module: Skipping invitation sending. Trustpilot module is not configured.', 2, null, null, null, true);
            return;
        }

        $config = Trustpilot_Config::getInstance();
        $key = $config->getFromMasterSettings('general')->key;
        $orders = new Trustpilot_Orders($this->context);
        $invitation = $orders->getInvitation($params, 'postUpdateOrderStatus', false);
        if (in_array((string)$params['newOrderStatus']->id, $config->getFromMasterSettings('general')->mappedInvitationTrigger)) {
            $response = $this->httpClient->postInvitation($key, $invitation);
            if ($response['code'] == __ACCEPTED__) { // request to send products & skus
                $invitation = $orders->getInvitation($params, 'postUpdateOrderStatus');
                $response = $this->httpClient->postInvitation($key, $invitation);
            }
            $orders->handleSingleResponse($response, $invitation);
        } else {
            $invitation['payloadType'] = 'OrderStatusUpdate';
            $this->httpClient->postInvitation($key, $invitation);
        }
    }

    private function registerHooks()
    {
        $hooks = array('displayOrderConfirmation', 'displayHeader', 'postUpdateOrderStatus', 'displayBackOfficeHeader');
        foreach ($hooks as $hook) {
            if (!Hook::getIdByName($hook)) {
                Logger::addLog('Trustpilot module: ' . $hook . ' hook was not found', 2, null, null, null, true);
                return false;
            }
            if (!$this->registerHook($hook)) {
                Logger::addLog('Trustpilot module: Failed to register ' . $hook . ' hook', 2, null, null, null, true);
                return false;
            }
        }
        return true;
    }

    private function isModuleConfigured()
    {
        return $this->isValidLength(Trustpilot_Config::getInstance()->getFromMasterSettings('general')->key, 64);
    }

    private function isValidLength($value, $maxlength = 254)
    {
        return !empty($value) && Tools::strlen($value) <= $maxlength;
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
