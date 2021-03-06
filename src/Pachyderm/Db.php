<?php

namespace Pachyderm;

class DuplicateException extends \Exception {};

class Db
{
    protected static $_instance = NULL;
    protected $_mysql = NULL;

    protected $_last_query = '';

    public function __construct()
    {
        $this->_mysql = new \MySQLi(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    }

    public static function getInstance() {
        if(!self::$_instance) {
            self::$_instance = new Db();
        }
        return self::$_instance;
    }

    public static function query($query) {
        $db = self::getInstance();
        return $db->_query($query);
    }

    public static function escape($field) {
        $db = self::getInstance();
        return $db->mysql()->real_escape_string($field);
    }

    public function mysql() {
        return $this->_mysql;
    }

    public function _query($query) {
        $this->_last_query = $query;
        $result = $this->_mysql->query($query);

        $this->checkDbError();
        return $result;
    }

    public function getInsertedId()
    {
        if($id = $this->_mysql->insert_id)
        {
            return $id;
        }
        return FALSE;
    }

    public function getAffectedRows()
    {
        return $this->_mysql->affected_rows;
    }

    protected function checkDbError()
    {
        if(!empty($this->_mysql->error))
        {
            switch($this->_mysql->errno)
            {
                case 1062:
                    throw new DuplicateException($this->_mysql->error);
                default:
                    throw new \Exception('SQL Error: ' . $this->_mysql->error . ' Last Query:(' . $this->_last_query . ')');
            }
        }
        if($this->_mysql->warning_count != 0)
        {
            $message = '';
            if ($result = $this->_mysql->query("SHOW WARNINGS"))
            {
                while($row = $result->fetch_row())
                {
                    $message .= $row[0] . ' (' . $row[1] . '): ' . $row[2] . PHP_EOL;
                }
                $result->close();
            }
            throw new \Exception('SQL Warning: ' . $message . ' Last Query:(' . $this->_last_query . ')');
        }
        return TRUE;
    }

    /**
     * @param $table String Table name
     * @param array $payload Data to insert
     * @return false|integer Return new inserted id if success, false otherwise
     */
    public static function insert($table, array $payload) {
        $sql = 'INSERT INTO '.$table.'('.implode(', ', array_keys($payload)).') VALUES(';
        foreach (array_values($payload) as $value) {
            $sql .= "'".self::escape($value)."',";
        }
        $sql = (substr($sql, 0, -1)).')';

        self::query($sql);
        return self::getInstance()->getInsertedId();
    }

    /**
     * @param $table String table name
     * @param array $content Data to insert
     * @param $where String Where condition
     */
    public static function update($table, array $content, $where) {
        $sql = 'UPDATE '.$table.' SET ';
        foreach ($content as $column => $value) {
            $sql .= $column.' = "'.self::escape($value).'",';
        }
        $sql = (substr($sql, 0, -1));
        $sql .= ' WHERE '.self::escape($where);

        Db::query($sql);
    }
}

