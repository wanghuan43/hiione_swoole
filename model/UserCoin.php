<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/9/15
 * Time: 02:33
 */

namespace hiione\model;


use hiione\library\HiioneModel;

class UserCoin extends HiioneModel
{
    public function changeUserCoin($coin, $field, $userid, $num, $op = 'INC')
    {
        $this->setTable('user_coin' . $coin, true);
        $this->checkUserCoin($coin, $userid);
        if ($op == 'INC') {
            $rs = $this->where(['userid' => $userid])->setInc($field, $num);
        } else {
            $rs = $this->where(['userid' => $userid])->setSub($field, $num);
        }
        return $rs;
    }

    public function checkUserCoin($coin, $userid)
    {
        $this->setTable('user_coin' . $coin, true);
        $check = $this->where(['userid' => $userid])->find();
        if (!$check) {
            $this->save([
                'userid' => $userid,
                $coin => 0,
                $coin . "d" => 0,
                $coin . "b" => 0,
            ]);
        }
    }
}