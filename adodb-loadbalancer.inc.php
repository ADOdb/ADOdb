<?php
// Copyright (c) 2015 Mike Benoit, all rights reserved
/* ******************************************************************************
    Released under both BSD license and Lesser GPL library license.
 	Whenever there is any discrepancy between the two licenses,
 	the BSD license will take precedence.
*******************************************************************************/

/*
  @version   v5.21.0-dev	??-???-2016
  @copyright (c) 2016		Mike Benoit and the ADOdb community
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence. See License.txt.
  Set tabs to 4 for best viewing.

  ADOdb loadbalancer is a class that allows the user to do read/write splitting and load balancing across multiple connections.
  It can handle and load balance any number of master or slaves, including dealing with connection failures.
*/

/**
 * Class ADOdbLoadBalancer
 */
class ADOdbLoadBalancer
{
    /**
     * @var Bool|Array    All connections to each database.
     */
    protected $connections = false;

    /**
     * @var bool|Array    Just connections to the master database.
     */
    protected $connections_master = false;

    /**
     * @var bool|Array    Just connections to the slave database.
     */
    protected $connections_slave = false;

    /**
     * @var array    Counts of all connections and their types.
     */
    protected $total_connections = array('all' => 0, 'master' => 0, 'slave' => 0);

    /**
     * @var array    Weights of all connections for each type.
     */
    protected $total_connection_weights = array('all' => 0, 'master' => 0, 'slave' => 0);

    /**
     * @var bool    Once a master or slave connection is made, stick to that connection for the entire request.
     */
    protected $enable_sticky_sessions = true;

    /**
     * @var bool    When in transactions, always use this connection.
     */
    protected $pinned_connection_id = false;

    /**
     * @var array    Last connection_id for each database type.
     */
    protected $last_connection_id = array('master' => false, 'slave' => false, 'all' => false);

    /**
     * @var bool    Session variables that must be maintained across all connections, ie: SET TIME ZONE.
     */
    protected $session_variables = false;

    /**
     * @var bool    Called immediately after connecting to any DB.
     */
    protected $user_defined_session_init_sql = false;


    /**
     * Defines SQL queries that are executed each time a new database connection is established.
     *
     * @param $sql
     * @return bool
     */
    public function setSessionInitSQL($sql)
    {
        $this->user_defined_session_init_sql[] = $sql;

        return true;
    }

    /**
     * Adds a new database connection to the pool, but no actual connection is made until its needed.
     *
     * @param $obj
     * @return bool
     * @throws Exception
     */
    public function addConnection($obj)
    {
        if ($obj instanceof ADOdbLoadBalancerConnection) {
            $this->connections[] = $obj;
            end($this->connections);
            $i = key($this->connections);

            $this->total_connections[$obj->type]++;
            $this->total_connections['all']++;

            $this->total_connection_weights[$obj->type] += abs($obj->weight);
            $this->total_connection_weights['all'] += abs($obj->weight);

            if ($obj->type == 'master') {
                $this->connections_master[] = $i;
            } else {
                $this->connections_slave[] = $i;
            }

            return true;
        }

        throw new Exception('Connection object is not an instance of ADOdbLoadBalancerConnection');

        return false;
    }

    /**
     * Removes a database connection from the pool.
     *
     * @param $i
     * @return bool
     */
    public function removeConnection($i)
    {
        $obj = $this->connections[$i];

        $this->total_connections[$obj->type]--;
        $this->total_connections['all']--;

        $this->total_connection_weights[$obj->type] -= abs($obj->weight);
        $this->total_connection_weights['all'] -= abs($obj->weight);

        if ($obj->type == 'master') {
            unset($this->connections_master[array_search($i, $this->connections_master)]);
            $this->connections_master = array_values($this->connections_master); //Reindex array.
        } else {
            unset($this->connections_slave[array_search($i, $this->connections_slave)]);
            $this->connections_slave = array_values($this->connections_slave); //Reindex array.
        }

        //Remove any sticky connections as well.
        if ($this->last_connection_id[$obj->type] == $i) {
            $this->last_connection_id[$obj->type] = false;
        }

        unset($this->connections[$i]);

        return true;
    }

