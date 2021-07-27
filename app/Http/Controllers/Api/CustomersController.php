<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Route;

use App\Customer;
use Carbon\Carbon;

class CustomersController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => 'required|unique:customers',
            'astroselling_token' => 'required',
            'miratio_token' => 'required'
        ]);

        if($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $customer = new Customer();
        $customer->channel_id = $request['channel_id'];
        $customer->astroselling_token = $request['astroselling_token'];
        $customer->miratio_token = $request['miratio_token'];
        $customer->save();

        return response()->json([
            'channel_id' => $customer->channel_id,
            'astroselling_token' => $customer->astroselling_token,
            'miratio_token' => $customer->miratio_token,
            'active' => true,
            'deleted' => false,
            'created' => $customer->created_at->toDateTimeString()
        ], 200);
    }

    public function show($id)
    {
        $customer = Customer::where('channel_id', $id)->first();
        if($customer == null) {
            return response()->json([
                'channel_id' => $id,
                'error' => 'No data'
            ], 400);
        }
        return response()->json([
            'channel_id' => $id,
            'astroselling_token' => $customer->astroselling_token,
            'miratio_token' => $customer->miratio_token,
            'active' => $customer->active == 1 ? true : false,
            'deleted' => $customer->deleted_at == null ? false : true,
            'created' => $customer->created_at->toDateTimeString(),
            'updated' => $customer->updated_at->toDateTimeString()
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::where('channel_id', $id)->first();
        if($customer == null) {
            return response()->json([
                'channel_id' => $id,
                'error' => 'No data'
            ], 400);
        }
        
        if(isset($request['astroselling_token'])) {
            $customer->astroselling_token = $request['astroselling_token'];
        }
        if(isset($request['miratio_token'])) {
            $customer->miratio_token = $request['miratio_token'];
        }
        if(isset($request['active'])) {
            $customer->active = $request['active'];
            if($customer->active == true) {
                $customer->deleted_at = null;
            }
        }

        $customer->save();

        return response()->json([
            'channel_id' => $id,
            'astroselling_token' => $customer->astroselling_token,
            'miratio_token' => $customer->miratio_token,
            'active' => $customer->active == 1 ? true : false,
            'deleted' => $customer->deleted_at == null ? false : true,
            'updated' => $customer->updated_at->toDateTimeString()
        ], 200);        
    }

    public function destroy($id)
    {
        $customer = Customer::where('channel_id', $id)->first();
        if($customer == null) {
            return response()->json([
                'channel_id' => $id,
                'error' => 'No data'
            ], 400);
        }

        $customer->active = false;
        $customer->deleted_at = Carbon::now();
        $customer->save();

        return response()->json([
            'channel_id' => $id,
            'active' => false,
            'deleted' => true,
            'updated' => $customer->updated_at->toDateTimeString()
        ], 200);  
    }

    public function sync(Request $request)
    {
        $customers = Customer::where('active', 1)->where('deleted_at', null)->get();
        
        $response = array();
        foreach($customers as $customer) {
            $data = [
                'channel_id' => $customer->channel_id,
                'token' => $customer->astroselling_token,
                'miratio_token' => $customer->miratio_token
            ];        
            $newRequest = Request::create('api/miratio/sync', 'post', $data);
            $result = app()->handle($newRequest)->getContent();

            array_push($response, json_decode($result));
        }

        return response()->json([
            'data' => $response
        ], 200);
    }
}
