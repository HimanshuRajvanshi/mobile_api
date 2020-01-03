<?php

namespace App\Http\Controllers;

use Hash, Auth, DB, Validator;
use Illuminate\Http\Request;

//Resources
use App\Http\Resources\UserResources;
use App\Http\Resources\UserDetailResources;
use App\Http\Resources\UserResourcesCollection;

//Model
use App\Model\OfferForBuyer;
use App\Model\User;
use App\Model\user_details;
use App\Model\UserProfileSetting;
use App\Model\Favourite;
use App\Model\OfferForBuyerDetails;


class ApiAccountController extends Controller
{

    protected function check_required($array){
        $requried = "";
        foreach($array as $key => $value) {
            if ($value==''){
                $requried .= $key.',';
            }
        }
        if($requried!=""){
            return rtrim($requried,',');
        }else{
            return false;
        }
    }

    protected function response($arr){
        return response($arr,$arr['status'])->header('Content-Type', 'application/json');
    }

    protected function getToken(){
        $token   =  md5(uniqid(rand().microtime(), true));
        return $token;
    }

    protected function upload_multiple($request,$file,$path){
        if($request->file($file)){
            $files = $request->file($file);
            foreach($files as $file){
                $name = md5(md5(time()).md5(rand(12345,99999))).'.'.$file->extension();
                $picture = $file->move($path,$name);
                $final[] = $path.'/'.$name; 
            }
            return $final;
        }else{
            return [];
        }
    }

