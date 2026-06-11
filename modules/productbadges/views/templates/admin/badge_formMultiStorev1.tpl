{*
 * Badge add/edit form — replaces HelperForm to support product multiselect
 *}
<div class="defaultForm form-horizontal">
<form id="badge_form" method="post" action="{$form_action|escape:'html':'UTF-8'}">
    <input type="hidden" name="id_product_badge" value="{$badge.id_product_badge|intval}">
    <input type="hidden" name="{$token_name|escape:'html':'UTF-8'}" value="{$token_value|escape:'html':'UTF-8'}">
    <input type="hidden" name="submitAddproduct_badge" value="1">

    {* ── Shop selector (multistore only) ─────────────────────────────────── *}
    {if $multistore_active && $shops|@count > 1}
    <div class="panel" style="margin-bottom:16px;">
        <div class="panel-heading">
            <i class="icon-home"></i> {l s='Select Store' mod='productbadges'}
        </div>
        <div class="form-group" style="padding:12px 0 0;">
            <label class="control-label col-lg-3">
                {l s='Manage badges:' mod='productbadges'}
            </label>
            <div class="col-lg-5">
                <select id="pb_shop_selector" class="form-control">
                    {foreach from=$shops item=shop}
                    <option value="{$shop.id_shop|intval}"
                        {if $shop.id_shop == $selected_shop_id} selected{/if}>
                        {$shop.name|escape:'html':'UTF-8'}
                    </option>
                    {/foreach}
                </select>
                <p class="help-block">
                    {l s='Product list and assignments below are specific to this shop.' mod='productbadges'}
                </p>
            </div>
        </div>
    </div>
    <input type="hidden" name="id_shop" value="{$selected_shop_id|intval}">
    <script>
    (function () {
        var sel = document.getElementById('pb_shop_selector');
        if (!sel) { return; }
        sel.addEventListener('change', function () {
            window.location.href = '{$reload_url|escape:'javascript':'UTF-8'}' +
                '&id_shop=' + encodeURIComponent(this.value);
        });
    }());
    </script>
    {else}
    <input type="hidden" name="id_shop" value="{$selected_shop_id|intval}">
    {/if}

    <div class="panel">
        <div class="panel-heading">
            <i class="icon-tag"></i>
            {l s='Badge' mod='productbadges'}
        </div>

        {* ── Label (multilang) ────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3 required">
                {l s='Label' mod='productbadges'}
            </label>
            <div class="col-lg-9">
                {if $languages|@count > 1}
                <ul class="nav nav-tabs" id="badge_label_tabs">
                    {foreach from=$languages item=lang}
                    <li{if $lang.id_lang == $default_lang} class="active"{/if}>
                        <a href="#label_tab_{$lang.id_lang|intval}" data-toggle="tab">
                            {$lang.name|escape:'html':'UTF-8'}
                        </a>
                    </li>
                    {/foreach}
                </ul>
                <div class="tab-content panel">
                    {foreach from=$languages item=lang}
                    <div id="label_tab_{$lang.id_lang|intval}"
                         class="tab-pane{if $lang.id_lang == $default_lang} active{/if}">
                        <input type="text"
                               id="label_{$lang.id_lang|intval}"
                               name="label_{$lang.id_lang|intval}"
                               value="{$badge.label[$lang.id_lang]|default:''|escape:'html':'UTF-8'}"
                               maxlength="64"
                               class="form-control">
                    </div>
                    {/foreach}
                </div>
                {else}
                    {assign var=lang value=$languages[0]}
                    <input type="text"
                           id="label_{$lang.id_lang|intval}"
                           name="label_{$lang.id_lang|intval}"
                           value="{$badge.label[$lang.id_lang]|default:''|escape:'html':'UTF-8'}"
                           maxlength="64"
                           class="form-control col-lg-4">
                {/if}
                <p class="help-block">{l s='Badge text shown on the product image.' mod='productbadges'}</p>
            </div>
        </div>

        {* ── Background color ─────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3 required">
                {l s='Background color' mod='productbadges'}
            </label>
            <div class="col-lg-2">
                <input type="color"
                       id="bg_color"
                       name="bg_color"
                       value="{$badge.bg_color|default:'#ff0000'|escape:'html':'UTF-8'}"
                       class="form-control">
            </div>
        </div>

        {* ── Text color ───────────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3 required">
                {l s='Text color' mod='productbadges'}
            </label>
            <div class="col-lg-2">
                <input type="color"
                       id="text_color"
                       name="text_color"
                       value="{$badge.text_color|default:'#ffffff'|escape:'html':'UTF-8'}"
                       class="form-control">
            </div>
        </div>

        {* ── Live preview ─────────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Preview' mod='productbadges'}
            </label>
            <div class="col-lg-9">
                <span id="productbadges-live-preview" class="productbadges-preview">BADGE</span>
            </div>
        </div>

        {* ── Position ─────────────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3 required">
                {l s='Position' mod='productbadges'}
            </label>
            <div class="col-lg-3">
                <select name="position" class="form-control">
                    <option value="top-left"{if $badge.position|default:'top-left' == 'top-left'} selected{/if}>
                        {l s='Top left' mod='productbadges'}
                    </option>
                    <option value="top-right"{if $badge.position|default:'' == 'top-right'} selected{/if}>
                        {l s='Top right' mod='productbadges'}
                    </option>
                </select>
            </div>
        </div>

        {* ── Sort order ───────────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Order' mod='productbadges'}
            </label>
            <div class="col-lg-2">
                <input type="number"
                       name="sort_order"
                       value="{$badge.sort_order|intval}"
                       min="0"
                       max="255"
                       class="form-control fixed-width-sm">
            </div>
            <div class="col-lg-7">
                <p class="help-block">{l s='Lower = shown first. Same position stacks top to bottom.' mod='productbadges'}</p>
            </div>
        </div>

        {* ── Active ───────────────────────────────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Active' mod='productbadges'}
            </label>
            <div class="col-lg-9">
                <span class="switch prestashop-switch fixed-width-lg">
                    <input type="radio" name="active" id="active_on" value="1"
                        {if $badge.active|default:1} checked{/if}>
                    <label for="active_on">{l s='Yes' mod='productbadges'}</label>
                    <input type="radio" name="active" id="active_off" value="0"
                        {if !$badge.active|default:1} checked{/if}>
                    <label for="active_off">{l s='No' mod='productbadges'}</label>
                    <a class="slide-button btn"></a>
                </span>
            </div>
        </div>

        {* ── Product assignment multiselect ───────────────────────────── *}
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Assign to products' mod='productbadges'}
            </label>
            <div class="col-lg-7">
                <input type="text"
                       id="pb_product_filter"
                       placeholder="{l s='Filter products...' mod='productbadges'}"
                       class="form-control"
                       style="margin-bottom:6px;">
                <select name="product_ids[]"
                        id="pb_product_multiselect"
                        multiple
                        size="12"
                        class="form-control"
                        style="height:240px;">
                    {foreach from=$products item=product}
                    <option value="{$product.id_product|intval}"
                        {if in_array($product.id_product, $assigned_ids)} selected{/if}>
                        {$product.name|escape:'html':'UTF-8'}
                    </option>
                    {/foreach}
                </select>
                <p class="help-block">
                    {l s='Hold Ctrl / Cmd to select multiple products.' mod='productbadges'}
                    &nbsp;({$products|@count} {l s='products available' mod='productbadges'})
                </p>
            </div>
        </div>

        <div class="panel-footer">
            <a href="{$cancel_url|escape:'html':'UTF-8'}" class="btn btn-default">
                <i class="process-icon-cancel"></i>
                {l s='Cancel' mod='productbadges'}
            </a>
            <button type="submit" name="submitAddproduct_badge" class="btn btn-default pull-right">
                <i class="process-icon-save"></i>
                {l s='Save' mod='productbadges'}
            </button>
        </div>
    </div>
</form>
</div>
