{**
* Trustpilot Module
*
*  @author    Trustpilot
*  @copyright Trustpilot
*  @license   https://opensource.org/licenses/OSL-3.0
*}
{literal}<div>
    <script type="text/javascript" data-keepinline="true">
        let trustpilot_integration_app_url = '{/literal}{$integration_app_url}{literal}';
        let user_id = '{/literal}{$user_id}{literal}';
        let ajax_url = urlWithoutProtocol();

        function urlWithoutProtocol() {
            let url = '{/literal}{$ajax_url}{literal}';
            url = url.replace(/(^\w+:|^)/, '');
            return url;
        }
    </script>
    <script type="text/javascript" src="{/literal}{$admin_js_dir}{literal}"></script>
    <fieldset id="trustpilot_signup">
        <iframe
            src='{/literal}{$integration_app_url}{literal}'
            id='configuration_iframe'
            frameborder='0'
            scrolling='no'
            width='100%'
            height='1400px'
            data-source='Prestashop'
            data-plugin-version='{/literal}{$plugin_version}{literal}'
            data-version='{/literal}Prestashop-{$version}{literal}'
            data-page-urls='{/literal}{$page_urls}{literal}'
            data-custom-trustboxes='{/literal}{$custom_trustboxes}{literal}'
            data-transfer='{/literal}{$integration_app_url}{literal}'
            data-past-orders='{/literal}{$data_past_orders}{literal}'
            data-settings='{/literal}{$settings}{literal}'
            data-product-identification-options='{/literal}{$product_identification_options}{literal}'
            data-is-from-marketplace='{/literal}{$is_from_marketplace}{literal}'
            onload='sendSettings(); sendPastOrdersInfo();'>
        </iframe>
        <div id='trustpilot-trustbox-preview'
            hidden='true'
            data-page-urls='{/literal}{$page_urls}{literal}'
            data-custom-trustboxes='{/literal}{$custom_trustboxes}{literal}'
            data-settings='{/literal}{$settings}{literal}'
            data-src='{/literal}{$starting_url}{literal}'
            data-source='Prestashop'
            data-sku='{/literal}{$sku}{literal}'
            data-name='{/literal}{$name}{literal}'>
        </div>
    </fieldset>
    <script src='{/literal}{$trustbox_preview_url}{literal}' id='TrustBoxPreviewComponent'></script>
</div>{/literal}