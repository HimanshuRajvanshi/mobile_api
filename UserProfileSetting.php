<?php

namespace App\Model;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UserProfileSetting extends Authenticatable
{
    use Notifiable;
    protected $table='user_profile_setting';

    protected $fillable = [
        'id', 'user_id', 'private_account', 'push_notification'
    ];
    
    //For ByDefault set Profile Setting
     public static function profileSettingManually($id){
        $prfile_setting                     =new UserProfileSetting();
        $prfile_setting->user_id            =$id;
        $prfile_setting->private_account    ='deactive';
        $prfile_setting->push_notification  ='active';
        $prfile_setting->created_at         =date("Y-m-d");
        $prfile_setting->save();

        return $prfile_setting;

     }


}
