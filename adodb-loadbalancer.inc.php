<?php
// Copyright (c) 2015 Mike Benoit, all rights reserved
/* ******************************************************************************
    Released under both BSD license and Lesser GPL library license.
 	Whenever there is any discrepancy between the two licenses,
 	the BSD license will take precedence.
*******************************************************************************/
/**
 * loadbalancer is a class that allows the user to do read/write splitting and load balancing across multiple connections.
 *
 * Last Editor: $Author: Mike Benoit $
 * @author Mike Benoit
 * @version $Revision: 1.0 $
 *
 */

/*
 * Example:
 *  $db = new ADOdbLoadBalancer( 'postgres8' );
 *  $db->addConnection( 'master', 100, $dsn ); //Master
 *  $db->addConnection( 'slave', 100, $dsn ); //Slave
 *  $db->addConnection( 'slave', 150, $dsn ); //Slave
 *
 */
class ADOdbLoadBalancer {
	const ADODB_LB_TYPE_MASTER = 'master';
	const ADODB_LB_TYPE_SLAVE = 'slave';

	protected $driver = NULL;
	protected $connections = FALSE;

	protected $session_init_sql = FALSE; //Called immediately after connecting to any DB.

	function __construct( $driver = '' ) {
		$this->driver = $driver;

		return TRUE;
	}

	function addConnection( $type, $weight, $argHostname = '', $argUsername = '', $argPassword = '', $argDatabaseName = '' ) {
		if ( $type !== 'master' AND $type !== 'slave' ) {
			return FALSE;
		}
		
		$adodb_obj = ADONewConnection( $this->driver );

		$this->connections[$type][] = array(
										'type' => $type,
										'weight' => $weight,
										'host' => $argHostname,
										'user' => $argUsername,
										'password' => $argPassword,
										'database' => $argDatabaseName,
										'adodb_obj' => $adodb_obj,
									 );

		return $adodb_obj; //Return the ADODB object so additional settings/flags can be set on it.
	}
	
	function getConnection() {
		return $this->connection_type[ADODB_LB_TYPE_MASTER]['adodb_obj'];
	}

	public function __call( $method, $args ) { //Intercept ADOdb functions
		$adodb_obj = $this->getConnect();

		//try {
			$result = call_user_func_array( array( $adodb_obj, $method ), $args );
		//} catch ( \ADODB_Exception $e ) {
		//	$result = FALSE;
		//	$error 	= $e->getMessage();
		//}

		return $result;
		
	}

	function __get( $property ) {
		return $this->getConnection()->$property;
	}
	function __set( $property, $value ) {
		return $this->getConnection()->$property = $value;
		//$this->_lazyLoadAdodbConnection();
		//self::$adodb[self::$destination_type][$property] = $value;
	}
	
	private function __clone() { }
}