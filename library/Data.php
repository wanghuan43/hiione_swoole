<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/30
 * Time: 16:41
 */

namespace hiione\library;

use hiione\model\Market;

class Data
{
    protected $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function getIndexBlock($ids)
    {
        $marketModel = new Market();
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
                if ($v['begintrade']) {
                    $v['begintrade'] = $v['begintrade'];
                } else {
                    $v['begintrade'] = "00:00:00";
                }
                if ($v['endtrade']) {
                    $v['endtrade'] = $v['endtrade'];
                } else {
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
            $this->redis->set('trends', $data);
        }
        $return['trades'] = $data;
        return $return;
    }

    public function getTradeBlock()
    {
        return ['我是交易代码'];
    }
}