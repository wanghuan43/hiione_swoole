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

                $oneT = $this->tradeModel->fields('id')->where(['status' => 0, 'type' => '1'])
                    ->order("price DESC,id ASC")->find();
                MyLog::setLogLine('oneT:' . json_encode($oneT));
                $twoT = $this->tradeModel->fields('id')->where(['status' => 0, 'type' => '2'])
                    ->order("price ASC,id ASC")->find();
                MyLog::setLogLine('twoT:' . json_encode($twoT));

                if (!$oneT || !$twoT) {
                    throw new HiioneException('', '100');
                }

                $one = $this->tradeModel->where(['id' => $oneT['id']])
                    ->order("price DESC,id ASC")->lock()->find();
                MyLog::setLogLine('one:' . json_encode($one));
                $two = $this->tradeModel->where(['id' => $twoT['id']])
                    ->order("price ASC,id ASC")->lock()->find();
                MyLog::setLogLine('two:' . json_encode($two));

                if (!$one || !$two) {
                    throw new HiioneException('', '100');
                }
                $oneU = $this->userCoinModel->setTable('user_coin' . $this->coin2, true)->where(['userid' => $one['userid']])
                    ->lock()->find();
                MyLog::setLogLine('oneU:' . json_encode($oneU));
                $oneUt = $this->userCoinModel->setTable('user_coin' . $this->coin1, true)->where(['userid' => $one['userid']])
                    ->lock()->find();
                MyLog::setLogLine('oneUt:' . json_encode($oneUt));
                $twoU = $this->userCoinModel->setTable('user_coin' . $this->coin1, true)->where(['userid' => $two['userid']])
                    ->lock()->find();
                MyLog::setLogLine('twoU:' . json_encode($twoU));

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
                MyLog::setLogLine('do:' . $do);
                $dt = round($two['num'] - $two['deal'], $this->marketInfo['round']);
                MyLog::setLogLine('dt:' . $dt);
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
                } else {
                    $tl = $two['type'];
                }
                if ($i == 1 && $one['price'] < $two['price']) {
                    throw new HiioneException('', '100');
                }
                $price = ($tl == '1' ? $one['price'] : $two['price']);
                $num = ($do > $dt ? $dt : $do);
                if ($num <= 0) {
                    throw new HiioneException(['buy' => $one['id'], 'sell' => $two['id']], '200');
                }
                $mum = round($price * $num, $this->marketInfo['round']);
                $buy_fee = round($mum * $this->marketInfo['fee_buy'], $this->marketInfo['round']);
                $sell_fee = round($mum * $this->marketInfo['fee_sell'], $this->marketInfo['round']);
                $buy_total = round($mum + $buy_fee, $this->marketInfo['round']);
                $sell_total = round($mum - $sell_fee, $this->marketInfo['round']);
                if ($oneU[$this->coin2 . 'd'] < $buy_total) {
                    MyLog::setLogLine('买家-冻结不够:' . $oneU[$this->coin2 . 'd'] . ":实际" . $buy_total);
                    throw new HiioneException($one['id'], '200');
                }
                if ($twoU[$this->coin1 . 'd'] < $num) {
                    MyLog::setLogLine('卖家-冻结不够:' . $twoU[$this->coin1 . 'd'] . ":实际" . $num);
                    throw new HiioneException($two['id'], '200');
                }
                if ($one['num'] < $do) {
                    MyLog::setLogLine('买家-数量异常:' . $do . ":实际" . $one['num']);
                    throw new HiioneException($one['id'], '200');
                }
                if ($two['num'] < $dt) {
                    MyLog::setLogLine('卖家-数量异常:' . $dt . ":实际" . $two['num']);
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

                $rs[] = $this->userCoinModel->changeUserCoin($this->coin1, $this->coin1, $one['userid'], $num, 'INC');
                $rs[] = $this->userCoinModel->changeUserCoin($this->coin2, $this->coin2, $two['userid'], $sell_total, 'INC');

                $rs[] = $this->userCoinModel->changeUserCoin($this->coin2, $this->coin2 . "d", $one['userid'], $buy_total, 'SUB');
                $rs[] = $this->userCoinModel->changeUserCoin($this->coin1, $this->coin1 . "d", $two['userid'], $num, 'SUB');

                $rs[] = $model->setTable('finance_' . $this->coin1, true)->save([
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

                $rs[] = $model->setTable('finance_' . $this->coin2, true)->save([
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
                if (($num + $one['deal']) == $one['num']) {
                    $this->tradeModel->where(['id' => $one['id']])->save(['status' => '1'], true);
                }
                if (($num + $two['deal']) == $two['num']) {
                    $this->tradeModel->where(['id' => $two['id']])->save(['status' => '1'], true);
                }
                if (check_arr($rs)) {
                    MyLog::setLogLine('撮合成功');
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
                    throw new HiioneException('', '900');
                } else {
                    MyLog::setLogLine('撮合失败');
                    throw new HiioneException('', '100');
                }
            } catch (HiioneException $e) {
                MyLog::setLogLine('异常:' . $e->getCode() . "(" . $e->getMessage() . ")");
                if ($e->getCode() != '900') {
                    $model->rollback();
                }
                if ($e->getCode() == '100') {
                    break;
                }
                switch ($e->getCode()) {
                    case "200":
                        $this->tradeModel->where(['id' => $e->getMessage()])->save(['status' => '3'], true);
                        MyLog::setLogLine('保存200:' . $this->tradeModel->getLastSql());
                        break;
                    case "400":
                        $this->tradeModel->where(['id' => $e->getMessage()])->save(['status' => '1'], true);
                        MyLog::setLogLine('保存400:' . $this->tradeModel->getLastSql());
                        break;
                }
                if ($i >= 10) {
                    break;
                }
                $i++;
            }
        }
        return true;
    }

    private function tradedig($type, $buy, $sell, $buy_fee, $sell_fee, $market)
    {
        MyLog::setLogLine('挖矿');
        $model = new HiioneModel();
        $times = time();
        $base = 60 * 60;
        $dig = $this->redis->get('trade_manager');
        MyLog::setLogLine('trade_manager:' . json_encode($dig));
        $cm = $this->redis->get('manager');
        MyLog::setLogLine('manager:' . json_encode($cm));
        $ex = $this->redis->get('extend');
        MyLog::setLogLine('extend:' . json_encode($ex));
        if (empty($ex['trade'])) {
            MyLog::setLogLine("错误1");
            return false;
        }
        if ($cm['trade_num'] <= 0) {
            MyLog::setLogLine("错误2");
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
            MyLog::setLogLine("错误3");
            return false;
        }
        $left = $this->redis->get('dig' . date('Ymd') . $tt, false);
        MyLog::setLogLine('dig' . date('Ymd') . $tt . ':' . json_encode($left));
        if ($left === null) {
            $left = $tv;
        } elseif ($left <= 0) {
            MyLog::setLogLine("错误4");
            return false;
        }
        $fee = round(($type == 1 ? $buy_fee : $sell_fee) * ($dig['dig_per'] / 100), 8);
        $userid = ($type == 1 ? $buy['userid'] : $sell['userid']);
        $tmp = explode("_", $market);
        $coin = $tmp[1];
        if ($coin == 'hit') {
            MyLog::setLogLine("错误5");
            return false;
        }
        if ($tmp[0] == 'hit' && $type == 1) {
            MyLog::setLogLine("错误6");
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
        $tmp = $model->setTable('coin_slog', true)->fields('SUM(num) AS total')->where(['date' => ['BETWEEN', [$b, $e - 1]], 'userid' => $userid, 'type' => 10, 'user_per' => 0])->find();
        $sum = (empty($tmp['total']) ? 0 : $tmp['total']);
        $per = round($sum / $tv * 100, 2);
        $eve = round($tv * 0.01, 8);
        $lp = round($dig['dig_user'] - $per, 2);
        if ($lp > 0) {
            $t = round($lp * $eve, 8);
            if ($t < $fee) {
                $fee = $t;
            }
        } else {
            MyLog::setLogLine("错误7");
            return false;
        }
        $this->userCoinModel->setTable('user_coinhit', true);
        $q = $model->setTable('auto_trade', true)
            ->fields('userid')->group('userid')->order('userid ASC')->select();
        $autoTrade = [];
        foreach ($q as $vv) {
            $autoTrade[$vv['userid']] = $vv['userid'];
        }
        if (array_key_exists($userid, $autoTrade)) {
            $userid = $ex['plat_user'];
        }
        $this->userCoinModel->beginTransaction();
        $update = $this->userCoinModel->changeUserCoin('hit', 'hit', $userid, $fee);
        if ($update) {
            $this->userCoinModel->commit();
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
            $this->userCoinModel->rollback();
        }
    }
}