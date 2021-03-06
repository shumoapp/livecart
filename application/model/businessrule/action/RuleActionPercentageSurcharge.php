<?php

ClassLoader::import('application.model.businessrule.action.RuleActionFixedDiscount');
ClassLoader::import('application.model.businessrule.action.RuleActionPercentageDiscount');

/**
 *
 * @author Integry Systems
 * @package application.model.businessrule.action
 */
class RuleActionPercentageSurcharge extends RuleActionFixedDiscount
{
	protected function getDiscountAmount($price)
	{
		return RuleActionPercentageDiscount::getDiscountAmount($price) * -1;
	}

	public static function getSortOrder()
	{
		return 3;
	}
}

?>