{activeListItem id=$item.ID deletable=`$item.isDefault-1`}
	<input type="checkbox" id="languageList_enable_{$item.ID}" {if $item.isEnabled}checked{/if} {if $item.isDefault}disabled{/if} onclick="setEnabled('{$item.ID}', 1 - {$item.isEnabled});">

	<img src="image/localeflag/{$item.ID}.png" />

	<span class="enabled_{$item.isEnabled}">{$item.name} ({$item.ID})</span> 

	{if !$item.isEnabled}
	(inactive)
	{/if}
		
	<br />

	<span id="languageList_progress_{$item.ID}">

		<small><a href="{link language=$language controller=backend.language action=edit id=$item.ID}" class="listLink">{t _edit_definitions}</a>

			{if $item.isEnabled}		
		 | 
			{if !$item.isDefault}
		<a href="{link language=$language controller=backend.language action=setDefault id=$item.ID}" class="listLink">{t _set_as_default}</a>
			{else}
				<strong>{t _default_language}</strong>
			{/if}
			{/if}
		</small>
			
	</span>	
{/activeListItem}