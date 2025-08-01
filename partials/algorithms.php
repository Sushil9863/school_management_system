<?php
function custom_password_hash($password)
{
    $salt = 'XyZ@2025!abc123';
    $rounds = 3;
    $result = $password;
    for ($r = 0; $r < $rounds; $r++) {
        $temp = '';
        for ($i = 0; $i < strlen($result); $i++) {
            $char = ord($result[$i]);
            $saltChar = ord($salt[$i % strlen($salt)]);
            $mix = ($char ^ $saltChar) + ($char << 1);
            $hex = dechex($mix);
            $temp .= $hex;
        }
        $base64 = base64_encode($temp);
        $result = substr($temp, 0, 16) . substr($base64, -16);
    }
    return strtoupper($result);
}


function generate_otp()
{
    $otp = '';
    $seed = random_int(100000, 999999);
    for ($i = 0; $i < 6; $i++) {
        $otp .= ($seed * ($i + 1) + random_int(0, 9)) % 10;
        $seed += random_int(10, 99);
    }
    return $otp;
}
?>