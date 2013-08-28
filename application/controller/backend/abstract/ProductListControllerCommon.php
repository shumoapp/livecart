<?php


/**
 * Product list management - related products, category lists, etc.
 *
 * @package application.controller.backend.abstract
 * @author Integry Systems
 * @role product
 */
abstract class ProductListControllerCommon extends StoreManagementController
{
	protected abstract function getOwnerClassName();

	protected abstract function getGroupClassName();

	protected function getOwnerInstanceByID($id)
	{
		return ActiveRecordModel::getInstanceByID($this->getOwnerClassName(), $id, ActiveRecordModel::LOAD_DATA);
	}

	protected function getGroupInstanceByID($id, $loadData = true)
	{
		return ActiveRecordModel::getInstanceByID($this->getGroupClassName(), $id, $loadData);
	}

	/**
	 * @role update
	 */
	public function create()
	{
		$group = call_user_func(array($this->getGroupClassName(), 'getNewInstance'), $this->getOwnerInstanceByID($this->request->gget('ownerID')));
		return $this->save($group);
	}

	/**
	 * @role update
	 */
	public function update()
	{
		return $this->save($this->getGroupInstanceByID($this->request->gget('ID')));
	}

	/**
	 * @role update
	 */
	public function delete()
	{
		$this->getGroupInstanceByID($this->request->gget('id'))->delete();
		return new JSONResponse(false, 'success');
	}

	/**
	 * @role update
	 */
	public function sort()
	{
		foreach($this->request->gget($this->request->gget('target'), array()) as $position => $key)
		{
			if(empty($key)) continue;
			$relationship = $this->getGroupInstanceByID($key, false);
			$relationship->position->set((int)$position);
			$relationship->save();
		}

		return new JSONResponse(false, 'success');
	}

	public function edit()
	{
		return new JSONResponse($this->getGroupInstanceByID($this->request->gget('id'))->toArray());
	}

	protected function buildValidator()
	{
		$validator = $this->getValidator(get_class($this) . "Validator", $this->request);

		$validator->addCheck('name', new IsNotEmptyCheck($this->translate('_err_relationship_name_is_empty')));

		return $validator;
	}

	protected function save(ActiveRecordModel $listGroup)
	{
		$validator = $this->buildValidator();
		if ($validator->isValid())
		{
			$listGroup->loadRequestData($this->request);
			$listGroup->save();

			return new JSONResponse(array('ID' => $listGroup->getID(), 'data' => $listGroup->toArray()), 'success');
		}
		else
		{
			return new JSONResponse(array('errors' => $validator->getErrorList()), 'failure');
		}
	}
}

?>