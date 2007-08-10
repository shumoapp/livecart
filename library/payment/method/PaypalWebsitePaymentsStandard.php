<?php

include_once(dirname(__file__) . '/../abstract/ExternalPayment.php');

class PaypalWebsitePaymentsStandard extends ExternalPayment
{
    public function getUrl()
    {
        $url = 'https://www.' . ($this->getConfigValue('SANDBOX') ? 'sandbox.' : '') . 'paypal.com/cgi-bin/webscr';
        
        $params = array();
        $params['cmd'] = '_xclick';
        $params['business'] = $this->getConfigValue('EMAIL');
        $params['item_name'] = $this->getConfigValue('ITEM_NAME');
        $params['amount'] = $this->details->amount->get();
        $params['mc_currency'] = $this->details->currency->get();
        $params['custom'] = $this->details->invoiceID->get();
        $params['return'] = $this->getConfigValue('RETURN_URL');
        $params['notify_url'] = $this->getConfigValue('NOTIFY_URL');
        
        $pairs = array();
        foreach ($params as $key => $value)
        {
            $pairs[] = $key . '=' . urlencode($value);
        }
        
        return $url . '?' . implode('&', $pairs);
    }
    
    public function notify($requestArray)
    {
        file_put_contents('cache/ipn.txt', var_export($requestArray, true));
        exit;
        
        // assign posted variables to local variables
        $paymentStatus = $_POST['payment_status'];
        $paymentAmount = $_POST['mc_gross'];
        $paymentCurrency = $_POST['mc_currency'];
        $txn_id = $_POST['txn_id'];
        $receiverEmail = $_POST['receiver_email'];
        $payerEmail = $_POST['payer_email'];

        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-validate';

        foreach ($_POST as $key => $value) 
        {
            $value = urlencode(stripslashes($value));
            $req .= "&".$key."=".$value;
        }

        // check that receiver_email is your Primary PayPal email
        if ($receiverEmail != $this->getConfigValue('EMAIL'))
        {
            throw new PaymentException('Invalid PayPal receiver e-mail');
        }

        // check that payment_amount/payment_currency are correct
        if ($paymentCurrency != $this->details->currency->get())
        {
            throw new PaymentException('Payment currency does not match order currency');
        }

        // post back to PayPal system to validate
        $header .= "POST /cgi-bin/webscr HTTP/1.0\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
        $fp = fsockopen ('www.' . ($this->getConfigValue('SANDBOX') ? 'sandbox.' : '') . 'paypal.com', 80, $errno, $errstr, 30);

        if (!$fp) 
        {
            throw new PaymentException('Could not connect to PayPal server');
        } 
        else 
        {
            fputs ($fp, $header . $req);

            while (!feof($fp)) 
            {
                $res = fgets ($fp, 1024);
                if (strcmp ($res, "VERIFIED") == 0) 
                {
                    if ($paymentStatus != 'Completed')
                    {
                        throw new PaymentException('Payment is not completed');
                    }
                }
                else if (strcmp ($res, "INVALID") == 0) 
                {
                    throw new PaymentException('Invalid response from PayPal');                  
                }
            }
            
            fclose ($fp);
        }
        
        return true;
    }
    
    public static function isVoidable()
    {
        return false;
    }
    
    public function void()
    {
        return false;
    }
}

?>