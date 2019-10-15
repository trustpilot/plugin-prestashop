/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

if (trustpilot_trustbox_settings) {
    if (this.document.readyState !== 'loading') {
        tp('trustBox', trustpilot_trustbox_settings);
    } else {
        document.addEventListener('DOMContentLoaded', tp('trustBox', trustpilot_trustbox_settings));
    }
}
