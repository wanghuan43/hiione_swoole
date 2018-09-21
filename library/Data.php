<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/30
 * Time: 16:41
 */

namespace hiione\library;

use hiione\model\Market;
use hiione\model\TradeLog;

class Data
{
    protected $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function getIndexBlock($ids, $uid)
    {
        $marketModel = new Market();
        $marketModel->setTable('market', true);
        $model = new HiioneModel();
        $extend = $this->redis->get('extend');
        $jiaoyiqu = $this->redis->get('jiaoyiqu');
        $init = HiioneServer::getInit();

        if (!$jiaoyiqu) {
            $market = $marketModel->where(['status' => '1'])->order('sort ASC,id ASC')->select();
            $tmps = $model->setTable('coin', true)->select();
            $coin = [];
            foreach ($tmps as $value) {
                $coin[$value['name']] = $value;
            }
            $ml = [];
            foreach ($market as $v) {
                if (!$v['round']) {
                    $v['round'] = 4;
                }
                $v['new_price'] = round($v['new_price'], $v['round']);
                $v['buy_price'] = round($v['buy_price'], $v['round']);
                $v['sell_price'] = round($v['sell_price'], $v['round']);
                $v['min_price'] = round($v['min_price'], $v['round']);
                $v['max_price'] = round($v['max_price'], $v['round']);
                $v['xnb'] = explode('_', $v['name'])[0];
                $v['rmb'] = explode('_', $v['name'])[1];
                $v['xnbimg'] = $coin[$v['xnb']]['img'];
                $v['rmbimg'] = $coin[$v['rmb']]['img'];
                $v['volume'] = $v['volume'] * 1;
                $v['change'] = $v['change'] * 1;
                $v['js_yw'] = $coin[$v['xnb']]['js_yw'] . '(' . strtoupper($v['xnb']) . '/' . strtoupper($v['rmb']) . ')';
                $v['title'] = $coin[$v['xnb']]['title'] . '(' . strtoupper($v['xnb']) . '/' . strtoupper($v['rmb']) . ')';
                $v['title_pro'] = strtoupper($v['xnb']) . '/' . strtoupper($v['rmb']);
                $v['navtitle'] = $coin[$v['xnb']]['title'] . '(' . strtoupper($v['xnb']) . ')';
                if (!$v['begintrade']) {
                    $v['begintrade'] = "00:00:00";
                }
                if (!$v['endtrade']) {
                    $v['endtrade'] = "23:59:59";
                }
                $ml[$v['name']] = $v;
            }
            foreach ($ml as $key => $value) {
                $jiaoyiqu[$value['menu_id']][$value['name']] = $value;
            }
            $this->redis->set('jiaoyiqu', $jiaoyiqu);
        }
        $themarketLogs = $this->redis->get('marketjiaoyie24');
        if (!$themarketLogs) {
            foreach ($ml as $k => $v) {
                $themarketLogs[$k] = round($model->setTable('trade_log' . $k, true)->sum('mum'), 6);
                $themarketLogs[$k] = $themarketLogs[$k] * 2;
            }
            $this->redis->set('marketjiaoyie24', $themarketLogs);
        }
        $return = [
            'list' => [],
            'trades' => [],
        ];
        foreach ($ids as $id) {
            $data = $this->redis->get('wkj_allcoin' . $id);
            if (!$data) {
                $data = [];
                foreach ($jiaoyiqu[$id] as $k => $v) {
                    $data[$k][0] = ($id == 19) ? (($init['language'] == 'en-us') ? $v['js_yw'] : $v['title']) : $v['title_pro'];
                    $data[$k][1] = rtrim(rtrim(sprintf("%1\$." . $v['round'] . "f", round($v['new_price'], $v['round'])), '0'), '.');
                    /* $wkj_data['url'][$v][2] = rtrim(rtrim(sprintf("%1\$." . $v['round'] . "f", $v['buy_price']), '0'), '.');//?round($v['buy_price'], 2):$wkj_data['url'][$v][1]-rand(1,3);
                     $wkj_data['url'][$v][3] = rtrim(rtrim(sprintf("%1\$." . $v['round'] . "f", $v['sell_price']), '0'), '.');//?round($v['sell_price'], 2):$wkj_data['url'][$v][1]+rand(1,5);*/
                    $data[$k][2] = rtrim(rtrim(sprintf("%1\$." . $v['round'] . "f", $v['max_price']), '0'), '.');//?round($v['buy_price'], 2):$wkj_data['url'][$v][1]-rand(1,3);
                    $data[$k][3] = rtrim(rtrim(sprintf("%1\$." . $v['round'] . "f", $v['min_price']), '0'), '.');//?round($v['sell_price'], 2):$wkj_data['url'][$v][1]+rand(1,5);
                    $data[$k][1] = ($data[$k][1] == 0) ? '0.00' : $data[$k][1];
                    $data[$k][2] = ($data[$k][2] == 0) ? '0.00' : $data[$k][2];
                    $data[$k][3] = ($data[$k][3] == 0) ? '0.00' : $data[$k][3];
                    $data[$k][4] = isset($themarketLogs[$k]) ? $themarketLogs[$k] : 0;
                    $data[$k][5] = '';
                    $data[$k][6] = round($v['volume'], 2) * 1;
                    $data[$k][7] = round($v['change'], 2);
                    $data[$k][8] = $v['name'];
                    $data[$k][9] = $v['xnbimg'];
                    $data[$k][10] = '';
                    $data[$k][11] = changeToRMB($v, $data[$k][1]);
                    $data[$k][12] = (!empty($extend['click']) ? (in_array($v['id'], $extend['click_join']) ? 'true' : 'false') : 'false');
                }
                $this->redis->set('wkj_allcoin' . $id, $data);
            }
            $return['list'][$id] = $data;
        }
        $data = $this->redis->get('trades');
        if (!$data) {
            $data = [];
            foreach ($ml as $k => $v) {
                $tendency = json_decode($v['tendency'], true);
                $data[$k]['data'] = $tendency;
                $data[$k]['yprice'] = $v['new_price'];
            }
            $this->redis->set('trades', $data);
        }
        $return['trades'] = $data;
        $base = 60 * 60;
        $time = date('G', time());
        $time = $time * $base;
        $trade_manager = $this->redis->get('trade_manager');
        if (!$trade_manager) {
            $trade_manager = $model->setTable('coin_manager')->find();
            $trade_manager['dig_time'] = json_decode($trade_manager['dig_time'], true);
            $trade_manager['trade_time'] = json_decode($trade_manager['trade_time'], true);
            $this->redis->set('trade_manager', $trade_manager);
        }
        $dig_time = $trade_manager['dig_time'];
        $tv = 0;
        $tt = 0;
        foreach ($dig_time as $key => $value) {
            $begin = $key * $base;
            $end = ($key + $trade_manager['dig_hour']) * $base - 1;
            if ($time >= $begin && $time <= $end) {
                $tt = round(($end + 1) / $base);
                $tv = $value;
                break;
            }
        }
        $num = $this->redis->get('dig' . date('Ymd') . $tt);
        if (!$num) {
            $num = $tv;
        }
        $return['indexDiv']['change_coin'] = $num;
        $query = $model->select("SELECT TABLE_NAME FROM `INFORMATION_SCHEMA`.`TABLES` 
                                      WHERE `TABLE_SCHEMA`='hidb' AND `TABLE_NAME`='wkj_trade_log%' ");
        $users = [];
        foreach ($query as $value) {
            if ($value['table_name'] == 'wkj_tarde_log') {
                continue;
            }
            $mt = explode("_", $value['table_name']);
            $coin = $mt[count($mt) - 1];
            if ($coin == 'hit') {
                continue;
            }
            $sql_buy = "SELECT SUM(fee_buy) as fee_buy,market,userid
                  FROM " . $value['table_name'] . " WHERE `status` = 1 AND `type` = '1' AND addtime BETWEEN $begin AND $end
                  GROUP BY userid";
            $sql_sell = "SELECT SUM(fee_sell) as fee_sell,market,peerid
                  FROM " . $value['table_name'] . " WHERE `status` = 1 AND `type` = '2' AND addtime BETWEEN $begin AND $end
                  GROUP BY peerid";
            $buy = $model->select($sql_buy);
            $sell = $model->select($sql_sell);
            foreach ($buy as $val) {
                $usdt = changeToRMB($val['market'], $val['fee_buy'], true);
                if (isset($users[$val['userid']])) {
                    $users[$val['userid']] = round($users[$val['userid']] + $usdt, 8);
                } else {
                    $users[$val['userid']] = $usdt;
                }
            }
            foreach ($sell as $val) {
                $usdt = changeToRMB($val['market'], $val['fee_sell'], true);
                if (isset($users[$val['peerid']])) {
                    $users[$val['peerid']] = round($users[$val['peerid']] + $usdt, 8);
                } else {
                    $users[$val['peerid']] = $usdt;
                }
            }
        }
        $tun = count($users) * ($trade_manager['trade_total'] / 100);
        if ($tun < 1) {
            $tun = 1;
        } else {
            $tun = floor($tun);
        }
        $a = array_chunk($users, $tun)[0];
        $return['indexDiv']['hit_min'] = (min($a) ? min($a) : 0);
        $return['indexDiv']['hit_max'] = (max($a) ? max($a) : 0);
        if (!empty($uid)) {
            $return['indexDiv']['my_poundage'] = (isset($users[$uid]) ? $users[$uid] : 0);
        }
        return $return;
    }

    public function getTradeBlock($market, $userid, $name, $menu_id)
    {
        $return = [];
        $mmodel = new Market();
        $minfo = $mmodel->getMarketByName($market);

        $buyData = array();
        $length = 10;
        $buy = $this->redis->lRange('trade_buy' . $market, 0, -1);
        $sell = $this->redis->lRange('trade_sell' . $market, 0, -1);
        for ($i = 0; $i < $length; $i++) {
            if (!json_decode($buy[0], true)[$i]) {
                continue;
            }
            $buyData['buy'][] = json_decode($buy[0], true)[$i];;
        }
        $sell_data = array_reverse(json_decode($sell[0], true));
        for ($i = 0; $i < $length; $i++) {
            if (!$sell_data[$i]) {
                continue;
            }
            $buyData['sell'][] = $sell_data[$i];
        }
        $buyData['sell'] = array_reverse($buyData['sell']);

        $data['depth']['buy'] = $buyData['buy'];
        $data['depth']['sell'] = $buyData['sell'];
        $this->redis->set('ajax_buy' . $market, $buyData['buy'][0][0]);
        $this->redis->set('ajax_sell' . $market, $buyData['sell'][($length - 1)][0]);
        $this->redis->set('getJsonTop' . $market, null);
        $return['depth'] = $data;

        $model = new HiioneModel();
        $data = $this->redis->get('getTradelog' . $market);
        if (!$data) {
            $tradeLog = $model->setTable('trade_log' . $market, true)->order('id desc')->limit(50)->select();

            if ($tradeLog) {
                foreach ($tradeLog as $k => $v) {
                    $data['tradelog'][$k]['addtime'] = date('m-d H:i:s', $v['addtime']);
                    $data['tradelog'][$k]['type'] = $v['type'];
                    $data['tradelog'][$k]['price'] = $v['price'];
                    $data['tradelog'][$k]['num'] = round($v['num'], 6);
                    $data['tradelog'][$k]['mum'] = round($v['mum'], 6);
                    $data['tradelog'][$k]['price_rmb'] = changeToRMB($market, $v['price']);
                }

                $this->redis->set('getTradelog' . $market, $data);
            }
        }
        $return['tradelog'] = $data;

        $name = strtolower($name);
        $tables = $model->setTable('market', true)->aliase('l1')->fields('l1.name,l1.change')
            ->join('front_menu', 'l2', ['l1.menu', '=', 'l2.id'])
            ->where('l2.parent=' . $menu_id . ' AND l1.name LIKE \'%_' . $name . '\' AND l1.status=\'1\'')->select();
        $lists = [];
        foreach ($tables as $value) {
            $table = 'trade_log' . $value['name'];
            /**
             * dengkaiyang 2018-08-15
             * start
             */
            $tmp = $model->setTable($table, true)->fields('price as per1')
                ->order("endtime desc,addtime desc,id desc")->find();
            $tmp['p'] = $value['change'];
            /**
             * dengkaiyang
             * end
             */
            $tmp['coin'] = strtoupper(explode('_', $value['name'])[0]);
            $rmb = changeToRMB($value['name'], $tmp['per1']);
            if ($rmb <= 0) {
                $rmb = '0.00';
            }
            $tmp['per1_rmb'] = $rmb;
            $lists[] = $tmp;
        }
        $tmp = [];
        foreach ($lists as $key => $value) {
            $tmp[$key] = $value['coin'];
        }
        array_multisort($tmp, SORT_ASC, $lists);
        $return['zonedata'] = $lists;

        $data = $this->redis->get('getJsonTop' . $market);
        if (!$data) {
            if ($market) {
                $data['info']['img'] = $minfo['xnbimg'];
                $data['info']['title'] = $minfo['title'];
                $data['info']['new_price'] = $minfo['new_price'];
                $data['info']['new_price_rmb'] = changeToRMB($market, $minfo['new_price']);
                if ($minfo['zhang'] > 0) {
                    if ($minfo['hou_price'] > 0) {
                        $data['info']['zhang'] = $minfo['hou_price'] + floatval($minfo['hou_price'] * floatval(($minfo['zhang']) / 100));
                    }
                } else {
                    $data['info']['zhang'] = '';
                }
                if ($minfo['die'] > 0) {
                    if ($minfo['hou_price'] > 0) {
                        $data['info']['die'] = $minfo['hou_price'] - floatval($minfo['hou_price'] * floatval(($minfo['die']) / 100));
                    }
                } else {
                    $data['info']['die'] = '';
                }
                $data['info']['max_price'] = $minfo['max_price'];
                $data['info']['min_price'] = $minfo['min_price'];
                $data['info']['buy_price'] = $this->redis->get('ajax_buy' . $market) ? $this->redis->get('ajax_buy' . $market) : $minfo['buy_price'];
                $data['info']['sell_price'] = $this->redis->get('ajax_sell' . $market) ? $this->redis->get('ajax_sell' . $market) : $minfo['sell_price'];
                $totle_mum = round($model->setTable('trade_log' . $market)->sum('mum'), 6);
                $data['info']['volume'] = $totle_mum * 2;
                $data['info']['change'] = $minfo['change'];
                $this->redis->set('getJsonTop' . $market, $data);
            }
        }
        $return['topLine'] = $data;

        return $return;
    }
}