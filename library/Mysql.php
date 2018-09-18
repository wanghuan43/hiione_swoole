<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/8/31
 * Time: 09:41
 */

namespace hiione\library;

class Mysql
{
    protected static $db;
    protected $pre_sql = [
        'table' => [],
        'fields' => '',
        'where' => [],
        'join' => [],
        'order' => [],
        'group' => [],
        'limit' => '',
        'lock' => false,
    ];
    protected static $prefix;
    protected $query;

    public static function init($config)
    {
        $dsn = "mysql:host=" . $config['hostname'] . ";dbname=" . $config['database'] . ";port=" . $config['hostport'];
        self::$db = new \PDO($dsn, $config['username'], $config['password'], array(\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"));
        self::$prefix = $config['prefix'];
    }

    public function beginTransaction()
    {
        self::$db->exec('SET AUTOCOMMIT=0');
        return self::$db->beginTransaction();
    }

    public function rollback()
    {
        $rs = self::$db->rollBack();
        self::$db->exec('SET AUTOCOMMIT=1');
        return $rs;
    }

    public function commit()
    {
        $rs = self::$db->commit();
        self::$db->exec('SET AUTOCOMMIT=1');
        return $rs;
    }

    public function setTable($table, $isOne = false)
    {
        if ($isOne) {
            $this->pre_sql['table'] = [];
        }
        $this->pre_sql['table'][$table] = self::$prefix . $table;
        return $this;
    }

    public function where(array $conditions)
    {
        array_merge($this->pre_sql['where'], $this->createWhere($conditions));
        return $this;
    }

    public function order($order)
    {
        $this->pre_sql['order'][] = $order;
        return $this;
    }

    public function group($group)
    {
        $this->pre_sql['group'][] = $group;
        return $this;
    }

    public function limit($limit)
    {
        $this->pre_sql['limit'] = $limit;
        return $this;
    }

    public function aliase($table, $alias)
    {
        if (!isset($this->pre_sql['table'][$table])) {
            $this->setTable($table);
        }
        array_push($this->pre_sql['table'][$table], $alias);
        return $this;
    }

    public function fields($fields)
    {
        $this->pre_sql['fields'] = $fields;
        return $this;
    }

    public function join($table, $aliase, $data, $op = 'LEFT')
    {
        $table = self::$prefix . $table;
        $this->pre_sql['join'][] = $op . " JOIN " . $table . " AS " . $aliase . " ON " . $aliase . "." . $data[0] . $data[1] . $data[2];
        return $this;
    }

    public function query($sql = '')
    {
        if (empty($sql)) {
            $sql = $this->createSql();
        }
        $this->query = self::$db->query($sql);
        return $this->query;
    }

    public function find()
    {
        $this->limit(1);
        return $this->query()->fetch(\PDO::FETCH_ASSOC);
    }

    public function select()
    {
        return $this->query()->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function sum($filed)
    {
        $tmp = $this->fields('SUM(`' . $filed . '`) AS sumC')->find();
        return $tmp['sumC'];
    }

    public function createSql($sql = NULL)
    {
        if (is_null($sql)) {
            $sql = 'SELECT ';
            if (empty($this->pre_sql['fields'])) {
                $sql .= '* FROM ';
            } else {
                $sql .= $this->pre_sql['fields'] . ' FROM ';
            }
            $table = [];
            if (count($this->pre_sql['table']) > 0) {
                foreach ($this->pre_sql['table'] as $value) {
                    if (is_array($value)) {
                        $table[] = $value[0] . " AS " . $value[1];
                    } else {
                        $table[] = $value;
                    }
                }
            } else {
                $class = explode("\\", get_class($this));
                $table[] = self::$prefix . strtolower(preg_replace("/([A-Z])/", "_\\1", lcfirst($class[count($class) - 1])));
            }
            $sql .= implode(",", $table) . " ";
            if (count($this->pre_sql['join']) > 0) {
                $sql .= implode(" ", $this->pre_sql['join']);
            }
            if (count($this->pre_sql['where']) > 0) {
                $sql .= "WHERE " . implode('and', $this->pre_sql['where']) . " ";
            }
            if (count($this->pre_sql['group']) > 0) {
                $sql .= "GROUP BY " . implode(',', $this->pre_sql['group']) . " ";
            }
            if (count($this->pre_sql['order']) > 0) {
                $sql .= "ORDER BY " . implode(',', $this->pre_sql['order']) . " ";
            }
            if (!empty($this->pre_sql['limit'])) {
                $sql .= "LIMIT " . $this->pre_sql['limit'] . " ";
            }
            if ($this->pre_sql['lock']) {
                $sql .= "FOR UPDATE";
            }
        }
        $this->cleanSql();
        return $sql;
    }

    public function getLastSql()
    {
        return $this->createSql();
    }

    public function lock()
    {
        $this->pre_sql['lock'] = true;
        return $this;
    }

    public function save($data, $isUpdate = false, $batch = false)
    {
        $sql = $this->createExecSql($data, $isUpdate);
        $num = self::$db->exec($sql);
        if (!$isUpdate && !$batch) {
            return self::$db->lastInsertId();
        }
        return $num;
    }

    public function setInc($field, $num = 1)
    {
        return $this->save([$field => [$field, '+', $num]]);
    }

    public function setSub($field, $num = 1)
    {
        return $this->save([$field => [$field, '-', $num]]);
    }

    public function getLastId()
    {
        return self::$db->lastInsertId();
    }

    protected function createExecSql($data, $isUpdate, $batch = false)
    {
        if (!$isUpdate) {
            $sql = "INSERT INTO " . $this->pre_sql['table'][0];
            $keys = array_keys($data);
            $values = array_values($data);
            $sql .= "(`" . implode("`,`", $keys) . "`)";
            if ($batch) {
                $sql .= " VALUES('" . implode("','", $values) . "')";
            } else {
                $sql .= " VALUE('" . implode("','", $values) . "')";
            }
        } else {
            $sql = "UPDATE " . $this->pre_sql['table'][0] . " SET ";
            $sets = [];
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $sets[] = "`" . $key . "`=`" . $value[0] . "`" . $value[1] . "'" . $value[2] . "'";
                } else {
                    $sets[] = "`" . $key . "`='" . $value . "'";
                }
            }
            $sql .= implode(",", $sets);
            if (count($this->pre_sql['where']) > 0) {
                $sql .= " WHERE " . implode('and', $this->pre_sql['where']);
            }
        }
        $this->cleanSql();
        return $sql;
    }

    protected function cleanSql()
    {
        $this->pre_sql = [
            'fields' => '',
            'where' => [],
            'join' => [],
            'order' => [],
            'group' => [],
            'limit' => '',
            'lock' => false,
        ];
    }

    private function createWhere($where)
    {
        $r = [];
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                switch (strtolower($value[0])) {
                    case 'between':
                        $where = "`$key` " . $value[0] . " '" . implode("' AND '", $value[1]) . "'";
                        break;
                    default:
                        $where = "`$key`" . $value[0] . "'" . $value[1] . "'";
                        break;
                }
            } else {
                $where = "`$key`='" . $value . "'";
            }
            $r[] = $where;
        }
        return $r;
    }
}