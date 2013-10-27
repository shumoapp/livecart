<?php

namespace eav;

/**
 * Product specification wrapper class. Loads/modifies product specification data.
 *
 * This class usually should not be used directly as most of the attribute manipulations
 * can be done with Product class itself.
 *
 * @package application/model/eav
 * @author Integry Systems <http://integry.com>
 */
class EavSpecificationManager
{
	/**
	 * Owner object instance
	 *
	 * @var ActiveRecordModel
	 */
	protected $owner = null;

	protected $attributes = array();

	protected $removedAttributes = array();
	
	protected $fieldManager;

	public function __construct(\ActiveRecordModel $owner, $specificationDataArray = array())
	{
		$this->fieldManager = new EavFieldManager(EavField::getClassID($owner));
		$this->owner = $owner;
		
		if (is_null($specificationDataArray) && $owner->getID())
		{
			$specificationDataArray = self::fetchRawSpecificationData(get_class($this), array($owner->eavObjectID), true);

/*
			// preload attribute groups
			$groups = array();
			foreach ($specificationDataArray as $spec)
			{
				if ($spec[$groupIDColumn])
				{
					$groups[$spec[$groupIDColumn]] = true;
				}
			}
			$groups = array_keys($groups);

			ActiveRecordModel::getInstanceArray($groupClass, $groups);
*/
		}

		$this->loadSpecificationData($specificationDataArray);
	}

	public function getSpecificationFieldSet()
	{
		return $this->fieldManager->getFields();
	}

	public function setOwner(\ActiveRecordModel $owner)
	{
		$this->owner = $owner;
		$eavObject = $owner->get_EavObject();
		if (!$eavObject)
		{
			return;
		}

		foreach ($this->attributes as $attribute)
		{
			$attribute->setOwner($eavObject);
		}
	}

	public function getGroupClass()
	{
		return 'EavFieldGroup';
	}

