<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

define('PATH_ROOT', dirname(__FILE__));

include_once PATH_ROOT . "/../../viewLoader.php";

class TrustpilotTabController extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
        $this->module = 'TrustpilotTab';
        $this->lang = (!isset($this->context->cookie) || !is_object($this->context->cookie)) ?
            (int)Configuration::get('PS_LANG_DEFAULT') : (int)$this->context->cookie->id_lang;
    }

    public function renderList()
    {
        $helper = new TrustpilotViewLoader($this->context);
        $this->context->smarty->assign(
            $helper->getValues()
        );

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'trustpilot/views/templates/admin/admin.tpl');
    }
}
