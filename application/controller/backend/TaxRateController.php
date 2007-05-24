<?php
ClassLoader::import("application.controller.backend.abstract.StoreManagementController");
ClassLoader::import("application.model.tax.TaxRate");
ClassLoader::import("framework.request.validator.RequestValidator");
ClassLoader::import("framework.request.validator.Form");
		
		
/**
 * Application settings management
 *
 * @package application.controller.backend
 *
 * @role delivery
 */
class TaxRateController extends StoreManagementController
{
	public function index() 
	{
	    if(($zoneID = (int)$this->request->getValue('id')) <= 0)
	    {
	        $deliveryZone = null;
	        $deliveryZoneArray = array('ID' => '');
	        $taxRatesArray = TaxRate::getRecordSetByDeliveryZone($deliveryZone)->toArray();
	    }
	    else
	    {
	        $deliveryZone = DeliveryZone::getInstanceByID($zoneID, true);
	        $deliveryZoneArray = $deliveryZone->toArray();
	        $taxRatesArray = $deliveryZone->getTaxRates()->toArray();
	    }
	    
	      
		$form = $this->createTaxRateForm();
		$enabledTaxes = array();
		foreach(Tax::getTaxes(false, $deliveryZone)->toArray() as $tax)
		{
		    $enabledTaxes[$tax['ID']] = $tax['name'];
		}
		
		
		$response = new ActionResponse();
		$response->setValue('enabledTaxes', $enabledTaxes);
		$response->setValue('defaultLanguageCode', $this->store->getDefaultLanguageCode());
		$response->setValue('taxRates', $taxRatesArray);
		$response->setValue('alternativeLanguagesCodes', $this->store->getLanguageSetArray(false, false));
		$response->setValue('newTaxRate', array('ID' => '', 'DeliveryZone' => $deliveryZoneArray));
		$response->setValue('deliveryZone', $deliveryZoneArray);
	    $response->setValue('form', $form);
	    return $response;
	}

	/**
	 * @role update
	 */
    public function delete()
    {
        $taxRate = TaxRate::getInstanceByID((int)$this->request->getValue('id'), true, array('Tax'));
        $tax = $taxRate->tax->get();
        $taxRate->delete();
        
        return new JSONResponse(array('status' => 'success', 'tax' => $tax->toArray()));
    }
    
    public function edit()
    {
	    $rate = TaxRate::getInstanceByID((int)$this->request->getValue('id'), true, array('Tax'));
		
	    $form = $this->createTaxRateForm();
		$form->setData($rate->toArray());
		
		$response = new ActionResponse();
		$response->setValue('defaultLanguageCode', $this->store->getDefaultLanguageCode());
		$response->setValue('alternativeLanguagesCodes', $this->store->getLanguageSetArray(false, false));
		$response->setValue('taxRate', $rate->toArray());
	    $response->setValue('form', $form);
	    
	    return $response;
    }
    
	/**
	 * @role update
	 */
    public function create()
    {
        if(($deliveryZoneId = (int)$this->request->getValue('deliveryZoneID')) > 0)
        {
            $deliveryZone = DeliveryZone::getInstanceByID($deliveryZoneId, true);
        }
        else
        {
            $deliveryZone = null;
        }
        
        $taxRate = TaxRate::getNewInstance($deliveryZone, Tax::getInstanceByID((int)$this->request->getValue('taxID'), true), (float)$this->request->getValue('rate'));
        
        return $this->save($taxRate);
    }
    
	/**
	 * @role update
	 */
    public function update()
    {
        $taxRate = TaxRate::getInstanceByID($taxRateID, true);
        
        return $this->save($taxRate);
    }
    
	/**
	 * @role update
	 */
    public function save(TaxRate $taxRate)
    {
        $validator = $this->createTaxRateFormValidator();
        if($validator->isValid())
        {          
	        $taxRate->setValueArrayByLang(array('name'), $this->store->getDefaultLanguageCode(), $this->store->getLanguageArray(true, false), $this->request);      
		    $taxRate->save();
	            
            return new JSONResponse(array('status' => 'success', 'rate' => $taxRate->toArray()));
        }
        else
        {
            return new JSONResponse(array('status' => 'failure', 'errors' => $validator->getErrorList()));
        }
    }
	
	/**
	 * @return Form
	 */
	private function createTaxRateForm()
	{
		return new Form($this->createTaxRateFormValidator());
	}
	
	/**
	 * @return RequestValidator
	 */
	private function createTaxRateFormValidator()
	{	
		$validator = new RequestValidator('shippingService', $this->request);
		
		$validator->addCheck("taxID", new IsNotEmptyCheck($this->translate("_error_tax_should_not_be_empty")));
		$validator->addCheck("rate", new IsNotEmptyCheck($this->translate("_error_rate_should_not_be_empty")));
		$validator->addCheck("rate", new IsNumericCheck($this->translate("_error_rate_should_be_numeric_value")));
		$validator->addCheck("rate", new MinValueCheck($this->translate("_error_rate_should_be_greater_than_zero_and_less_than_hundred"), 0));
		$validator->addCheck("rate", new MaxValueCheck($this->translate("_error_rate_should_be_greater_than_zero_and_less_than_hundred"), 100));
		
		return $validator;
	}	
	
}
?>