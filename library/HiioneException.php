<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/30
 * Time: 16:42
 */

namespace hiione\library;

use Throwable;

class HiioneException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}