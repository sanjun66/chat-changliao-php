<?php


namespace App\Tool;

class Aes
{
    const CIPHER = "AES-128-CBC";

    private string $key;
    private string $iv;

    public function __construct($iv = '')
    {
        $this->key = config('app.msg_key');
        $this->iv  = $iv ? str_pad($iv , 16 , '0') : config('app.msg_iv');
    }

    public function encrypt($plaintext) : bool|string
    {
        return openssl_encrypt($plaintext , self::CIPHER , $this->key , 0 , $this->iv);
    }

    public function decrypt($ciphertext) : bool|string
    {
        return openssl_decrypt($ciphertext , self::CIPHER , $this->key , 0 , $this->iv);
    }

}
