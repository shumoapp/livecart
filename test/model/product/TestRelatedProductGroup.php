<?php
if(!defined('TEST_SUITE')) require_once dirname(__FILE__) . '/../../Initialize.php';

ClassLoader::import("application.model.product.Product");
ClassLoader::import("application.model.category.Category");

class TestRelatedProductGroup extends UnitTestCase
{
    private $groupAutoIncrementNumber = 0;
    private $productAutoIncrementNumber = 0;
    
    /**
     * @var Product
     */
    private $product = null;

    /**
     * @var Category
     */
    private $rootCategory = null;
    
        
    /**
     * Creole database connection wrapper
     *
     * @var Connection
     */
    private $db = null;
    
    public function __construct()
    {
        parent::__construct('Related product groups tests');
        
        $this->rootCategory = Category::getInstanceByID(Category::ROOT_ID);
	    $this->db = ActiveRecord::getDBConnection();
    }
	
	public function tearDown()
	{
	    ActiveRecordModel::rollback();		
	    $this->db->executeUpdate("ALTER TABLE RelatedProductGroup AUTO_INCREMENT=" . $this->groupAutoIncrementNumber);
	    $this->db->executeUpdate("ALTER TABLE Product AUTO_INCREMENT=" . $this->productAutoIncrementNumber);
	}
	
    public function setUp()
	{
	    ActiveRecordModel::beginTransaction();	
		
	    // Create some product
		$this->product = Product::getNewInstance($this->rootCategory);
		$this->product->save();
		$this->productAutoIncrementNumber = $this->product->getID();
		
   		// create new group
		$dump = RelatedProductGroup::getNewInstance($this->product);
		$dump->save();		
		
		$this->groupAutoIncrementNumber = $dump->getID();
	}
	
	public function testCreateNewGroup()
	{
	    $group = RelatedProductGroup::getNewInstance($this->product);
	    $group->position->set(5);
	    $group->setValueByLang('name', 'en', 'TEST_GROUP');
	    $group->save();
	    
	    // Reload
	    $group->markAsNotLoaded();
	    $group->load(true);
	    
	    $this->assertEqual($group->position->get(), 5);
	    $name = $group->name->get();
	    $this->assertEqual($name['en'], 'TEST_GROUP');
	    $this->assertEqual($this->product->getID(), $group->product->get()->getID());
	    $this->assertTrue($this->product === $group->product->get());
	}
		
	public function testDeleteGroup()
	{
	    $group = RelatedProductGroup::getNewInstance($this->product);
	    $group->save();
	    $this->assertTrue($group->isExistingRecord());
	    
	    $group->delete();
	    $this->assertFalse($group->isLoaded());
	}

	public function testGetProductGroups()
	{
	    // new product
		$product = Product::getNewInstance($this->rootCategory);
		$product->save();	
	    
	    $groups = array();
	    foreach(range(1, 3) as $i)
	    {
		    $groups[$i] = RelatedProductGroup::getNewInstance($product);
		    $groups[$i]->position->set($i);
		    $groups[$i]->setValueByLang('name', 'en', 'TEST_GROUP_' . $i);
		    $groups[$i]->save();
	    }
	    
	    $this->assertEqual(count($groups), RelatedProductGroup::getProductGroups($product)->getTotalRecordCount());
	    $i = 1;
	    foreach(RelatedProductGroup::getProductGroups($product) as $group)
	    {
	        $this->assertTrue($groups[$i] === $group);
	        $i++;
	    }
	}
	
	public function getRelatedProductGroup(Product $relatedToProduct)
	{
	    $relationship = RelatedProductGroup::getInstance($relatedToProduct, $this);
	    
	    return $relationship ? $relationship->relatedProductGroup->get() : null;
	}
}
?>