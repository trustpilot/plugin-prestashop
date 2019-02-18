{**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 *}
{literal}
<script type="text/javascript" data-keepinline="true">
    let trustpilot_script_url = '{/literal}{$script_url}{literal}';
    let trustpilot_key = '{/literal}{$key}{literal}';
    let trustpilot_widget_script_url = '{/literal}{$widget_script_url}{literal}';
    let trustpilot_trustbox_settings = {/literal}{$trustbox_settings|@json_encode nofilter}{literal};
    let trustpilot_integration_app_url = '{/literal}{$integration_app_url}{literal}';
    let trustpilot_preview_css_url = '{/literal}{$preview_css_url}{literal}';
    let trustpilot_preview_script_url = '{/literal}{$preview_script_url}{literal}';
</script>
<script type="text/javascript" src="{/literal}{$register_js_dir}{literal}"></script>
<script type="text/javascript" src="{/literal}{$trustbox_js_dir}{literal}"></script>
<script type="text/javascript" src="{/literal}{$preview_js_dir}{literal}"></script>
{/literal}