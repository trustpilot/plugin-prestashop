<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

// include_once('../../config/config.inc.php');

class TrustpilotUpdater
{
    public static function trustpilotGetPlugins($plugins)
    {
        $args = array(
            'path' => _PS_MODULE_DIR_ . '/',
            'trustpilot_preserve_zip' => false
        );

        foreach ($plugins as $plugin) {
            $source = $plugin['path'];
            $target = $args['path'].$plugin['name'].'.zip';

            Logger::addLog('Updating Trustpilot reviews plugin. Source: ' . $source . ', target: ' . $target, 1);

            if (file_exists($target)) {
                unlink($target);
            }

            self::trustpilotPluginDownload($source, $target);
            self::trustpilotPluginUnpack($args, $target);
            self::trustpilotPluginActivate($plugin['name']);
        }
    }

    private static function trustpilotPluginDownload($url, $path)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        if (file_put_contents($path, $data)) {
            return true;
        } else {
            return false;
        }
    }

    private static function trustpilotPluginUnpack($args, $target)
    {
        if ($zip = zip_open($target)) {
            while ($entry = zip_read($zip)) {
                $is_file = Tools::substr(zip_entry_name($entry), -1) == '/' ? false : true;
                $file_path = $args['path'] . zip_entry_name($entry);
                if ($is_file) {
                    if (zip_entry_open($zip, $entry, "r")) {
                        $fstream = zip_entry_read($entry, zip_entry_filesize($entry));
                        file_put_contents($file_path, $fstream);
                        chmod($file_path, 0777);
                    }

                    zip_entry_close($entry);
                } else {
                    if (zip_entry_name($entry)) {
                        if (!file_exists($file_path)) {
                            mkdir($file_path);
                        }
                        chmod($file_path, 0777);
                    }
                }
            }

            zip_close($zip);
        }
        if ($args['trustpilot_preserve_zip'] === false) {
            unlink($target);
        }
    }

    private static function trustpilotPluginActivate($installer)
    {
        Tools::clearSmartyCache();
        Tools::clearXMLCache();
        Media::clearCache();
        Tools::generateIndex();

        $trustpilot = Module::getInstanceByName($installer);
        $trustpilot->enable();
        return $trustpilot->isEnabled($installer);
    }
}
