<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/9/20
 * Time: 10:38
 */

namespace hiione\library;


class Kline
{
    protected $market;
    protected $split;
    protected $size;
    protected $time;
    protected $redis;

    public function __construct($redis, $market, $split, $size, $time)
    {
        $this->redis = $redis;
        $this->market = $market;
        $this->split = $split;
        $this->size = $size;
        $this->time = $time;
    }

    public function getKline()
    {
        $timearr = array('1m' => 1, '3m' => 3, '5m' => 5, '10m' => 10, '15m' => 15,
            '30m' => 30, '1h' => 60, '2h' => 120, '4h' => 240, '6h' => 360, '12h' => 720, '1d' => 1440, '7d' => 10080);
        switch ($this->split) {
            case "1day":
                $time = 1440;
                break;
            case "12hour":
                $time = 720;
                break;
            case "6hour":
                $time = 360;
                break;
            case "4hour":
                $time = 240;
                break;
            case "2hour":
                $time = 120;
                break;
            case "1hour":
                $time = 60;
                break;
            case "30min":
                $time = 30;
                break;
            case "15min":
                $time = 15;
                break;
            case "5min":
                $time = 5;
                break;
            case "3min":
                $time = 3;
                break;
            case "1min":
                $time = 1;
                break;
            default:
                $time = 15;
        }
        $marketchar_pro = array();
        $tempdata = array();
        $tempdata['DSCCNY'] = $this->redis->get('usdtormb');
        $tempdata['contractUnit'] = "DSC";
        $n = array_search($time, $timearr);

        $tradeJson = $this->redis->get('ChartgetMarketSpecialtyJson' . $this->market . $n);

        if (!$tradeJson) {
            $model = new HiioneModel();
            $tradeJson = $model->setTable('trade_json' . $n . $this->market)->where([
                'type' => $n,
                'data' => ['neq', ''],
                'addtime' => ['>=', $this->time],
            ])->order('id desc')->limit($this->size)->select();
            $this->redis->set('ChartgetMarketSpecialtyJson' . $this->market . $n, $tradeJson);
        }
        krsort($tradeJson);
        $json_data = $data = array();
        foreach ($tradeJson as $k => $v) {
            $json_data[] = json_decode($v['data'], true);
        }
        $json = $this->redis->get('jsonLine' . $n . $this->market);
        if ($json_data[count($json_data) - 1][0] < $json[0]) {
            $json_data[] = $this->redis->get('jsonLine' . $n . $this->market);
        }

        foreach ($json_data as $k => $v) {
            $data[] = array($v[0] * 1000, floatval($v[2]), floatval($v[3]), floatval($v[4]), floatval($v[5]), floatval($v[1]));
        }


        $tempdata['data'] = $data;
        $marketchar_pro['datas'] = $tempdata;
        $marketchar_pro['des'] = "";
        $marketchar_pro['isSuc'] = true;
        $marketchar_pro['marketName'] = "wkj";
        $marketchar_pro['moneyType'] = 'hcny';
        $marketchar_pro['symbol'] = $this->market;
        $marketchar_pro['url'] = "";
        return $marketchar_pro;
    }
}