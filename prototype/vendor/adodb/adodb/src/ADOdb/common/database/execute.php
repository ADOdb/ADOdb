<?php
namespace ADOdb\common\database;

use ADOdb;

class execute extends ADOdb\common\ADOdbMethod
{

	
	public function __construct($connection,$sql, $inputarr = false)
	{
	
		if ($connection->fnExecute) {
			$fn = $connection->fnExecute;
			$this->methodResult = $fn($this,$sql,$inputarr);
			if (isset($this->methodResult)) {
				return $this->methodResult;
			}
		}
		if ($inputarr !== false) {
			if (!is_array($inputarr)) {
				$inputarr = array($inputarr);
			}

			$element0 = reset($inputarr);
			# is_object check because oci8 descriptors can be passed in
			$array_2d = $connection->bulkBind && is_array($element0) && !is_object(reset($element0));

			//remove extra memory copy of input -mikefedyk
			unset($element0);

			if (!is_array($sql) && !$connection->_bindInputArray) {
				// @TODO this would consider a '?' within a string as a parameter...
				$sqlarr = explode('?',$sql);
				$nparams = sizeof($sqlarr)-1;

				if (!$array_2d) {
					// When not Bind Bulk - convert to array of arguments list
					$inputarr = array($inputarr);
				} else {
					// Bulk bind - Make sure all list of params have the same number of elements
					$countElements = array_map('count', $inputarr);
					if (1 != count(array_unique($countElements))) {
						$connection->logMessage(
							"[bulk execute] Input array has different number of params  [" . print_r($countElements, true) .  "].",
							'Execute'
						);
						return false;
					}
					unset($countElements);
				}
				// Make sure the number of parameters provided in the input
				// array matches what the query expects
				$element0 = reset($inputarr);
				if ($nparams != count($element0)) {
					$connection->logMessage(
						"Input array has " . count($element0) .
						" params, does not match query: '" . htmlspecialchars($sql) . "'",
						'Execute'
					);
					return false;
				}

				// clean memory
				unset($element0);

				foreach($inputarr as $arr) {
					$sql = ''; $i = 0;
					foreach ($arr as $v) {
						$sql .= $sqlarr[$i];
						// from Ron Baldwin <ron.baldwin#sourceprose.com>
						// Only quote string types
						$typ = gettype($v);
						if ($typ == 'string') {
							//New memory copy of input created here -mikefedyk
							$sql .= $connection->qstr($v);
						} else if ($typ == 'double') {
							$sql .= str_replace(',','.',$v); // locales fix so 1.1 does not get converted to 1,1
						} else if ($typ == 'boolean') {
							$sql .= $v ? $connection->true : $connection->false;
						} else if ($typ == 'object') {
							if (method_exists($v, '__toString')) {
								$sql .= $connection->qstr($v->__toString());
							} else {
								$sql .= $connection->qstr((string) $v);
							}
						} else if ($v === null) {
							$sql .= 'NULL';
						} else {
							$sql .= $v;
						}
						$i += 1;

						if ($i == $nparams) {
							break;
						}
					} // while
					if (isset($sqlarr[$i])) {
						$sql .= $sqlarr[$i];
						if ($i+1 != sizeof($sqlarr)) {
							$connection->logMessage( "Input Array does not match ?: ".htmlspecialchars($sql),'Execute');
						}
					} else if ($i != sizeof($sqlarr)) {
						$connection->logMessage( "Input array does not match ?: ".htmlspecialchars($sql),'Execute');
					}

					$this->methodResult = $connection->_Execute($sql);
					if (!$this->methodResult) {
						return $this->methodResult;
					}
				}
			} else {
				if ($array_2d) {
					if (is_string($sql)) {
						$stmt = $connection->Prepare($sql);
					} else {
						$stmt = $sql;
					}

					foreach($inputarr as $arr) {
						$this->methodResult = $connection->_Execute($stmt,$arr);
						if (!$this->methodResult) {
							return $this->methodResult;
						}
					}
				} else {
					$this->methodResult = $connection->_Execute($sql,$inputarr);
				}
			}
		} else {
			$this->methodResult = $connection->_Execute($sql,false);
		}

	}
	
}