    //For Login User Throw Api
    public function loginClient(Request $request) {
      try{
            $input = $request->all();
            $data  = ['email'=>@$input['email'],'password'=>@$input['password'],'device_token'=>@$input['device_token'],'device_type'=>@$input['device_type']];
            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }else{
                $user = User::where(['email'=>$data['email']])->first();
                if(!isset($user)){
                    return $this->response([ 'status'=>404,"message"=>"Account Doesn't Exist.","success"=>0 ]);
                }

                $isvalid = Hash::check($data['password'], $user->password);
                if($isvalid){
                    // set device_type and device_type
                    $user->device_token = $data['device_token'];
                    $user->device_type  = $data['device_type'];
                    $user->sessionkey = $this->getToken();
                    $user->save();

                    $user=User::where('email',$user->email)->with('userDetails','userSellerAlbum','userProfileSetting','userOffers')->with('userOffers.offers')->first();

                    return $this->response([ 'status'=>200,"message"=>'Login Successfull', "data"=>new UserResources($user),"success"=>1 ]);

                }else{
                    return $this->response([ 'status'=>404,"message"=>"Invalid credential. Please Check Email and Password","success"=>0 ]);
                }
            }
        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    //For Registation Throw Api
    public function signupClient(Request $request) {
     try{
        $input = $request->all();
        $data  = [
                   'name'               =>@$input['name'],
                   'email'              =>@$input['email'],
                   'password'           =>@$input['password'],
                   'phone'              =>@$input['phone'],
                   'dob'                =>@$input['dob'],
                   'gender'             =>@$input['gender'],
                   'four_digit_pin'     =>@$input['four_digit_pin'],
                   'role'               =>@$input['role'],
                   'profile_img_path'   =>@$input['profile_img_path'],
                   'device_token'       =>@$input['device_token'],
                   'device_type'        =>@$input['device_type'],
                   'latitude'           =>@$input['latitude'],
                   'longitude'          =>@$input['longitude'],
                   'offer_id'           =>@$input['offer_id'],
                   'location'           =>@$input['location']
                ];

        if($this->check_required($data))
        {
            $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
            return $this->response($response);
        }else{
            $user = User::where(['email'=>$data['email']])->first();
                if($user!=null)
                {
                 return $this->response([ 'status'=>409,"message"=>"Email Id already exists","success"=>0 ]);
                }
                $data['password']   = Hash::make($data['password']);
                //call this function bcz this is also working on socail sign up
                $user=$this->signupSaveData($data,$input);
                return $this->response([ 'status'=>200,"message"=>"Signup Successfully", "data"=>new UserResources($user),"success"=>1 ]);
        }
       }catch(\Exception $e){
        return $this->response([ 'status'=>404,"message"=>"There is something wrong  ".$e->getMessage(), 'error'=> true]);
      }
    }

    // check socialId exists or not
    public function isSocialIdExists(Request $request){
        $input    =  $request->all();
        $data  = [ 'socialId'=>@$input['socialId'],'socialAccountType'=>@$input['socialAccountType'] ];

        if($this->check_required($data)){
            $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
            return $this->response($response);
        }else{
            // check this socialId exists or not
            $user = User::where([ 'socialId'=>$data['socialId'], 'socialAccountType'=>$data['socialAccountType'] ])->first();
            if($user!=null){
                return $this->response([ 'status'=>200,"message"=>"socialId Exists","success"=>1 ]);
            }
            return $this->response([ 'status'=>200,"message"=>"socialId not Exists","success"=>1 ]);
        }
    }

    //For Upload file and send part
    public function uploadFiles(Request $request){
        if( $request->file('files') != null ){
            $data = $this->upload_multiple($request,'files','public/Uploads/user-profile-pics');
            $counter = 0;
            foreach($data as $data2){
                $data[$counter] = substr($data2,7);
                $counter++;
            }
            return $this->response([ 'status'=>200,"message"=>"Files Uploaded", "data"=>$data,"success"=>1 ]);

        }
        return $this->response([ 'status'=>400,"message"=>"Files Upload Failed","success"=>0 ]);
    }

    /*
     *for Social Siginup and Login base on what paramentrs we are getting
     * check user is already signUp or not , {{ if not }} signup user else login the user
    */
    public function socialLogin(Request $request){
     try{
            $input = $request->all();
            if( !isset($input['signUp']) && trim(@$input['signUp'])=='' ){
                $response = ['status'=>400,"message"=>"Missing field : signUp "];
                return $this->response($response);
            }
            if( $input['signUp']==1 ){
                // sign up the user
                $data  = [
                            'name'              =>@$input['name'],
                            'email'             =>@$input['email'],
                            'phone'             =>@$input['phone'],
                            'dob'               =>@$input['dob'],
                            'gender'            =>@$input['gender'],
                            'four_digit_pin'    =>@$input['four_digit_pin'],
                            'role'              =>@$input['role'],
                            'profile_img_path'  =>@$input['profile_img_path'],
                            'device_token'      =>@$input['device_token'],
                            'device_type'       =>@$input['device_type'],
                            'socialId'          =>@$input['socialId'],
                            'socialAccountType' =>@$input['socialAccountType'],
                            'latitude'          =>@$input['latitude'],
                            'longitude'         =>@$input['longitude'],
                            'offer_id'          =>@$input['offer_id'],
                            'location'          =>@$input['location']
                        ];

                if($this->check_required($data))
                {
                    $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                    return $this->response($response);
                }else{
                      //call this function bcz this is also working on Normal Signup
                      $user=$this->signupSaveData($data,$input);
                      return $this->response([ 'status'=>200,"message"=>"Signup Successfully", "data"=>new UserResources($user),"success"=>1 ]);
                }
            }else{
                // already signed up , set sessionkey and login the user
                $data  = [ 'socialId'=>@$input['socialId'],'device_token'=>@$input['device_token'],'device_type'=>@$input['device_type'] ];

                if($this->check_required($data)){
                    $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                    return $this->response($response);
                }else{
                    $user = User::where(['socialId'=>$data['socialId']])->first();
                    if($user!=null){
                        // set device_type and device_type
                        $user->device_token = $data['device_token'];
                        $user->device_type  = $data['device_type'];
                        // set session for this Login
                        $user->sessionkey = $this->getToken();
                        $user->save();

                        $user=User::where('socialId',$data['socialId'])->with('userDetails','userSellerAlbum','userProfileSetting')->first();

                        return $this->response([ 'status'=>200,"message"=>"Login Success", "data"=>new UserResources($user),"success"=>1 ]);
                    }else{
                        return $this->response([ 'status'=>401,"message"=>"Login Failed","success"=>0 ]);
                    }
                }
            }
        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    //For forget Password throw CURL
    public function forgotPassword(Request $request){
        $input = $request->all();
        $data  = [ 'email'=>@$input['email'] ];

        if($this->check_required($data)){
            $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
            return $this->response($response);
        }else{
            $userEmail = $data['email'];
            $user = User::where([ 'email'=>$userEmail ])->first();
            if($user==null){
                return $this->response([ 'status'=>401,"message"=>"No such Email Id exists","success"=>0 ]);
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://nladultcams.com/password/email",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => "------WebKitFormBoundary7MA4YWxkTrZu0gW\r\nContent-Disposition: form-data; name=\"email\"\r\n\r\n$userEmail\r\n------WebKitFormBoundary7MA4YWxkTrZu0gW--",
              CURLOPT_HTTPHEADER => array(
                "Accept: */*",
                "Accept-Encoding: gzip, deflate",
                "Authorization: Bearer ghdytuy7s6$%$%^&dfsbguey67Ft567er4tysb76tbvsrtyvuesr7htd",
                "Cache-Control: no-cache",
                "Connection: keep-alive",
                "Cookie: XSRF-TOKEN=eyJpdiI6Ikd3OE9ndkNVcSttc3dSQloySlV1Q2c9PSIsInZhbHVlIjoiUkdQeTF0enRyWUpOWVVUSmZIdzRHY2wwWng1a1ZyYmtzb3QyV05XUHJUWENkd1hld1J1cWVWSEkxazR1dXRTdyIsIm1hYyI6IjgzN2FjYzhiZjRhNDJhNzRjMzRmM2I4N2M0MTRlMjY1ZGQzNjMyNjIzNjdhNWFlZDg2MjI5ODgyMWMxNGE2ODcifQ%3D%3D; nladult_session=eyJpdiI6InJRZjA0aTZTSDNCdDlBd1ZnK2VzWHc9PSIsInZhbHVlIjoiV3ExWldQcVkzZVg1cUlCMDlzaUlCOXhIaDFoemNSOElEZllHRjlGQXdJMVkwSVJ4Z0lmcys0b08xVlYwVjNDTCIsIm1hYyI6IjRkOTQ1YzAzOWYyNjc0MDQwYjYwNjk5NGY2ODgyNjVhNWI5NmQ2YzJmMjFiM2U5YWExMTBhZjBjZWNlNmVlOGEifQ%3D%3D",
                "Postman-Token: 76ed3317-d446-4875-bc8a-cf692f94f0b3,bc6b8865-03c0-4095-9a8a-8e699c2eef32",
                "Referer: https://nladultcams.com/password/email",
                "User-Agent: PostmanRuntime/7.15.2",
                "cache-control: no-cache",
                "content-type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW"
              ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            if($err){
                return $this->response([ 'status'=>400,"message"=>"Email sent Failed","success"=>0 ]);
            }
            return $this->response([ 'status'=>200,"message"=>"Email sent for password reset", "success"=>1 ]);
        }

    }

    /*
    *For User Profile update
    */
    public function updateProfile(Request $request)
    {
      try{
          $input = $request->all();
          $data  = [
              'name'                =>@$input['name'],
              'email'               =>@$input['email'],
              'phone'               =>@$input['phone'],
              'location'            =>@$input['location'],
              'dob'                 =>@$input['dob'],
              'gender'              =>@$input['gender'],
              'profile_img_path'    =>@$input['profile_img_path']
            ];

            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }

            $header=$request->header();
            $userId=$header['userid'][0];
            $user = User::where('id',$userId)->first();

            if($user->name != $data['name']){
                $tmp_user =User::Where(['name' =>$data['name']])->first();
                if($tmp_user!=null){
                    return $this->response([ 'status'=>409,"message"=>"username already exists","success"=>0,"is_exist"=>'yes']);
                }
            }

            if($user->email != $data['email']) {
                $tmp_user = User::where(['email'=>$data['email']])->first();
                if($tmp_user!=null){
                    return $this->response([ 'status'=>409,"message"=>"Email Id already exists","success"=> 0,"is_exist"=>'yes']);
                }
            }

            $user->name              =$data['name'];
            $user->email             =$data['email'];
            $user->phone             =$data['phone'];
            $user->location          =$data['location'];
            $user->gender            =$data['gender'];
            $user->profile_img_path  =$data['profile_img_path'];
            $user->save();

            $userDetails=user_details::where('ref_id',$userId)->first();
            $fulldate = explode('-', $data['dob']);

            $userDetails->birth_date    =$fulldate[2];
            $userDetails->birth_month   =$fulldate[1];
            $userDetails->birth_year    =$fulldate[0];
            $userDetails->updated_at    =date("Y-m-d");
            $userDetails->save();

            $user=User::where('id',$userId)->with('userDetails','userSellerAlbum','userProfileSetting')->first();

            return $this->response([ 'status'=>200,"message"=>"Profile Update", "data"=>new UserResources($user),"success"=>1 ]);

        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    /*
    *For User logout we delete sessionkey for that
    */
    public function logout(Request $request)
    {
        $header=$request->header();
        $user = User::where(['id' =>$header['userid'][0]])->first();
        $user->sessionkey ='';
        $user->save();
        return $this->response([ 'status'=>200,"message"=>"Logout successfully","success"=>1 ]);
    }

    /*
    *For Profile setting like Private Account or push notification
    */
    public function profileSetting(Request $request)
    {
      try{
            $input = $request->all();
            $data  = ['private_account'=>@$input['private_account'],'push_notification'=>@$input['push_notification']];

            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }

            $header=$request->header();
            $prfile_setting=UserProfileSetting::where('user_id',$header['userid'][0])->first();
            if($prfile_setting != null){
                $prfile_setting->updated_at=date("Y-m-d");
                $msg='Record successfully updated';

            }else{
                $prfile_setting= new UserProfileSetting();
                $prfile_setting->created_at=date("Y-m-d");
                $msg='Record successfully added';
            }

            $prfile_setting->user_id =$header['userid'][0];
            $prfile_setting->private_account    =$data['private_account'];
            $prfile_setting->push_notification  =$data['push_notification'];
            $prfile_setting->save();

            return $this->response([ 'status'=>200,"message"=>$msg, "data"=>$prfile_setting,"success"=>1 ]);

        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    /*
    *For all buyer and seller details get and We can also Search name
    */
    public function buyerSellerDetails(Request $request)
    {
       try{
            $input = $request->all();
           
            $data  = [
                'user_type'=>@$input['user_type'],
                'search_keyword'=>@$input['search_keyword'],
                'is_filter'=>@$input['is_filter']
            ];

            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }

            $header=$request->header();
            $userId=$header['userid'][0];
            $user_type=$request->user_type;

            if($request->search_keyword == '0'){
                $keyword=null;
            }else{
                $keyword =$input['search_keyword'];
            }

            if(($input['is_filter'] )== 1){
                $data= (json_decode(stripslashes($input['filter']),true));
                $users_details=$this->userFilter($data,$userId,$user_type);
                if($users_details == null){
                      $msg="Record Not Found";  
                }else{
                    $msg=$user_type." all records";  
                }
                return $this->response([ 'status'=>200,"message"=>$msg, "data"=>$users_details,"success"=>1 ]);

            }else{
                $users_details=DB::table('users')
                ->select(DB::raw("users.id,name,email,phone,users.gender,bio,hobbies,location,latitude,longitude,profile_img_path,role,role_text,cover_img_path ,CONCAT(if(birth_year,birth_year,'0000'),'-',if(birth_month,birth_month,'00'),'-',if(birth_date,birth_date,'00')) as dob,(SELECT count(liked_user_id) FROM favourites where user_id= '".$header["userid"][0]."' AND liked_user_id=users.id) as is_like"))
                ->where('role_text',$user_type)
                ->where('users.id' ,'!=',$header['userid'][0])
                ->where('users.name','like',$keyword.'%')
                ->leftJoin('user_details', 'users.id', '=', 'user_details.ref_id')
                ->paginate(20);
                 return $this->response([ 'status'=>200,"message"=>$user_type." all records", "data"=>$users_details,"success"=>1 ]);
            }

      

        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    /*
    *User like seller profile api
    */
    public function likeBuyerProfile(Request $request)
    {
      try{
            $input = $request->all();
            $data  = ['liked_user_id'=>@$input['liked_user_id']];

            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }

            $header=$request->header();
            $favourite=Favourite::where('user_id',$header['userid'][0])->where('liked_user_id',$data['liked_user_id'])->first();

            if($favourite != null){
                $favourite->delete();
                $msg='Dislike successfully';
                $favourite="no data found";

            }else{
                $favourite= new Favourite();
                $favourite->user_id         =$header['userid'][0];
                $favourite->liked_user_id   =$data['liked_user_id'];
                $favourite->save();
                // $favourite->created_at=date("Y-m-d");
                $msg='Like successfully';
            }

            return $this->response(['status'=>200,"message"=>$msg, "data"=>$favourite,"success"=>1 ]);

        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    /*
    * Check Username or Email is exit or Need field|Type
    */
    public function checkUsernameEmail(Request $request)
    {
      try{
            $input = $request->all();
            $data  = ['name_or_email'=>@$input['name_or_email'],'type'=>@$input['type']];
            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }

            if($data['type'] == 'name'){
                $user_data=User::where('name',$data['name_or_email'] )->first();
                if($user_data!=null){
                    return $this->response([ 'status'=>200,"message"=>"username already exists","success"=>0 ,"is_exist"=>'yes']);
                }
            }else{
                $user_data=User::where('email',$data['name_or_email'] )->first();
                if($user_data!=null){
                    return $this->response([ 'status'=>200,"message"=>"Email already exists","success"=>0 ,"is_exist"=>'yes']);
                }
            }
            return $this->response([ 'status'=>200,"message"=>"No Record Found","success"=>1 ]);

        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }

    /*
    *  Get All Offer form
    */
    public function offersForBuyer(Request $request)
    {
       try{
            $offers=OfferForBuyer::Get();
            return $this->response([ 'status'=>200,"message"=>'All offer record', "data"=>$offers,"success"=>1 ]);

         }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=>  true]);
        }
    }

    //Get Seller Details With Albums
    public function getSellerDetails(Request $request)
    { 
       try{
            $input = $request->all();
            $data  = ['seller_id'=>@$input['seller_id']];
            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }

            $header=$request->header();
            $user_id=$header['userid'][0];
            $user=User::where('id',$data['seller_id'])->first();
            if(!$user){
                return $this->response([ 'status'=>200,"message"=>"No Seller Record Found", "success"=>1 ]);
            }else{
                $user=User::where('id',$data['seller_id'])
                        ->with(['userDetails','userSellerAlbum','userProfileSetting','userFavourite' =>
                        function ($query) use ($user_id)
                        {
                            $query->where('user_id',$user_id);
                        }]
                        )->first();
                return $this->response([ 'status'=>200,"message"=>"User Details", "data"=>new UserDetailResources($user),"success"=>1 ]);
            }
        }catch(\Exception $e){
            return $this->response([ 'status'=>404,"message"=>"There is something wrong".$e->getMessage(), 'error'=> true]);
        }
    }//end getSellerDetails function

    /*
    *Here is save data into Database from Normal Signup and Social Signup
    */
    protected function signupSaveData($data,$input)
    {
        $data['role_text']  = $data['role']==1 ? 'Seller' : 'Buyer' ;
        $data['sessionkey'] = $this->getToken();

        //DOD save DB date|month|day
        $fulldate                           = explode('-', $data['dob']);
        $dataForUserDetails['birth_month']  = $fulldate[1];
        $dataForUserDetails['birth_date']   = $fulldate[2];
        $dataForUserDetails['birth_year']   = $fulldate[0];
        $dataForUserDetails['latitude']     = $data['latitude'];
        $dataForUserDetails['longitude']    = $data['longitude'];

        //for remove user table
        unset($data['birth_date'],$data['birth_month'],$data['birth_year'],$data['latitude'],$data['longitude'],$data['dob'],$data['offer_id']);

        $data['is_active'] = 1;
        if(User::insert( $data )){
            $user = User::where(['email'=>$data['email']])->first();
            //for User Profile Setting like Push Notification and private Account
            UserProfileSetting::profileSettingManually($user->id);
            //For OfferSave TO User
            OfferForBuyerDetails::postOfferUser(@$input['offer_id'],$user->id);

            $dataForUserDetails['ref_id'] = $user->id;
            // insert data for `user_details` table
            if(user_details::insert( $dataForUserDetails )){
                if(isset($input['images']) && $input['images'] ){
                    $arrImages = explode(",",$input['images']);
                    foreach( $arrImages as $img ){
                        DB::table('seller_pictures_for_sell')->insert(
                            [
                                'user_id'        =>  $user->id,
                                'picture_path'   =>  $img
                            ]
                        );
                    }
                }
                $user=User::where('email',$user->email)->with('userDetails','userSellerAlbum','userProfileSetting','userOffers')->with('userOffers.offers')->first();
                return $user;
            }
        }
        return $this->response([ 'status'=>401,"message"=>"Signup Failed","success"=>0 ]);

    }//end signupSaveData function

    /*
    *For Filter(Age|Location|Gender|Offer)
    */
    protected function userFilter($data,$userId,$user_type)
    {
      try{
            $new_age_from  =date("Y") - $data['age_from'];
            $new_age_to    =date("Y") - $data['age_to'];
            
            $user_data=user_details::where('ref_id',$userId)->first();
            if(empty($user_data)){
                $latitude  =null;
                $longitude =null;
            }else{
                $latitude  =$user_data->latitude;
                $longitude =$user_data->longitude;
            }

            // echo $user_data;
            $users_details=DB::table('users')
                           ->select(DB::raw("users.id,name,email,phone,users.gender,bio,hobbies,location,latitude,longitude,profile_img_path,role,role_text,cover_img_path,CONCAT(if(birth_year,birth_year,'0000'),'-',if(birth_month,birth_month,'00'),'-',if(birth_date,birth_date,'00')) as dob,(SELECT count(liked_user_id) FROM favourites where user_id= '".$userId."' AND liked_user_id=users.id) as is_like,(6371 * acos(cos(radians(" . $latitude . "))* cos(radians(user_details.latitude))* cos(radians(user_details.longitude) - radians(" . $longitude . ")) + sin(radians(" .$latitude. "))
                            * sin(radians(user_details.latitude)))) AS distance "))
                            ->leftJoin('user_details', 'users.id', '=', 'user_details.ref_id')
                            ->leftJoin('offer_for_buyer_details', 'users.id', '=', 'offer_for_buyer_details.user_id')
                            ->where('role_text',$user_type)
                            ->where('users.id' ,'!=',$userId)
                            ->whereBetween('user_details.birth_year',[$new_age_to, $new_age_from])
                            ->where('users.gender','like',$data['gender'].'%')
                            ->having('distance', '<', $data['miles'])
                            ->orderBy('distance')
                            ->simplePaginate(20);

                            if(count($users_details) ==0){
                                return $users_details=null;
                            }else{
                                return $users_details;
                            }

      }catch(\Exception $e){
         return $this->response([ 'status'=>404,"message"=>"There is something wrong ".$e->getMessage(), 'error'=> true]);
      }
    }//end userFilter function


    /*
     *For Update latitude and longitude
    */
    public function updateLocation(Request $request)
    {
      try{
            $input = $request->all();
            $data  = [
                'latitude'    =>@$input['latitude'],
                'longitude'   =>@$input['longitude'],
            ];
            
            if($this->check_required($data)){
                $response = ['status'=>400,"message"=>"Missing fields: ".$this->check_required($data)];
                return $this->response($response);
            }
            $header=$request->header();
            $userId=$header['userid'][0];

            $user_detail=user_details::where('ref_id',$userId)->first();
            $user_detail->latitude    =$data['latitude'];
            $user_detail->longitude   =$data['longitude'];
            $user_detail->save();

            return $this->response([ 'status'=>200,"message"=>"Latitude and longitude update successfull", "data"=>'Update record',"success"=>1 ]);
       }catch(\Exception $e){
         return $this->response([ 'status'=>404,"message"=>"There is something wrong ".$e->getMessage(), 'error'=> true]);
      }
    }//end For Update Lat and long
    
    

}