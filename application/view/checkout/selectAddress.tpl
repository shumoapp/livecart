{includeJs file="library/form/Validator.js"}
{includeJs file="library/form/ActiveForm.js"}

{include file="layout/frontend/header.tpl"}
{* include file="layout/frontend/leftSide.tpl" *}
{* include file="layout/frontend/rightSide.tpl" *}

<div id="content" class="left right">
	
	<h1>{t _select_addresses}</h1>
	
	{form action="controller=checkout action=doSelectAddress" method="POST" handle=$form}

    <h2>{t _billing_address}</h2>

    <a href="{link controller=user action=addBillingAddress returnPath=true}" class="menu">
        {t _add_billing_address}
    </a>

    <table class="addressSelector">
	{foreach from=$billingAddresses item="item"}
        <tr>
            <td class="selector">
                <input type="radio" class="radio" name="billingAddress" id="billing_{$item.UserAddress.ID}" value="{$item.UserAddress.ID}" />
            </td>        
            <td class="address" onclick="$('billing_{$item.UserAddress.ID}').checked = true;">        	    
                {include file="user/address.tpl"}                
            </td>
        </tr>        
	{/foreach}
	</table>	

    <p>
        {checkbox name="sameAsBilling" checked="checked" class="checkbox"}
        <label for="sameAsBilling" class="checkbox">{t _the_same_as_shipping_address}</label>
    </p>
    
    <div style="clear: both;"></div>
    
    <div id="shippingSelector">

    <h2>{t _shipping_address}</h2>

    <a href="{link controller=user action=addShippingAddress returnPath=true}" class="menu">
        {t _add_shipping_address}
    </a>

        <table class="addressSelector">
    	{foreach from=$shippingAddresses item="item"}
            <tr>
                <td class="selector">
                    <input type="radio" class="radio" name="shippingAddress" id="shipping_{$item.UserAddress.ID}" value="{$item.UserAddress.ID}" />
                </td>        
                <td class="address" onclick="$('shipping_{$item.UserAddress.ID}').checked = true;">        	    
                    {include file="user/address.tpl"}                
                </td>
            </tr>        
    	{/foreach}
    	</table>

    </div>

    {literal}
    <script type="text/javascript">
        new User.ShippingFormToggler($('sameAsBilling'), $('shippingSelector'));    
    </script>
    {/literal}

    <input type="submit" class="submit" value="{tn _continue}" />
    
    {/form}

</div>

{include file="layout/frontend/footer.tpl"}