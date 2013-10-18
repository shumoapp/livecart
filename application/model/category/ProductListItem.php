<?php


/**
 *
 * @package application/model/category
 * @author Integry Systems <http://integry.com>
 */
class ProductListItem extends ActiveRecordModel
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName($className);

		public $ID;
		public $productListID', 'ProductList', 'ID', null, ARInteger::instance()));
		public $productID', 'Product', 'ID', null, ARInteger::instance()));
		public $position;
		$schema->registerAutoReference('productID');
	}

	/*####################  Static method implementations ####################*/

	public static function getNewInstance(ProductList $productList, Product $product)
	{
		$instance = new self();
		$instance->productList = $productList;
		$instance->product = $product;
		return $instance;
	}

	/*####################  Saving ####################*/

	public function beforeCreate()
	{
		$this->setLastPosition('productList');


	}
}

?>