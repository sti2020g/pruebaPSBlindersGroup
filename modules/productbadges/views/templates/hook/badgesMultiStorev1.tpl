{**
 * Product Badges - front-office badge overlay
 *}
{if $productbadges_badges|@count > 0}
<div class="productbadges-wrapper">
    <div class="productbadges-col productbadges-col-left">
        {foreach from=$productbadges_badges item=badge}
            {if $badge.position == 'top-left'}
            <span class="productbadges-badge"
                  style="background-color:{$badge.bg_color|escape:'html':'UTF-8'};color:{$badge.text_color|escape:'html':'UTF-8'};">
                {$badge.label|escape:'html':'UTF-8'}
            </span>
            {/if}
        {/foreach}
    </div>
    <div class="productbadges-col productbadges-col-right">
        {foreach from=$productbadges_badges item=badge}
            {if $badge.position == 'top-right'}
            <span class="productbadges-badge"
                  style="background-color:{$badge.bg_color|escape:'html':'UTF-8'};color:{$badge.text_color|escape:'html':'UTF-8'};">
                {$badge.label|escape:'html':'UTF-8'}
            </span>
            {/if}
        {/foreach}
    </div>
</div>
{/if}
