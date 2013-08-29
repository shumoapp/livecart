<?php


/**
 * Product Category Image controller
 *
 * @package application/controller/backend
 * @author Integry Systems
 *
 * @role category
 */
class CategoryImageController extends ObjectImageController
{
	public function indexAction()
	{
		return parent::index();
	}

	/**
	 * @role update
	 */
	public function uploadAction()
	{
		return parent::upload();
	}

	/**
	 * @role update
	 */
	public function saveAction()
	{
		return parent::save();
	}

	public function resizeImagesAction()
	{
		return parent::resizeImages();
	}

	/**
	 * @role update
	 */
	public function deleteAction()
	{
		if(parent::delete())
		{
			return new JSONResponse(false);
		}
		else
		{
			return new JSONResponse(false, 'failure', $this->translate('_could_not_remove_category_image'));
		}
	}

	/**
	 * @role sort
	 */
	public function saveOrderAction()
	{
		parent::saveOrder();

		return new JSONResponse(true);
	}

	protected function getModelClass()
	{
		return 'CategoryImage';
	}

	protected function getOwnerClass()
	{
		return 'Category';
	}

	protected function getForeignKeyName()
	{
		return 'categoryID';
	}

}

?>