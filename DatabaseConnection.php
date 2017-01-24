<?php

/**
 * PDO wrapper
 */
class DatabaseConnection extends PDO {
    /**
     * @param string $dbname
     * @param string $dbuser
     * @param string $dbpass
     * @param string $dbhost
     */
    function __construct($dbname, $dbuser, $dbpass, $dbhost) {
        // Check Unix socket in MySQL host
        $socket = null;
        if (preg_match('/^(.+):(.+$)/', $dbhost, $matches)) {
            $dbhost = $matches[1];
            $socket = $matches[2];
        }
        
        parent::__construct(
            sprintf('mysql:%s;dbname=%s;charset=utf8',
                $socket? ('unix_socket=' . $socket) : ('host=' . $dbhost),
                $dbname),
            $dbuser,
            $dbpass,
            [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
            ]
        );
        
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->useBufferedQuery(true);
    }
        
    /**
     * Do a SELECT query
     * @param string $query
     * @param array $values
     * @param bool $singleRecord
     * @param callable $callback
     * @return array|object
     */
    public function querySelect($query, $values = [], $singleRecord = false, $callback = null) {
        $output = [];
        $stmt = $this->createStatement($query, $values);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = is_callable($callback)? $callback($row) : $row;
                if (!is_null($value)) {
                    $output[] = $value;
                }
            }
            $stmt->closeCursor();
        }
        $stmt = null;
        return $singleRecord? array_pop($output) : $output;
    }
    
    /**
     * Execute an SQL query
     * @param string $query
     * @param array $values
     * @return bool
     */
    public function queryExecute($query, $values = []) {
        $stmt = $this->createStatement($query, $values);
        $result = $stmt->execute();
        $stmt = null;
        return $result;
    }
    
    /**
     * 
     * @param bool $status
     * @return bool
     */
    public function useBufferedQuery($status) {
        return $this->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $status);
    }
    
    /**
     * 
     * @param string $query
     * @param array $values
     * @return \PDOStatement
     */
    private function createStatement(&$query, $values) {
        // analyse binding values
        if (!empty($values)) {
            foreach ($values as $k => $v) {
                if (is_array($v)) {
                    $param = strpos($k, ':') === false? (':' . $k) : $k;
                    
                    // replace the param with a comma-separated list of params suffixed by '_number'
                    $params = [];
                    
                    if (empty($v)) {
                        // safely handle empty array
                        $p = $param . '_0';
                        $params[] = $p;
                        $values[$p] = '';
                    } else {
                        foreach ($v as $vk => $vv) {
                            $p = $param . '_' . $vk;

                            // store the new binding value
                            $params[] = $p;
                            $values[$p] = $vv;
                        }
                    }
                                        
                    // replace the old param with the new param list
                    $query = str_replace($param, implode(',', $params), $query);
                    unset($values[$k]);
                }
            }
        }
        
        // create the statement
        $stmt = $this->prepare($query);
        
        // bind values
        if (!empty($values)) {
            foreach ($values as $k => $v) {
                $this->bindStatementValue($stmt, $k, $v);
            }
        }
        
        return $stmt;
    }
    
    /**
     * 
     * @param \PDOStatement $stmt
     * @param string $param
     * @param int|bool|string $value
     */
    private function bindStatementValue(&$stmt, $param, $value) {
        if (strpos($param, ':') === false) {
            $param = ':' . $param;
        }

        if (is_int($value)) {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        } else if (is_bool($value)) {
            $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
        } else {
            $stmt->bindValue($param, $value);
        }
    }
}
