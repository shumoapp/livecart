<div id="productManagerContainer" class="managerContainer" style="display: none;">
    
	<fieldset class="container">
		<ul class="menu">
			<li><a href="#cancelEditing" id="cancel_product_edit" class="cancel">{t Cancel editing product}</a></li>
		</ul>
	</fieldset>
	
	<div class="tabContainer">
		<ul class="tabList tabs">
			<li id="productBasic" class="tab active">
				<a href="{link controller=backend.product action=basicData id=_id_}?categoryID=_categoryID_}">{t Basic data}</a>
				<span class="tabHelp">products.edit</span>
			</li>
			
			<li id="productInventory" class="tab inactive">
				<a href="{link controller=backend.product action=inventory id=_id_}?categoryID=_categoryID_">{t Inventory}</a>
				<span class="tabHelp">products.edit.inventory</span>
			</li>

			<li id="productDiscounts" class="tab inactive">
				<a href="{link controller=backend.productPrice action=index id=_id_}?categoryID=_categoryID_">{t Prices &amp; Shipping}</a>
				<span class="tabHelp">products.edit.pricing</span>
			</li>
			
			<li id="productImages" class="tab inactive">
				<a href="{link    controller=backend.productImage action=index id=_id_}?categoryID=_categoryID_">{t Images}</a>
				<span class="tabHelp">products.edit.images</span>
			</li>
			
			<li id="productRelated" class="tab inactive">
				<a href="{link   controller=backend.productRelated action=index id=_id_}?categoryID=_categoryID_">{t Related products}</a>
				<span class="tabHelp">products.edit.related</span>
			</li>
			
			<li id="productOptions" class="tab inactive">
				<a href="{link   controller=backend.product action=options id=_id_}?categoryID=_categoryID_">{t Options}</a>
				<span class="tabHelp">products.edit.options</span>
			</li>
						
			<li id="productFiles" class="tab inactive">
				<a href="{link controller=backend.productFile action=index id=_id_}?categoryID=_categoryID_">{t Files}</a>
				<span class="tabHelp">products.edit.files</span>
			</li>
		</ul>
	</div>
	<div class="sectionContainer maxHeight h--50"></div>
</div>
{literal}
<script type="text/javascript">
    Event.observe($("cancel_product_edit"), "click", function(e) {
        Event.stop(e); 
        var product = Backend.Product.Editor.prototype.getInstance(Backend.Product.Editor.prototype.getCurrentProductId(), false);
        
        var textareas = product.nodes.parent.getElementsByTagName('textarea');
		for (k = 0; k < textareas.length; k++)
		{
			tinyMCE.execCommand('mceRemoveControl', true, textareas[k].id);
		}
        
        product.cancelForm();
        SectionExpander.prototype.unexpand(product.nodes.parent);
        Backend.Product.Editor.prototype.showCategoriesContainer();
    });
</script>
{/literal}