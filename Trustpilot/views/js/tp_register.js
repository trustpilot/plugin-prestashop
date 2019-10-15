/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

if (trustpilot_script_url) {
    load_tp_min();
} else {
    window.addEventListener('DOMContentLoaded', load_tp_min());
}

function load_tp_min() {
    (function(w,d,s,r,n){w.TrustpilotObject=n;w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)};
    a=d.createElement(s);a.async=1;a.src=r;a.type='text/java'+s;f=d.getElementsByTagName(s)[0];
    f.parentNode.insertBefore(a,f)})(window,document,'script',trustpilot_script_url,'tp');
    tp('register', trustpilot_key);
}