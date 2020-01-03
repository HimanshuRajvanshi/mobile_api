<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/loginClient','ApiAccountController@loginClient');
Route::post('/signupClient','ApiAccountController@signupClient');
Route::post('/uploadFiles','ApiAccountController@uploadFiles');
Route::post('/isSocialIdExists','ApiAccountController@isSocialIdExists');
Route::post('/socialLogin','ApiAccountController@socialLogin');
Route::post('/forgotPassword','ApiAccountController@forgotPassword');

Route::middleware(['userIdSessionKey'])->group(function () { 
    Route::Post('/update_profile','ApiAccountController@updateProfile');
    Route::Post('/logout','ApiAccountController@logout');
    Route::Post('/profile_setting','ApiAccountController@profileSetting');
    Route::Post('/buyer_seller_details','ApiAccountController@buyerSellerDetails');
    Route::Post('/like_buyer_profile','ApiAccountController@likeBuyerProfile');
    Route::Post('/get_seller_details','ApiAccountController@getSellerDetails');
    Route::Post('/user_filter','ApiAccountController@userFilter');
    Route::Post('/update_location','ApiAccountController@updateLocation');
});

Route::Post('/check_username_email','ApiAccountController@checkUsernameEmail');
Route::Get('/offers_for_buyer','ApiAccountController@offersForBuyer');

Route::Get('/testing',function(){
    return response([ 'status'=>200,"message"=>'Testing Successfull', "success"=>1 ]);
});

//For chat Model
Route::Get('/get_inbox','Api\MessageApiController@getIndex');
Route::Post('/get_chat','Api\MessageApiController@getChat');
Route::Post('/start_chat','Api\MessageApiController@startChat');

