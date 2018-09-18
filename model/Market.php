<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/30
 * Time: 18:32
 */

namespace hiione\model;

use hiione\library\HiioneModel;

class Market extends HiioneModel
{
    public function getMarketByName($name)
    {
        return $this->setTable('market')->where(['name' => $name])->find();
    }
}