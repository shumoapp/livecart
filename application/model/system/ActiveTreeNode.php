<?php

ClassLoader::import("application.model.ActiveRecordModel");

/**
 * A node of a hierarchial database record structure (preorder tree traversal implementation)
 *
 * <code>
 * //Defining a new class for some table
 * class Catalog
 * {
 *    public static function defineSchema($className = __CLASS__)
 *    {
 *        // <strog>Note:</strong> The folowing methods must be called in an exact order as shown in example.
 *        // 1. Get a schema instance,
 *        // 2. set a schema name,
 *        // 3. call a parent::defineSchema() to register schema fields needed for a hierarchial data structure
 *        // 4. Add your own fields if needed
 *        $schema = self::getSchemaInstance($className);
 *		  $schema->setName("Catalog");
 *
 *		  parent::defineSchema($className);
 *		  $schema->registerField(new ARField("name", Varchar::instance(40)));
 *		  $schema->registerField(new ARField("description", Varchar::instance(200)));
 *    }
 * }
 *
 * // Retrieving a subtree
 * $catalog = ARTreeNode::getRootNode("Catalog");
 * $catalog->loadChildNodes();
 *
 * // or...
 * $catalog = ARTreeNode::getInstanceByID("Catalog", $catalogNodeID);
 * $catalog->loadChildNodes();
 *
 * $childList = $catalog->getChildNodes();
 *
 * // Inserting a new node
 * $parent = getParentNodeFromSomewhere();
 * $catalogNode = ARTreeNode::getNewInstance("Catalog", $parent);
 * $catalogNode->name->set("This is my new catalog node!");
 * $catalogNode->name->set("This node will be created as child for a gived $parent instance");
 * $catalogNode->save();
 *
 * // Deleting a node and all its childs
 * ARTreeNode::deleteByID("Catalog", $catalogNodeID);
 *
 * </code>
 *
 * @link http://www.sitepoint.com/article/hierarchical-data-database/
 * @author Saulius Rupainis <saulius@integry.net>
 * @package application.model.system
 *
 */
class ActiveTreeNode extends ActiveRecordModel
{
    /**
	 * Table field name for left value container of tree traversal order
	 *
	 * @var string
	 */
	const LEFT_NODE_FIELD_NAME = 'lft';

	/**
	 * Table field name for right value container of tree traversal order
	 *
	 * @var string
	 */
	const RIGHT_NODE_FIELD_NAME = 'rgt';

	/**
	 * The name of table field that represents a parent node ID
	 *
	 * @var string
	 */
	const PARENT_NODE_FIELD_NAME = 'parentNodeID';

	/**
	 * Root node ID
	 *
	 * @var int
	 */
	const ROOT_ID = 1;

	/**
	 * Indicator wheather child nodes are loaded or not for this node
	 *
	 * @var bool
	 */
	const INCLUDE_ROOT_NODE = true;
	
	/**
	 * Child node container
	 *
	 * @var ARTreeNode[]
	 */
	private $childList = null;
	
	/**
	 * Path node container
	 *
	 * @var ARSet[]
	 */
	private $pathNodes = null;

