<?php

namespace App\Lib;
use stdClass;

class Miratio
{
    private $token;

    public function getMiratioUrl()
    {
            return "https://facturalaya.com/sys_prueba/api/"; 
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getProducts()
    {
        try {
			$url = $this->getMiratioUrl(); 
			
			$curl = curl_init();
            curl_setopt_array($curl, array(
                    CURLOPT_URL => $this->getMiratioUrl()."get_productos",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                            "Authorization: Bearer ".$this->token
                    ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $products = json_decode($response);

            $response = new stdClass();

            if(isset($products->titulo)) {
                if($products->titulo == 'error') {
                    $response->success = false;
                }
            } else {
                $response->success = true;
                $response->products = $products;
            }

            return $response;

		} catch (ThrowException $e) {
            $this->AstroLog('GET products: ' . $e->getMessage(), false, true, 'error');
            
            $response = new stdClass();
            $response->success = false;
            return $response;
        }
    }

    public function getLogDirectory()
	{
		return base_path().'/logs'. '/';
    }
    
    public function AstroLog( $text = "n/a", $echo = true, $newLine = false, $level = 'info', $obj = null) :bool
	{
	    if(is_string($obj)) {
	        $text .= " : " . $obj;
	    }
	    elseif(!is_null($obj)) {
	        $text .= " : " . json_encode($obj);
	        $isObject = true;
	    }

        $logDirectory = $this->getLogDirectory();
        
        $logger = new \Katzgrau\KLogger\Logger($logDirectory, $level, array (
            'dateFormat' => 'Y-m-d G:i:s', 
        ));
        
        
        $obj = array();
        $level = strtolower($level);
        
        $logger->$level($text, $obj);

        if($echo) {
        	echo $text . ($newLine ? '<br>' : '');
        }

        return false;
	}

}