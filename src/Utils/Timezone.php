<?php


namespace App\Utils;
use DateTimeZone;
use DateTime;

class Timezone
{
    public function server2local($date,$timzone,$_format="Y-m-d H:i:s"){
        $server_timezone = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime)/3600;
        $timzone_diff = $timzone - $server_timezone;
        return date($_format,strtotime($timzone_diff." hours",strtotime($date)));
    }

    public function local2server($date,$timzone,$_format="Y-m-d H:i:s"){
        $server_timezone = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime)/3600;
        $timzone_diff = $server_timezone - $timzone;
        return date($_format,strtotime($timzone_diff." hours",strtotime($date)));
    }

    public function get_diff_timezone($timzone){
        $server_timezone = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime)/3600;
        $timzone_diff = $timzone - $server_timezone;
        return $timzone_diff;
    }

    public function get_timezone_server(){
        $server_timezone = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime)/3600;
        return $server_timezone;
    }
}