	/**
	 * Partial schema definition for a hierarchial data storage in a database
	 *
	 * @param string $className
	 */
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$tableName = $schema->getName();
		$schema->registerField(new ARPrimaryKeyField("ID", ARInteger::instance()));
		$schema->registerField(new ARForeignKeyField(self::PARENT_NODE_FIELD_NAME, $tableName, "ID",$className, ARInteger::instance()));
		$schema->registerField(new ARField(self::LEFT_NODE_FIELD_NAME, ARInteger::instance()));
		$schema->registerField(new ARField(self::RIGHT_NODE_FIELD_NAME, ARInteger::instance()));
	}
	
	/**
	 * Gets a persisted record object
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * @param bool $loadRecordData
	 * @param bool $loadReferencedRecords
	 * @param bool $loadChildRecords
	 * @return ActiveTreeNode
	 */
	public static function getInstanceByID($className, $recordID, $loadRecordData = false, $loadReferencedRecords = false)
	{
		$instance = parent::getInstanceByID($className, $recordID, $loadRecordData, $loadReferencedRecords);
		return $instance;
	}

	/**
	 * Create new Active Tree instance
	 *
	 * @param string $className 
	 * @param ActiveTreeNode $parentNode Parent node
	 * @return ActiveTreeNode
	 */
	public static function getNewInstance($className, ActiveTreeNode $parentNode)
	{
		$instance = parent::getNewInstance($className);
		$instance->setParentNode($parentNode);
		return $instance;
	}

	/**
	 * Loads a list of child records for this node
	 *
	 * @param bool $loadReferencedRecords
	 * @param bool $loadOnlyDirectChildren
	 * @return ARSet
	 *
	 * @see self::getDirectChildNodes()
	 */
	public function getChildNodes($loadReferencedRecords = false, $loadOnlyDirectChildren = false)
	{
		$this->load();
		$className = get_class($this);

		$nodeFilter = new ARSelectFilter();
		$cond = new OperatorCond(new ArFieldHandle($className, self::LEFT_NODE_FIELD_NAME), $this->getField(self::LEFT_NODE_FIELD_NAME)->get(), ">");
		$cond->addAND(new OperatorCond(new ArFieldHandle($className, self::RIGHT_NODE_FIELD_NAME), $this->getField(self::RIGHT_NODE_FIELD_NAME)->get(), "<"));

		if ($loadOnlyDirectChildren)
		{
			$cond->addAND(new EqualsCond(new ARFieldHandle($className, self::PARENT_NODE_FIELD_NAME), $this->getID()));
		}

		$nodeFilter->setCondition($cond);
		$nodeFilter->setOrder(new ArFieldHandle($className, self::LEFT_NODE_FIELD_NAME));

		$childList = ActiveRecord::getRecordSet($className, $nodeFilter, $loadReferencedRecords);
		
		return $childList;
	}

	/**
	 * Gets a list of direct child nodes
	 *
	 * @param bool $loadReferencedRecords
	 * @return ARSet
	 */
	public function getDirectChildNodes($loadReferencedRecords = false)
	{
		return $this->getChildNodes($loadReferencedRecords, false);
	}

	/**
	 * Loads and builds a hierarchial subtree for this node
	 * (loads a list of child records and then builds a hierarchial structure)
	 *
	 * @param bool $loadReferencedRecords
	 */
	public function loadSubTree($loadReferencedRecords = false)
	{
		$childList = $this->getChildNodes($loadReferencedRecords);
		$indexedNodeList = array();
		$indexedNodeList[$this->getID()] = $this;

		foreach ($childList as $child)
		{
			$nodeId = $child->getID();
			$indexedNodeList[$nodeId] = $child;
		}
		foreach ($childList as $child)
		{
			$parentId = $child->getParentNode()->getID();
			$indexedNodeList[$parentId]->registerChildNode($child);
		}
	}

	/**
	 * Loads from database and builds a hierarchial record tree
	 *
	 * @param string $className
	 * @param bool $loadReferencedRecords
	 */
	public static function loadTree($className, $loadReferencedRecords = false)
	{
		$root = self::getRootNode($className);
		$root->loadSubTree($loadReferencedRecords);
	}

	/**
	 * Adds (registers) a child node to this node
	 *
	 * @param ARTreeNode $childNode
	 */
	public function registerChildNode(ActiveTreeNode $childNode)
	{
		if ($this->childList == null)
		{
			$this->childList = new ARSet(null);
		}
		$this->childList->add($childNode);
	}

	/**
	 * Sets a parent node
	 *
	 * @param ARTreeNode $parentNode
	 */
	public function setParentNode(ActiveTreeNode $parentNode)
	{
		$this->getField(self::PARENT_NODE_FIELD_NAME)->set($parentNode);
	}

	/**
	 * Gets a parent node
	 *
	 * @return ARSet
	 */
	public function getParentNode()
	{
		return $this->getField(self::PARENT_NODE_FIELD_NAME)->get();
	}

	/**
	 * Gets a tree root node
	 *
	 * @param string $className
	 * @param bool $loadChildRecords
	 * @return ARTreeNode
	 */
	public static function getRootNode($className)
	{
		return self::getInstanceByID($className, self::ROOT_ID, false, false);
	}


	/**
	 * Gets a hierarchial path to a given tree node
	 *
	 * The result is a sequence of record starting from a root node
	 * E.x. Consider a tree branch: Electronics -> Computers -> Laptops
	 * The path of "Laptops" will be a record set (ARSet) with a following order of records:
	 * 1. Electronics
	 * 2. Computers
	 *
	 * @param bool $includeRootNode
	 * @param bool $loadReferencedRecords
	 * @return ARSet
	 * 
	 * @see ARSet
	 */
	public function getPathNodeSet($includeRootNode = false, $loadReferencedRecords = false)
	{
		$className = get_class($this);

		// cache data if referenced records are not being loaded
		if (!$loadReferencedRecords)		
		{
			if (!$this->pathNodes)
			{
			  	$this->pathNodes = ActiveTreeNode::getRecordSet($className, $this->getPathNodeFilter(true), false);
			}
									
			$nodeSet = clone $this->pathNodes;					  	
			
			if (!$includeRootNode)
			{
				$nodeSet->remove(0);	
			}
			
			return $nodeSet;

		}
		else
		{
		  	return ActiveTreeNode::getRecordSet($className, $this->getPathNodeFilter($includeRootNode), $loadReferencedRecords);
		  	
		}
	}

	/**
	 * Gets a hierarchial path to a given tree node
	 *
	 * The result is a sequence of record starting from a root node
	 * E.x. Consider a tree branch: Electronics -> Computers -> Laptops
	 * The path of "Laptops" will be a record set (ARSet) with a following order of records:
	 * 1. Electronics
	 * 2. Computers
	 *
	 * @param bool $includeRootNode
	 * @param bool $loadReferencedRecords
	 * @return array
	 */
	public function getPathNodeArray($includeRootNode = false, $loadReferencedRecords = false)
	{
		$className = get_class($this);
		return ActiveTreeNode::getRecordSetArray($className, $this->getPathNodeFilter($includeRootNode), $loadReferencedRecords);
	}

	/**
	 * Return get filter to get all nodes in subtree
	 */
	private function getPathNodeFilter($includeRootNode)
	{
		$className = get_class($this);
		$this->load();
		$leftValue = $this->getFieldValue(self::LEFT_NODE_FIELD_NAME);
		$rightValue = $this->getFieldValue(self::RIGHT_NODE_FIELD_NAME);

		$filter = new ARSelectFilter();
		$cond = new OperatorCond(new ARFieldHandle($className, self::LEFT_NODE_FIELD_NAME), $leftValue, "<=");
		$cond->addAND(new OperatorCond(new ARFieldHandle($className, self::RIGHT_NODE_FIELD_NAME), $rightValue, ">="));

		if (!$includeRootNode)
		{
			$cond->addAND(new OperatorCond(new ARFieldHandle($className, "ID"), self::ROOT_ID, "<>"));
		}

		$filter->setCondition($cond);
		$filter->setOrder(new ARFieldHandle($className, self::LEFT_NODE_FIELD_NAME), ARSelectFilter::ORDER_ASC);

		return $filter;
	}

	/**
	 * Get node weight: left + right - 1 
	 */
	function getWidth()
	{
	    if($this->isLoaded()) $this->load();
	    
		$t_r = $this->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
		$t_l = $this->getFieldValue(self::LEFT_NODE_FIELD_NAME);
	    
        return $t_r - $t_l + 1;
	}

	/**
	 * @todo Implementation
	 *
	 * @param ActiveTreeNode $parentNode
	 * @return bool
	 * 
	 * @throws Exception If failed to commit the transaction
	 */
	public function moveTo(ActiveTreeNode $parentNode)
	{
        $this->load();
	    if($this->getFieldValue(self::PARENT_NODE_FIELD_NAME)->getID() == $parentNode->getID()) return true;
	    
		$className = get_class($this);
    	    
        $db = ActiveRecord::getDBConnection();
	    try
	    {
    	    # Step #1: Update parentNodeID
    	    $this->setParentNode($parentNode);
    	    $this->save();
    			
    	    $parentNode->load();
    		$t_r = $this->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
    		$t_l = $this->getFieldValue(self::LEFT_NODE_FIELD_NAME);
    		$p_r = $parentNode->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
    		$p_l = $parentNode->getFieldValue(self::LEFT_NODE_FIELD_NAME);
    		
    		$width = $this->getWidth();
    		$s = $p_l - $t_l > 0 ? -1 : 0;
    		
    		// Step #2: Change target node left and right values to negotive
    		$updates[] = "UPDATE $className SET ".self::LEFT_NODE_FIELD_NAME."=-".self::LEFT_NODE_FIELD_NAME.", ".self::RIGHT_NODE_FIELD_NAME."=-".self::RIGHT_NODE_FIELD_NAME." WHERE ".self::LEFT_NODE_FIELD_NAME." BETWEEN $t_l AND $t_r";
    		
    		// Step #3: No that there is no target node decrement all left and right values after target node position
    		$updates[] = "UPDATE $className SET ".self::RIGHT_NODE_FIELD_NAME." = ".self::RIGHT_NODE_FIELD_NAME." - $width WHERE ".self::RIGHT_NODE_FIELD_NAME." > $t_r";
            $updates[] = "UPDATE $className SET ".self::LEFT_NODE_FIELD_NAME." = ".self::LEFT_NODE_FIELD_NAME." - $width WHERE ".self::LEFT_NODE_FIELD_NAME." > $t_r";
            
            // Step #4: Make free space for new node to insert
            $updates[] = "UPDATE $className SET ".self::RIGHT_NODE_FIELD_NAME." = ".self::RIGHT_NODE_FIELD_NAME." + $width WHERE ".self::RIGHT_NODE_FIELD_NAME." > " . ($p_l + $s * $width);
            $updates[] = "UPDATE $className SET ".self::LEFT_NODE_FIELD_NAME." = ".self::LEFT_NODE_FIELD_NAME." + $width WHERE ".self::LEFT_NODE_FIELD_NAME." > " . ($p_l + $s * $width);
            
            // Step #5: Change target node left and right values back to positive and put them to their place
            $updates[] = "UPDATE $className SET ".self::LEFT_NODE_FIELD_NAME."=-".self::LEFT_NODE_FIELD_NAME."+(" . ($p_l - $t_l + 1 + $s * $width) . "), ".self::RIGHT_NODE_FIELD_NAME."=-".self::RIGHT_NODE_FIELD_NAME."+(" . ($p_l - $t_l + 1 + $s * $width) . ") WHERE ".self::LEFT_NODE_FIELD_NAME." < 0";
            
			foreach($updates as $update)
			{
				self::getLogger()->logQuery($update);
				$db->executeUpdate($update);
			}	
		}
		catch (Exception $e)
		{
			ActiveRecordModel::rollback();
			throw $e;
		}
		
		return true;
	}
    
	/**
	 * Save ActiveTreeNode data in database
	 * 
	 * @return bool
	 * 
	 * @throws Exception If failed to commit the transaction
	 */
	public function save()
	{
		if (!$this->hasID())
		{
			ActiveRecordModel::beginTransaction();
			try
			{
				$className = get_class($this);
				
			    // Inserting new node
				$parentNode = $this->getField(self::PARENT_NODE_FIELD_NAME)->get();
				$parentNode->load();
				$parentRightValue = $parentNode->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
				$nodeLeftValue = $parentRightValue;
				$nodeRightValue = $nodeLeftValue + 1;

				$tableName = self::getSchemaInstance(get_class($this))->getName();
				$db = self::getDBConnection();

				$updates[] = "UPDATE $className SET ".self::RIGHT_NODE_FIELD_NAME." = ".self::RIGHT_NODE_FIELD_NAME." + 2 WHERE ".self::RIGHT_NODE_FIELD_NAME." >= $parentRightValue";
				$updates[] = "UPDATE $className SET ".self::LEFT_NODE_FIELD_NAME." = ".self::LEFT_NODE_FIELD_NAME." + 2 WHERE ".self::LEFT_NODE_FIELD_NAME." >= $parentRightValue";

				foreach($updates as $update)
				{
    				self::getLogger()->logQuery($update);
    				$db->executeUpdate($update);
				}

				$this->getField(self::RIGHT_NODE_FIELD_NAME)->set($nodeRightValue);
				$this->getField(self::LEFT_NODE_FIELD_NAME)->set($nodeLeftValue);

				ActiveRecordModel::commit();
			}
			catch (Exception $e)
			{
				ActiveRecordModel::rollback();
				throw $e;
			}
		}
		
		return parent::save();
	}
    
	/**
	 * Delete this node with subtree
	 * 
	 * @return bool
	 * 
	 * @throws Exception If failed to commit the transaction
	 */
	public function delete()
	{
	    $className = get_class($this);
	    $this->load();
	    
		$t_r = $this->getFieldValue(self::RIGHT_NODE_FIELD_NAME);
		$t_l = $this->getFieldValue(self::LEFT_NODE_FIELD_NAME);
		
		$width = $this->getWidth();
		
		ActiveRecordModel::beginTransaction();
		try
		{
			$result = parent::deleteByID($className, $this->getID());
			
			$updates[] = "UPDATE $className SET ".self::RIGHT_NODE_FIELD_NAME." = ".self::RIGHT_NODE_FIELD_NAME." - $width  WHERE ".self::RIGHT_NODE_FIELD_NAME." >= $t_r";
			$updates[] = "UPDATE $className SET ".self::LEFT_NODE_FIELD_NAME." = ".self::LEFT_NODE_FIELD_NAME." - $width WHERE ".self::LEFT_NODE_FIELD_NAME." >= $t_l";
            
			foreach($updates as $update)
			{
    			self::getLogger()->logQuery($update);
    			self::getDBConnection()->executeUpdate($update);
			}

			ActiveRecordModel::commit();
		}
		catch (Exception $e)
		{
			ActiveRecordModel::rollback();
			throw $e;
		}
		
		return $result;
	}
    
	/**
	 * Reindex traversal tree left and right indexes using parentNodesID of the same tree
	 * 
	 * @todo This method does nothing
	 */
	public static function reindex($className)
	{
	    
	}

	/**
	 * Creates an array representation of this node
	 *
	 * @return array
	 */
	public function toArray()
	{
		$data = array();
		foreach ($this->data as $name => $field)
		{
			if ($name == self::PARENT_NODE_FIELD_NAME && $field->get() != null)
			{
				$data['parent'] = $field->get()->getID();
			}
			else
			{
				$data[$name] = $field->get();
			}
		}
		$data["childrenCount"] = ($data[self::RIGHT_NODE_FIELD_NAME] - $data[self::LEFT_NODE_FIELD_NAME] - 1) / 2;
		
		
		$childArray = array();

		if ($this->childList != null)
		{
			foreach ($this->childList as $child)
			{
				$childArray[] = $child->toArray();
			}
			$data['children'] = $childArray;
		}
		
		return $data;
	}
	
	/**
	 * This method works similar to ActiveTreeNode::getPathNodeSet
	 */
	public function getPathBranchesArray() 
	{
	    return $this->buildPathBranchesArray($this->getPathNodeSet()->toArray(), 0);
	}
	
	private function buildPathBranchesArray($path, $level) 
	{
	    $branch = array();  
	    $branch['children'] = Category::getInstanceByID($path[$level]['ID'])->getChildNodes(false, true)->toArray();
	   
	    $childrenCount = count($branch['children']);
	    $pathCount = count($path);
	    for($i = 0; $i < $childrenCount; $i++) 
	    {
	        if(($level + 1) <$pathCount && $branch['children'][$i]['ID'] == $path[$level+1]['ID'])
	        {
	            $branch['children'][$i] = array_merge($branch['children'][$i], $this->buildPathBranchesArray($path, $level+1));
	        }
	    }
			        
        return $branch;
	}
}

?>