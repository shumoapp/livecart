<?php


/**
 *
 * @package application/controller/backend
 * @author Integry Systems
 *
 * @role currency
 */
class CurrencyController extends StoreManagementController
{
	/**
	 * List all system currencies
	 * @return ActionResponse
	 */
	public function indexAction()
	{
		$filter = new ARSelectFilter();
		$filter->setOrder(new ARFieldHandle('Currency', 'position'), 'ASC');

		$curr = ActiveRecord::getRecordSet("Currency", $filter, true)->toArray();

		$response = new ActionResponse();
		$response->set("currencies", json_encode($curr));

		return $response;
	}

	/**
	 * Displays form for adding new currency
	 *
	 * @role create
	 * @return ActionRedirectResponse
	 */
	public function addFormAction()
	{
		$currencies = $this->locale->info()->getAllCurrencies();

		foreach ($currencies as $key => $currency)
		{
		  	$currencies[$key] = $key . ' - ' . $currency;
		}

		// remove already added currencies from list
		$addedCurrencies = $this->getCurrencySet();
		foreach ($addedCurrencies as $currency)
		{
			unset($currencies[$currency->getID()]);
		}

		unset($currencies[$this->application->getDefaultCurrencyCode()]);

		$response = new ActionResponse();
		$response->set('currencies', $currencies);
		return $response;
	}

	/**
	 * @role create
	 */
	public function addAction()
	{
		try
		{
			$newCurrency = ActiveRecord::getNewInstance('Currency');
			$newCurrency->setId($this->request->gget('id'));
			$config = $this->getApplication()->getConfig();
			// if can use external currency rate source
			if ($config->get('CURRENCY_RATE_UPDATE'))
			{
				// get exchange rate from external currency rate source
				$source = CurrencyRateSource::getInstance($this->application,null,array($newCurrency->getID()));
				$rate = $source->getRate($newCurrency->getID());
				if ($rate != null)
				{
					$newCurrency->rate->set($rate);
				}
			}
			$newCurrency->save(ActiveRecord::PERFORM_INSERT);

			return new JSONResponse($newCurrency->toArray());
		}
		catch (Exception $exc)
		{
			return new JSONResponse(0);
		}
	}

	/**
	 * Sets default currency.
	 * @role status
	 * @return ActionRedirectResponse
	 */
	public function setDefaultAction()
	{
		try
		{
			$r = ActiveRecord::getInstanceByID('Currency', $this->request->gget('id'), true);
		}
		catch (ARNotFoundException $e)
		{
			return new ActionRedirectResponse('backend.currency', 'index');
		}

		ActiveRecord::beginTransaction();

		$update = new ARUpdateFilter();
		$update->addModifier('isDefault', 0);
		ActiveRecord::updateRecordSet('Currency', $update);

		$r->setAsDefault(true);
		$r->save();

		$config = $this->getApplication()->getConfig();
		if ($config->get('CURRENCY_RATE_UPDATE'))
		{
			$source = CurrencyRateSource::getInstance($this->application, $r->getID());

			foreach($source->getAllCurrencyCodes() as $currencyCode)
			{
				$rate = $source->getRate($currencyCode);
				if ($rate != null)
				{
					$currency = Currency::getInstanceById($currencyCode);
					$currency->rate->set($rate);
					$currency->lastUpdated->set(date('Y-m-d H:i:s', time()));
					$currency->save();
				}
			}
		}

		ActiveRecord::commit();

		return new ActionRedirectResponse('backend.currency', 'index');
	}

	/**
	 * Save currency order
	 * @role sort
	 * @return RawResponse
	 */
	public function saveOrderAction()
	{
	  	$order = $this->request->gget('currencyList');
		foreach ($order as $key => $value)
		{
			$update = new ARUpdateFilter();
			$update->setCondition(new EqualsCond(new ARFieldHandle('Currency', 'ID'), $value));
			$update->addModifier('position', $key);
			ActiveRecord::updateRecordSet('Currency', $update);
		}

		return new JSONResponse(false, 'success');
	}

	/**
	 * Sets if currency is enabled
	 * @role status
	 * @return ActionResponse
	 */
	public function setEnabledAction()
	{
		$id = $this->request->gget('id');
		$curr = ActiveRecord::getInstanceById('Currency', $id, true);
		$curr->isEnabled->set((int)(bool)$this->request->gget("status"));
		$curr->save();


		return new JSONResponse(array('currency' => $curr->toArray()), 'success');
	}

	/**
	 * Remove a currency
	 * @role remove
	 * @return RawResponse
	 */
	public function deleteAction()
	{
		try
	  	{
			$success = Currency::deleteById($this->request->gget('id'));
			return new JSONResponse(false, 'success');
		}
		catch (Exception $exc)
		{
			return new JSONResponse(false, 'failure');
		}
	}

	public function editAction()
	{
		$currency = Currency::getInstanceByID($this->request->gget('id'), Currency::LOAD_DATA);

		$form = new Form($this->buildFormattingValidator());
		$form->setData($currency->toArray());

		$response = new ActionResponse();
		$response->set('form', $form);
		$response->set('id', $this->request->gget('id'));
		$response->set('currency', $currency->toArray());
		return $response;
	}

	/**
	 * @role update
	 */
	public function saveAction()
	{
		$currency = Currency::getInstanceByID($this->request->gget('id'), Currency::LOAD_DATA);
		$currency->loadRequestData($this->request);
		$currency->rounding->set(serialize(json_decode($this->request->gget('rounding'), true)));
		$currency->save();

		return new JSONResponse(false, 'success');
	}