    /**
     * Returns a database connection of the specified type, but takes into account the connection weight for load balancing.
     *
     * @param $type    Type of database connection, either: 'master' or 'slave'
     * @return bool|int|string
     */
    private function getConnectionByWeight($type)
    {
        if ($type == 'slave') {
            $total_weight = $this->total_connection_weights['all'];
        } else {
            $total_weight = $this->total_connection_weights['master'];
        }

        $i = false;
        if (is_array($this->connections)) {
            $n = 0;
            $num = mt_rand(0, $total_weight);
            foreach ($this->connections as $i => $connection_obj) {
                if ($connection_obj->weight > 0 && ($type == 'slave' || $connection_obj->type == 'master')) {
                    $n += $connection_obj->weight;
                    if ($n >= $num) {
                        break;
                    }
                }
            }
        }

        return $i;
    }

    /**
     * Returns the proper database connection when taking into account sticky sessions and load balancing.
     *
     * @param $type
     * @return bool|int|mixed|string
     */
    public function getLoadBalancedConnection($type)
    {
        if ($this->total_connections == 0) {
            $connection_id = 0;
        } else {
            if ($this->enable_sticky_sessions == true && $this->last_connection_id[$type] !== false) {
                $connection_id = $this->last_connection_id[$type];
            } else {
                if ($type == 'master' && $this->total_connections['master'] == 1) {
                    $connection_id = $this->connections_master[0];
                } else {
                    $connection_id = $this->getConnectionByWeight($type);
                }
            }
        }

        return $connection_id;
    }

    /**
     * Returns the ADODB connection object by connection_id and ensures that its connected and the session variables are executed.
     *
     * @param $connection_id
     * @return bool
     * @throws Exception
     */
    private function _getConnection($connection_id)
    {
        if (isset($this->connections[$connection_id])) {
            $connection_obj = $this->connections[$connection_id];
            $adodb_obj = $connection_obj->getADOdbObject();
            if (is_object($adodb_obj) && $adodb_obj->_connectionID == false) {
                try {
                    if ($connection_obj->persistent_connection == true) {
                        $adodb_obj->Pconnect($connection_obj->host, $connection_obj->user, $connection_obj->password,
                                $connection_obj->database);
                    } else {
                        $adodb_obj->Connect($connection_obj->host, $connection_obj->user, $connection_obj->password,
                                $connection_obj->database);
                    }
                } catch (Exception $e) {
                    //Connection error, see if there are other connections to try still.
                    throw $e; //No connections left, reThrow exception so application can catch it.
                    return false;
                }

                if (is_array($this->user_defined_session_init_sql)) {
                    foreach ($this->user_defined_session_init_sql as $session_init_sql) {
                        $adodb_obj->Execute($session_init_sql);
                    }
                }
                $this->executeSessionVariables($adodb_obj);
            }

            return $adodb_obj;
        } else {
            throw new Exception('Unable to return Connection object...');
        }
    }

    /**
     * Returns the ADODB connection object by database type and ensures that its connected and the session variables are executed.
     *
     * @param string $type
     * @param null $pin_connection
     * @param bool $force_connection_id
     * @return bool
     * @throws Exception
     */
    public function getConnection($type = 'master', $pin_connection = null, $force_connection_id = false)
    {
        if ($this->pinned_connection_id !== false) {
            $connection_id = $this->pinned_connection_id;
        } else {
            $connection_id = $this->getLoadBalancedConnection($type);
        }

        try {
            $adodb_obj = $this->_getConnection($connection_id);
            $connection_obj = $this->connections[$connection_id];
        } catch (Exception $e) {
            //Connection error, see if there are other connections to try still.
            if (($type == 'master' && $this->total_connections['master'] > 0) || ($type == 'slave' && $this->total_connections['all'] > 0)) {
                $this->removeConnection($connection_id);

                return $this->getConnection($type, $pin_connection);
            } else {
                throw $e; //No connections left, reThrow exception so application can catch it.
                return false;
            }
        }

        $this->last_connection_id[$type] = $connection_id;

        if ($pin_connection === true) {
            $this->pinned_connection_id = $connection_id;
        } elseif ($pin_connection === false && $adodb_obj->transOff <= 1) { //UnPin connection only if we are 1 level deep in a transaction.
            $this->pinned_connection_id = false;

            //When unpinning connection, reset last_connection_id so slave queries don't get stuck on the master.
            $this->last_connection_id['master'] = false;
            $this->last_connection_id['slave'] = false;
        }

        return $adodb_obj;
    }

    /**
     * This is a hack to work around pass by reference error.
     * Parameter 1 to ADOConnection::GetInsertSQL() expected to be a reference, value given in adodb-loadbalancer.inc.php on line 83
     *
     * @param $arr
     * @return array
     */
    private function makeValuesReferenced($arr)
    {
        $refs = array();

        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }

        return $refs;
    }

    /**
     * Allow setting session variables that are maintained across connections.
     *
     * Its important that these are set using name/value, so it can determine if the same variable is set multiple times
     * causing bloat/clutter when new connections are established. For example if the time_zone is set to many different
     * ones through the course of a single connection, a new connection should only set it to the most recent value.
     *
     *
     * @param $name
     * @param $value
     * @param bool $execute_immediately
     * @return array|bool|mixed
     */
    public function setSessionVariable($name, $value, $execute_immediately = true)
    {
        $this->session_variables[$name] = $value;

        if ($execute_immediately == true) {
            return $this->executeSessionVariables();
        } else {
            return true;
        }
    }

    /**
     * Executes the session variables on a given ADODB object.
     *
     * @param bool $adodb_obj
     * @return array|bool|mixed
     */
    private function executeSessionVariables($adodb_obj = false)
    {
        if (is_array($this->session_variables)) {
            $sql = '';
            foreach ($this->session_variables as $name => $value) {
                //$sql .= 'SET SESSION '. $name .' '. $value;
                //MySQL uses: SET SESSION foo_bar='foo'
                //PGSQL uses: SET SESSION foo_bar 'foo'
                //So leave it up to the user to pass the proper value with '=' if needed.
                //This may be a candidate to move into ADOdb proper.
                $sql .= 'SET SESSION ' . $name . ' ' . $value;
            }

            if ($adodb_obj !== false) {
                return $adodb_obj->Execute($sql);
            } else {
                return $this->ClusterExecute($sql);
            }
        }

        return false;
    }

    /**
     * Executes the same SQL QUERY on the entire cluster of connections.
     * Would be used for things like SET SESSION TIME ZONE calls and such.
     *
     * @param $sql
     * @param bool $inputarr
     * @param bool $return_all_results
     * @param bool $existing_connections_only
     * @return array|bool|mixed
     * @throws Exception
     */
    public function ClusterExecute(
            $sql,
            $inputarr = false,
            $return_all_results = false,
            $existing_connections_only = true
    ) {
        if (is_array($this->connections) && count($this->connections) > 0) {
            foreach ($this->connections as $key => $connection_obj) {
                if ($existing_connections_only == false || ($existing_connections_only == true && $connection_obj->getADOdbObject()->_connectionID !== false)) {
                    $adodb_obj = $this->_getConnection($key);
                    if (is_object($adodb_obj)) {
                        $result_arr[] = $adodb_obj->Execute($sql, $inputarr);
                    }
                }
            }

            if (isset($result_arr) && $return_all_results == true) {
                return $result_arr;
            } else {
                //Loop through all results checking to see if they match, if they do return the first one
                //otherwise return an array of all results.
                if (isset($result_arr)) {
                    foreach ($result_arr as $result) {
                        if ($result == false) {
                            return $result_arr;
                        }
                    }

                    return $result_arr[0];
                }
            }
        }

        return false;
    }

    /**
     * Determines if a SQL query is read-only or not.
     *
     * @param $sql    SQL Query to test.
     * @return bool
     */
    public function isReadOnlyQuery($sql)
    {
        if (stripos($sql, 'SELECT') === 0 && stripos($sql, 'FOR UPDATE') === false && stripos($sql,
                        ' INTO ') === false && stripos($sql, 'LOCK IN') === false
        ) {
            return true;
        }

        return false;
    }

    /**
     * Use this instead of __call() as it significantly reduces the overhead of call_user_func_array().
     *
     * @param $sql
     * @param bool $inputarr
     * @return array|bool|mixed
     * @throws Exception
     */
    public function Execute($sql, $inputarr = false)
    {
        $type = 'master';
        $pin_connection = null;

        //SELECT queries that can write and therefore must be run on MASTER.
        //SELECT ... FOR UPDATE;
        //SELECT ... INTO ...
        //SELECT .. LOCK IN ... (MYSQL)
        if ($this->isReadOnlyQuery($sql) == true) {
            $type = 'slave';
        } elseif (stripos($sql, 'SET') === 0) {
            //SET SQL statements should likely use setSessionVariable() instead,
            //so state is properly maintained across connections, especially when they are lazily created.
            return $this->ClusterExecute($sql, $inputarr);
        }

        $adodb_obj = $this->getConnection($type, $pin_connection);
        if ($adodb_obj !== false) {
            return $adodb_obj->Execute($sql, $inputarr);
        }

        return false;
    }

    /**
     * Magic method to intercept method calls back to the proper ADODB object for master/slaves.
     *
     * @param $method    ADODB method to call.
     * @param $args        Arguments to the ADODB method.
     * @return bool|mixed
     * @throws Exception
     */
    public function __call($method, $args)
    {
        $type = 'master';
        $pin_connection = null;

        //Intercept specific methods to determine if they are read-only or not.
        $method = strtolower($method);
        switch ($method) {
            //case 'execute': //This is the direct overloaded function above instead.
            case 'getone':
            case 'getrow':
            case 'getall':
            case 'getcol':
            case 'getassoc':
            case 'selectlimit':
                if ($this->isReadOnlyQuery($args[0]) == true) {
                    $type = 'slave';
                }
                break;
            case 'cachegetone':
            case 'cachegetrow':
            case 'cachegetall':
            case 'cachegetcol':
            case 'cachegetassoc':
            case 'cacheexecute':
            case 'cacheselect':
            case 'pageexecute':
            case 'cachepageexecute':
                $type = 'slave';
                break;
            //case 'ignoreerrors':
            //	//When ignoreerrors is called, PIN to the connection until its called again.
            //	if ( !isset($args[0]) || ( isset($args[0]) && $args[0] == FALSE ) ) {
            //		$pin_connection = TRUE;
            //	} else {
            //		$pin_connection = FALSE;
            //	}
            //	break;

            //Manual transactions
            case 'begintrans':
                $pin_connection = true;
                break;
            case 'rollbacktrans':
            case 'committrans':
                $pin_connection = false;
                break;
            //Smart transactions
            case 'starttrans':
                $pin_connection = true;
                break;
            case 'completetrans':
            case 'failtrans':
                //getConnection() will only unpin the transaction if we're exiting the last nested transaction
                $pin_connection = false;
                break;
            default:
                break;
        }

        $adodb_obj = $this->getConnection($type, $pin_connection);
        if (is_object($adodb_obj)) {
            $result = call_user_func_array(array($adodb_obj, $method), $this->makeValuesReferenced($args));

            return $result;
        }

        return false;
    }

    /**
     * Magic method to proxy property getter calls back to the proper ADODB object currently in use.
     *
     * @param $property
     * @return mixed
     * @throws Exception
     */
    public function __get($property)
    {
        return $this->getConnection()->$property;
    }

    /**
     * Magic method to proxy property setter calls back to the proper ADODB object currently in use.
     *
     * @param $property
     * @param $value
     * @return mixed
     * @throws Exception
     */
    public function __set($property, $value)
    {
        return $this->getConnection()->$property = $value;
    }

    /**
     *  Override the __clone() magic method.
     */
    private function __clone()
    {
    }
}

