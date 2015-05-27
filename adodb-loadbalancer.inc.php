<?php
// Copyright (c) 2015 Mike Benoit, all rights reserved
/* ******************************************************************************
    Released under both BSD license and Lesser GPL library license.
 	Whenever there is any discrepancy between the two licenses,
 	the BSD license will take precedence.
*******************************************************************************/
/**
 * ADOdb loadbalancer is a class that allows the user to do read/write splitting and load balancing across multiple connections.
 * It can handle and load balance any number of master or slaves, including dealing with connection failures.
 *
 * Last Editor: $Author: Mike Benoit $
 * @author Mike Benoit
 * @version $Revision: 1.0 $
 *
 */

/*
 * Example Usage:
 *  $db = new ADOdbLoadBalancer( 'postgres8' );
 *  $db_connection_obj = new ADOdbLoadBalancerConnection( 'master', 10, $dsn ); //Master with weight of 10
 *  $db_connection_obj->getADODbObject()->SetFetchMode(ADODB_FETCH_ASSOC); //Pass specific settings to the ADOdb object itself.
 *  $db->addConnection( $db_connection_obj );
 *
 *  $db_connection_obj = new ADOdbLoadBalancerConnection( 'slave', 100, $dsn ); //Slave with weight of 100
 *  $db_connection_obj->getADODbObject()->SetFetchMode(ADODB_FETCH_ASSOC); //Pass specific settings to the ADOdb object itself.
 *  $db->addConnection( $db_connection_obj );
 *
 *  $db_connection_obj = new ADOdbLoadBalancerConnection( 'slave', 100, $dsn ); //Slave with weight of 100
 *  $db_connection_obj->getADODbObject()->SetFetchMode(ADODB_FETCH_ASSOC); //Pass specific settings to the ADOdb object itself.
 *  $db->addConnection( $db_connection_obj );
 *
 *  //Perform ADODB calls as normal..
 *  $db->Excute( 'SELECT * FROM MYTABLE' );
 */
class ADOdbLoadBalancer {

	protected $connections = FALSE;
	protected $connections_master = FALSE; //Links to just master connections
	protected $connections_slave = FALSE; //Links to just slave connections

	protected $total_connections = array( 'all' => 0, 'master' => 0, 'slave' => 0 );
	protected $total_connection_weights = array( 'all' => 0, 'master' => 0, 'slave' => 0 );

	protected $enable_sticky_sessions = TRUE; //Once a master or slave connection is made, stick to that connection for the entire request.
	protected $pinned_connection_id = FALSE; //When in transactions, always use this connection.
	protected $last_connection_id = array( 'master' => FALSE, 'slave' => FALSE, 'all' => FALSE );
	
	protected $session_variables = FALSE; //Session variables that must be maintained across all connections, ie: SET TIME ZONE.
	
	protected $blacklist_functions = FALSE; //List of functions to blacklist as write-only (must run on master) **NOT YET IMPLEMENTED**

	protected $user_defined_session_init_sql = FALSE; //Called immediately after connecting to any DB.

	function setBlackListFunctions( $arr ) {
		$this->blacklist_functions = (array)$arr;
		return TRUE;
	}
	
	function setSessionInitSQL( $sql ) {
		$this->user_defined_session_init_sql[] = $sql;
		return TRUE;
	}

	function addConnection( $obj ) {
		if ( $obj instanceof ADOdbLoadBalancerConnection ) {
			$this->connections[] = $obj;
			end( $this->connections );
			$i = key( $this->connections );

			$this->total_connections[$obj->type]++;
			$this->total_connections['all']++;

			$this->total_connection_weights[$obj->type] += abs( $obj->weight );
			$this->total_connection_weights['all'] += abs( $obj->weight );
			
			if ( $obj->type == 'master' ) {
				$this->connections_master[] = $i;
			} else {
				$this->connections_slave[] = $i;
			}
			
			return TRUE;
		}

		throw new Exception('Connection object is not an instance of ADOdbLoadBalancerConnection');

		return FALSE;
	}

	function removeConnection( $i ) {
		$obj = $this->connections[$i];

		$this->total_connections[$obj->type]--;
		$this->total_connections['all']--;

		$this->total_connection_weights[$obj->type] -= abs( $obj->weight );
		$this->total_connection_weights['all'] -= abs( $obj->weight );

		if ( $obj->type == 'master' ) {
			unset($this->connections_master[array_search( $i, $this->connections_master )]);
			$this->connections_master = array_values($this->connections_master); //Reindex array.
		} else {
			unset($this->connections_slave[array_search( $i, $this->connections_slave )]);
			$this->connections_slave = array_values($this->connections_slave); //Reindex array.
		}

		//Remove any sticky connections as well.
		if ( $this->last_connection_id[$obj->type] == $i ) {
			$this->last_connection_id[$obj->type] = FALSE;
		}
		
		unset($this->connections[$i]);

		return TRUE;
	}