	/**
	 * Currency rates form
	 * @return ActionResponse
	 */
	public function ratesAction()
	{
		$currencies = $this->getCurrencySet()->toArray();
		$form = $this->buildForm($currencies);

		foreach ($currencies as $currency)
		{
			$form->set('rate_' . $currency['ID'], $currency['rate']);
		}

		$response = new ActionResponse();
		$response->set('currencies', $currencies);
		$response->set('saved', $this->request->gget('saved'));
		$response->set('rateForm', $form);
		$response->set('defaultCurrency', $this->application->getDefaultCurrency()->getID());
		return $response;
	}

	/**
	 * Change currency options
	 * @role update
	 * @return ActionResponse
	 */
	public function optionsAction()
	{
		$form = new Form($this->buildOptionsValidator());
		$form->set('updateCb', $this->config->get('currencyAutoUpdate'));
		$form->set('frequency', $this->config->get('currencyUpdateFrequency'));

		// get all feeds
		$dir = new DirectoryIterator(ClassLoader::getRealPath('library/currency'));
		foreach ($dir as $file) {
			$p = pathinfo($file->getFilename());
			if ($p['extension'] == 'php')
			{
				include_once($file->getPathName());
				$className = basename($file->getFilename(), '.php');
				$classInfo = new ReflectionClass($className);
				if (!$classInfo->isAbstract())
				{
					$feeds[$className] = call_user_func(array($className, 'getName'));
				}
			}
		}

		// get currency settings
		$currencies = $this->getCurrencySet()->toArray();

		$settings = $this->config->get('currencyFeeds');

		foreach ($currencies as $id => &$currency)
		{
			if (isset($settings[$currency['ID']]))
			{
			  	$form->set('curr_' . $currency['ID'], $settings[$currency['ID']]['enabled']);
			  	$form->set('feed_' . $currency['ID'], $settings[$currency['ID']]['feed']);
			}
		}

		$frequency = array();
		foreach (array(15, 60, 240, 1440) as $mins)
		{
			$frequency[$mins] = $this->translate('_freq_' . $mins);
		}

		$response = new ActionResponse();
		$response->set('form', $form);
		$response->set('currencies', $currencies);
		$response->set('frequency', $frequency);
		$response->set('feeds', $feeds);
		return $response;
	}

	/**
	 * @role update
	 */
	public function saveOptionsAction()
	{
		$val = $this->buildOptionsValidator();

		// main update setting
		$this->setConfigValue('currencyAutoUpdate', $this->request->gget('updateCb'));

		// frequency
		$this->setConfigValue('currencyUpdateFrequency', $this->request->gget('frequency'));

		// individual currency settings
		$setting = $this->config->get('currencyFeeds');
		if (!is_array($setting))
		{
		  	$setting = array();
		}
		$currencies = $this->getCurrencySet();
		foreach ($currencies as $currency)
		{
			$setting[$currency->getID()] = array('enabled' => $this->request->gget('curr_' . $currency->getID()),
												 'feed' => $this->request->gget('feed_' . $currency->getID())
												);
		}
		$this->setConfigValue('currencyFeeds', $setting);

		$this->config->save();

		return new JSONResponse(1);
	}

	/**
	 * Saves currency rates.
	 * @role update
	 * @return JSONResponse
	 */
	public function saveRatesAction()
	{
		$currencies = $this->getCurrencySet();

		// save rates
		if($this->buildValidator($currencies->toArray())->isValid())
		{
			foreach($currencies as &$currency)
			{
				$currency->rate->set($this->request->gget('rate_' . $currency->getID()));
				$currency->save();
			}
		}

		// read back from DB
		$currencies = $this->getCurrencySet();
		$values = array();

		foreach($currencies as &$currency)
		{
			$values[$currency->getID()] = $currency->rate->get();
		}

		return new JSONResponse(array('values' => $values), 'success', $this->translate('_rate_save_conf'));
	}

	private function getCurrencySet()
	{
		$filter = new ARSelectFilter();
		$filter->setCondition(new NotEqualsCond(new ARFieldHandle("Currency", "isDefault"), 1));
		$filter->setOrder(new ARFieldHandle("Currency", "isEnabled"), 'DESC');
		$filter->setOrder(new ARFieldHandle("Currency", "position"), 'ASC');
		return ActiveRecord::getRecordSet('Currency', $filter);
	}

	private function buildOptionsValidator()
	{
		return $this->getValidator("currencySettings", $this->request);
	}

	private function buildFormattingValidator()
	{
		$validator = $this->getValidator("priceFormatting", $this->request);
		$validator->addFilter('decimalCount', new NumericFilter());
		return $validator;
	}

	/**
	 * Builds a currency form validator
	 *
	 * @return RequestValidator
	 */
	private function buildValidator($currencies)
	{
		$validator = $this->getValidator("rate", $this->request);
		foreach ($currencies as $currency)
		{
			$validator->addCheck('rate_' . $currency['ID'], new IsNotEmptyCheck($this->translate('_err_empty')));
			$validator->addCheck('rate_' . $currency['ID'], new IsNumericCheck($this->translate('_err_numeric')));
			$validator->addCheck('rate_' . $currency['ID'], new MinValueCheck($this->translate('_err_negative'), 0));
			$validator->addFilter('rate_' . $currency['ID'], new NumericFilter());
		}

		return $validator;
	}

	/**
	 * Builds a currency form instance
	 *
	 * @return Form
	 */
	private function buildForm($currencies)
	{
		return new Form($this->buildValidator($currencies));
	}
}

?>