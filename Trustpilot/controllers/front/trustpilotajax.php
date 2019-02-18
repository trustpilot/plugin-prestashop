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


class trustpilottrustpilotajaxModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $this->process();
    }

    public function process()
    {
        if (Tools::getIsset('settings')) {
            $settings = base64_decode(Tools::getValue('settings'));
            $queries = array();
            parse_str($settings, $queries);

            if (isset($queries["action"])) {
                $action = $queries["action"];
                if ($action !== 'reload_trustpilot_settings') {
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

                switch($action) {
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
                                'path' => Trustpilot_Config::getInstance()->plugin_url,
                            )
                        );
                        Trustpilot_Updater::trustpilotGetPlugins($plugins);
                        die();
                    case 'reload_trustpilot_settings':
                        $info = new stdClass();
                        $info->pluginVersion = Trustpilot_Config::getInstance()->version;
                        $info->basis = 'plugin';
                        echo json_encode($info);
                        die();
                }
            }
        }
    }
}