	function getConnectionByWeight( $type ) {
		if ( $type == 'slave' ) {
			$total_weight = $this->total_connection_weights['all'];
		} else {
			$total_weight = $this->total_connection_weights['master'];
		}

		$i = FALSE;
		if ( is_array( $this->connections ) ) {
			$n = 0;
			$num = mt_rand(0, $total_weight );
			foreach( $this->connections as $i => $connection_obj ) {
				if ( $connection_obj->weight > 0 && ( $type == 'slave' || $connection_obj->type == 'master' ) ) {
					$n += $connection_obj->weight;
					if ( $n >= $num) {
						break;
					}
				}
			}
		}
		return $i;
	}

	function getLoadBalancedConnection( $type ) {
		if ( $this->total_connections == 0 ) {
			$connection_id = 0;
		} else {
			if ( $this->enable_sticky_sessions == TRUE && $this->last_connection_id[$type] !== FALSE ) {
				$connection_id = $this->last_connection_id[$type];
			} else {
				if ( $type == 'master' && $this->total_connections['master'] == 1 ) {
					$connection_id = $this->connections_master[0];
				} else {
					$connection_id = $this->getConnectionByWeight( $type );
				}
			}
		}

		return $connection_id;
	}

	function _getConnection( $connection_id ) {
		if ( isset($this->connections[$connection_id]) ) {
			$connection_obj = $this->connections[$connection_id];
			$adodb_obj = $connection_obj->getADOdbObject();
			if ( is_object($adodb_obj) && $adodb_obj->_connectionID == FALSE ) {
				try {
					if ( $connection_obj->persistent_connection == TRUE ) {
						$connection_result = $adodb_obj->Pconnect( $connection_obj->host, $connection_obj->user, $connection_obj->password, $connection_obj->database );
					} else {
						$connection_result = $adodb_obj->Connect( $connection_obj->host, $connection_obj->user, $connection_obj->password, $connection_obj->database );
					}
				} catch ( Exception $e ) {
					//Connection error, see if there are other connections to try still.
					throw $e; //No connections left, reThrow exception so application can catch it.
					return FALSE;
				}

				if ( is_array( $this->user_defined_session_init_sql ) ) {
					foreach( $this->user_defined_session_init_sql as $session_init_sql ) {
						$adodb_obj->Execute( $session_init_sql );
					}					
				}
				$this->executeSessionVariables( $adodb_obj );
			}

			return $adodb_obj;
		} else {
			throw new Exception('Unable to return Connection object...');
		}
	}

	function getConnection( $type = 'master', $pin_connection = NULL, $force_connection_id = FALSE ) {
		if ( $this->pinned_connection_id !== FALSE ) {
			$connection_id = $this->pinned_connection_id;
		} else {
			$connection_id = $this->getLoadBalancedConnection( $type );			
		}

		try {
			$adodb_obj = $this->_getConnection( $connection_id );
			$connection_obj = $this->connections[$connection_id];
		} catch ( Exception $e ) {
			//Connection error, see if there are other connections to try still.
			if ( ($type == 'master' && $this->total_connections['master'] > 0 ) || ( $type == 'slave' && $this->total_connections['all'] > 0 ) ) {
				$this->removeConnection( $connection_id );
				return $this->getConnection( $type, $pin_connection );
			} else {
				throw $e; //No connections left, reThrow exception so application can catch it.
				return FALSE;
			}
		}

		$this->last_connection_id[$type] = $connection_id;

		if ( $pin_connection === TRUE ) {
			$this->pinned_connection_id = $connection_id;
		} elseif( $pin_connection === FALSE && $adodb_obj->transOff <= 1 ) { //UnPin connection only if we are 1 level deep in a transaction.
			$this->pinned_connection_id = FALSE;

			//When unpinning connection, reset last_connection_id so slave queries don't get stuck on the master.
			$this->last_connection_id['master'] = FALSE;
			$this->last_connection_id['slave'] = FALSE;
		}

		return $adodb_obj;
	}

	function makeValuesReferenced( $arr ) {
		$refs = array();

		//This is a hack to work around pass by reference error.
		//Parameter 1 to ADOConnection::GetInsertSQL() expected to be a reference, value given in adodb-loadbalancer.inc.php on line 83
		foreach( $arr as $key => $value ) {
			$refs[$key] = &$arr[$key];
		}

		return $refs;
	}
	
	//Allow setting session variables that are maintained across connections.
	public function setSessionVariable( $name, $value, $execute_immediately = TRUE ) {
		$this->session_variables[$name] = $value;

		if ( $execute_immediately == TRUE ) {
			return $this->executeSessionVariables();
		} else {
			return TRUE;
		}
	}
	private function executeSessionVariables( $adodb_obj = FALSE ) {
		if ( is_array( $this->session_variables ) ) {
			$sql = '';
			foreach( $this->session_variables as $name => $value ) {
				//$sql .= 'SET SESSION '. $name .' '. $value;
				//MySQL uses: SET SESSION foo_bar='foo'
				//PGSQL uses: SET SESSION foo_bar 'foo'
				//So leave it up to the user to pass the proper value with '=' if needed.
				//This may be a candidate to move into ADOdb proper.
				$sql .= 'SET SESSION '. $name .' '. $value;
			}

			if ( $adodb_obj !== FALSE ) {
				return $adodb_obj->Execute( $sql );
			} else {
				return $this->ClusterExecute( $sql );
			}
		}

		return FALSE;
	}

