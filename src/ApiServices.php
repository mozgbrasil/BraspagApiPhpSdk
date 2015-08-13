<?php

/**
 * Contains calls to Braspag API.
 *
 * ApiServices description.
 *
 * @version 2.0
 * @author pfernandes
 */
class BraspagApiServices
{
    function __construct(){
        $this->headers = array(
                'MerchantId' => BraspagApiConfig::merchantId,
                'MerchantKey' => BraspagApiConfig::merchantKey
            );

        $this->utils = new BraspagUtils();
    }
    
    /**
     * Creates a sale
    
     * @param Sale $sale 
     * @return mixed
     */
    public function CreateSale(BraspagSale $sale){

        $uri = BraspagApiConfig::apiUri . 'sales'; 

        $request = json_encode($sale, JSON_UNESCAPED_UNICODE);
        
        $response = \Httpful\Request::post($uri)
            ->sendsJson()
            ->addHeaders($this->headers)
            ->body($request)            
            ->send();
        
        if($response->code == BraspagHttpStatus::Created){            
            $responsePayment = $response->body->Payment;

            $sale->payment->paymentId = $responsePayment->PaymentId;
            $sale->payment->status = $responsePayment->Status;
            $sale->payment->reasonCode = $responsePayment->ReasonCode;
            $sale->payment->reasonMessage = $responsePayment->ReasonMessage;
            $sale->payment->currency = $responsePayment->Currency;
            $sale->payment->country = $responsePayment->Country;
            $sale->payment->providerReturnCode = $this->utils->getResponseValue($responsePayment, 'ProviderReturnCode');
            $sale->payment->providerReturnMessage = $this->utils->getResponseValue($responsePayment, 'ProviderReturnMessage');
            
            if($responsePayment->Type == 'CreditCard' || $responsePayment->Type == 'DebitCard'){
                $sale->payment->authenticationUrl = $this->utils->getResponseValue($responsePayment, 'AuthenticationUrl');
                $sale->payment->authorizationCode = $this->utils->getResponseValue($responsePayment, 'AuthorizationCode');
                $sale->payment->acquirerTransactionId = $this->utils->getResponseValue($responsePayment, 'AcquirerTransactionId');
                $sale->payment->proofOfSale = $this->utils->getResponseValue($responsePayment, 'ProofOfSale');

            }elseif($response->body->Payment->Type == 'Boleto'){
                $sale->payment->url = $this->utils->getResponseValue($responsePayment, 'Url');
                $sale->payment->barCodeNumber = $this->utils->getResponseValue($responsePayment, 'BarCodeNumber');
                $sale->payment->digitableLine = $this->utils->getResponseValue($responsePayment, 'DigitableLine');
                $sale->payment->boletoNumber = $this->utils->getResponseValue($responsePayment, 'BoletoNumber');

            }elseif($response->body->Payment->Type == 'EletronicTransfer'){    
                $sale->payment->url = $this->utils->getResponseValue($responsePayment, 'Url');                

            }            

            $recurrentResponse = $this->utils->getResponseValue($responsePayment, 'RecurrentPayment');

            if($recurrentResponse != null){
                $sale->payment->recurrentPayment->recurrentPaymentId = $this->utils->getResponseValue($recurrentResponse, 'RecurrentPaymentId');
                $sale->payment->recurrentPayment->reasonCode = $recurrentResponse->ReasonCode;
                $sale->payment->recurrentPayment->reasonMessage = $recurrentResponse->ReasonMessage;
                $sale->payment->recurrentPayment->nextRecurrency = $this->utils->getResponseValue($recurrentResponse, 'NextRecurrency');
                $sale->payment->recurrentPayment->startDate = $this->utils->getResponseValue($recurrentResponse, 'StartDate');
                $sale->payment->recurrentPayment->endDate = $this->utils->getResponseValue($recurrentResponse, 'EndDate');
                $sale->payment->recurrentPayment->interval = $this->utils->getResponseValue($recurrentResponse, 'Interval');
                $sale->payment->recurrentPayment->link = $this->parseLink($this->utils->getResponseValue($recurrentResponse, 'Link'));
            }

            $sale->payment->links = $this->parseLinks($response->body->Payment->Links);
            
            return $sale;
        }elseif($response->code == BraspagHttpStatus::BadRequest){          
            return $this->getBadRequestErros($response->body);             
        }  
        
        return $response->code;
    }
    