/**
 * Class ADOdbLoadBalancerConnection
 */
class ADOdbLoadBalancerConnection
{
    /**
     * @var bool    ADOdb drive name.
     */
    protected $driver = false;

    /**
     * @var bool    ADODB object.
     */
    protected $adodb_obj = false;

    /**
     * @var string    Type of connection, either 'master' or 'slave'
     */
    public $type = 'master';

    /**
     * @var int        Weight of connection, lower receives less queries, higher receives more queries.
     */
    public $weight = 1;

    /**
     * @var bool    Determines if the connection persistent.
     */
    public $persistent_connection = false;

    /**
     * @var string    Database connection host
     */
    public $host = '';

    /**
     * @var string    Database connection user
     */
    public $user = '';

    /**
     * @var string    Database connection password
     */
    public $password = '';

    /**
     * @var string    Database connection database name
     */
    public $database = '';

    /**
     * ADOdbLoadBalancerConnection constructor to setup the ADODB object.
     *
     * @param $driver
     * @param string $type
     * @param int $weight
     * @param bool $persistent_connection
     * @param string $argHostname
     * @param string $argUsername
     * @param string $argPassword
     * @param string $argDatabaseName
     */
    public function __construct(
            $driver,
            $type = 'master',
            $weight = 1,
            $persistent_connection = false,
            $argHostname = '',
            $argUsername = '',
            $argPassword = '',
            $argDatabaseName = ''
    ) {
        if ($type !== 'master' && $type !== 'slave') {
            return false;
        }

        $this->adodb_obj = ADONewConnection($driver);

        $this->type = $type;
        $this->weight = $weight;
        $this->persistent_connection = $persistent_connection;

        $this->host = $argHostname;
        $this->user = $argUsername;
        $this->password = $argPassword;
        $this->database = $argDatabaseName;

        return true;
    }

    /**
     * Returns the ADODB object for this connection.
     *
     * @return bool
     */
    public function getADOdbObject()
    {
        return $this->adodb_obj;
    }
}
