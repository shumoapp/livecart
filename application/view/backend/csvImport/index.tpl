{includeCss file="backend/DatabaseImport.css"}
{includeCss file="backend/CsvImport.css"}

{includeJs file="library/dhtmlxtree/dhtmlXCommon.js"}
{includeJs file="library/dhtmlxtree/dhtmlXTree.js"}

{includeJs file="library/ActiveList.js"}
{includeJs file="library/form/ActiveForm.js"}
{includeJs file="library/form/State.js"}
{includeJs file="library/form/Validator.js"}
{includeJs file="backend/SelectFile.js"}

{pageTitle}{t _import_csv}{/pageTitle}

{include file="layout/backend/header.tpl"}

<div id="import">

{include file="backend/csvImport/wizardProgress.tpl"}

{form action="controller=backend.csvImport action=setFile" method="POST" handle=$form onsubmit="new Backend.DatabaseImport(this); return false;"}

	<fieldset>
		<legend>{t _select_file}</legend>

		<p class="required">
			{err for="upload"}
				{{label {t _upload_file} }}
				{filefield}
			{/err}
		</p>

		<p>
			<label></label>
			- {t _or} -
		</p>

		<p class="required">
			{err for="atServer"}
				{{label {t _select_at_server} }}
				{textfield id="atServer" class="file"}<input type="button" class="button browse" id="selectAtServer" value="{tn _browse}" />
			{/err}
		</p>

	</fieldset>

	<fieldset class="controls">
		<span class="progressIndicator" style="display: none;"></span>
		<input type="submit" class="submit" value="{tn _continue}" />
		{t _or}
		<a class="cancel" href="{link controller=backend.csvImport}">{t _cancel}</a>
	</fieldset>

{/form}
</div>

{literal}
	<script type="text/javascript">
		Backend.SelectFile.url = {/literal}'{link controller=backend.selectFile}'{literal};
		Event.observe($('selectAtServer'), 'click', function() {new Backend.SelectFile($('atServer')); });
	</script>
{/literal}

{include file="layout/backend/footer.tpl"}