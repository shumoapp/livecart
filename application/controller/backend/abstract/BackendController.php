<?php

ClassLoader::import("application.controller.BaseController");
ClassLoader::import("application.helper.*");
ClassLoader::import("application.model.system.Language");
ClassLoader::import("application.model.system.Store");
ClassLoader::import("library.locale.*");
ClassLoader::import("library.json.json");

/**
 * Generic backend controller for administrative tools (actions, modules etc.)
 *
 * @package application.backend.controller.abstract
 */
abstract class BackendController extends BaseController implements LCiTranslator
{
	/**
	 * Locale
	 *
	 * @var Locale
	 */
	protected $locale = null;

	/**
	 * Store instance
	 *
	 * @var Store
	 */
	protected $store = null;

	public function __construct(Request $request)
	{
		parent::__construct($request);

		if (!$this->user->hasAccess($this->getRoleName())) {
			//throw new AccessDeniedException($this->user, $this->request->getControllerName(), $this->request->getActionName());
		}

		$this->store = Store::getInstance();
		$this->store->setRequestLanguage($this->request->getValue('requestLanguage'));
		$this->loadLanguageFiles();

		unset($this->locale);

		Router::setAutoAppendVariables(array('requestLanguage' => $this->store->getLocaleInstance()->getLocaleCode()));
	}

	/**
	 * Translates text using Locale::LCInterfaceTranslator
	 * @param string $key
	 * @return string
	 */
	public function translate($key)
	{
		return $this->locale->translator()->translate($key);
	}

	/**
	 * Performs MakeText translation using Locale::LCInterfaceTranslator
	 * @param string $key
	 * @param array $params
	 * @return string
	 */
	public function makeText($key, $params)
	{
		return $this->locale->translator()->makeText($key, $params);
	}

	/**
	 * Gets a @role tag value in a class and method comments
	 *
	 * @return string
	 * @todo default action and controller name should be defined in one place accessible by all framework parts
	 */
	private final function getRoleName()
	{
		$controllerClassName = get_class($this);
		$actionName = $this->request->getActionName();
		if (empty($actionName))
		{
			$actionName = "index";
		}

		$class = new ReflectionClass($controllerClassName);
		$classDocComment = $class->getDocComment();

		try
		{
			$method = new ReflectionMethod($controllerClassName, $actionName);
			$actionDocComment = $method->getDocComment();
		}
		catch (ReflectionException $e)
		{
			throw new ActionNotFoundException($controllerClassName, $actionName);
		}

		$roleTag = " @role";
		$classRoleMatches = array();
		$actionRoleMatches = array();
		preg_match("/".$roleTag." (.*)(\\r\\n|\\r|\\n)/U", $classDocComment, $classRoleMatches);
		preg_match("/".$roleTag." (.*)(\\r\\n|\\r|\\n)/U", $actionDocComment, $actionRoleMatches);

		$roleValue = "";

		if (!empty($classRoleMatches))
		{
			$roleValue = trim(substr($classRoleMatches[0], strlen($roleTag), strlen($classRoleMatches[0])));
		}
		if (!empty($actionRoleMatches))
		{
			$roleValue .= "." . trim(substr($actionRoleMatches[0], strlen($roleTag), strlen($actionRoleMatches[0])));
		}

		return $roleValue;
	}

	/**
	 * 	Automatically preloads language files
	 *
	 */
	private function loadLanguageFiles()
	{
		$controllerRoot = Classloader::getRealPath('application.controller');

		// get all inherited controller classes
		$class = new ReflectionClass(get_class($this));
		while ($class)
		{
			if ($class->getParentClass()) 
			{
				$file = substr($class->getFileName(), strlen($controllerRoot) + 1);
				$langFiles[] = substr($file, 0, -14);
			}
			$class = $class->getParentClass();
		}
			
		$this->store->setLanguageFiles($langFiles);
	}

	private function __get($name)
	{
		switch ($name)
	  	{
		    case 'locale':
		    	$this->locale = $this->store->getLocaleInstance();
		    	$this->loadLanguageFiles();
				return $this->locale;
		    break;

			default:
		    break;
		}
	}

}

?>