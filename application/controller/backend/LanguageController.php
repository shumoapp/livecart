<?php

ClassLoader::import("application.controller.backend.abstract.StoreManagementController");
ClassLoader::import("application.model.*");
ClassLoader::import("application.model.system.*");
ClassLoader::import("library.*");

/**
 * Language management
 * Handles adding languages, modifying language definitions (translations), activating and deactivating languages
 *
 * @package application.controller.backend
 * @author Rinalds Uzkalns <rinalds@integry.net>
 * @role admin.site.language
 */
class LanguageController extends StoreManagementController
{
	
	const langFileExt = 'lng';

	/**
	 * Gets definitions from project files and updates them in database.
	 * @return ActionResponse
	 */
	public function update()
	{
		$enLocale = Locale::getInstance('en');
		$enLocale->translationManager()->updateDefinitions();

		return new ActionRedirectResponse($this->request->getControllerName(), 'index');
	}

	/**
	 * Displays definitions edit page.
	 * @return ActionResponse	 
	 */
	public function edit()
	{		
		// preload current locale
		$this->locale;
		
		// get locale instance for the language being translated
		$editLocaleName = $this->request->getValue('id');
		$editLocale = Locale::getInstance($editLocaleName);

		// get all English configuration files
		$enLocale = Locale::getInstance('en');
		$fileDir = ClassLoader::getRealPath('application.configuration.language.en');
		$files = $enLocale->translationManager()->getDefinitionFiles($fileDir);

		// sort files
		usort($files, array($this, 'translationSort'));

		// get currently translated definitions
		$translated = array();
		$enDefs = array();
		foreach ($files as $file)
		{
			$relPath = substr($file, strlen($fileDir) + 1);
			
			// get default English definitions (to get all definition keys)
			$keys = $enLocale->translationManager()->getFileDefs($file);

			$enDefs[$relPath] = $keys;

		  	foreach ($keys as $key => $value)
		  	{
			    $keys[$key] = '';
			}
				  		  			
			// get language default definitions
			$default = $editLocale->translationManager()->getFileDefs($relPath, true);
			if (!is_array($default))
			{
				$default = array();
			}
			
			// get translated definitions
			$transl = $editLocale->translationManager()->getCacheDefs($relPath, true);
						
			// put all definitions together
			$translated[$relPath] = array_merge($keys, $default, $transl);	
		}

/*
		// modifying a single file definitions only
		if ($this->request->isValueSet('file'))
		{
			$file = $this->request->getValue("file");
			$toTranslate = $translated[$file];
			$translated = array();
			$translated[$file] = $toTranslate;
		}		
*/

		// determine which definitions should be displayed (All, defined, undefined)
		$selectedAll = '';
		$selectedDefined = '';
		$selectedNotDefined = '';

		switch ($this->request->getValue("show"))
		{
			case 'all':
				$selectedAll = 'checked';
			break;

			case 'defined':
				$selectedDefined = 'checked';
			break;

			case 'notDefined':
				$selectedNotDefined = 'checked';
			break;
			
			default:
				$selectedAll = 'checked';
			break;
		}

		// arrange files into a hierarchical structure
		$hierarchical = array();
		foreach ($translated as $file => $values)
		{
		  	$file = str_replace(chr(92), '/', $file);
			$path = explode('/', $file);
		  	
			// remove file name from path
			array_pop($path);
		  	
			$last = &$hierarchical;
			foreach ($path as $part)
		  	{
				if (!isset($last[$part]))
				{
					$last[$part] = array();
				}
				$last = &$last[$part];
			}
			$last[$file] = $values;			
		}
		
		$response = new ActionResponse();
		$response->setValue("id", $editLocaleName);
		$response->SetValue("language", $this->request->getValue("language"));
		$response->SetValue("edit_language", $editLocale->info()->getLanguageName($editLocaleName));		
		if ($this->request->isValueSet('saved'))
		{
			$response->SetValue("saved", $editLocale->info()->getLanguageName($editLocaleName));		
		}

		$response->setValue("translations", json_encode($hierarchical));
		$response->setValue("english", json_encode($enDefs));
	
		// navigation
		$response->setValue("selected_all", $selectedAll);
		$response->setValue("selected_defined", $selectedDefined);
		$response->setValue("selected_not_defined", $selectedNotDefined);
		$response->setValue("show", $this->request->getValue("show"));				
		
		$langFileSel = $this->request->getValue('langFileSel');
		if (!$langFileSel)
		{
		  	$langFileSel = '{}';
		}
		$response->setValue("langFileSel", $langFileSel);
		
		return $response;
	}

