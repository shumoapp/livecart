<?php

ClassLoader::import("application.model.ActiveRecordModel");

/**
 * System language logic - adding, removing or enabling languages.
 *
 * @author Integry Systems <http://integry.com>  
 * @package application.model.system
 */
class Language extends ActiveRecordModel
{
   	/**
	 * Languages schema definition
	 * @param string $className
	 * @todo code must be Unique
	 */
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName("Language");

		$schema->registerField(new ARPrimaryKeyField("ID", ARChar::instance(2)));
		$schema->registerField(new ARField("isEnabled", ARBool::instance()));
		$schema->registerField(new ARField("isDefault", ARBool::instance()));
		$schema->registerField(new ARField("position", ARInteger::instance()));
	}

	/**
	 * Gets language by its id.
	 * @param string(2) $ID
	 * @return Language
	 */
	public static function getInstanceByID($ID)
	{
		return ActiveRecord::getInstanceByID("Language", $ID, true);
	}

	/**
	 * Checks whether the language is systems default language
	 * @return bool
	 */
	public function isDefault()
	{
	  	return (bool)$this->isDefault->get();
	}

	/**
	 * Changes default language status
	 * @param bool $isDefault (sets as default if true, unsets default status if false)
	 */
	public function setAsDefault($isDefault = 1)
	{
	  	$this->isDefault->set($isDefault == 1 ? 1 : 0);
	  	return true;
	}

	/**
	 * Changes language status to enabled or disabled
	 * @param bool $isEnabled (sets as enabled if true, unsets enabled status if false)
	 */
	public function setAsEnabled($isEnabled = 1)
	{
	  	$this->isEnabled->set($isEnabled == 1 ? 1 : 0);
	  	return true;
	}

	public function save($forceOperation = 0)
	{
		self::deleteCache();
		
		return parent::save($forceOperation);
	}
	
	public static function deleteById($id)
	{
		self::deleteCache();

		// make sure the language record exists
		$inst = ActiveRecord::getInstanceById('Language', $id, true);
		
		// make sure it's not the default currency
		if (true != $inst->isDefault->get())			
		{
			ActiveRecord::deleteByID('Language', $id);
			return true;
		}
		else
		{
		  	return false;
		}
	}
	
	public static function deleteCache()
	{
		@unlink(ClassLoader::getRealPath('cache') . '/languages.php');		
	}
	
	protected function insert()
	{
	  	// get max position
	  	$f = new ARSelectFilter();
	  	$f->setOrder(new ARFieldHandle('Language', 'position'), 'DESC');
	  	$f->setLimit(1);
	  	$rec = ActiveRecord::getRecordSetArray('Language', $f);
		$position = (is_array($rec) && count($rec) > 0) ? $rec[0]['position'] + 1 : 1;

		// default new language state
		$this->position->set($position);	  	
		
		parent::insert();
	}
	
	public function toArray()
	{
	  	$array = parent::toArray();
	  	
	  	$info = self::getApplication()->getLocale()->info();
		$array['name'] = $info->getLanguageName($array['ID']);
	  	$array['originalName'] = $info->getOriginalLanguageName($array['ID']);
	  	
		if (file_exists(ClassLoader::getRealPath('public.image.localeflag') . '/' . $array['ID'] . '.png'))
		{
		  	$array['image'] = 'image/localeflag/' . $array['ID'] . '.png';
		}	  	
		
		return $array;
	}
}

?>