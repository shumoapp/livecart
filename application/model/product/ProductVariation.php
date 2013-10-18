<?php

ClassLoader::import('application/model/product/ProductVariationType');
ClassLoader::import('application/model/system/MultilingualObject');

/**
 * Defines a product variation selection.
 *
 * For example, if "size" is ProductVariationType, then ProductVariation would be "small", "normal", "large", etc.
 *
 * @package application/model/product
 * @author Integry Systems <http://integry.com>
 */
class ProductVariation extends MultilingualObject
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);


		public $ID;
		public $typeID", "ProductVariationType", "ID", null, ARInteger::instance()));

		public $name;
		public $position;
	}

	public static function getNewInstance(ProductVariationType $type)
	{
		$instance = new self();
		$instance->type = $type;
		return $instance;
	}

	public function beforeCreate()
	{
		if (is_null($this->position))
		{
			$this->setLastPosition('type');
		}
		parent::insert();
	}
}

?>