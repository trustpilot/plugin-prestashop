<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

include_once TP_PATH_ROOT . '/config.php';

if (!defined('TP_PATH_ROOT')) {
    define('TP_PATH_ROOT', dirname(__FILE__));
}

class TrustpilotPluginStatus
{
    const SUCCESSFUL_STATUS = 200;

    public static function checkPluginStatus($host)
    {
        $config = TrustpilotConfig::getInstance();
        $plugin_status = json_decode($config->getConfigValues('plugin_status'));

        if ($plugin_status && in_array($host, $plugin_status->blockedDomains)) {
            return $plugin_status->pluginStatus;
        } else {
            return self::SUCCESSFUL_STATUS;
        }
    }

    public static function setPluginStatus($status, $blockedDomains)
    {
        $config = TrustpilotConfig::getInstance();
        $plugin_status = array(
            'pluginStatus' => $status,
            'blockedDomains' => $blockedDomains ?: array(),
        );
        $config->setConfigValues('plugin_status', json_encode($plugin_status));

        Logger::addLog('Trustpilot plugin status changed to ' . (($status === SUCCESSFUL_STATUS) ? 'enabled' : 'disabled'), 2);
    }
}