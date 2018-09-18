<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/9/15
 * Time: 02:31
 */

namespace hiione\library;


use hiione\model\Market;
use hiione\model\Trade;
use hiione\model\TradeLog;
use hiione\model\UserCoin;

class HiioneMatch
{
    protected $tradeModel;
    protected $tradeLogModel;
    protected $userCoinModel;
    protected $coin1;
    protected $coin2;
    protected $market;
    protected $type;
    protected $price;
    protected $thisPrice;
    protected $baseLimit = 20;
    protected $redis;

    public function __construct($market, $type, $redis, $price = 0)
    {
        $this->redis = $redis;
        $tmp = new Market();
        $this->price = $price;
        $this->type = $type;
        $this->market = $market;
        $this->marketInfo = $tmp->getMarketByName($market);
        $this->coin1 = explode('_', $market)[0];
        $this->coin2 = explode('_', $market)[1];
        $this->tradeModel = new Trade();
        $this->tradeLogModel = new TradeLog();
        $this->userCoinModel = new UserCoin();
        $this->tradeModel->setTable('trade' . $this->market, true);
        $this->tradeLogModel->setTable('trade_log' . $this->market, true);
    }

    public function matchTrade()
    {
        $model = new HiioneModel();
        $i = 1;
        while (true) {
            try {
                $model->beginTransaction();
                $one = $this->tradeModel->where(['status' => 0, 'type' => '1'])
                    ->order("price DESC,id ASC")->lock()->find();
                $two = $this->tradeModel->where(['status' => 0, 'type' => '2'])
                    ->order("price ASC,id ASC")->lock()->find();
                $oneU = $this->userCoinModel->setTable('user_coin' . $this->coin2, true)->where(['userid' => $one['userid']])
                    ->lock()->find();
                $oneUt = $this->userCoinModel->setTable('user_coin' . $this->coin1, true)->where(['userid' => $one['userid']])
                    ->lock()->find();
                $twoU = $this->userCoinModel->setTable('user_coin' . $this->coin1, true)->where(['userid' => $one['userid']])
                    ->lock()->find();
                if ($one['price'] < $two['price']) {
                    throw new HiioneException('价格不匹配,程序结束', '100');
                }
                if ($one['num'] == $one['deal']) {
                    throw new HiioneException($one['id'], '400');
                }
                if ($two['num'] == $two['deal']) {
                    throw new HiioneException($two['id'], '400');
                }
                $do = round($one['num'] - $one['deal'], $this->marketInfo['round']);
                $dt = round($two['num'] - $two['deal'], $this->marketInfo['round']);
                if ($do == 0) {
                    throw new HiioneException($one['id'], '400');
                }
                if ($do < 0) {
                    throw new HiioneException($one['id'], '200');
                }
                if ($dt == 0) {
                    throw new HiioneException($two['id'], '400');
                }
                if ($dt < 0) {
                    throw new HiioneException($two['id'], '200');
                }
                if ($one['id'] < $two['id']) {
                    $tl = $one['type'];
                    $price = $one['price'];
                } else {
                    $tl = $two['type'];
                    $price = $two['price'];
                }
                $num = ($do > $dt ? $dt : $do);
                $mum = round($price * $num, $this->marketInfo['round']);
                $buy_fee = round($mum * $this->marketInfo['fee_buy'], $this->marketInfo['round']);
                $sell_fee = round($mum * $this->marketInfo['fee_sell'], $this->marketInfo['round']);
                $buy_total = round($mum + $buy_fee, $this->marketInfo['round']);
                $sell_total = round($mum - $sell_fee, $this->marketInfo['round']);
                if ($oneU[$this->coin2 . 'd'] < $buy_total) {
                    throw new HiioneException($one['id'], '200');
                }
                if ($twoU[$this->coin1 . 'd'] < $num) {
                    throw new HiioneException($two['id'], '200');
                }
                if ($one['num'] < ($do + $num)) {
                    throw new HiioneException($one['id'], '200');
                }
                if ($two['num'] < ($dt + $num)) {
                    throw new HiioneException($two['id'], '200');
                }
                $rs[] = $this->tradeLogModel->save([
                    'userid' => $one['userid'],
                    'peerid' => $two['userid'],
                    'market' => $this->market,
                    'price' => $price,
                    'num' => $num,
                    'mum' => $mum,
                    'type' => $tl,
                    'fee_buy' => $buy_fee,
                    'fee_sell' => $sell_fee,
                    'addtime' => time(),
                    'status' => 1
                ]);
                $rs[] = $this->tradeModel->where(['id' => $one['id']])->setInc('deal', $num);
                $rs[] = $this->tradeModel->where(['id' => $two['id']])->setInc('deal', $num);

                $rs[] = $this->tradeModel->where(['id' => $one['id']])->save(['status' => '1'], true);
                $rs[] = $this->tradeModel->where(['id' => $two['id']])->save(['status' => '1'], true);

                $this->userCoinModel->setTable('user_coin' . $this->coin1, true);
                $rs[] = $this->userCoinModel->where(['userid' => $one['userid']])->setInc($this->coin1, $num);
                $this->userCoinModel->setTable('user_coin' . $this->coin2, true);
                $rs[] = $this->userCoinModel->where(['userid' => $one['userid']])->setSub($this->coin2 . "d", $buy_total);

                $this->userCoinModel->setTable('user_coin' . $this->coin1, true);
                $rs[] = $this->userCoinModel->where(['userid' => $one['userid']])->setSub($this->coin1 . "d", $num);
                $this->userCoinModel->setTable('user_coin' . $this->coin2, true);
                $rs[] = $this->userCoinModel->where(['userid' => $one['userid']])->setInc($this->coin2, $sell_total);

                $rs[] = $model->setTable('finance' . $this->coin1, true)->save([
                    'userid' => $one['userid'],
                    'coinname' => $this->coin1,
                    'num_a' => $oneUt[$this->coin1],
                    'num_b' => $oneUt[$this->coin1 . 'd'],
                    'num' => round($oneUt[$this->coin1] + $oneUt[$this->coin1 . 'd'], $this->marketInfo['round']),
                    'fee' => $buy_fee,
                    'type' => 2,
                    'name' => 'tradelog',
                    'nameid' => $one['id'],
                    'remark' => '交易中心-成功买入-市场' . $this->market,
                    'mum_a' => round($oneUt[$this->coin1] + $num, $this->marketInfo['round']),
                    'mum_b' => $oneUt[$this->coin1 . 'd'],
                    'mum' => round(round($oneUt[$this->coin1] + $num, $this->marketInfo['round']) + $oneUt[$this->coin1 . 'd'], $this->marketInfo['round']),
                    'move' => getFinanceHash(),
                    'addtime' => time(),
                    'status' => 1
                ]);

                $rs[] = $model->setTable('finance' . $this->coin2, true)->save([
                    'userid' => $two['userid'],
                    'coinname' => $this->coin2,
                    'num_a' => $two[$this->coin2],
                    'num_b' => $two[$this->coin2 . 'd'],
                    'num' => round($two[$this->coin2] + $two[$this->coin2 . 'd'], $this->marketInfo['round']),
                    'fee' => $sell_fee,
                    'type' => 2,
                    'name' => 'tradelog',
                    'nameid' => $two['id'],
                    'remark' => '交易中心-成功卖出-市场' . $this->market,
                    'mum_a' => round($two[$this->coin2] + $sell_total, $this->marketInfo['round']),
                    'mum_b' => $two[$this->coin2 . 'd'],
                    'mum' => round(round($two[$this->coin2] + $sell_total, $this->marketInfo['round']) + $two[$this->coin2 . 'd'], $this->marketInfo['round']),
                    'move' => getFinanceHash(),
                    'addtime' => time(),
                    'status' => 1
                ]);

                if (check_arr($rs)) {
                    $model->commit();
                    $this->tradedig($tl, $one, $two, $buy_fee, $sell_fee, $this->market);
                    $this->redis->delCache([
                        'wkj_allcoin' . $this->marketInfo['menu_id'],
                        'marketjiaoyie24',
                        'marketappjiaoyi',
                        'allsum',
                        'getJsonTop' . $this->market,
                        'getTradelog' . $this->market,
                        'getDepth' . $this->market . '1',
                        'getDepth' . $this->market . '3',
                        'getDepth' . $this->market . '4',
                        'ChartgetJsonData' . $this->market,
                        'allcoin',
                        'trends',
                    ]);
                } else {
                    throw new HiioneException('', '100');
                }
            } catch (HiioneException $e) {
                $model->rollback();
                if ($e->getCode() == '100') {
                    break;
                }
                switch ($e->getCode()) {
                    case "200":
                        $this->tradeModel->where(['id' => $e->getMessage()])->save(['status' => 3], true);
                        break;
                    case "400":
                        $this->tradeModel->where(['id' => $e->getMessage()])->save(['status' => 1], true);
                        break;
                }
            } finally {
                $i++;
            }
        }
        return true;
    }

