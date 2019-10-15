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
include_once TP_PATH_ROOT . '/products.php';
include_once TP_PATH_ROOT . '/trustbox.php';
include_once TP_PATH_ROOT . '/viewLoader.php';
include_once TP_PATH_ROOT . '/updater.php';
include_once TP_PATH_ROOT . '/apiClients/TrustpilotHttpClient.php';
include_once TP_PATH_ROOT . '/pluginStatus.php';

class Trustpilot extends Module
{
    private $trustpilotPluginStatus;

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

        $this->httpClient = new TrustpilotHttpClient(TrustpilotConfig::getInstance()->apiUrl);
        $this->trustpilotPluginStatus = new TrustpilotPluginStatus();

        parent::__construct();
        $this->displayName = $this->l('Trustpilot reviews');
        $this->version = '2.50.762';
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
        $config = TrustpilotConfig::getInstance();
        if (Tools::getIsset('settings')) {
            $settings = base64_decode(Tools::getValue('settings'));
            $queries = array();
            parse_str($settings, $queries);

            if (isset($queries['settings'])) {
                $settings = $queries['settings'];
                $config->setConfigValues('master_settings', $settings, $queries['context_scope']);
                return $config->getConfigValues('master_settings');
            }
            if (isset($queries['customTrustboxes'])) {
                $customTrustboxes = $queries['customTrustboxes'];
                $config->setConfigValues('custom_trustboxes', $customTrustboxes, $queries['context_scope']);
                return $config->getConfigValues('custom_trustboxes');
            }
            if (isset($queries['pageUrls'])) {
                $pageUrls = $queries['pageUrls'];
                $config->setConfigValues('page_urls', $pageUrls, $queries['context_scope']);
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
        $past_orders = new TrustpilotPastOrders($this->context, $queries['context_scope']);
        $past_orders->sync($period_in_days);
        $info = $past_orders->getPastOrdersInfo();
        $info['basis'] = 'plugin';
        return json_encode($info);
    }

    public function checkSkus()
    {
        $settings = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settings, $queries);

        $skuSelector = $queries['skuSelector'];
        $products = new TrustpilotProducts($this->context);
        $result = array(
            'skuScannerResults' => $products->checkSkus($skuSelector)
        );
        return json_encode($result);
    }

    public function resync()
    {
        $settings = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settings, $queries);

