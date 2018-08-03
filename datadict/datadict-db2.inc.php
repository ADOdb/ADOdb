<?php
/**
  @version   v5.21.0-dev  ??-???-2016
  @copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
  @copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.

  Set tabs to 4 for best viewing.

*/
// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB2_db2 extends ADODB_DataDict {

	var $databaseType = 'db2';
	var $seqField = false;
	var $dropCol = 'ALTER TABLE %s DROP COLUMN %s';

 	function ActualType($meta)
	{
		switch($meta) {
		case 'C': return 'VARCHAR';
		case 'XL': return 'CLOB';
		case 'X': return 'VARCHAR(3600)';

		case 'C2': return 'VARCHAR'; // up to 32K
		case 'X2': return 'VARCHAR(3600)'; // up to 32000, but default page size too small

		case 'B': return 'BLOB';

		case 'D': return 'DATE';
		case 'TS':
		case 'T': return 'TIMESTAMP';

		case 'L': return 'SMALLINT';
		case 'I': return 'INTEGER';
		case 'I1': return 'SMALLINT';
		case 'I2': return 'SMALLINT';
		case 'I4': return 'INTEGER';
		case 'I8': return 'BIGINT';

		case 'F': return 'DOUBLE';
		case 'N': return 'DECIMAL';
		default:
			return $meta;
		}
	}

	// return string must begin with space
	function _CreateSuffix($fname,&$ftype,$fnotnull,$fdefault,$fautoinc,$fconstraint,$funsigned)
	{
		$suffix = '';
		if ($fautoinc) return ' GENERATED ALWAYS AS IDENTITY'; # as identity start with
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fnotnull) $suffix .= ' NOT NULL';
		if ($fconstraint) $suffix .= ' '.$fconstraint;
		return $suffix;
	}

	function alterColumnSQL($tabname, $flds, $tableflds='',$tableoptions='')
	{
		$tabname = $this->TableName ($tabname);
		$sql = array();
		list($lines,$pkey,$idxs) = $this->_GenFields($flds);
		// genfields can return FALSE at times
		if ($lines == null) $lines = array();
		$alter = 'ALTER TABLE ' . $tabname . $this->alterCol . ' ';
		
		$dataTypeWords = array('SET','DATA','TYPE');
		
		foreach($lines as $v) 
		{
			/*
			 * We must now post-process the line to insert the 'SET DATA TYPE'
			 * text into the alter statement
			 */
			$e = explode(' ',$v);
			
			array_splice($e,1,0,$dataTypeWords);
			
			$v = implode(' ',$e);
			
			$sql[] = $alter . $v;
		}
		if (is_array($idxs)) 
		{
			foreach($idxs as $idx => $idxdef) {
				$sql_idxs = $this->CreateIndexSql($idx, $tabname, $idxdef['cols'], $idxdef['opts']);
				$sql = array_merge($sql, $sql_idxs);
			}

		}
		return $sql;
	}


	
	function dropColumnSql($tabname, $flds, $tableflds='',$tableoptions='')
	{
		
		
		$tabname = $this->connection->getMetaCasedValue($tabname);
		$flds    = $this->connection->getMetaCasedValue($flds);
		
		if (ADODB_ASSOC_CASE  == ADODB_ASSOC_CASE_NATIVE )
		{
			/*
			 * METACASE_NATIVE
			 */
			$tabname = $this->connection->nameQuote . $tabname . $this->connection->nameQuote;
			$flds    = $this->connection->nameQuote . $flds . $this->connection->nameQuote;
		}
		$sql = sprintf($this->dropCol,$tabname,$flds);
		return (array)$sql;

	}
    

	function changeTableSQL($tablename, $flds, $tableoptions = false, $dropOldFields=false)
	{

		/**
		  Allow basic table changes to DB2 databases
		  DB2 will fatally reject changes to non character columns

		*/

		$validTypes = array("CHAR","VARC");
		$invalidTypes = array("BIGI","BLOB","CLOB","DATE", "DECI","DOUB", "INTE", "REAL","SMAL", "TIME");
		// check table exists
		
		
		$cols = $this->metaColumns($tablename);
		if ( empty($cols)) {
			return $this->createTableSQL($tablename, $flds, $tableoptions);
		}

		// already exists, alter table instead
		list($lines,$pkey) = $this->_GenFields($flds);
		$alter = 'ALTER TABLE ' . $this->tableName($tablename);
		$sql = array();

		foreach ( $lines as $id => $v ) {
			/*
			 * If the metaCasing was NATIVE the col returned with nameQuotes
			 * around the field. We need to remove this for the metaColumn
			 * match
			 */
			$id = str_replace($this->connection->nameQuote,'',$id);
			if ( isset($cols[$id]) && is_object($cols[$id]) ) {
				/**
				  If the first field of $v is the fieldname, and
				  the second is the field type/size, we assume its an
				  attempt to modify the column size, so check that it is allowed
				  $v can have an indeterminate number of blanks between the
				  fields, so account for that too
				 */
				$vargs = explode(' ' , $v);
				// assume that $vargs[0] is the field name.
				$i=0;
				// Find the next non-blank value;
				for ($i=1;$i<sizeof($vargs);$i++)
					if ($vargs[$i] != '')
						break;

				// if $vargs[$i] is one of the following, we are trying to change the
				// size of the field, if not allowed, simply ignore the request.
				if (in_array(substr($vargs[$i],0,4),$invalidTypes))
					continue;
				// insert the appropriate DB2 syntax
				if (in_array(substr($vargs[$i],0,4),$validTypes)) {
					array_splice($vargs,$i,0,array('SET','DATA','TYPE'));
				}

				// Now Look for the NOT NULL statement as this is not allowed in
				// the ALTER table statement. If it is in there, remove it
				if (in_array('NOT',$vargs) && in_array('NULL',$vargs)) {
					for ($i=1;$i<sizeof($vargs);$i++)
					if ($vargs[$i] == 'NOT')
						break;
					array_splice($vargs,$i,2,'');
				}
				$v = implode(' ',$vargs);
				$sql[] = $alter . $this->alterCol . ' ' . $v;
			} else {
				$sql[] = $alter . $this->addCol . ' ' . $v;
			}
		}

		return $sql;
	}

}