	/**
	 * Saves translations
	 * @return ActionRedirectResponse
	 */
	public function save()
	{
		// preload current locale
		$this->locale;

		// get locale instance
		$localeCode = $this->request->getValue("id");		
		$editLocale = Locale::getInstance($localeCode);
		
		if (!$editLocale)
		{
		  	throw new ApplicationException('Locale "' . $localeCode .'" not found');
		}
		
		// get submited translation data
		$submitedLang = $this->request->getValue("lang");
		if (!is_array($submitedLang))
		{
		  	$submitedLang = array();
		}
		
		// walk through all files and update definitions
		foreach ($submitedLang as $file => $data)
		{
		  	$file = substr($file, 0, -4);
			$existing = $editLocale->translationManager()->getCacheDefs($file . '.php', true);				
		  	$data = array_merge($existing, $data);
		  	$editLocale->translationManager()->saveCacheData($localeCode . '/' . $file, $data);
		}
		
		// create a common language file for menu
		$menuTranslations = array();
		foreach ($submitedLang as $file => $data)
		{
			if (substr($file, 0, 12) == 'backend/menu')
			{
				$d = $editLocale->translationManager()->getCacheDefs($file, true);
				$menuTranslations = array_merge($menuTranslations, $d);
			}			
		}

		$editLocale->translationManager()->saveCacheData($localeCode . '/' .'menu/menu', $menuTranslations);
			
		return new ActionRedirectResponse($this->request->getControllerName(), 'edit', array('id' => $localeCode, 'query' => 'langFileSel='.$this->request->getValue('langFileSel').'&saved=true'));
	}
	
	/**
	 * Displays main admin page.
	 * @return ActionResponse
	 */
	public function index()
	{
		// get all system languages
		$list = Language::getLanguages()->toArray();
		$countActive = 0;
		foreach($list as $key => $value)
		{
			if ($value["isEnabled"] == 1)
			{
				$countActive++;
			}
			$list[$key]['name'] = $this->locale->info()->getLanguageName($value['ID']);
		}

		$response = new ActionResponse();
		$response->SetValue("language", $this->request->getValue("language"));
		$response->SetValue("languagesList", $list);
		$response->SetValue("count_all", count($list));
		$response->SetValue("count_active", $countActive);

		return $response;
	}
	
	public function addForm()
	{
		// get all Locale languages
		$languagesSelect = $this->locale->info()->getAllLanguages();

		// get all system languages	and remove already added languages from Locale language list
		$list = Language::getLanguages()->toArray();
		foreach($list as $key => $value)
		{
			unset($languagesSelect[$value['ID']]);			
		}

		// sort Locale language list
		asort($languagesSelect);	  	

		$response = new ActionResponse();
		$response->SetValue("languages_select", $languagesSelect);
		return $response;				
	}
	
	/**
	 * Remove a language
	 * @return RawResponse
	 */
	public function delete()
	{  	
		$langId = $this->request->getValue('id');
		
		try
	  	{
			// make sure the language record exists
			$inst = Language::getInstanceById($langId);
			
			$success = $langId;
			
			// make sure it's not the default language
			if (true == $inst->isDefault->get())
				$success = false;
			
			// remove it
			if ($success)
			{
				ActiveRecord::deleteByID('Language', $langId);
			}

		}
		catch (Exception $exc)
		{			  	
		  	$success = false;
		}
		  
		$resp = new RawResponse();
	  	$resp->setContent($success);
		return $resp;
	}

	/**
	 * Save language order
	 * @return RawResponse
	 */
	public function saveOrder()
	{
	  	$order = $this->request->getValue('languageList');
		foreach ($order as $key => $value)
		{
			$update = new ARUpdateFilter();
			$update->setCondition(new EqualsCond(new ARFieldHandle('Language', 'ID'), $value));
			$update->addModifier('position', $key);
			ActiveRecord::updateRecordSet('Language', $update);  	
		}

		$resp = new RawResponse();
	  	$resp->setContent($this->request->getValue('draggedId'));
		return $resp;		  	
	}

	/**
	 * Sets default language.
	 * @return ActionRedirectResponse
	 */
	public function setDefault()
	{
		Language::setDefault($this->request->getValue("id"));  	
		return new ActionRedirectResponse($this->request->getControllerName(), "index");		
	}

