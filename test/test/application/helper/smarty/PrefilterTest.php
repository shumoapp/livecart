<?php

if(!defined('TEST_SUITE')) require_once dirname(__FILE__) . '/../../Initialize.php';

ClassLoader::importNow("application.helper.smarty.prefilter#config");

/**
 *  @author Integry Systems
 *  @package test.helper.smarty
 */
class PrefilterTest extends LiveCartTest
{
	public function testErrShorthand()
	{
		$code = '{err for="firstName"}{{label {t _your_first_name}:}}{textfield class="text"}{/err}';
		$replaced = smarty_prefilter_config($code, null);
		$expected = '<label for="firstName"><span class="label">{translate text="_your_first_name"}:</span></label><fieldset class="error">{textfield name="firstName"  class="text"}
	<div class="errorText hidden{error for="firstName"} visible{/error}">{error for="firstName"}{$msg}{/error}</div>
	</fieldset>';

		$this->assertEqual($replaced, $expected);
	}

	public function testErrShorthandTranslation()
	{
		$code = '{err for="firstName"}{label _your_first_name}{textfield class="text"}{/err}';
		$replaced = smarty_prefilter_config($code, null);
		$expected = '<label for="firstName"><span class="label">{translate text="_your_first_name"}</span></label><fieldset class="error">{textfield name="firstName"  class="text"}
	<div class="errorText hidden{error for="firstName"} visible{/error}">{error for="firstName"}{$msg}{/error}</div>
	</fieldset>';

		$this->assertEqual($replaced, $expected);
	}

	public function testBlockAsParamValue()
	{
		$code = '{someblock test={anotherblock}}';
		$replaced = smarty_prefilter_config($code, null);
		$this->assertEqual($replaced, '{capture assign=blockAsParamValue}{anotherblock}{/capture}{someblock test=$blockAsParamValue}');
	}

	public function testBlockAsParamValueInsideLiteral()
	{
		$code = '<script type="text/javascript">
	{literal}
		var emptyGroupModel = new Backend.RelatedProduct.Group.Model({Product: {ID: {/literal}{$productID}{literal}}}, Backend.availableLanguages);
		new Backend.RelatedProduct.Group.Controller($("productRelationshipGroup_new_{/literal}{$productID}{literal}_form").down(\'.productRelationshipGroup_form\'), emptyGroupModel);
	{/literal}
	</script>';
		$replaced = smarty_prefilter_config($code, null);

		$this->assertEqual($replaced, $code);
	}

    /**
     * Testing for AngularJS vars
     */
    public function testAngularJSVars()
    {
        $code = '{{product.name}}';
        $replaced = smarty_prefilter_config($code,  null);

        $this->assertEqual($replaced, '{literal}{{product.name}}{/literal}');
    }

    /**
     * Testing for structures that look like AngularJS vars
     */
    public function testNonAngularJSVars()
    {
        $code = "{{capture assign=blockAsParamValue}{id: category.id}{/capture}";
        $replaced = smarty_prefilter_config($code,  null);

        $this->assertEqual($code, $replaced);
    }


}
?>
