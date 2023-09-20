<?php


namespace App\Tool\UfileSdk;


use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Ucloud
{
    public static function uploadFile($fileName , $file = null)
    {
        require_once(__DIR__ . "/v1/ucloud/proxy.php");
        //存储空间名
        $bucket = env('UCLOUD_BUCKET','june');
        //上传至存储空间后的文件名称(请不要和API公私钥混淆)
        $key = $fileName;
        //待上传文件的本地路径
        $file = $file ?: request()->file('image')->getRealPath();
        [$data , $err] = UCloud_MInit($bucket , $key);
        if ($err) {
            throw new BadRequestHttpException($err->ErrMsg);
        }
        $uploadId = $data['UploadId'];
        $blkSize  = $data['BlkSize'];
        [$etagList , $err] = UCloud_MUpload($bucket , $key , $file , $uploadId , $blkSize);
        if ($err) {
            throw new BadRequestHttpException($err->ErrMsg);
        }
        [, $err] = UCloud_MFinish($bucket , $key , $uploadId , $etagList);
        if ($err) {
            throw new BadRequestHttpException($err->ErrMsg);
        }
    }
}
