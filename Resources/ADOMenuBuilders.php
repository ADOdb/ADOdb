<?php

namespace ADOdb\Resources;

use \ADOdb\Resources\ADOConnection;

class ADOMenuBuilders {

	protected ?ADOConnection $conn;

	public function __construct(ADOConnection $conn)
	{
		$this->conn = $conn;
	}

	function _adodb_getmenu($zthis, $name,$defstr='',$blank1stItem=true,$multiple=false,
				$size=0, $selectAttr='',$compareFields0=true)
	{
		global $ADODB_FETCH_MODE;

		$s = $this->_adodb_getmenu_select($name, $defstr, $blank1stItem, $multiple, $size, $selectAttr);

		$hasvalue = $zthis->FieldCount() > 1;
		if (!$hasvalue) {
			$compareFields0 = true;
		}

		$value = '';
		while(!$zthis->EOF) {
			$zval = rtrim(reset($zthis->fields));

			if ($blank1stItem && $zval == "") {
				$zthis->MoveNext();
				continue;
			}

			if ($hasvalue) {
				if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC) {
					// Get 2nd field's value regardless of its name
					$zval2 = current(array_slice($zthis->fields, 1, 1));
				} else {
					// With NUM or BOTH fetch modes, we have a numeric index
					$zval2 = $zthis->fields[1];
				}
				$zval2 = trim($zval2);
				$value = 'value="' . htmlspecialchars($zval2) . '"';
			}

			/** @noinspection PhpUndefinedVariableInspection */
			$s .= $this->_adodb_getmenu_option($defstr, $compareFields0 ? $zval : $zval2, $value, $zval);

			$zthis->MoveNext();
		} // while

		return $s ."\n</select>\n";
	}

	function _adodb_getmenu_gp($zthis, $name,$defstr='',$blank1stItem=true,$multiple=false,
				$size=0, $selectAttr='',$compareFields0=true)
	{
		global $ADODB_FETCH_MODE;

		$s = $this->_adodb_getmenu_select($name, $defstr, $blank1stItem, $multiple, $size, $selectAttr);

		$hasvalue = $zthis->FieldCount() > 1;
		$hasgroup = $zthis->FieldCount() > 2;
		if (!$hasvalue) {
			$compareFields0 = true;
		}

		$value = '';
		$optgroup = null;
		$firstgroup = true;
		while(!$zthis->EOF) {
			$zval = rtrim(reset($zthis->fields));
			$group = '';

			if ($blank1stItem && $zval=="") {
				$zthis->MoveNext();
				continue;
			}

			if ($hasvalue) {
				if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC) {
					// Get 2nd field's value regardless of its name
					$fields = array_slice($zthis->fields, 1);
					$zval2 = current($fields);
					if ($hasgroup) {
						$group = trim(next($fields));
					}
				} else {
					// With NUM or BOTH fetch modes, we have a numeric index
					$zval2 = $zthis->fields[1];
					if ($hasgroup) {
						$group = trim($zthis->fields[2]);
					}
				}
				$zval2 = trim($zval2);
				$value = "value='".htmlspecialchars($zval2)."'";
			}

			if ($optgroup != $group) {
				$optgroup = $group;
				if ($firstgroup) {
					$firstgroup = false;
				} else {
					$s .="\n</optgroup>";
				}
				$s .="\n<optgroup label='". htmlspecialchars($group) ."'>";
			}

			/** @noinspection PhpUndefinedVariableInspection */
			$s .= $this->_adodb_getmenu_option($defstr, $compareFields0 ? $zval : $zval2, $value, $zval);

			$zthis->MoveNext();
		} // while

		// closing last optgroup
		if($optgroup != null) {
			$s .= "\n</optgroup>";
		}
		return $s ."\n</select>\n";
	}

    /**
	 * Generate the opening SELECT tag for getmenu functions.
	 *
	 * ADOdb internal function, used by _adodb_getmenu() and _adodb_getmenu_gp().
	 *
	 * @param string $name
	 * @param string $defstr
	 * @param bool   $blank1stItem
	 * @param bool   $multiple
	 * @param int    $size
	 * @param string $selectAttr
	 *
	 * @return string HTML
	 */
	function _adodb_getmenu_select($name, $defstr = '', $blank1stItem = true,
								$multiple = false, $size = 0, $selectAttr = '')
	{
		if ($multiple || is_array($defstr)) {
			if ($size == 0 ) {
				$size = 5;
			}
			$attr = ' multiple size="' . $size . '"';
			if (!strpos($name,'[]')) {
				$name .= '[]';
			}
		} elseif ($size) {
			$attr = ' size="' . $size . '"';
		} else {
			$attr = '';
		}

		$html = '<select name="' . $name . '"' . $attr . ' ' . $selectAttr . '>';
		if ($blank1stItem) {
			if (is_string($blank1stItem)) {
				$barr = explode(':',$blank1stItem);
				if (sizeof($barr) == 1) {
					$barr[] = '';
				}
				$html .= "\n<option value=\"" . $barr[0] . "\">" . $barr[1] . "</option>";
			} else {
				$html .= "\n<option></option>";
			}
		}

		return $html;
	}

	/**
	 * Print the OPTION tags for getmenu functions.
	 *
	 * ADOdb internal function, used by _adodb_getmenu() and _adodb_getmenu_gp().
	 *
	 * @param string $defstr  Default values
	 * @param string $compare Value to compare against defaults
	 * @param string $value   Ready-to-print `value="xxx"` (or empty) string
	 * @param string $display Display value
	 *
	 * @return string HTML
	 */
	function _adodb_getmenu_option($defstr, $compare, $value, $display)
	{
		if (   is_array($defstr) && in_array($compare, $defstr)
			|| !is_array($defstr) && strcasecmp($compare, $defstr ?? '') == 0
		) {
			$selected = ' selected="selected"';
		} else {
			$selected = '';
		}

		return "\n<option $value$selected>" . htmlspecialchars($display) . '</option>';
	}


}