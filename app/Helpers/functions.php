<?php

use App\Tool\UfileSdk\Ucloud;
use App\Providers\Facades\Hook;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Plugin\Plugin;

if (!function_exists('parse_ids')) {
    function parse_ids($ids) : array
    {
        $data =  array_unique(explode(',' , trim($ids)));
        return array_filter($data, function ($value) {
            return !empty($value);
        });
    }
}


if (!function_exists('page_size')) {
    function page_size()
    {
        return request()->input('page_size' , 15);
    }
}

if (!function_exists('auth_list')) {
    function auth_list($arr , $pid = 0 , $level = 0) : bool|array
    {
        if (!is_array($arr)) {
            return false;
        }
        $tree = [];
        foreach ($arr as $v) {
            if ($v['parent_id'] == $pid) {
                $v['level'] = $level + 1;
                $v['str']   = str_repeat('├' , $level);
                $tree[]     = $v;
                $tree       = array_merge($tree , auth_list($arr , $v['id'] , $level + 1));
            }
        }

        return $tree;
    }
}
if (!function_exists('cover_upload')) {
    function cover_upload($path , $width , $height) : string
    {
        $filePath = storage_path('app/public/');
        $fileName = date('YmdHis') . '_' . md5(uniqid() . $width . $height) . '.jpg';
        $file     = $filePath . $fileName;
        $shell    = "ffmpeg -i " . $path . " -q 75 -r 3 -y -f mjpeg -ss 1 -t  1 -s " . $width . "x" . $height . ' ' . $file;
        exec($shell);
        Ucloud::uploadFile($fileName , $file);
        unlink($file);

        return $fileName;

    }
}
if (!function_exists('seco_time')) {
    function seco_time($times) : string
    {
        if ($times > 0) {
            $hour = floor($times / 3600);
            if ($hour < 10) {
                $hour = "0" . $hour;
            }
            $minute = floor(($times - 3600 * $hour) / 60);
            if ($minute < 10) {
                $minute = "0" . $minute;
            }
            $second = floor((($times - 3600 * $hour) - 60 * $minute) % 60);
            if ($second < 10) {
                $second = "0" . $second;
            }
            $result = $hour . ':' . $minute . ':' . $second;
        } else {
            return '00:00';
        }
        return str_starts_with($result , '00') ? substr($result,3) : $result;

    }

}