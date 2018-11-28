<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/30
 * Time: 17:09
 */
function changeToRMB($market, $value)
{
    $value = sprintf("%1\$.8f", $value);
    $redis = \hiione\library\MyRedis::getInstance();
    $rates = $redis->get('rates');
    $usdtormb = (\hiione\library\HiioneServer::getInit()['language'] == 'en-us') ? 1 : $redis->get('usdtormb');
    $market = explode('_', $market);
    if ($market[1] != 'usdt') {
        $usdt = $rates['rates']['usdt-' . $rates['base_coin']];
        if ($rates['base_coin'] != $market[1]) {
            $key2 = $market[1] . "-" . $rates['base_coin'];
            $value = round($value * $rates['rates'][$key2], 8);
        }
        $tmp = round($value / $usdt, 8);
    } else {
        $tmp = $value;
    }
    return round($tmp * $usdtormb, 4);
}

function chsToJson($value)
{
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = chsToJson($v);
            } else {
                $value[$k] = urlencode($v);
            }
        }
    } else {
        $value = urlencode($value);
    }
    return $value;
}

function check_arr($rs)
{
    foreach ($rs as $v) {
        if (!$v) {
            return false;
        }
    }
    return true;
}

function getFinanceHash()
{
    return 'stop';
}