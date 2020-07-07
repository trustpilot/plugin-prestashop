/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

getController();

function getController() {
    const query = window.location.search.substring(1);
    const vars = query.split("&");

    for (i = 0 ; i < vars.length; i++) {
        const pair = vars[i].split("=");

        if (pair[0] === "controller" && typeof controller === 'undefined')
            controller = pair[1];
    }

    if (typeof help_class_name != 'undefined')
        controller = help_class_name;
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof prestashop !== 'undefined') {
        prestashop.on('updateProductList', (list) => {
            if (typeof tp !== undefined &&
                typeof trustpilot_trustbox_settings !== 'undefined' &&
                trustpilot_trustbox_settings.trustboxes.some((tb) => tb.repeat).length > 0) {
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', trustpilot_ajax_url);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status >= 400) {
                            console.log(`callback error: ${xhr.response} ${xhr.status}`);
                        } else {
                            trustpilot_trustbox_settings.categoryProductsData = JSON.parse(xhr.response);
                            tp('trustBox', trustpilot_trustbox_settings);
                        }
                    }
                };

                const data = {
                    action: 'get_category_product_info',
                    products: JSON.stringify(list.products.map((p) => p.id_product)),
                    controller: controller,
                    user_id: user_id,
                };
                xhr.send('settings=' + btoa(encodeSettings(data)));
            }
        });
    }
});

function encodeSettings(settings) {
    let encodedString = '';
    for (const setting in settings) {
        encodedString += setting + '=' + settings[setting] + '&';
    }
    return encodedString.substring(0, encodedString.length - 1);
}

if (typeof tp !== undefined && typeof trustpilot_trustbox_settings  !== 'undefined') {
    tp('trustBox', trustpilot_trustbox_settings);
} else {
    document.addEventListener('DOMContentLoaded', function() { tp('trustBox', trustpilot_trustbox_settings) });
}