    private function tradedig($type, $buy, $sell, $buy_fee, $sell_fee, $market)
    {
        $model = new HiioneModel();
        $times = time();
        $base = 60 * 60;
        $dig = $this->redis->get('trade_manager');
        $cm = $this->redis->get('manager');
        $ex = $this->redis->get('extend');
        if (empty($ex['trade'])) {
            return false;
        }
        if ($cm['trade_num'] <= 0) {
            return false;
        }
        $hour = intval(date('G', $times));
        $pi = $hour * $base;
        $tt = $tv = 0;
        $b = $e = strtotime(date('Y-m-d', $times) . " 00:00:00");
        foreach ($dig['dig_time'] as $key => $value) {
            $begin = $key * $base;
            $end = ($key + $dig['dig_hour']) * $base - 1;
            if ($pi >= $begin && $pi <= $end) {
                $b += $begin;
                $e += $end;
                $tt = intval(date('G', $e + 1));
                $tv = $dig['dig_time'][$tt];
                break;
            }
        }
        if (empty($tv)) {
            return false;
        }
        $left = $this->redis->get('dig' . date('Ymd') . $tt, false);
        if ($left === false) {
            $left = $tv;
        } elseif ($left <= 0) {
            return false;
        }
        $fee = round(($type == 1 ? $buy_fee : $sell_fee) * ($dig['dig_per'] / 100), 8);
        $userid = ($type == 1 ? $buy['userid'] : $sell['userid']);
        $tmp = explode("_", $market);
        $coin = $tmp[1];
        if ($coin == 'hit') {
            return false;
        }
        if ($tmp[0] == 'hit' && $type == 1) {
            return false;
        }
        $rates = $this->redis->get('rates');
        if ($coin != 'hit') {
            $sk = $coin . "-hit";
            if (isset($rates['rates'][$sk])) {
                $fee = round($fee * $rates['rates'][$sk], 8);
            }
        }
        if ($fee > $left) {
            $fee = $left;
        }
        $tmp = $model->setTable('coin_slog', true)->field('SUM(num) AS total')->where(['date' => ['BETWEEN', [$b, $e - 1]], 'userid' => $userid, 'type' => 10, 'user_per' => 0])->find();
        $sum = $tmp['total'];
        $per = round($sum / $tv * 100, 2);
        $eve = round($tv * 0.01, 8);
        $lp = round($dig['dig_user'] - $per, 2);
        if ($lp > 0) {
            $t = round($lp * $eve, 8);
            if ($t < $fee) {
                $fee = $t;
            }
        } else {
            return false;
        }
        $uc = new UserCoin();
        $uc->setTable('user_coinhit', true);
        $q = $model->setTable('auto_trade', true)->aliase('auto_trade', 'a')
            ->field('b.name,a.userid')->join('market', 'b', ['market', '=', 'b.id'])->select();
        $autoTrade = [];
        foreach ($q as $vv) {
            $autoTrade[$vv['userid']][$vv['name']] = $vv['name'];
        }
        if (array_key_exists($userid, $autoTrade)) {
            $userid = $ex['plat_user'];
        }
        $uc->startTrans();
        $sqlTMP = $uc->fields('COUNT(1) as c')->where(['userid' => $userid])->lock()->find();
        if (!empty($sqlTMP['c'])) {
            $update = $uc->where(['userid' => $userid])->setInc('hit', $fee);
        } else {
            $update = $uc->save(['userid' => $userid, 'hit' => $fee, 'hitd' => 0, 'hitb' => 0]);
        }
        if ($update) {
            $uc->commit();
            $left = round($left - $fee, 8);
            $this->redis->set('dig' . date('Ymd', $times) . $tt, $left, false);
            $model->setTable('coin_slog', true)->save([
                'date' => $times,
                'year' => date('Y', $times),
                'month' => date('m', $times),
                'day' => date('d', $times),
                'hour' => date('H', $times),
                'min' => date('i', $times),
                'sec' => date('s', $times),
                'userid' => $userid,
                'num' => $fee,
                'mnum' => $left,
                'tnum' => $cm['trade_num'],
                'user_per' => '0',
                'type' => '10',
                'coin' => '10',
            ]);
        } else {
            $uc->rollback();
        }
    }
}