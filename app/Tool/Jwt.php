<?php


namespace App\Tool;

class Jwt
{
    private static array $header = [
        'alg' => 'HS256' ,
        'typ' => 'JWT' ,
    ];
    private static string $key = 'lwGPdWgwdoDVkNBI0iPYHjNKJREPud6E4xeNOrR1jZ53w5tX2l00iGeUJrCiSE2V';
    private static int $seconds = 0;

    public static function getToken($id , array $payload = []) : string
    {
        $time          = time();
        $payload       = array_merge([
            'iat' => $time ,
            'exp' => $time + env('JWT_TTL' , 4320) * 60 ,
            'jti' => md5(uniqid('JWT') . $time) ,
            'uid' => $id ,
        ] , $payload);
        $base64header  = self::base64UrlEncode(json_encode(self::$header , JSON_UNESCAPED_UNICODE));
        $base64payload = self::base64UrlEncode(json_encode($payload , JSON_UNESCAPED_UNICODE));

        self::$seconds = $payload['exp'] - time();

        return $base64header . '.' . $base64payload . '.' . self::signature($base64header . '.' . $base64payload ,
                self::$key , self::$header['alg']);
    }

    public static function getExpireSeconds() : int
    {
        return self::$seconds;
    }

    public static function verifyToken(string $Token)
    {
        $tokens = explode('.' , $Token);
        if (count($tokens) != 3)
            return false;

        [$base64header , $base64payload , $sign] = $tokens;

        $base64UrlDecode = json_decode(self::base64UrlDecode($base64header) , JSON_OBJECT_AS_ARRAY);
        if (empty($base64UrlDecode['alg'])) {
            return false;
        }
        if (self::signature($base64header . '.' . $base64payload , self::$key , $base64UrlDecode['alg']) !== $sign) {
            return false;
        }
        $payload = json_decode(self::base64UrlDecode($base64payload) , JSON_OBJECT_AS_ARRAY);
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            return false;
        }
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return false;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $input) : array|string
    {
        return str_replace('=' , '' , strtr(base64_encode($input) , '+/' , '-_'));
    }

    private static function base64UrlDecode(string $input) : bool|string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $addles = 4 - $remainder;
            $input  .= str_repeat('=' , $addles);
        }

        return base64_decode(strtr($input , '-_' , '+/'));
    }

    private static function signature(string $input , string $key , string $alg = 'HS256') : array|string
    {
        $alg_config = ['HS256' => 'sha256'];

        return self::base64UrlEncode(hash_hmac($alg_config[$alg] , $input , $key , true));
    }
}
