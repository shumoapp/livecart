<div>

<fieldset class="container activeGridControls">
                
    <span style="{if $orderGroupID == 8}visibility: hidden;{else}{denied role="order.mass"}visibility: hidden;{/denied}{/if}" id="orderMass_{$orderGroupID}" class="activeGridMass">

	    {form action="controller=backend.customerOrder action=processMass id=$orderGroupID" handle=$massForm onsubmit="return false;"}
	    
	    <input type="hidden" name="filters" value="" />
	    <input type="hidden" name="selectedIDs" value="" />
	    <input type="hidden" name="isInverse" value="" />
	    
        {t _with_selected}:
        <select name="act" class="select">
            <optgroup label="{t _order_status}">
                <option value="setNew">{t _set_new}</option>
                <option value="setBackordered">{t _set_backordered}</option>
                <option value="setAwaitingShipment">{t _set_awaiting_shipment}</option>
                <option value="setShipped">{t _set_shipped}</option>
                <option value="setReturned">{t _set_returned}</option>
            </optgroup>
            <option value="delete">{t _delete}</option>
        </select>
        
        <span class="bulkValues" style="display: none;">

        </span>
        
        <input type="submit" value="{tn _process}" class="submit" />
        <span class="progressIndicator" style="display: none;"></span>
        
        {/form}
        
    </span>
    
    <span class="activeGridItemsCount">
		<span id="orderCount_{$orderGroupID}" >
			<span class="rangeCount">Listing orders %from - %to of %count</span>
			<span class="notFound">No orders found</span>
		</span>    
	</span>
    
</fieldset>

{activeGrid 
	prefix="orders" 
	id=$orderGroupID 
	role="order.mass" 
	controller="backend.customerOrder" action="lists" 
	displayedColumns=$displayedColumns 
	availableColumns=$availableColumns 
	totalCount=$totalCount 
	rowCount=17 
	showID=true
	container="tabPageContainer"
}

</div>

{literal}
<script type="text/javascript">
    try
    {
        {/literal}
            {if $userID > 0}
                {assign var="userID" value="?filters[User.ID]=`$userID`"}
            {/if}
        {literal}
    
    	grid.setDataFormatter(Backend.CustomerOrder.GridFormatter);
    	
        var massHandler = new Backend.CustomerOrder.massActionHandler($('{/literal}orderMass_{$orderGroupID}{literal}'), grid);
        massHandler.deleteConfirmMessage = '{/literal}{t _are_you_sure_you_want_to_delete_this_order|addslashes}{literal}' ;
        ordersActiveGrid['{/literal}{$orderGroupID}{literal}'] = grid;
    }
    catch(e)
    {
        console.info(e);
    }
</script>
{/literal}
