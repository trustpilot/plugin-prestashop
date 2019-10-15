
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */
if (trustpilot_widget_script_url) {
    load_preview();
} else {
    window.addEventListener('DOMContentLoaded', load_preview());
}


function inIframe () {
    try {
        return window.self !== window.top;
    } catch (e) {
        return false;
    }
}

function load_preview() {
    var w = document.createElement("script");
    w.type = "text/javascript";
    w.src = trustpilot_widget_script_url;
    w.async = true;
    document.head.appendChild(w);

    if (inIframe()) {
        window.addEventListener('message', function(e) {
            var adminOrign = new URL(window.location).hostname;
            if (!e.data || e.origin.indexOf(adminOrign) === -1) {
                return;
            }
            if (typeof TrustpilotPreview !== 'undefined') {
                if (typeof e.data === 'string' && e.data === 'submit') {
                    TrustpilotPreview.sendTrustboxes();
                } else {
                    jsonData = JSON.parse(e.data);
                    if (jsonData.trustbox) {
                        TrustpilotPreview.setSettings(jsonData.trustbox);
                    } else if (jsonData.customised) {
                        TrustpilotPreview.updateActive(jsonData.customised);
                    }
                }
            } else {
                try {
                    var jsonData = JSON.parse(e.data);
                    if (jsonData.trustboxes) {
                        var p = document.createElement("script");
                        p.type = "text/javascript";
                        p.onload = function () {
                            const iFrame = e.source.parent.document.getElementById('configuration_iframe').contentWindow;
                            TrustpilotPreview.init([trustpilot_preview_css_url], jsonData, iFrame, e.source);
                        };
                        p.src = trustpilot_preview_script_url;
                        document.head.appendChild(p);
                    }
                } catch (e) {
                    console.log(`TrustpilotPreview couldn't load due to an error: ${e.message}`);
                }
            }
        });
    }
}