        $past_orders = new TrustpilotPastOrders($this->context, $queries['context_scope']);
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
        $config = TrustpilotConfig::getInstance();
        $config->setConfigValues('show_past_orders_initial', $value);
    }

    public function getPastOrdersInfo()
    {
        $settings = base64_decode(Tools::getValue('settings'));
        $queries = array();
        parse_str($settings, $queries);

        $past_orders = new TrustpilotPastOrders($this->context, $queries['context_scope']);
        $info = $past_orders->getPastOrdersInfo();
        $info['basis'] = 'plugin';
        return json_encode($info);
    }

    public function enable($force_all = false)
    {
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
        $config = TrustpilotConfig::getInstance();
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
            Logger::addLog('Trustpilot module: Skipping trustpilot script rendering. Module is not active', 2);
            return;
        }

        $config = TrustpilotConfig::getInstance();
        $trustbox = TrustpilotTrustbox::getInstance($this->context);
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
            Logger::addLog('Trustpilot module: Skipping invitation sending. Module is not active', 2);
            return;
        }

        if (!$this->isModuleConfigured()) {
            Logger::addLog('Trustpilot module: Skipping invitation sending. Trustpilot module is not configured.', 2);
            return;
        }

        $host = parse_url(_PS_BASE_URL_, PHP_URL_HOST);
        $code = $this->trustpilotPluginStatus->checkPluginStatus($host);
        if ($code > 250 && $code < 254) {
            Logger::addLog('Trustpilot module: Skipping invitation sending from confirmation page. Trustpilot module is disabled.');
            return;
        }

        $orders = new TrustpilotOrders($this->context);
        $invitation = $orders->getInvitation($params, 'displayOrderConfirmation');
        $mapped_invitation_trigger = TrustpilotConfig::getInstance()->getFromMasterSettings('general')->mappedInvitationTrigger;
        if (!in_array(__TRUSTPILOTORDERCONFIRMED__, $mapped_invitation_trigger)) {
            $invitation['payloadType'] = 'OrderStatusUpdate';
        }

        try {
            $order = isset($params['order']) ? $params['order'] : $params['objOrder'];
            $currency = new CurrencyCore($order->id_currency);
            $invitation['totalCost'] = $order->total_paid;
            $invitation['currency'] = $currency->iso_code;
        } catch (\Throwable $e) {
            $message = 'Unable to get order total cost';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 2);
            $this->logError($e, $message);
        } catch (\Exception $e) {
            $message = 'Unable to get order total cost';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 2);
            $this->logError($e, $message);
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

    // Let's delete this hook (after checking in prod) if hookActionOrderHistoryAddAfter will cover all invitations plus missing ones
    public function hookPostUpdateOrderStatus($params)
    {
        $this->sendBackendInvitation($params, 'postUpdateOrderStatus');
    }
    
    public function hookActionOrderHistoryAddAfter($params)
    {
        $newOrderStatus = new OrderState((int) $params['order_history']->id_order_state, (int) $params['cart']->id_lang);
        $transformedParams = array('newOrderStatus' => $newOrderStatus, 'id_order' => $params['order_history']->id_order);

        $this->sendBackendInvitation($transformedParams, 'actionOrderHistoryAddAfter');
    }
    
    public function sendBackendInvitation($params, $hook)
    {
        try {
            if (!$this->active) {
                Logger::addLog('Trustpilot module: Skipping invitation sending. Module is not active', 2);
                return;
            }

            if (!$this->isModuleConfigured()) {
                Logger::addLog('Trustpilot module: Skipping invitation sending. Trustpilot module is not configured.', 2);
                return;
            }

            $host = parse_url(_PS_BASE_URL_, PHP_URL_HOST);
            $code = $this->trustpilotPluginStatus->checkPluginStatus($host);
            if ($code > 250 && $code < 254) {
                Logger::addLog('Trustpilot module: Skipping invitation sending from backend. Trustpilot module is disabled.');
                return;
            }

            $config = TrustpilotConfig::getInstance();
            $key = $config->getFromMasterSettings('general')->key;
            $orders = new TrustpilotOrders($this->context);
            $invitation = $orders->getInvitation($params, $hook, false);
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
        } catch (\Throwable $e) {
            $message = 'Trustpilot module: Unable to process order status change event';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 3);
            $this->logError($e, $message);
        } catch (\Exception $e) {
            $message = 'Trustpilot module: Unable to process order status change event';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 3);
            $this->logError($e, $message);
        }
    }

    private function registerHooks()
    {
        $hooks = array('displayOrderConfirmation', 'displayHeader', 'postUpdateOrderStatus', 'displayBackOfficeHeader');
        foreach ($hooks as $hook) {
            if (!Hook::getIdByName($hook)) {
                Logger::addLog('Trustpilot module: ' . $hook . ' hook was not found', 2);
                return false;
            }
            if (!$this->registerHook($hook)) {
                Logger::addLog('Trustpilot module: Failed to register ' . $hook . ' hook', 2);
                return false;
            }
        }

        // This hook is not listed in hooks table, therefore we just register it without a check
        if (!$this->registerHook('actionOrderHistoryAddAfter')) {
            Logger::addLog('Trustpilot module: Failed to register actionOrderHistoryAddAfter hook', 2);
        }

        return true;
    }

    private function isModuleConfigured()
    {
        return $this->isValidLength(TrustpilotConfig::getInstance()->getFromMasterSettings('general')->key, 64);
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

    public function logError($e, $description, $optional = array()) {
        try {
            $log = array(
                'error' => $e->getMessage(),
                'description' => $description,
                'platform' => 'PrestaShop',
                'version' => $this->version,
                'method' => $this->getMethodName($e),
                'trace' => $e->getTraceAsString(),
                'variables' => $optional
            );
            $this->httpClient->postLog($log);
        } catch (\Throwable $e) {
            $exception = 'Trustpilot module: Unable to log error. Error: ' . $e->getMessage();
            Logger::addLog($exception, 3);
        } catch (\Exception $e) {
            $exception = 'Trustpilot module: Unable to log error. Error: ' . $e->getMessage();
            Logger::addLog($exception, 3);
        }
    }

    private function getMethodName($e) {
        $trace = $e->getTrace();
        if (array_key_exists(0, $trace)) {
            $firstNode = $trace[0];
            if (array_key_exists('function', $firstNode)) {
                return $firstNode['function'];
            }
        }
        return '';
    }
}
