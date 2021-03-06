<?php

ClassLoader::import('library.activerecord.ARSet');

/**
 *
 * @package application.model.product
 * @author Integry Systems <http://integry.com>
 */
class ProductVariationTypeSet extends ARSet
{
	public function getVariations()
	{
		$f = new ARSelectFilter(new INCOnd(new ARFieldHandle('ProductVariation', 'typeID'), $this->getRecordIDs()));
		$f->setOrder(new ARFieldHandle('ProductVariation', 'position'));

		return ActiveRecordModel::getRecordSet('ProductVariation', $f);
	}
}

?>