	/**
	 * Sets specification attribute value by mapping product, specification field, and
	 * assigned value to one record (atomic item)
	 *
	 * @param iEavSpecification $specification Specification item value
	 */
	public function setAttribute(iEavSpecification $newSpecification)
	{
		$specField = $newSpecification->getFieldInstance();

		if ($this->owner->getID()
			&& isset($this->attributes[$newSpecification->getFieldInstance()->getID()]))
		{
			$this->attributes[$newSpecification->getFieldInstance()->getID()]->replaceValue($newSpecification);
		}
		else
		{
			$this->attributes[$specField->getID()] = $newSpecification;
		}

		unset($this->removedAttributes[$specField->getID()]);
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

	public function getAttributesByGroup(EavFieldGroup $group)
	{
		$res = array();
		foreach ($this->attributes as $attribute)
		{
			if ($attribute->getField()->getGroup() === $group)
			{
				$res[] = $attribute;
			}
		}

		return $res;
	}

	/**
	 * Removes persisted product specification property
	 *
	 *	@param SpecField $field SpecField instance
	 */
	public function removeAttribute(EavField $field)
	{
		if (isset($this->attributes[$field->getID()]))
		{
			$this->removedAttributes[$field->getID()] = $this->attributes[$field->getID()];
		}

		unset($this->attributes[$field->getID()]);
	}

	public function removeAttributeValue(EavField $field, EavValue $value)
	{
		if (!$field->isSelector())
	  	{
			throw new Exception('Cannot remove a value from non selector type specification field');
		}

		if (!isset($this->attributes[$field->getID()]))
		{
		  	return false;
		}

		if ($field->isMultiValue)
		{
			$this->attributes[$field->getID()]->removeValue($value);
		}
		else
		{
			// no changes should be made until the save() function is called
			$this->attributes[$field->getID()]->delete();
		}
	}

	public function isAttributeSet(EavField $field)
	{
		return isset($this->attributes[$field->getID()]);
	}

	/**
	 *	Get attribute instance for the particular SpecField.
	 *
	 *	If it is a single value selector a SpecFieldValue instance needs to be passed as well
	 *
	 *	@param SpecField $field SpecField instance
	 *	@param SpecFieldValue $defaultValue SpecFieldValue instance (or nothing if SpecField is not selector)
	 *
	 * @return Specification
	 */
	public function getAttribute(EavField $field, $defaultValue = null)
	{
		if (!$this->isAttributeSet($field))
		{
			if (!$field->isSelector())
			{
				$this->attributes[$field->getID()] = EavObjectValue::getNewInstance($this->owner, $field, $defaultValue);
			}
			else
			{
				if ($field->isMultiValue)
				{
					$this->attributes[$field->getID()] = EavMultiValueItem::getNewInstance($this->owner, $field, $defaultValue);
				}
				else
				{
					$this->attributes[$field->getID()] = EavItem::getNewInstance($this->owner, $field, $defaultValue);
				}
			}
		}

		return $this->attributes[$field->getID()];
	}

	public function getAttributeByHandle($handle)
	{
		foreach ($this->attributes as $attribute)
		{
			if ($attribute->getField() && ($attribute->getField()->handle == $handle))
			{
				return $attribute;
			}
		}
	}

	/**
	 * Sets specification attribute value
	 *
	 * @param SpecField $field Specification field instance
	 * @param mixed $value Attribute value
	 */
	public function setAttributeValue(EavField $field, $value)
	{
		if (!is_null($value))
		{
			$specification = $this->getAttribute($field, $value);
			$specification->setValue($value);

			$this->setAttribute($specification);
		}
		else
		{
			$this->removeAttribute($field);
		}
	}

	/**
	 * Sets specification String attribute value by language
	 *
	 * @param SpecField $field Specification field instance
	 * @param unknown $value Attribute value
	 */
/*
	public function setAttributeValueByLang(EavField $field, $langCode, $value)
	{
		$specification = $this->getAttribute($field);
		$specification->setValueByLang($langCode, $value);
		$this->setAttribute($specification);
	}
*/

	public function save()
	{
		$obj = $this->owner->get_EavObject();
		foreach ($this->removedAttributes as $attribute)
		{
			$attribute->delete();
		}
		$this->removedAttributes = array();

		foreach ($this->attributes as $attribute)
		{
			$attribute->setOwner($obj);
			$attribute->save();
		}
	}

	public function hasValues()
	{
		return !empty($this->attributes);
	}

	public function toArray()
	{
		$arr = array();
		foreach ($this->attributes as $id => $attribute)
		{
			$arr['attr'][$id] = $attribute->toArray();
			$arr[$id] = $attribute->getRawValue();
		}

		//uasort($arr, array($this, 'sortAttributeArray'));

		return $arr;
	}

	/*
	private function sortAttributeArray($a, $b)
	{
		$field = 'EavField';
		$fieldGroup = $field . 'Group';

		if (!isset($a[$field][$fieldGroup]['position']))
		{
			$a[$field][$fieldGroup]['position'] = -1;
		}

		if (!isset($b[$field][$fieldGroup]['position']))
		{
			$b[$field][$fieldGroup]['position'] = -1;
		}

		if (($a[$field][$fieldGroup]['position'] == $b[$field][$fieldGroup]['position']))
		{
			if (!isset($a[$field]['position']))
			{
				$a[$field]['position'] = 0;
			}

			if (!isset($b[$field]['position']))
			{
				$b[$field]['position'] = 0;
			}

			return ($a[$field]['position'] < $b[$field]['position']) ? -1 : 1;
		}

		return ($a[$field][$fieldGroup]['position'] < $b[$field][$fieldGroup]['position']) ? -1 : 1;
	}
	*/

	public function loadRequestData(\Phalcon\Http\Request $request, $prefix = '')
	{
		$fields = $this->getSpecificationFieldSet();

		$eav = $request->getJson('eav');
		
		// create new select values
		if ($request->has($prefix . 'other'))
		{
			foreach ($request->get($prefix . 'other') as $fieldID => $values)
			{
				$field = call_user_func_array(array('EavField', 'getInstanceByID'), array($fieldID, ActiveRecordModel::LOAD_DATA));

				if (is_array($values))
				{
					// multiple select
					foreach ($values as $value)
					{
						if ($value)
						{
							$fieldValue = $field->getNewValueInstance();
							$fieldValue->setValueByLang('value', $application->getDefaultLanguageCode(), $value);
							$fieldValue->save();

							$request->set($prefix . 'specItem_' . $fieldValue->getID(), 'on');
						}
					}
				}
				else
				{
					// single select
					if ('other' == $request->get($prefix . 'specField_' . $fieldID))
					{
						$fieldValue = $field->getNewValueInstance();
						$fieldValue->setValueByLang('value', $application->getDefaultLanguageCode(), $values);
						$fieldValue->save();

						$request->set($prefix . 'specField_' . $fieldID, $fieldValue->getID());
					}
				}
			}
		}

		foreach ($fields as $field)
		{
			$isset = isset($eav[$field->getID()]);
			$value = $isset ? $eav[$field->getID()] : null;

			if ($field->isSelector())
			{
				if (!$field->isMultiValue)
				{
					if ($isset /* && !in_array($request->get($fieldName), array('other'))*/)
				  	{
				  		if ($value)
				  		{
				  			$this->setAttributeValue($field, $field->getValueInstanceByID($value));
				  		}
				  		else
				  		{
				  			$this->removeAttribute($field);
						}
				  	}
				}
				else
				{
					if (!$value)
					{
						$value = array();
					}
					
					foreach ($field->getValues() as $val)
					{
						if (in_array($val->getID(), $value))
						{
							$this->setAttributeValue($field, $val);
						}
						else
						{
							$this->removeAttributeValue($field, $val);
						}
					}
				}
			}
			else
			{
				if ($field->isTextField())
				{
					/*
					foreach ($languages as $language)
					{
						if ($request->has($prefix . $field->getFormFieldName($language)))
						{
							$this->setAttributeValueByLang($field, $language, $request->get($prefix . $field->getFormFieldName($language)));
						}
					}
					*/
					$this->setAttributeValue($field, $value);
				}
				else
				{
					if (strlen($value))
					{
						$this->setAttributeValue($field, $value);
					}
					else
					{
						$this->removeAttribute($field);
					}
				}
			}
		}
	}

	/*
	public function getFormData($prefix = '')
	{
		$selectorTypes = EavField::getSelectorValueTypes();
		$multiLingualTypes = EavField::getMultilanguageTypes();
		$languageArray = ActiveRecordModel::getApplication()->getLanguageArray();
		$fieldClass = 'EavField';

		$formData = array();

		foreach($this->toArray() as $attr)
		{
			$fieldName = $prefix . $attr[$fieldClass]['fieldName'];

			if(in_array($attr[$fieldClass]['type'], $selectorTypes))
			{
				if(1 == $attr[$fieldClass]['isMultiValue'])
				{
					foreach($attr['valueIDs'] as $valueID)
					{
						$formData[$prefix . 'specItem_' . $valueID] = "on";
					}
				}
				else
				{
					$formData[$fieldName] = $attr['ID'];
				}
			}
			else if(in_array($attr[$fieldClass]['type'], $multiLingualTypes))
			{
				$formData[$fieldName] = $attr['value'];
				foreach($languageArray as $lang)
				{
					if (isset($attr['value_' . $lang]))
					{
						$formData[$fieldName . '_' . $lang] = $attr['value_' . $lang];
					}
				}
			}
			else
			{
				$formData[$fieldName] = isset($attr['value']) ? $attr['value'] : 0;
			}
		}

		return $formData;
	}
	*/

	/*
	public function isValid($validatorName = 'eavValidator')
	{
		$request = new Request();
		$request->setValueArray($this->getFormData());
		$validator = new \Phalcon\Validation($validatorName, $request);
		$this->setValidation($validator);

		return $validator->isValid();
	}
	*/

	/*
	public function setFormResponse(ActionResponse $response, Form $form, $prefix = '')
	{
		$specFields = $this->owner->getSpecification()->getSpecificationFieldSet(ActiveRecordModel::LOAD_REFERENCES);
		$specFieldArray = $specFields->toArray();

		// set select values
		$selectors = EavField::getSelectorValueTypes();
		foreach ($specFields as $key => $field)
		{
			if (in_array($field->type, $selectors))
			{
				$values = $field->getValuesSet()->toArray();
				$specFieldArray[$key]['values'] = array('' => '');
				foreach ($values as $value)
				{
					$specFieldArray[$key]['values'][$value['ID']] = isset($value['value_lang']) ? $value['value_lang'] : $value['value'];
				}
			}
		}

		// arrange SpecFields's into groups
		$specFieldsByGroup = array();
		$prevGroupID = -1;

		$groupClass = 'EavField' . 'Group';
		foreach ($specFieldArray as $field)
		{
			$groupID = isset($field[$groupClass]['ID']) ? $field[$groupClass]['ID'] : '';
			if((int)$groupID && $prevGroupID != $groupID)
			{
				$prevGroupID = $groupID;
			}

			$specFieldsByGroup[$groupID][] = $field;
		}

		// get multi language spec fields
		$multiLingualSpecFields = array();
		foreach ($specFields as $key => $field)
		{
			if ($field->isTextField())
			{
				$multiLingualSpecFields[] = $field->toArray();
			}
		}

		if (!$prefix)
		{
			$response->set("specFieldList", $specFieldsByGroup);
		}

		$response->set("groupClass", $groupClass);
		$response->set("multiLingualSpecFieldss", $multiLingualSpecFields);

		// set fields by prefix
		$prefixed = $response->get("specFieldList_prefix", array());
		$prefixed[$prefix] = $specFieldsByGroup;
		$response->set("specFieldList_prefix", $prefixed);

		$this->owner->load();

		// set fields by owner
		if (($this->owner instanceof EavObject) && ($owner = $this->owner->getOwner()))
		{
			$byOwner = $response->get("specFieldListByOwner", array());
			$byOwner[get_class($owner)][$owner->getID()] = $specFieldsByGroup;
			$response->set("specFieldListByOwner", $byOwner);
		}

		$form->setData($this->getFormData($prefix));
	}
	*/

	/*
	public function setValidation(\Phalcon\Validation $validator, $filtersOnly = false, $fieldPrefix = '')
	{
		$specFields = $this->getSpecificationFieldSet(ActiveRecordModel::LOAD_REFERENCES);

		$application = ActiveRecordModel::getApplication();

		foreach ($specFields as $key => $field)
		{
			$fieldname = $fieldPrefix . $field->getFormFieldName();

		  	// validate numeric values
			if (EavField::TYPE_NUMBERS_SIMPLE == $field->type)
		  	{
				if (!$filtersOnly)
				{
					$validator->add($fieldname, new IsNumericCheck($application->translate('_err_numeric')));
				}

				$validator->addFilter($fieldname, new NumericFilter());
			}

		  	// validate required fields
			if ($field->isRequired && !$filtersOnly)
		  	{
				if (!($field->isSelector() && $field->isMultiValue))
				{
					$validator->add($fieldname, new Validator\PresenceOf(array('message' => $application->translate('_err_specfield_required'))));
				}
				else
				{
					$validator->add($fieldname, new SpecFieldIsValueSelectedCheck($application->translate('_err_specfield_multivaluerequired'), $field, $application->getRequest()));
				}
			}
		}
	}
	*/

	/*
	public static function loadSpecificationForRecordArray($class, &$productArray)
	{
		$array = array(&$productArray);
		self::loadSpecificationForRecordSetArray($class, $array, true);

		$fieldClass = call_user_func(array($class, 'getFieldClass'));
		$groupClass = $fieldClass . 'Group';
		$groupIDColumn = strtolower(substr($groupClass, 0, 1)) . substr($groupClass, 1) . 'ID';

		$groupIds = array();
		foreach ($productArray['attributes'] as $attr)
		{
			$groupIds[isset($attr[$fieldClass][$groupIDColumn]) ? $attr[$fieldClass][$groupIDColumn] : 'NULL'] = true;
		}

		$f = new ARSelectFilter(new INCond(new ARFieldHandle($groupClass, 'ID'), array_keys($groupIds)));
		$indexedGroups = array();
		$res = ActiveRecordModel::getRecordSetArray($groupClass, $f);
		foreach ($res as $group)
		{
			$indexedGroups[$group['ID']] = $group;
		}

		foreach ($productArray['attributes'] as &$attr)
		{
			if (isset($attr[$fieldClass][$groupIDColumn]))
			{
				$attr[$fieldClass][$groupClass] = $indexedGroups[$attr[$fieldClass][$groupIDColumn]];
			}
		}
	}
	*/

	/**
	 * Load product specification data for a whole array of products at once
	 */
	/*
	public static function loadSpecificationForRecordSetArray($class, &$productArray, $fullSpecification = false)
	{
		$ids = array();
		foreach ($productArray as $key => $product)
	  	{
			$ids[$product['ID']] = $key;
		}

		$fieldClass = call_user_func(array($class, 'getFieldClass'));
		$groupClass = $fieldClass . 'Group';
		$groupColumn = call_user_func_array(array($fieldClass, 'getGroupIDColumnName'), array($fieldClass));
		$stringClass = call_user_func(array($fieldClass, 'getStringValueClass'));
		$fieldColumn = call_user_func(array($fieldClass, 'getFieldIDColumnName'));
		$objectColumn = call_user_func(array($fieldClass, 'getObjectIDColumnName'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueColumn = call_user_func(array($valueItemClass, 'getValueIDColumnName'));

		$specificationArray = self::fetchSpecificationData($class, array_flip($ids), $fullSpecification);

		$specFieldSchema = ActiveRecordModel::getSchemaInstance($fieldClass);
		$specStringSchema = ActiveRecordModel::getSchemaInstance($stringClass);
		$specFieldColumns = array_keys($specFieldSchema->getFieldList());

		foreach ($specificationArray as &$spec)
		{
			if ($spec['isMultiValue'])
			{
				$value['value'] = $spec['value'];
				$value = MultiLingualObject::transformArray($value, $specStringSchema);

				if (isset($productArray[$ids[$spec[$objectColumn]]]['attributes'][$spec[$fieldColumn]]))
				{
					$sp =& $productArray[$ids[$spec[$objectColumn]]]['attributes'][$spec[$fieldColumn]];
					$sp['valueIDs'][] = $spec['valueID'];
					$sp['values'][] = $value;
					continue;
				}

			}

			foreach ($specFieldColumns as $key)
			{
				$spec[$fieldClass][$key] = $spec[$key];
				unset($spec[$key]);
			}

			// transform for presentation
			$spec[$fieldClass] = MultiLingualObject::transformArray($spec[$fieldClass], $specFieldSchema);

			if ($spec[$fieldClass]['isMultiValue'])
			{
				$spec['valueIDs'] = array($spec['valueID']);
				$spec['values'] = array($value);
			}
			else
			{
				$spec = MultiLingualObject::transformArray($spec, $specStringSchema);
			}

			// groups
			if ($spec[$fieldClass][$groupColumn])
			{
				$spec[$fieldClass][$groupClass] = array(
										'ID' => $spec[$fieldClass][$groupColumn],
										'name' => $spec['SpecFieldGroupName'],
										'position' => $spec['SpecFieldGroupPosition']);

				if (!isset($groupSchema))
				{
					$groupSchema = ActiveRecordModel::getSchemaInstance($groupClass);
				}

				$spec[$fieldClass][$groupClass] = MultiLingualObject::transformArray($spec[$fieldClass][$groupClass], $groupSchema);
			}

			if ((!empty($spec['value']) || !empty($spec['values']) || !empty($spec['value_lang'])))
			{
				// append to product array
				$productArray[$ids[$spec[$objectColumn]]]['attributes'][$spec[$fieldColumn]] = $spec;
				self::sortAttributesByHandle($class, $productArray[$ids[$spec[$objectColumn]]]);
			}
		}
	}
	*/

	/*
	public static function sortAttributesByHandle($class, &$array)
	{
		$fieldClass = call_user_func(array($class, 'getFieldClass'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueClass = call_user_func(array($valueItemClass, 'getValueClass'));

		if (isset($array['attributes']))
		{
			foreach ($array['attributes'] as $attr)
			{
				if (empty($attr['handle']) && empty($attr[$fieldClass]['handle']))
				{
					continue;
				}

				if (isset($attr[$fieldClass]))
				{
					$array['byHandle'][$attr[$fieldClass]['handle']] = $attr;
				}
				else
				{
					if (!$attr['isMultiValue'])
					{
						$array['byHandle'][$attr['handle']] = $attr;
					}
					else
					{
						$array['byHandle'][$attr['handle']][$attr[$valueClass]] = $attr;
					}
				}
			}
		}
	}
	*/

	private static function fetchRawSpecificationData($class, $objectIDs, $fullSpecification = false)
	{
		if (!$objectIDs)
		{
			return array();
		}
		
		$values = EavObjectValue::query()->inWhere('objectID', $objectIDs)->execute();
		$items = EavItem::query()->join('EavValue')->inWhere('objectID', $objectIDs)->execute();
		
		return array($values, $items);

		/*
		$fieldClass = call_user_func(array($class, 'getFieldClass'));
		$groupClass = $fieldClass . 'Group';
		$fieldColumn = call_user_func(array($fieldClass, 'getFieldIDColumnName'));
		$objectColumn = call_user_func(array($fieldClass, 'getObjectIDColumnName'));
		$stringClass = call_user_func(array($fieldClass, 'getStringValueClass'));
		$numericClass = call_user_func(array($fieldClass, 'getNumericValueClass'));
		$dateClass = call_user_func(array($fieldClass, 'getDateValueClass'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueClass = call_user_func(array($valueItemClass, 'getValueClass'));
		$valueColumn = call_user_func(array($valueItemClass, 'getValueIDColumnName'));
		$groupColumn = strtolower(substr($groupClass, 0, 1)) . substr($groupClass, 1) . 'ID';

		$cond = '
		LEFT JOIN
			' . $fieldClass . ' ON ' . $fieldColumn . ' = ' . $fieldClass . '.ID
		LEFT JOIN
			' . $groupClass . ' ON ' . $fieldClass . '.' . $groupColumn . ' = ' . $groupClass . '.ID
		WHERE
			' . $objectColumn . ' IN (' . implode(', ', $objectIDs) . ')' . ($fullSpecification ? '' : ' AND ' . $fieldClass . '.isDisplayedInList = 1');

		$group = $groupClass . '.position AS SpecFieldGroupPosition, ' . $groupClass . '.name AS SpecFieldGroupName, ';

		$query = '
		SELECT ' . $dateClass . '.*, NULL AS valueID, NULL AS specFieldValuePosition, ' . $group . $fieldClass . '.* /* as valueID  FROM ' . $dateClass . ' ' . $cond . '
		UNION
		SELECT ' . $stringClass . '.*, NULL, NULL AS specFieldValuePosition, ' . $group . $fieldClass . '.* /* as valueID  FROM ' . $stringClass . ' ' . $cond . '
		UNION
		SELECT ' . $numericClass . '.*, NULL, NULL AS specFieldValuePosition, ' . $group . $fieldClass . '.* /* as valueID FROM ' . $numericClass . ' ' . $cond . '
		UNION
		SELECT ' . $valueItemClass . '.' . $objectColumn . ', ' . $valueItemClass . '.' . $fieldColumn . ', ' . $valueClass . '.value, ' . $valueClass . '.ID, ' . $valueClass . '.position, ' . $group . $fieldClass . '.*
				 FROM ' . $valueItemClass . '
				 	LEFT JOIN ' . $valueClass . ' ON ' . $valueItemClass . '.' . $valueColumn . ' = ' . $valueClass . '.ID
				 ' . str_replace('ON ' . $fieldColumn, 'ON ' . $valueItemClass . '.' . $fieldColumn, $cond) .
				 ' ORDER BY ' . $objectColumn . ', SpecFieldGroupPosition, position, specFieldValuePosition';

		return ActiveRecordModel::getDataBySQL($query);
		*/
	}

	/*
	protected static function fetchSpecificationData($class, $objectIDs, $fullSpecification)
	{
		if (!$objectIDs)
		{
			return array();
		}

		$specificationArray = self::fetchRawSpecificationData($class, $objectIDs, $fullSpecification);
		$multiLingualFields = array('name', 'description', 'valuePrefix', 'valueSuffix', 'SpecFieldGroupName');

		foreach ($specificationArray as &$spec)
		{
			// unserialize language field values
			foreach ($multiLingualFields as $value)
			{
				$spec[$value] = unserialize($spec[$value]);
			}

			if ((EavField::DATATYPE_TEXT == $spec['dataType'] && EavField::TYPE_TEXT_DATE != $spec['type'])
				|| (EavField::TYPE_NUMBERS_SELECTOR == $spec['type']))
			{
				$spec['value'] = unserialize($spec['value']);
			}
		}

		return $specificationArray;
	}

*/
	protected function loadSpecificationData($specificationDataArray)
	{
		// @todo: remove
		if (!is_array($specificationDataArray))
		{
			return;
		}
		
		foreach ($specificationDataArray as $type)
		{
			foreach ($type as $attr)
			{
				if ($attr instanceof EavObjectValue)
				{
					$this->attributes[$attr->fieldID] = $attr;
				}
				else if ($attr instanceof EavItem)
				{
					$field = $this->fieldManager->getField($attr->fieldID);
					if ($field->isMultiValue)
					{
						if (empty($this->attributes[$attr->fieldID]))
						{
							$this->attributes[$attr->fieldID] = EavMultiValueItem::getNewInstance($this->owner, $field);
						}
						
						$this->attributes[$attr->fieldID]->setItem($attr);
					}
					else
					{
						$this->attributes[$attr->fieldID] = $attr;
					}
				}
			}
		}
		return;
		
		if (!is_array($specificationDataArray))
		{
			$specificationDataArray = array();
		}

		// get value class and field names
		$fieldClass = 'EavField';
		$fieldColumn = call_user_func(array($fieldClass, 'getFieldIDColumnName'));
		$valueItemClass = call_user_func(array($fieldClass, 'getSelectValueClass'));
		$valueClass = call_user_func(array($valueItemClass, 'getValueClass'));
		$multiValueItemClass = call_user_func(array($fieldClass, 'getMultiSelectValueClass'));

		// preload all specFields from database
		$specFieldIds = array();

		$selectors = array();
		$simpleValues = array();
		foreach ($specificationDataArray as $value)
		{
		  	$specFieldIds[$value[$fieldColumn]] = $value[$fieldColumn];
		  	if ($value['valueID'])
		  	{
		  		$selectors[$value[$fieldColumn]][$value['valueID']] = $value;
			}
			else
			{
				$simpleValues[$value[$fieldColumn]] = $value;
			}
		}

		$specFields = ActiveRecordModel::getInstanceArray($fieldClass, $specFieldIds);

		// simple values
		foreach ($simpleValues as $value)
		{
		  	$specField = $specFields[$value[$fieldColumn]];

		  	$class = $specField->getValueTableName();

			$specification = call_user_func_array(array($class, 'restoreInstance'), array($this->owner, $specField, $value['value']));
		  	$this->attributes[$specField->getID()] = $specification;
		}

		// selectors
		foreach ($selectors as $specFieldId => $value)
		{
			if (!isset($specFields[$specFieldId]))
			{
				continue;
			}

			$specField = $specFields[$specFieldId];
		  	if ($specField->isMultiValue)
		  	{
				$values = array();
				foreach ($value as $val)
				{
					$values[$val['valueID']] = $val['value'];
				}

				$specification = call_user_func_array(array($multiValueItemClass, 'restoreInstance'), array($this->owner, $specField, $values));
			}
			else
			{
			  	$value = array_pop($value);
				$specFieldValue = call_user_func_array(array($valueClass, 'restoreInstance'), array($specField, $value['valueID'], $value['value']));
				$specification = call_user_func_array(array($valueItemClass, 'restoreInstance'), array($this->owner, $specField, $specFieldValue));
			}

		  	$this->attributes[$specField->getID()] = $specification;
		}
	}
	
	public function getFieldManager()
	{
		return $this->fieldManager;
	}

	public function __clone()
	{
		foreach ($this->attributes as $key => $attribute)
		{
			$this->attributes[$key] = clone $attribute;
		}
	}

/*
	public function __destruct()
	{
		foreach ($this->attributes as $k => $attr)
		{
			$this->attributes[$k]->__destruct();
			unset($this->attributes[$k]);
		}

		foreach ($this->removedAttributes as $k => $attr)
		{
			$this->removedAttributes[$k]->__destruct();
			unset($this->removedAttributes[$k]);
		}

		unset($this->owner);
	}
*/
}

?>
