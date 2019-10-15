{**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 *}
{literal}
<script type="text/javascript" data-keepinline="true">
    var trustpilot_invitation = {/literal}{$invitation|@json_encode nofilter}{literal};
</script>
<script type="text/javascript" src="{/literal}{$invite_js_dir}{literal}"></script>
{/literal}