    /**
     * Captures a pre-authorized payment
     * @param GUID $paymentId 
     * @param CaptureRequest $captureRequest 
     * @return mixed
     */
    public function Capture($paymentId, BraspagCaptureRequest $captureRequest){        
        $uri = BraspagApiConfig::apiUri . "sales/{$paymentId}/capture"; 
        
        if($captureRequest != null){
            $uri = $uri . "?amount={$captureRequest->amount}&serviceTaxAmount={$captureRequest->serviceTaxAmount}";
        }
        
        $response = \Httpful\Request::put($uri)
            ->sendsJson()
            ->addHeaders($this->headers)
            ->send();
        
        if($response->code == BraspagHttpStatus::Ok){    
            
            $captureResponse = new BraspagCaptureResponse();
            $captureResponse->status = $response->body->Status;
            $captureResponse->reasonCode = $response->body->ReasonCode;
            $captureResponse->reasonMessage = $response->body->ReasonMessage;
            
            $captureResponse->links = $this->parseLinks($response->body->Links);
            
            return $captureResponse;
            
        }elseif($response->code == BraspagHttpStatus::BadRequest){            
            return $this->getBadRequestErros($response->body);            
        }   
        
        return $response->code;
    }
    
    /**
     * Void a payment
     * @param GUID $paymentId 
     * @param int $amount 
     * @return mixed
     */
    public function Void($paymentId, $amount){
        $uri = BraspagApiConfig::apiUri . "sales/{$paymentId}/void"; 
        
        if($amount != null){
            $uri = $uri . "?amount={$amount}";
        }
        
        $response = \Httpful\Request::put($uri)
            ->sendsJson()
            ->addHeaders($this->headers)
            ->send();
        
        if($response->code == BraspagHttpStatus::Ok){    
            
            $voidResponse = new BraspagVoidResponse();
            $voidResponse->status = $response->body->Status;
            $voidResponse->reasonCode = $response->body->ReasonCode;
            $voidResponse->reasonMessage = $response->body->ReasonMessage;
            
            $voidResponse->links = $this->parseLinks($response->body->Links);
            
            return $voidResponse;
            
        }elseif($response->code == BraspagHttpStatus::BadRequest){            
            return $this->getBadRequestErros($response->body);            
        }   
        
        return $response->code;
    }    
    
    /**
     * Gets a sale
     * @param GUID $paymentId 
     * @return mixed
     */
    public function Get($paymentId){
        $uri = BraspagApiConfig::apiQueryUri . "sales/{$paymentId}"; 
        $response = \Httpful\Request::get($uri)
            ->sendsJson()
            ->addHeaders($this->headers)
            ->send();
        
        if($response->code == BraspagHttpStatus::Ok){    
            $sale = new BraspagSale();
            $sale->merchantOrderId = $response->body->MerchantOrderId;
            $sale->customer = $this->parseCustomer($response->body->Customer);
            $sale->payment = $this->parsePayment($response->body->Payment);
            return $sale;
            
        }elseif($response->code == BraspagHttpStatus::BadRequest){            
            return $this->getBadRequestErros($response->body);            
        }   
        
        return $response->code;
    }
    
    /**
     * Summary of parseLink
     * @param mixed $source 
     * @return BraspagLink
     */
    private function parseLink($source){
        if($source == null) return null;

        $link = new BraspagLink();
        $link->href = $source->Href;
        $link->method = $source->Method;
        $link->rel = $source->Rel;

        return $link;
    }

    private function parseLinks($source){        
        $linkCollection = array();
        
        foreach ($source as $l)
        {
            $link = $this->parseLink($l);            
            array_push($linkCollection, $link);
        }
        
        return $linkCollection;
    }
    
    private function getBadRequestErros($errors){
        
        $badRequestErrors = array();
        
        foreach ($errors as $e)
        {
            $error = new BraspagError();
            $error->code = $e->Code;
            $error->message = $e->Message;
            
            array_push($badRequestErrors, $error);
        }  
        
        return $badRequestErrors;
    }
    
    private function parseCustomer($apiCustomer){
        $customer = new BraspagCustomer();
        (property_exists($apiCustomer,'Name'))?($customer->name = $apiCustomer->Name):('');
        (property_exists($apiCustomer,'Email'))?($customer->email = $apiCustomer->Email):('');
        (property_exists($apiCustomer,'Identity'))?($customer->identity = $apiCustomer->Identity):('');
        (property_exists($apiCustomer,'IdentityType'))?($customer->identityType = $apiCustomer->IdentityType):('');
        (property_exists($apiCustomer,'Birthdate'))?($customer->birthDate = $apiCustomer->Birthdate):('');
        
        if($apiCustomer->Address != null){
            $address = new BraspagAddress();
            (property_exists($apiCustomer->Address,'City'))?($address->city = $apiCustomer->Address->City):('');
            (property_exists($apiCustomer->Address,'Complement'))?($address->complement = $apiCustomer->Address->Complement ):('');
            (property_exists($apiCustomer->Address,'Country'))?($address->country = $apiCustomer->Address->Country):('');
            (property_exists($apiCustomer->Address,'District'))?($address->district = $apiCustomer->Address->District):('');
            (property_exists($apiCustomer->Address,'Number'))?($address->number = $apiCustomer->Address->Number):('');
            (property_exists($apiCustomer->Address,'State'))?($address->state = $apiCustomer->Address->State):('');
            (property_exists($apiCustomer->Address,'Street'))?($address->street = $apiCustomer->Address->Street):('');
            (property_exists($apiCustomer->Address,'ZipCode'))?($address->zipCode = $apiCustomer->Address->ZipCode):('');
            $customer->address = $address;
        }
        
        return $customer;
    }
    
