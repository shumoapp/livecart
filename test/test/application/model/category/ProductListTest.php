<?php
if(!defined('TEST_SUITE')) require_once dirname(__FILE__) . '/../../Initialize.php';

ClassLoader::import("application.model.category.ProductList");
ClassLoader::import("application.model.category.Category");
ClassLoader::import("application.model.product.Product");

/**
 *  @author Integry Systems
 *  @package test.model.category
 */
class ProductListTest extends UnitTest
{
	private $product;
	private $user;

	public function getUsedSchemas()
	{
		return array(
			'Product',
			'ProductList',
		);
	}

	public function setUp()
	{
		parent::setUp();
		ActiveRecordModel::executeUpdate('DELETE FROM ProductList');
	}

	public function testCreateAndRetrieve()
	{
		$root = Category::getRootNode();

		$list = ProductList::getNewInstance($root);
		$list->save();

		$list2 = ProductList::getNewInstance($root);
		$list2->save();

		$lists = ProductList::getCategoryLists($root);

		$this->assertSame($lists->get(0), $list);
		$this->assertSame($lists->get(1), $list2);

		$this->assertEqual($list->position->get(), 1);
		$this->assertEqual($list2->position->get(), 2);
	}

	public function testAddItems()
	{
		$root = Category::getRootNode();

		$list = ProductList::getNewInstance($root);
		$list->save();

		$product = Product::getNewInstance($root);
		$product->save();

		$list->addProduct($product);
	}

}
?>