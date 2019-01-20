<?php
/**
 * Generates an Update Query based on an existing recordset.
 * $arrFields is an associative array of fields with the value
 * that should be assigned.
 *
 * Note: This function should only be used on a recordset
 *	   that is run against a single table and sql should only
 *		 be a simple select stmt with no groupby/orderby/limit
 *
 * "Jonathan Younger" <jyounger@unilab.com>
 */
namespace ADOdb\common\datadict;
use ADOdb;

class getUpdateSQL extends \ADOdb\common\ADOdbMethod
{
	public function __construct($connection,$rs, $arrFields,$forceUpdate=false,$magicq=false,$force=null)
	{
		global $ADODB_QUOTE_FIELDNAMES;

		if (!$rs) {
			printf(ADODB_BAD_RS,'GetUpdateSQL');
			return false;
		}

		$fieldUpdatedCount = 0;
		$arrFields = array_change_key_case($arrFields,CASE_UPPER);

		$hasnumeric = isset($rs->fields[0]);
		$setFields = '';

		print_r($rs->fieldCount());
		// Loop through all of the fields in the recordset
		for ($i=0, $max=$rs->FieldCount(); $i < $max; $i++) {
			// Get the field from the recordset
			$field = $rs->FetchField($i);

			// If the recordset field is one
			// of the fields passed in then process.
			$upperfname = strtoupper($field->name);
			print 'UFN=' . $upperfname;
	print_r($arrFields);
			if ($this->adodb_key_exists($upperfname,$arrFields,$force)) {

				
				print 'UFN=' . $upperfname;
				// If the existing field value in the recordset
				// is different from the value passed in then
				// go ahead and append the field name and new value to
				// the update query.

				if ($hasnumeric) $val = $rs->fields[$i];
				else if (isset($rs->fields[$upperfname])) $val = $rs->fields[$upperfname];
				else if (isset($rs->fields[$field->name])) $val =  $rs->fields[$field->name];
				else if (isset($rs->fields[strtolower($upperfname)])) $val =  $rs->fields[strtolower($upperfname)];
				else $val = '';


				if ($forceUpdate || strcmp($val, $arrFields[$upperfname])) {
					// Set the counter for the number of fields that will be updated.
					$fieldUpdatedCount++;

					// Based on the datatype of the field
					// Format the value properly for the database
					$type = $rs->MetaType($field->type);


					if ($type == 'null') {
						$type = 'C';
					}

					if ((strpos($upperfname,' ') !== false) || ($ADODB_QUOTE_FIELDNAMES)) {
						switch ($ADODB_QUOTE_FIELDNAMES) {
						case 'LOWER':
							$fnameq = $zthis->nameQuote.strtolower($field->name).$zthis->nameQuote;break;
						case 'NATIVE':
							$fnameq = $zthis->nameQuote.$field->name.$zthis->nameQuote;break;
						case 'UPPER':
						default:
							$fnameq = $zthis->nameQuote.$upperfname.$zthis->nameQuote;break;
						}
					} else
						$fnameq = $upperfname;

                //********************************************************//
                if (is_null($arrFields[$upperfname])
					|| (empty($arrFields[$upperfname]) && strlen($arrFields[$upperfname]) == 0)
                    || $arrFields[$upperfname] === $zthis->null2null
                    )
                {
                    switch ($force) {

                        //case 0:
                        //    //Ignore empty values. This is allready handled in "adodb_key_exists" function.
                        //break;

                        case 1:
                            //Set null
                            $setFields .= $field->name . " = null, ";
                        break;

                        case 2:
                            //Set empty
                            $arrFields[$upperfname] = "";
                            $setFields .= _adodb_column_sql($zthis, 'U', $type, $upperfname, $fnameq,$arrFields, $magicq);
                        break;
						default:
                        case 3:
                            //Set the value that was given in array, so you can give both null and empty values
                            if (is_null($arrFields[$upperfname]) || $arrFields[$upperfname] === $zthis->null2null) {
                                $setFields .= $field->name . " = null, ";
                            } else {
                                $setFields .= _adodb_column_sql($zthis, 'U', $type, $upperfname, $fnameq,$arrFields, $magicq);
                            }
                        break;
		        case ADODB_FORCE_NULL_AND_ZERO:
					
			    switch ($type)
			    {
				case 'N':
				case 'I':
				case 'L':
				$setFields .= $field->name . ' = 0, ';
				break;
				default:
				$setFields .= $field->name . ' = null, ';
				break;
			    }
			    break;
                    }
                //********************************************************//
                } else {
						//we do this so each driver can customize the sql for
						//DB specific column types.
						//Oracle needs BLOB types to be handled with a returning clause
						//postgres has special needs as well
						$setFields .= _adodb_column_sql($zthis, 'U', $type, $upperfname, $fnameq,
														  $arrFields, $magicq);
					}
				}
			}
		}

		// If there were any modified fields then build the rest of the update query.
		if ($fieldUpdatedCount > 0 || $forceUpdate) {
					// Get the table name from the existing query.
			if (!empty($rs->tableName)) $tableName = $rs->tableName;
			else {
				preg_match("/FROM\s+".ADODB_TABLE_REGEX."/is", $rs->sql, $tableName);
				$tableName = $tableName[1];
			}
			// Get the full where clause excluding the word "WHERE" from
			// the existing query.
			preg_match('/\sWHERE\s(.*)/is', $rs->sql, $whereClause);

			$discard = false;
			// not a good hack, improvements?
			if ($whereClause) {
			#var_dump($whereClause);
				if (preg_match('/\s(ORDER\s.*)/is', $whereClause[1], $discard));
				else if (preg_match('/\s(LIMIT\s.*)/is', $whereClause[1], $discard));
				else if (preg_match('/\s(FOR UPDATE.*)/is', $whereClause[1], $discard));
				else preg_match('/\s.*(\) WHERE .*)/is', $whereClause[1], $discard); # see https://sourceforge.net/p/adodb/bugs/37/
			} else
				$whereClause = array(false,false);

			if ($discard)
				$whereClause[1] = substr($whereClause[1], 0, strlen($whereClause[1]) - strlen($discard[1]));

			$sql = 'UPDATE '.$tableName.' SET '.substr($setFields, 0, -2);
			if (strlen($whereClause[1]) > 0)
				$sql .= ' WHERE '.$whereClause[1];

			$this->methodResult = $sql;

		} else {
			print 'do false';
			return false;
		}
	}
}
