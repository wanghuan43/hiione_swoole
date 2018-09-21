<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/29
 * Time: 13:30
 */

namespace hiione\library;

use Swoole\WebSocket\Server AS WebSocketServer;
use Swoole\Http\Request;

class HiioneServer
{
    protected $server;
    protected $fd;
    protected $frame = [];
    protected $request;
    private $config;
    protected static $init = [];
    protected $redis;
    private $inableType = ['init'];
    private $inableBlock = ['index_block', 'trade_block', 'match_block', 'kline_block'];
    protected static $_instance;

    public function __construct($config, $host, $port, $redis)
    {
        $this->redis = $redis;
        $this->config = $config;
        $this->server = new WebSocketServer($host, $port);
        $this->server->set($config);
        $this->server->on('open', array($this, 'onOpen'));
        $this->server->on('message', array($this, 'onMessage'));
        $this->server->on('close', array($this, 'onClose'));
        self::$_instance = $this;
    }

    public function onOpen($server, Request $request)
    {
        MyLog::setLogLine(date('Y-m-d H:i:s', time()) . "onOpen:server:" . json_encode($server));
        MyLog::setLogLine(date('Y-m-d H:i:s', time()) . "onOpen:request:" . json_encode($request));
        $this->server = $server;
        $this->request = $request;
        $this->setFrame($this->request->fd);
        $this->sendMessage('200', '欢迎进入', $this->request->fd);
    }

    public function onMessage($server, $frame)
    {
        MyLog::setLogLine(date('Y-m-d H:i:s', time()) . "onMessage:frame:" . json_encode($frame));
        $this->exMessage($frame);
    }

    public function onClose($server, $fd)
    {
        MyLog::setLogLine(date('Y-m-d H:i:s', time()) . "onClose:frame:" . $fd);
        $this->delFrame($fd);
    }

    public function sendMessage($status = 200, $message = '', $fd = '')
    {
        $content = json_encode(['status' => $status, 'content' => $message], JSON_UNESCAPED_UNICODE);
        MyLog::setLogLine(date('Y-m-d H:i:s', time()) . ":sendmessage:" . $content);
        if (empty($fd)) {
            foreach ($this->frame as $key => $value) {
                $this->server->push($key, $content);
            }
        } else {
            $this->server->push($fd, $content);
        }
    }

    public function startWeb()
    {
        $this->server->start();
    }

    public function setFrame($fd, $sessionid = '')
    {
        if (!isset($this->frame[$fd])) {
            $this->frame[$fd] = $fd;
        }
        if (!empty($sessionid)) {
            $old = array_search($sessionid, $this->frame);
            if ($old !== false) {
                if ($old != $fd) {
                    $this->delFrame($old);
                } else {
                    $this->frame[$fd] = $sessionid;
                }
            } else {
                $this->frame[$fd] = $sessionid;
            }
        }
    }

    public function delFrame($fd)
    {
        $this->server->close($fd);
        if (isset($this->frame[$fd])) {
            unset($this->frame[$fd]);
        }
    }

    private function exMessage($frame)
    {
        try {
            if (empty($frame->data)) {
                throw new HiioneException('非法访问,我们会关闭此次链接1', 404);
            } else {
                $message = json_decode($frame->data, true);
            }
            if (empty($message['type']) && empty($message['block'])) {
                throw new HiioneException('非法访问,我们会关闭此次链接2', 404);
            }
            if (!empty($message['type'])) {
                if (!in_array($message['type'], $this->inableType)) {
                    throw new HiioneException('非法访问,我们会关闭此次链接3', 404);
                }
                switch ($message['type']) {
                    case 'init':
                        self::$init = [
                            'sessionid' => $message['sessionid'],
                            'language' => $message['language'],
                        ];
                        $this->setFrame($frame->fd, $message['sessionid']);
                        break;
                    default:
                        throw new HiioneException('非法访问,我们会关闭此次链接4', 404);
                        break;
                }
            } elseif (!empty($message['block'])) {
                if (!in_array($message['block'], $this->inableBlock)) {
                    throw new HiioneException('非法访问,我们会关闭此次链接5', 404);
                }
                $data = new Data($this->redis);
                switch ($message['block']) {
                    case 'index_block':
                        $return = $data->getIndexBlock($message['ids'], $message['uuu']);
                        break;
                    case 'trade_block':
                        $return = $data->getTradeBlock($message['market'], $message['uuu']);
                        break;
                    case 'match_block':
                        MyLog::setLogLine('进入撮合');
                        $hm = new HiioneMatch($message['market'], $message['tradeType'], $this->redis);
                        $return = $hm->matchTrade();
                        MyLog::setLogLine(json_encode($return));
                        break;
                    case 'kline_block':
                        MyLog::setLogLine('进入撮合');
                        $message['since'] = isset($message['since']) ? round($message['since'] / 1000) : time();
                        $hm = new Kline($this->redis, $message['symbol'], $message['times'], $message['size'], $message['since']);
                        $return = $hm->getKline();
                        MyLog::setLogLine(json_encode($return));
                        break;
                    default:
                        throw new HiioneException('非法访问,我们会关闭此次链接6', 404);
                        break;
                }
                $this->sendMessage(200, $return, $frame->fd);
            }
        } catch (HiioneException $e) {
            MyLog::setLogLine($e->getCode() . ':' . $e->getMessage() . ':' . $e->getLine());
            $this->sendMessage($e->getCode(), $e->getMessage(), $frame->fd);
            usleep(300);
            $this->server->close($frame->fd);
        }
    }

    public static function getInit()
    {
        return self::$init;
    }

    public static function getInstance()
    {
        return self::$_instance;
    }
}