<?php


namespace App\Tool;

class GoogleAuth
{
    const keyRegeneration = 30;
    const otpLength = 6;
    private static array $lut = [
        "A" => 0 ,
        "B" => 1 ,
        "C" => 2 ,
        "D" => 3 ,
        "E" => 4 ,
        "F" => 5 ,
        "G" => 6 ,
        "H" => 7 ,
        "I" => 8 ,
        "J" => 9 ,
        "K" => 10 ,
        "L" => 11 ,
        "M" => 12 ,
        "N" => 13 ,
        "O" => 14 ,
        "P" => 15 ,
        "Q" => 16 ,
        "R" => 17 ,
        "S" => 18 ,
        "T" => 19 ,
        "U" => 20 ,
        "V" => 21 ,
        "W" => 22 ,
        "X" => 23 ,
        "Y" => 24 ,
        "Z" => 25 ,
        "2" => 26 ,
        "3" => 27 ,
        "4" => 28 ,
        "5" => 29 ,
        "6" => 30 ,
        "7" => 31 ,
    ];

    // 生成2fa
    public static function generate_secret_key($length = 16) : string
    {
        $b32 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $s   = "";
        for ($i = 0 ; $i < $length ; $i++) {
            $s .= $b32[mt_rand(0 , 31)];
        }

        return $s;
    }


    public static function get_timestamp()
    {
        return floor(microtime(true) / self::keyRegeneration);
    }

    public static function base32_decode($b32) : string
    {
        $b32    = strtoupper($b32);
        $l      = strlen($b32);
        $n      = 0;
        $j      = 0;
        $binary = "";
        for ($i = 0 ; $i < $l ; $i++) {
            $n = $n << 5;
            $n = $n + self::$lut[$b32[$i]];
            $j = $j + 5;
            if ($j >= 8) {
                $j      = $j - 8;
                $binary .= chr(($n & (0xFF << $j)) >> $j);
            }
        }

        return $binary;
    }


    static function oath_hotp($key , $counter) : string
    {
        $bin_counter = pack('N*' , 0) . pack('N*' , $counter);
        $hash        = hash_hmac('sha1' , $bin_counter , $key , true);

        return str_pad(self::oath_truncate($hash) , self::otpLength , '0' , STR_PAD_LEFT);
    }

    // 校验auth
    public static function verify_key($b32seed , $key , $window = 4 , $useTimeStamp = true) : bool
    {
        $timeStamp = self::get_timestamp();
        if ($useTimeStamp !== true) {
            $timeStamp = (int) $useTimeStamp;
        }
        $binarySeed = self::base32_decode($b32seed);
        for ($ts = $timeStamp - $window ; $ts <= $timeStamp + $window ; $ts++) {
            if (self::oath_hotp($binarySeed , $ts) == $key) return true;
        }

        return false;
    }


    static function oath_truncate($hash) : int
    {
        $offset = ord($hash[19]) & 0xf;

        return (((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16) | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff)) % pow(10 ,
                self::otpLength);
    }
}
