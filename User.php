<?php

namespace App\Model;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;
    protected $fillable = [
        'name', 'email', 'password','is_deleted','is_active','category','cover_img_path','nlad_link','device_type','device_token','sessionkey','four_digit_pin',
    ];
    protected $hidden = [
        'password', 'remember_token',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    //For get User Details 
    public function userDetails()
    {
     return $this->hasOne('App\Model\user_details','ref_id');
    }

    //For get Seller All Images
    public function userSellerAlbum()
    {
      return $this->hasMany('App\Model\seller_pictures_for_sell','user_id');
    }

    //For Getting user Profile Setting
    public function userProfileSetting()
    {
      return $this->hasOne('App\Model\UserProfileSetting','user_id');
    }

    //For User Favourite details get
    public function userFavourite()
    {
      return $this->hasOne('App\Model\Favourite','liked_user_id');
    }

    //For user offer id get    
    public function userOffers()  
    {
      return $this->hasMany('App\Model\OfferForBuyerDetails','user_id');
    }
    
}
