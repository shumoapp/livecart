<?php

/**
 * Application settings management
 *
 * @package application/controller/backend
 * @author Integry Systems
 * @role userGroup
 */
class RolesController extends StoreManagementController
{
	public function indexAction()
	{
		Role::cleanUp();

		$userGroupID = (int)$this->request->get('id');


		$userGroup = UserGroup::getInstanceByID($userGroupID);
		$activeRoles = $userGroup->getRolesRecordSet();

		$roles = array();
		$parentID = 0;

		// sort roles by their appearance in backend menu
		$filter = new ARSelectFilter();
		foreach (array('product', 'category', 'option', 'filter', 'order', 'user') as $roleName)
		{
			$filter->order(new ARExpressionHandle('(Role.name LIKE "' . $roleName . '%")'), 'DESC');
		}

		// disabled roles
		$disable = $this->config->getPath('storage/configuration/DisabledRoles') . '.php';
		if (file_exists($disable))
		{
			$disabledRoles = include $disable;
			foreach ($disabledRoles as $disabled)
			{
				$filter->mergeCondition(new NotEqualsCond(new ARFieldHandle('Role', 'name'), $disabled));
			}
		}

		$menu = new MenuLoader($this->application);
		$menuItems = $menu->getCurrentHierarchy('', '');
		//print_r($menuItems);
		$menuItems = $menuItems['items'];

		foreach ($menuItems as $topLevel)
		{
			if (isset($topLevel['items']))
			{
				foreach ($topLevel['items'] as $item)
				{
					if (isset($item['role']))
					{
						$filter->order(new ARExpressionHandle('(Role.name LIKE "' . $item['role'] . '%")'), 'DESC');
					}
				}
			}
		}

		foreach(Role::getRecordSet($filter) as $role)
		{
			$roleArray = $role->toArray();

			if ('login' == $roleArray['name'])
			{
				continue;
			}

			$roleArray['indent'] = strpos($roleArray['name'], '.') ? 1 : 0;
			if($roleArray['indent'] > 0)
			{
				$rc = count($roles) - 1;
				if(isset($roles[$rc]) && $roles[$rc]['parent'] === 0)
				{
					$parentID = 'smart-' . $roles[$rc]['ID'];

					$roles[] = array(
						'ID' => $roles[$rc]['ID'],
						'name' => $roles[$rc]['name'] . '.misc',
						'translation' => $this->translate('_role_' . strtolower($roles[$rc]['name']) . '_misc'),
						'parent' => $parentID,
						'indent' => 1
					);

					$roles[$rc]['ID'] = $parentID;
				}
				$roleArray['parent'] = $parentID;
			}
			else
			{
				$parentID = $roleArray['ID'];
				$roleArray['parent'] = 0;
			}

			$roleArray['translation'] = $this->translate(strtolower("_role_" . str_replace('.', '_', $roleArray['name'])));
			$roles[] = $roleArray;
		}

		$activeRolesIDs = array();
		foreach($activeRoles as $role)
		{
			$activeRolesIDs[] = $role->getID();
		}

		$form = $this->createRolesForm($userGroup, $activeRoles);


		$this->set('form', $form);

		$this->set('roles', $roles);
		$this->set('userGroup', $userGroup->toArray());
		
		
		$this->set('activeRolesIDs', $activeRolesIDs);
		$disabledRolesIDs = $this->getRolesWithDisabledCheckboxes($roles, $userGroupID);
		$this->set('disabledRolesIDs', $disabledRolesIDs); // show, but with disabled checkboxes

	}

	/**
	 * Saves changes to current group roles
	 *
	 * @role permissions
	 */
	public function updateAction()
	{
		$userGroupID = (int)$this->request->get('id');
		$userGroup = UserGroup::getInstanceByID($userGroupID, UserGroup::LOAD_DATA);

		// disabled roles
		$disable = $this->config->getPath('storage/configuration/DisabledRoles') . '.php';
		if (file_exists($disable))
		{
			$disabledRoles = include $disable;
			foreach ($disabledRoles as $disabled)
			{
				$c = new EqualsCond(new ARFieldHandle('Role', 'name'), $disabled);

				if (!isset($cond))
				{
					$cond = $c;
				}
				else
				{
					$cond->addOr($c);
				}
			}

			if (isset($cond))
			{
				$disabled = array();
				foreach (ActiveRecordModel::getRecordSetArray('Role', new ARSelectFilter($cond)) as $role)
				{
					$disabled[$role['ID']] = true;
				}
			}
		}

		if (!isset($disabled))
		{
			$disabled = array();
		}

		$validator = $this->createRolesFormValidator($userGroup);
		if($validator->isValid())
		{
			foreach(explode(',', $this->request->get('checked')) as $roleID)
			{
				if (preg_match('/smart/', $roleID) || isset($disabled[$roleID]))
				{
					continue;
				}

				$role = Role::getInstanceByID((int)$roleID);
				if (count( $this->getRolesWithDisabledCheckboxes(array($role->toArray()), $userGroupID) ) != 0)
				{
					continue; // role with disabled checkbox.
				}
				$userGroup->applyRole($role);
			}

			foreach(explode(',', $this->request->get('unchecked')) as $roleID)
			{
				if (preg_match('/smart/', $roleID) || isset($disabled[$roleID]))
				{
					continue;
				}
				$role = Role::getInstanceByID((int)$roleID);
				if (count( $this->getRolesWithDisabledCheckboxes(array($role->toArray()), $userGroupID) ) != 0)
				{
					continue; // role with disabled checkbox.
				}
				$userGroup->cancelRole($role);
			}

			$userGroup->save();

			return new JSONResponse(false, 'success', $this->translate('_group_permissions_were_successfully_updated'));
		}
		else
		{
			return new JSONResponse(array('errors' => $validator->getErrorList()), 'failure', '_could_not_update_group_permissions');
		}
	}

	private function createRolesForm(UserGroup $userGroup, ARSet $activeRoles)
	{
		$form = new Form($this->createRolesFormValidator($userGroup));

		$userGroupID = $userGroup->getID();
		$activeRolesCheckboxes = array();
		foreach($activeRoles as $role)
		{
			$activeRolesCheckboxes['role_' . $role->getID()] = 1;
		}

		$form->setData($activeRolesCheckboxes);

		return $form;
	}

	private function createRolesFormValidator(UserGroup $userGroup)
	{
		return $this->getValidator('roles_' . $userGroup->getID(), $this->request);
	}

	private function getRolesWithDisabledCheckboxes($roles, $groupId)
	{
		$adminGroupId = 1; // ~

		$disabledRolesIDs = array();
		if ($adminGroupId == $groupId) // for Administrators
		{
			foreach ($roles as $role)
			{
				// diasable User Groups checkboxes
				if (substr($role['name'], 0, 10) == 'userGroup.' || $role['name'] == 'userGroup')
				{
					$disabledRolesIDs[] = $role['ID'];
				}
			}
		}
		return $disabledRolesIDs;
	}
}
?>