{if !$add}
	{assign var="action" value="Backend.Manufacturer.Editor.prototype.getInstance(`$manufacturer.ID`, false).submitForm();"}
	{assign var="urlAction" value="action=update id=`$manufacturer.ID`"}
{else}
	{assign var="action" value="Backend.Manufacturer.Editor.prototype.saveAdd(event);"}
	{assign var="urlAction" value="action=create"}
{/if}

{form handle=$form action="controller=backend.manufacturer `$urlAction`" id="userInfo_`$manufacturer.ID`_form" onsubmit="`$action`; return false;" method="post" role="product.update"}

	{input name="name"}
		{label}{t Manufacturer.name}:{/label}
		{textfield}
	{/input}

	{include file="backend/eav/fields.tpl" item=$manufacturer}

	{language}
		{include file="backend/eav/language.tpl" item=$manufacturer language=$lang.ID}
	{/language}

	<fieldset class="controls">
		<span class="progressIndicator" style="display: none;"></span>
		<input type="submit" name="save" class="submit" value="{t _save}">
		{t _or}
		<a class="cancel" href="#">{t _cancel}</a>
	</fieldset>

{/form}