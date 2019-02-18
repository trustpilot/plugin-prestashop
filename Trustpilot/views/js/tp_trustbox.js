/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

if (trustpilot_trustbox_settings) {
    document.addEventListener('DOMContentLoaded', function() {
        tp('trustBox', trustpilot_trustbox_settings);
    });
}