<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

require_once(dirname(__FILE__) . '/../../../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../../../init.php');
require_once(dirname(__FILE__) . '/../../trustpilot.php');


class TrustpilotTrustpilotAjaxModuleFrontController extends ModuleFrontController
{
    private $excludedActions = array('get_category_product_info', 'reload_trustpilot_settings');

    public function postProcess()
    {
        $this->process();
    }

    public function validateToken($action, $queries) {
        $token = Tools::getAdminToken(
            $queries['controller'].
            (int)Tab::getIdFromClassName($queries['controller']).
            (int)$queries['user_id']
        );
        if (Configuration::get('PS_TOKEN_ENABLE') == 1 && !($token == $queries['token'])) {
            echo 'Invalid token!';
            die();
        }
    }

    public function process()
    {
        header('Content-Type: application/json');

        if (Tools::getIsset('settings')) {
            $settings = base64_decode(Tools::getValue('settings'));
            $queries = array();
            parse_str($settings, $queries);

            if (isset($queries["action"])) {
                $action = $queries["action"];
                if (!in_array($action, $this->excludedActions)) {
                    $this->validateToken($action, $queries);
                }
                
                switch ($action) {
                    case 'save_changes':
                        $trustpilot = new Trustpilot();
                        $result = $trustpilot->handleSaveChanges();
                        echo $result;
                        die();
                    case 'sync_past_orders':
                        $trustpilot = new Trustpilot();
                        $result = $trustpilot->sync();
                        echo $result;
                        die();
                    case 'resync_past_orders':
                        $trustpilot = new Trustpilot();
                        $result = $trustpilot->resync();
                        echo $result;
                        die();
                    case 'is_past_orders_synced':
                        $trustpilot = new Trustpilot();
                        $result = $trustpilot->getPastOrdersInfo();
                        echo $result;
                        die();
                    case 'show_past_orders_initial':
                        $trustpilot = new Trustpilot();
                        $trustpilot->showPastOrdersInitial();
                        $result = $trustpilot->getPastOrdersInfo();
                        echo $result;
                        die();
                    case 'update_trustpilot_plugin':
                        $plugins = array(
                            array(
                                'name' => 'trustpilot',
                                'path' => TrustpilotConfig::getInstance()->plugin_url,
                            )
                        );
                        TrustpilotUpdater::trustpilotGetPlugins($plugins);
                        die();
                    case 'reload_trustpilot_settings':
                        $info = new stdClass();
                        $info->pluginVersion = TrustpilotConfig::getInstance()->version;
                        $info->basis = 'plugin';
                        echo json_encode($info);
                        die();
                    case 'check_product_skus':
                        $trustpilot = new Trustpilot();
                        $result = $trustpilot->checkSkus();
                        echo $result;
                        die();
                    case 'get_category_product_info':
                        $result = new stdClass();
                        $trustpilot = new Trustpilot();
                        $result->categoryProductsData = $trustpilot->updateProductList();
                        echo json_encode($result);
                        die();
                }
            }
        }
    }
}
