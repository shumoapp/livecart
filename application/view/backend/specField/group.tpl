<div class="specField_group_form">
    <form action="{link controller=backend.specFieldGroup action=save}/" method="post"> 
        <input type="hidden" name="categoryID" class="specField_group_categoryID" />
        <fieldset class="specField_group_translations specField_step_main">
            <p class="specField_group_default_language">
        		<label>{t _specField_group_title}</label>
        		<input type="text" name="name" />
                <span class="feedback"> </span>
        	</p>
        
        	<fieldset class="dom_template specField_step_translations_language specField_group_translations_language_">
        		<legend>
                    <span class="expandIcon">[+] </span>
                    <span class="specField_group_translation_language_name"></span>
                </legend>
        
                <div class="activeForm_translation_values specField_group_language_translation">
                    <p>
            			<label>{t _specField_group_title}</label>
            			<input type="text" name="name" />
        			</p>
                </div>
        	</fieldset>
        </fieldset>
        
        <fieldset class="specField_group_controls">
        	<span class="activeForm_progress"></span>
            <input type="submit" class="specField_save button" value="{translate text=_save}" />
            {t _or}
            <a href="#cancel" class="specField_cancel cancel">{t _cancel}</a>
        </fieldset>
    </form>
</div>