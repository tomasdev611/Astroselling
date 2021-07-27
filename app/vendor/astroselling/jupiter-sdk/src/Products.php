<?php

namespace astroselling\Jupiter;

class Products
{
    protected $version = "Jupiter SDK v1.09";
    protected $url;
    protected $token;
    protected $logPath;
    protected $echo;

    
    /**
     * Create a new Jupiter API Client with provided API keys
     *
     * @param string $apiUserName
     * @param string $apiUserKey
     */
    public function __construct(string $url = '', string $apiToken = '', string $logPath = '', bool $echo = false)
    {
        $this->url = $url;
        $this->token = $apiToken;
        $this->logPath = $logPath;
        $this->echo = $echo;
    }


    /**
     * Display SDK version
     *
     * @return void
     */    
    public function version() :string
    {
        return $this->version;
    }


    public function getUrl() :string
    {
        return $this->url; 
    }

    public function getApiToken() :string
    {
        return $this->token;
    }

    public function sendRequest($url, $header = '', $content = '', $type = 'POST', $xml = false)  :object
    {       
        ini_set('max_execution_time', 3000); 
        ini_set('memory_limit', '1024M');
        
        $result = new \stdClass();
                
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        if($content) {          
            if(!$xml) {
                $fields = json_encode($content, JSON_UNESCAPED_UNICODE);
            }   
            else {
                curl_setopt($curl,CURLOPT_POST, count($content));
                $fields = http_build_query($content);
            }
            
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        }
        
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);

        if($xml) {
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);          
        }

        if($header) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }   
            
        $response = curl_exec($curl);
        $error    = curl_error($curl);
            
        if($error || !$response) {     
            $result = new \stdClass();       
            $result->error = $error;
        }
        else {
            $curlResponse = json_decode($response);
            //print_r($curlResponse);
            if(!is_object($curlResponse)) {
                $result->data = (object) $curlResponse;
            }         
            else {
                $result = $curlResponse;
            }  
        }

        // keep http code
        $result->httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);

        return $result;
    }

    public function getHeader() : array
    {

        return array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json",
                     "Accept: application/json"
                    );
    }
    

    public function  getChannels() 
    {
        $channels = array();

        try {
            $action = "channels?api_token=" . $this->getApiToken();
            $url = $this->getUrl() . $action; 

            $header = $this->getHeader();
            $content = array();
            $channels = $this->sendRequest($url, $header, $content, 'GET');
            
            $httpCode = $channels->httpcode ?? 500;       
            if($httpCode == 200) {
                $updated = true;
            }
            else {
                $this->saveLog('GET channel', $channels, 'error');
            }

        } catch (ThrowException $e) {
           $this->saveLog('GET channel', $e->getMessage(), 'alert');
        }

        return $channels;
    }


    public function hasChannel(string $channel) :bool
    {
        $exist = false;

        $channels = $this->getChannels();

        if(isset($channels->data)) {
            foreach ($channels->data as $ch) {
                if($ch->id == $channel) {
                    $exist = true;
                    break;
                }
            }
        }

        return $exist;
    }

    public function createProduct(string $channel, object $product) :bool
    {
        $updated = false;
        $httpCode = 500;

        try {
            
            $id_in_channel = $product->id_in_erp;
            $action = "channels/{$channel}/products?api_token=" . $this->getApiToken();
            $url = $this->getUrl() . $action; 
            $header = $this->getHeader();
            $response = $this->sendRequest($url, $header, $product, 'POST');
           
            $httpCode = $response->httpcode ?? 500;       
            if($httpCode == 200) {
                $updated = true;
            }
            else {
                $this->saveLog('CREATE product', $response, 'error');
            }
            
        } catch (ThrowException $e) {
            $this->saveLog('CREATE product', $e->getMessage(), 'alert');
        }

        return $updated;
    }


    public function updateProduct(string $channel, object $product) :bool
    {
        $updated = false;
        $httpCode = 500;

        try {
            
            $id_in_channel = $product->id_in_erp;
            $action = "channels/{$channel}/products/{$id_in_channel}?api_token=" . $this->getApiToken();
            $url = $this->getUrl() . $action; 
            $header = $this->getHeader();
            $response = $this->sendRequest($url, $header, $product, 'PUT');          
            
            $httpCode = $response->httpcode ?? 500;            
            if($httpCode == 200) {
                $updated = true;
            }
            else {
                $this->saveLog('UPDATE product', $response, 'error');
            }

        } catch (ThrowException $e) {
            $this->saveLog('UPDATE product', $e->getMessage(), 'alert');
        }

        // si no existe el producto, lo mando crear ..
        if($httpCode == 404) {
            $updated = $this->createProduct($channel, $product);
        }

        return $updated;
    }

    public function getProducts(string $channel, $limit = 500) :array
    {
        $products = array();
        $empty    = array();
        $httpCode = 500;
        $error    = false;

        try {
            $next   = true;
            $page   = 1;
            $offset = 0;
            while($next) {
                $action = "channels/{$channel}/products?api_token=" . $this->getApiToken() . "&limit={$limit}&offset={$offset}";
                $url = $this->getUrl() . $action; 
                $header = $this->getHeader();
                $content = array();
                $begin = date('Y-m-d H:i:s');
                $response = $this->sendRequest($url, $header, $content, 'GET');          

                $next = false;
                $httpCode = $response->httpcode ?? 500;            
                if($httpCode == 200) {               
                    $products = array_merge($products, $response->data);
                    $meta = $response->meta_data;

                    // si la cantidad de productos es menor que el tamano de la pagina, estamos en el final ..
                    if(count($response->data) == $limit) {
                        
                        if($meta ) {
                            $offset = $page * $limit;
                            $page++;                            
                            $next = true;                            
                        }                       
                    }
                }
                else {
                    $error = true;
                    $this->saveLog('GET product', $response, 'error');
                    break;
                }                            
            }
            
        } catch (ThrowException $e) {
            $this->saveLog('GET product', $e->getMessage(), 'alert');
        }

        return ($error ? $empty : $products);
    }

    public function deleteProduct(string $id_in_erp, string $channel) :bool
    {
        $deleted = false;

        try {
            
            $action = "channels/{$channel}/products/{$id_in_erp}?api_token=" . $this->getApiToken();
            $url = $this->getUrl() . $action; 
            $header = $this->getHeader();
            $content = array();
            $response = $this->sendRequest($url, $header, $content, 'DELETE');
            
            $deleted = ($response->httpcode == 200);
            if(!$deleted) {
                $this->saveLog('DELETE product', $response, 'error');
            }            
            
        } catch (ThrowException $e) {
            $this->saveLog('DELETE product', $e->getMessage(), 'alert');
        }

        return $deleted;
    }    

    public function elapsedTime(string $begin) : string
    {
        $hourEnd   = new \DateTime();
        $hourBegin = new \DateTime($begin);
        
        return $hourEnd->diff($hourBegin)->format("%H:%I:%S");
    }

    public function saveLog( $text = "n/a", $obj = null, $level = 'info') :bool
    {    
        $text .= " : " . (is_string($obj) ? $obj :json_encode($obj));
            
        if(!empty($this->logPath)) {
           
            $logger = new \Katzgrau\KLogger\Logger($this->logPath, $level, array (
                                                        'dateFormat' => 'Y-m-d G:i:s', 
                                                    ));
            $level = strtolower($level);
            
            $logger->$level($text);
        }
        // if no log path, print to screen ..
        else {
            echo '<p>' . $text . '</p>';
        }

        return false;
    }

} // end class