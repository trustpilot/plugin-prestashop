/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

window.addEventListener("message", this.receiveSettings);

getTokenAndController();

function receiveSettings(e) {
    if (e.origin === location.origin){
        return receiveInternalData(e);
    }
    if (e.origin !== trustpilot_integration_app_url) {
        return;
    }
    const data = e.data;
    if (data.startsWith('sync:')) {
        const split = data.split(':');
        const action = {};
        action['action'] = 'sync_past_orders';
        action[split[0]] = split[1];
        this.submitPastOrdersCommand(action);
    } else if (data.startsWith('resync')) {
        const action = {};
        action['action'] = 'resync_past_orders';
        action['resync'] = 'resync';
        this.submitPastOrdersCommand(action);
    } else if (data.startsWith('issynced')) {
        const action = {};
        action['action'] = 'is_past_orders_synced';
        action['issynced'] = 'issynced';
        this.submitPastOrdersCommand(action);
    } else if (data.startsWith('showPastOrdersInitial:')) {
        const split = data.split(':');
        const action = {};
        action['action'] = 'show_past_orders_initial';
        action[split[0]] = split[1];
        this.submitPastOrdersCommand(action);
    } else if (data === 'update') {
        updateplugin();
    } else if (data === 'reload') {
        reloadSettings();
    } else if (data && JSON.parse(data).TrustBoxPreviewMode) {
        TrustBoxPreviewMode(data);
    } else {
        this.handleJSONMessage(data);
    }
}

function receiveInternalData(e) {
    const data = e.data;
    if (data && typeof data === 'string') {
        const jsonData = JSON.parse(data);
        if (jsonData) {
            if (jsonData.type == 'newTrustBox' || jsonData.type === 'updatePageUrls') {
                submitSettings(jsonData);
            }
        }
    }
}

function handleJSONMessage(data) {
    const parsedData = JSON.parse(data);
    if (parsedData.window) {
        this.updateIframeSize(parsedData);
    } else if (parsedData.type === 'submit') {
        this.submitSettings(parsedData);
    } else if (parsedData.trustbox) {
        const iframe = document.getElementById('trustbox_preview_frame');
        iframe.contentWindow.postMessage(JSON.stringify(parsedData.trustbox), "*");
    }
}

function TrustBoxPreviewMode(data) {
    const settings = JSON.parse(data);
    const div = document.getElementById('trustpilot-trustbox-preview');
    if (settings.TrustBoxPreviewMode.enable) {
        div.hidden = false;
    } else {
        div.hidden = true;
    }
}

function submitPastOrdersCommand(data) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajax_url);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status >= 400) {
                console.log(`callback error: ${xhr.response} ${xhr.status}`);
            } else {
                sendPastOrdersInfo(xhr.response);
            }
        }
    };
    data.token = token;
    data.controller = controller;
    data.user_id = user_id;
    xhr.send('settings=' + btoa(encodeSettings(data)));
}

function updateplugin() {
    const data = {
        action: 'update_trustpilot_plugin',
        token: token,
        controller: controller,
        user_id: user_id
    };
    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajax_url);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('settings=' + btoa(encodeSettings(data)));
}

function reloadSettings() {
    const data = {
        action: 'reload_trustpilot_settings',
        token: token,
        controller: controller,
        user_id: user_id
    };
    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajax_url);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status >= 400) {
                console.log(`callback error: ${xhr.response} ${xhr.status}`);
            } else {
                const pluginOldVersion = document.getElementById('configuration_iframe').dataset.pluginVersion;
                const response = JSON.parse(xhr.response);
                if (response.pluginVersion !== pluginOldVersion) {
                    window.location.reload(true);
                }
            }
        }
    };
    xhr.send('settings=' + btoa(encodeSettings(data)));
}

function sendPastOrdersInfo(data) {
    const iframe = document.getElementById('configuration_iframe');
    const attrs = iframe.dataset;
    if (data === undefined) {
        data = attrs.pastOrders;
    }
    iframe.contentWindow.postMessage(data, attrs.transfer);
}

function submitSettings(parsedData) {
    const data = {
        action: 'save_changes',
        token: token,
        controller: controller,
        user_id: user_id
    };

    if (parsedData.type === 'updatePageUrls') {
        data.pageUrls = encodeURIComponent(JSON.stringify(parsedData.pageUrls));
    } else if (parsedData.type === 'newTrustBox') {
        data.customTrustboxes = encodeURIComponent(JSON.stringify(parsedData));
    } else {
        data.settings = encodeURIComponent(JSON.stringify(parsedData.settings));
        document.getElementById('trustbox_preview_frame').dataset.settings = btoa(data.settings);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajax_url);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('settings=' + btoa(encodeSettings(data)));

}

function encodeSettings(settings) {
    let encodedString = '';
    for (const setting in settings) {
        encodedString += setting + '=' + settings[setting] + '&';
    }
    return encodedString.substring(0, encodedString.length - 1);
}

function updateIframeSize(settings) {
    const iframe = document.getElementById('configuration_iframe');
    if (iframe) {
        iframe.height=(settings.window.height) + "px";
    }
}

function sendSettings() {
    const iframe = document.getElementById('configuration_iframe');

    const attrs = iframe.dataset;
    const settings = JSON.parse(atob(attrs.settings));

    if (!settings.trustbox) {
        settings.trustbox = {};
    }

    settings.trustbox.pageUrls = JSON.parse(atob(attrs.pageUrls));
    settings.pluginVersion = attrs.pluginVersion;
    settings.source = attrs.source;
    settings.version = attrs.version;
    settings.basis = 'plugin';
    settings.productIdentificationOptions = JSON.parse(attrs.productIdentificationOptions);
    settings.isFromMarketplace = attrs.isFromMarketplace;

    if (settings.trustbox.trustboxes && attrs.sku) {
        for (trustbox of settings.trustbox.trustboxes) {
            trustbox.sku = attrs.sku;
        }
    }

    if (settings.trustbox.trustboxes && attrs.name) {
        for (trustbox of settings.trustbox.trustboxes) {
            trustbox.name = attrs.name;
        }
    }

    iframe.contentWindow.postMessage(JSON.stringify(settings), attrs.transfer);
}

function getTokenAndController() {
    const query = window.location.search.substring(1);
    const vars = query.split("&");

    for (i = 0 ; i < vars.length; i++) {
        const pair = vars[i].split("=");

        if (pair[0] === "token" && typeof token === 'undefined')
            token = pair[1];
        if (pair[0] === "controller" && typeof controller === 'undefined')
            controller = pair[1];
    }

    if (typeof help_class_name != 'undefined')
        controller = help_class_name;
}