    private function parsePayment($apiPayment){
        $payment = new BraspagPayment();

        if(property_exists($apiPayment,'BarCodeNumber')) {
            $payment = new BraspagBoletoPayment();    
            
            (property_exists($apiPayment,'Instructions'))?($payment->instructions = $apiPayment->Instructions):('');
            (property_exists($apiPayment,'ExpirationDate'))?($payment->expirationDate = $apiPayment->ExpirationDate):('');
            (property_exists($apiPayment,'Demonstrative'))?($payment->demonstrative = $apiPayment->Demonstrative):('');
            (property_exists($apiPayment,'Url'))?($payment->url = $apiPayment->Url):('');
            (property_exists($apiPayment,'BoletoNumber'))?($payment->boletoNumber = $apiPayment->BoletoNumber):('');
            (property_exists($apiPayment,'BarCodeNumber'))?($payment->barcodeNumber = $apiPayment->BarCodeNumber):('');
            (property_exists($apiPayment,'DigitableLine'))?($payment->digitableLine = $apiPayment->DigitableLine):('');
            (property_exists($apiPayment,'Assignor'))?($payment->assignor = $apiPayment->Assignor):('');
            (property_exists($apiPayment,'Address'))?($payment->address = $apiPayment->Address):('');
            (property_exists($apiPayment,'Identification'))?($payment->identification = $apiPayment->Identification):('');
        }
        
        if(property_exists($apiPayment,'CreditCard')){
            $payment = new BraspagCreditCardPayment();
            $payment->installments = $apiPayment->Installments;
            $payment->capture = $apiPayment->Capture;
            $payment->authenticate = $apiPayment->Authenticate;
            $payment->interest = $apiPayment->Interest;
            
            $card = new BraspagCard();
            $card->brand = $apiPayment->CreditCard->Brand;
            $card->cardNumber = $apiPayment->CreditCard->CardNumber;
            $card->expirationDate = $apiPayment->CreditCard->ExpirationDate;
            $card->holder = $apiPayment->CreditCard->Holder;
            $payment->creditCard = $card;
        }
        
        $payment->paymentId = $apiPayment->PaymentId;
        
        (property_exists($apiPayment,'AuthenticationUrl'))?($payment->authenticationUrl = $apiPayment->AuthenticationUrl):('');
        (property_exists($apiPayment,'AuthorizationCode'))?($payment->authorizationCode = $apiPayment->AuthorizationCode):('');
        (property_exists($apiPayment,'AcquirerTransactionId'))?($payment->acquirerTransactionId = $apiPayment->AcquirerTransactionId):('');
        (property_exists($apiPayment,'ProofOfSale'))?($payment->proofOfSale = $apiPayment->ProofOfSale):('');
        (property_exists($apiPayment,'Status'))?($payment->status = $apiPayment->Status):('');
        (property_exists($apiPayment,'ReasonCode'))?($payment->reasonCode = $apiPayment->ReasonCode):('');
        (property_exists($apiPayment,'reasonMessage'))?($payment->reasonMessage = $apiPayment->reasonMessage):('');
        (property_exists($apiPayment,'Amount'))?($payment->amount = $apiPayment->Amount):('');
        (property_exists($apiPayment,'Carrier'))?($payment->carrier = $apiPayment->Carrier):('');
        (property_exists($apiPayment,'Country'))?($payment->country = $apiPayment->Country):('');
        (property_exists($apiPayment,'Currency'))?($payment->currency = $apiPayment->Currency):('');
        
        $payment->links = $this->parseLinks($apiPayment->Links);
        
        return $payment;
    }
    
    /**     
     * Debug Function
     * @param Sale $debug,$title 
     * @return standardoutput
     * @autor interatia
     */
    public function debug($debug,$title="Debug:")
    {
        echo "<hr/>";
        echo "<h2>Start: $title</h2>";
        echo '<textarea cols="100" rows="50">';    
        print_r($debug);
        echo "</textarea>";
        echo "<h2>End: $title</h2>";
        echo "<hr/>";
    }   
}