	/**
	 * Sets if language is enabled
	 * @return ActionResponse
	 */
	public function setEnabled()
	{
		$id = $this->request->getValue('id');		
		$lang = Language::getInstanceById($id);
		$lang->setAsEnabled($this->request->getValue("status"));
		$lang->save();
		
		$response = new ActionResponse();
		$item = $lang->toArray();
		$item['name'] = $this->locale->info()->getLanguageName($item['ID']);
		$response->setValue('item', $item);

		return $response;		
	}

	/**
	 * Add new language
	 * @return ActionResponse
	 */
	public function add()
	{
		if ($this->request->isValueSet("id"))
		{
			$id = $this->request->getValue("id");
			$lang = Language::getNewInstance($id);
			$lang->save(ActiveRecord::PERFORM_INSERT);

			$response = new ActionResponse();
			$item = $lang->toArray();
			$item['name'] = $this->locale->info()->getLanguageName($item['ID']);
			$response->setValue('item', $item);			
			
			$response->setHeader('Content-type', 'application/xml');
			
			return $response;
		}
	}

	/**
	 * Displays variuos information.
	 * @return ActionResponse
	 */
	public function information()
	{
		$locale = Store::getLocaleInstance();
		
		$response = new ActionResponse();

		//$masyvas["I18Nv2::getInfo()"] = I18Nv2::getInfo();
		$masyvas["\$locale->GetCountries()"] = $locale->info()->GetAllCountries();
		$masyvas["\$locale->GetLanguages()"] = $locale->info()->GetAllLanguages();
		$masyvas["\$locale->GetCurrencies()"] = $locale->info()->GetAllCurrencies();

		$response->SetValue("masyvas", $masyvas);
		return $response;
	}

	/**
	 * Displays system menu for switching active language
	 * @return ActionResponse
	 */
	public function langSwitchMenu()
	{
		$response = new ActionResponse();

		// get all system languages
		$list = Language::getLanguages(true)->toArray();

		foreach($list as $key => $value)
		{
			$list[$key]['name'] = $this->locale->info()->getOriginalLanguageName($value['ID']);
		}
		
		$response->setValue('returnRoute', $this->request->getValue('returnRoute'));
		$response->setValue('languages', $list);
		$response->setValue('currentLanguage', $this->locale->getLocaleCode());
		return $response;
	}

	/**
	 * Changes active language
	 * @return RedirectResponse
	 * @todo Save language preference in User Settings, so the language would be selected automatically for subsequent visits
	 */
	public function changeLanguage() 
	{
		$returnRoute = base64_decode($this->request->getValue('returnRoute'));
		
		$lang = $this->request->getValue('id');
		$langInst = Language::getInstanceById($lang);
		if ($langInst)
		{
			if ('/' == substr($returnRoute, 2, 1))
			{
			  	$returnRoute = $lang . '/' . substr($returnRoute, 3);
			}
			else
			{
			  	$returnRoute = $lang . '/' . $returnRoute;			  
			}
		}		
				
		$url = Router::getInstance()->createUrlFromRoute($returnRoute);
			
		return new RedirectResponse($url);			
	}

	/**
	 * Displays translation dialog menu for Live Translations
	 * @return ActionResponse
	 */
	public function translationDialog()
	{
	  	$id = $this->request->getValue('id');
	  	$file = base64_decode($this->request->getValue('file'));
	  	$translation = $this->locale->translationManager()->getValue($file, $id);
	  		  	
	  	$response = new ActionResponse();
	  	$response->setValue('id', $id);
	  	$response->setValue('file', $file);
	  	$response->setValue('translation', $translation);
	  	return $response;
	}
	
	/**
	 * Saves a single translation entry from Live Translations dialog menu
	 * @return ActionResponse
	 */
	public function saveTranslationDialog()
	{
	  	$id = $this->request->getValue('id');
	  	$file = $this->request->getValue('file');
	  	$translation = $this->request->getValue('translation');

	  	$this->locale->translationManager()->loadCachedFile($this->locale->getLocaleCode() . '/' . $file);

		$res = $this->locale->translationManager()->updateValue($file, $id, $translation);
	  	
	  	return new RawResponse();
	}
	
	private function translationSort($a, $b)
	{
		if ($b == 'menu')
	    {
		  	return -1;
		}

		if ($a == $b) 
		{
			return 0;		  
		}

	    return ($a > $b) ? -1 : 1;
	}
}

?>