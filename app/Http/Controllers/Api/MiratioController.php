<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Lib\Miratio;
use stdClass;

class MiratioController extends Controller
{
    public function sync(Request $request)    
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => 'required',
            'token' => 'required',
            'miratio_token' => 'required'
        ]);

        if($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $url = "https://nova-back.astroselling.com/jupiter/v1/"; // stage
        // $miratio_token = '56JAKYY3L607HC0CBHJR0HF9WNJUPLHIIPD4P'; // Miratio api token

        $model = new Miratio();
        $logPath = $model->getLogDirectory();

        $qty = new stdClass();
        $qty->Updated = 0;
        $qty->Skipped = 0;
        $qty->Created = 0;
        $qty->Valid = 0;

        try {
            $channel_id = $request['channel_id'];
            $token = $request['token'];
            $miratio_token = $request['miratio_token'];
            $rootBegin = date('Y-m-d H:i:s');
            
            // connecting to sdk ..
            $jupiter = new \astroselling\Jupiter\Products($url, $token, $logPath);
            $sdkVersion = $jupiter->version();
        
            $model->AstroLog($sdkVersion, false, true);
            $model->AstroLog("channel: {$channel_id} ", false, true);
        
            // get products from Astroselling
            $jupiter_products = $jupiter->getProducts($channel_id);
            $model->AstroLog('GET products Jupiter: ' . count($jupiter_products), false, true);

            // get products from ERP ..
            $model->setToken($miratio_token);

            $result = $model->getProducts();
            
            if($result->success == false) {
                return response()->json([], 200);
            }
            
            $products = $result->products;

            if($products) {
                $model->AstroLog('Miratio products: ' . count($products), false, true);

                $exist = $jupiter->hasChannel($channel_id);

                if($exist) { // if channel is exist in Jupiter
                    foreach($products as $product) {
                        $qty->Valid ++;
                        
                        $exist_product = null;
                        foreach($jupiter_products as $jupiter_product) { // check exist if jupiter has miratio product
                            if($jupiter_product->id_in_erp == $product->id) {
                                $exist_product = $jupiter_product;
                                break;
                            }
                        }
                        
                        if($exist_product == null) { // create product
                            $new_product = new stdClass();

                            $new_product->id_in_erp = $product->id;
                            $new_product->channel_id = $channel_id;
                            $new_product->sku = $product->sku;
                            $new_product->title = $product->titulo;
                            $new_product->price = $product->price->listPrice;
                            $new_product->currency = $product->price->currency;
                            $new_product->stock = $product->stock;
                            $new_product->description = $product->longDescription;
                            $new_product->variations = $product->variations;
                            $new_product->extra_info = null;

                            $success = $jupiter->createProduct($channel_id, $new_product); // create
                            if($success) {
                                $qty->Created ++;
                            }							
                        } else { // update product or do nothing
                            //compare
                            $shouldUpdate = false;
                            if($product->price->currency != $exist_product->currency) {
                                $shouldUpdate = true;
                                $exist_product->currency = $product->price->currency;
                            }							
                            if($product->price->listPrice != $exist_product->price) {
                                $shouldUpdate = true;
                                $exist_product->price = $product->price->listPrice;
                            }							
                            if($product->stock != $exist_product->stock) {
                                $shouldUpdate = true;
                                $exist_product->stock = $product->stock;
                            }

                            if($shouldUpdate) { // update product
                                $jupiter->updateProduct($channel_id, $exist_product);
                                $qty->Updated ++;
                            } else { // do nothing
                                $qty->Skipped ++;
                            }
                        }
                    }
                } else { // if channel is not exist in Jupiter
                    $model->AstroLog('Astroselling has not this channel', false, true, 'warning');
                }
            }
            $model->AstroLog('totals', false, true, 'info', $qty);
        
        } catch (Throwable $e) {
            $model->AstroLog('main process: ' . $e->getMessage(), false, true, 'error');
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }

        $elapsed = $jupiter->elapsedTime($rootBegin);
        $model->AstroLog("Total process time: {$elapsed}", false, true, 'info');

        return response()->json([
            'meta' => [
                'version' => $sdkVersion,
                'channel_id' => $channel_id,
                'elapsed' => $elapsed,
                'totals' => [
                    'Updated' => $qty->Updated,
                    'Skipped' => $qty->Skipped,
                    'Created' => $qty->Created,
                    'Valid' => $qty->Valid,
                ]
            ]
        ], 200);
    }
}
