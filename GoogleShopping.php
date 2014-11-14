<?php

require 'simple_html_dom.php';

class GoogleShopping {
    
    protected $ean;
    protected $results;
    protected $url = 'https://www.google.nl';
    protected $urlsearch = '/search?hl=nl&output=search&tbm=shop&q=';
    protected $urlproduct = '/shopping/product/';
    protected $useragent = 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)';
    protected $referer;
    protected $products = array();
    protected $prices = array();
    
    public function getPrices($ean) {
        
        $this->ean = $ean;
       
        try{
            $this->checkEAN();
            $this->searchForProduct();
            $this->fetchProductPrices();
            
            return $this->prices;
        }
        catch (Exception $e){
            //TODO: return exception message instead of false only?
            //print_r($e);
            return false;
        }
        
    }
    
    private function checkEAN(){
        //padding nulls before string to fill 14 characters
        if(strlen($ean) < 14){
           $this->ean = str_pad($this->ean,14,"0",STR_PAD_LEFT);
        }
        else if(strlen($ean) > 14)
        {
            throw new Exception('Invalid EAN length');
        }
    }
    
    private function searchForProduct(){
        //append to url ean
        $searchUrl = $this->url . $this->urlsearch .  $this->ean;
        $this->referer = $searchUrl;
        $results = $this->httpFetcher($searchUrl);
        $dom = str_get_html($results);
                
        foreach($dom->find('ol.product-results li.psli div.pslicont div.pslimg div.overlay-container a.psliimg div') as $element) {            
            //only non empty attributes
            if($element->getAttribute('data-cid')){
            array_push($this->products,$element->getAttribute('data-cid'));
            }
        }
        
        if(!$this->products){
            throw new Exception('Cannot fetch any products with that EAN'); 
        }
        
    }
    
    private function fetchProductPrices(){
        $productUrl = $this->url . $this->urlproduct . $this->products[0] . '/online?hl=nl';
        $results = $this->httpFetcher($productUrl);
        $dom = str_get_html($results);
         
        foreach($dom->find('table.os-main-table tbody tr.os-row') as $element){
            $seller = $element->find('td span.os-seller-name-primary a',0)->plaintext;
            $price = trim($element->find('td.os-total-col',0)->plaintext);
            //it exists in price column without calculation.
            if($price == ""){
                $price = $element->find('td.os-price-col',0)->plaintext; 
            }
            //parse price to change , chars and get float value only with regex.
            $price = str_replace(",", ".", $price);
            $price = preg_match_all('!\d+(?:\.\d+)?!', $price, $match);
            $price = $match[0][0];
            $this->prices[] = array('seller' => $seller, 'price' => $price);
        }
        
        if(!$this->prices){
            throw new Exception('Cannot fetch product prices'); 
        }
        
    }
    
    private function httpFetcher($url){
        if(!function_exists(curl_init)){
            throw new Exception('Cannot Find CURL Library');
        }
        
        $s = curl_init();
        curl_setopt($s,CURLOPT_URL,$url); 

        curl_setopt($s,CURLOPT_RETURNTRANSFER,true); 
        curl_setopt($s,CURLOPT_FOLLOWLOCATION,true); 
        curl_setopt($s,CURLOPT_USERAGENT,$this->useragent); 
        curl_setopt($ch, CURLOPT_ENCODING ,"");
        if($this->referer)
        curl_setopt($s,CURLOPT_REFERER,$this->referer); 

        $results = curl_exec($s);
        $status = curl_getinfo($s,CURLINFO_HTTP_CODE); 
        
        curl_close($s); 
        
        if($status != 200){
            throw new Exception('Url Request response with error : ' . $status);
        }
        
        return $results;
        
    }
}