	//Executes the same QUERY on the entire cluster of connections.
	//Would be used for things like SET SESSION TIME ZONE calls and such.
	public function ClusterExecute( $sql, $inputarr = FALSE, $return_all_results = FALSE, $existing_connections_only = TRUE ) {
		if ( is_array($this->connections) && count($this->connections) > 0 ) {
			foreach( $this->connections as $key => $connection_obj ) {
				if ( $existing_connections_only == FALSE || ( $existing_connections_only == TRUE && $connection_obj->getADOdbObject()->_connectionID !== FALSE ) ) {
					$adodb_obj = $this->_getConnection( $key );
					if ( is_object( $adodb_obj ) ) {
						$result_arr[] = $adodb_obj->Execute( $sql, $inputarr );
					}
				}
			}

			if ( isset($result_arr) && $return_all_results == TRUE ) {
				return $result_arr;
			} else {
				//Loop through all results checking to see if they match, if they do return the first one
				//otherwise return an array of all results.
				if ( isset($result_arr) ) {
					foreach( $result_arr as $result ) {
						if ( $result == FALSE ) {
							return $result_arr;
						}
					}

					return $result_arr[0];
				}
			}
		}

		return FALSE;
	}

	//Use this instead of __call() as it significantly reduces the overhead of call_user_func_array().
	public function Execute( $sql, $inputarr = FALSE ) {
		$type = 'master';
		$pin_connection = NULL;

		if ( stripos( $sql, 'SELECT') === 0 ) {
			$type = 'slave';
		} elseif ( stripos( $sql, 'SET') === 0 ) {
			//SET SQL statements should likely use setSessionVariable() instead,
			//so state is properly maintained across connections, especially when they are lazily created.
			return $this->ClusterExecute( $sql, $inputarr );
		}

		$adodb_obj = $this->getConnection( $type, $pin_connection );
		if ( $adodb_obj !== FALSE ) {
			return $adodb_obj->Execute( $sql, $inputarr );
		}

		return FALSE;		
	}

	public function __call( $method, $args ) { //Intercept ADOdb functions
		$type = 'master';
		$pin_connection = NULL;

		//Intercept specific methods to determine if they are read-only or not.
		$method = strtolower($method);
		switch ( $method ) {
			//case 'execute': //This is the direct overloaded function above instead.
			case 'selectlimit':
			case 'getone':
			case 'getrow':
			case 'getall':
			case 'getcol':
			case 'getassoc':
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
				$pin_connection = TRUE;
				break;
			case 'rollbacktrans':
			case 'committrans':
				$pin_connection = FALSE;
				break;
			//Smart transactions
			case 'starttrans':
				$pin_connection = TRUE;
				break;
			case 'completetrans':
			case 'failtrans':
				//getConnection() will only unpin the transaction if we're exiting the last nested transaction
				$pin_connection = FALSE;
				break;
			default:
				break;
		}
		
		$adodb_obj = $this->getConnection( $type, $pin_connection );
		if ( is_object( $adodb_obj ) ) {
			$result = call_user_func_array( array( $adodb_obj, $method ), $this->makeValuesReferenced( $args ) );
			return $result;
		}

		return FALSE;
	}

	function __get( $property ) {
		return $this->getConnection()->$property;
	}
	
	function __set( $property, $value ) {
		return $this->getConnection()->$property = $value;
	}
	
	private function __clone() { }
}

class ADOdbLoadBalancerConnection {
	//ADOdb data
	protected $driver = FALSE;
	protected $adodb_obj = FALSE;

	//Load balancing data
	public $type = 'master';
	public $weight = 1;
	public $persistent_connection = FALSE;

	//DB connection data
	public $host = '';
	public $user = '';
	public $password = '';
	public $database = '';

	function __construct( $driver, $type = 'master', $weight = 1, $persistent_connection = FALSE, $argHostname = '', $argUsername = '', $argPassword = '', $argDatabaseName = '' ) {
		if ( $type !== 'master' && $type !== 'slave' ) {
			return FALSE;
		}

		$this->adodb_obj = ADONewConnection( $driver );

		$this->type = $type;
		$this->weight = $weight;
		$this->persistent_connection = $persistent_connection;
		
		$this->host = $argHostname;
		$this->user = $argUsername;
		$this->password = $argPassword;
		$this->database = $argDatabaseName;

		return TRUE;
	}	

	function getADOdbObject() {
		return $this->adodb_obj;
	}
}
