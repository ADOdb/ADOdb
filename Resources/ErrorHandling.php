<?php

/**
 * MetaFunctions
 *
 */

namespace ADOdb\Resources;

class ErrorHandling
{
    /**
     * Pretty print the debug_backtrace function
     *
     * @param string[]|bool $printOrArr       Whether to print the result directly or return the result
     * @param int           $maximumDepth     The maximum depth of the array to traverse
     * @param int           $elementsToIgnore The backtrace array indexes to ignore
     * @param null|bool     $isHtml           True if we are in a CGI environment, false for CLI,
     *                                        null to auto detect
     *
     * @return string Formatted backtrace
     */
    function _adodb_backtrace($printOrArr=true, $maximumDepth=0, $elementsToIgnore=0, $isHtml=null)
    {
        if ($isHtml === null) {
            // Auto determine if we in a CGI environment
            $isHtml = isset($_SERVER['HTTP_USER_AGENT']);
        }

        $s = "Call stack (most recent call first):\n";
        if ($isHtml) {
            $s = '<div class="adodb-debug-trace">' . PHP_EOL
                . "<h4>$s</h4>\n"
                . '<table>' . PHP_EOL
                . '<thead><tr><th>#</th><th>Function</th><th>Location</th></tr></thead>' . PHP_EOL;
            $fmt = '<tr><td>%1$d</td><td>%2$s</td><td>%3$s line %4$s</td></tr>' . PHP_EOL;
        } else {
            $fmt = '%1$2d. %2$s in %3$s line %4$s' . PHP_EOL;
        }

        // Maximum length for string arguments display
        $MAXSTRLEN = 128;

        // Get 2 extra elements if max depth is specified
        if ($maximumDepth) {
            $maximumDepth += 2;
        }
        if (is_array($printOrArr)) {
            $traceArr = array_slice($printOrArr, 0, $maximumDepth);
        } else {
            $traceArr = debug_backtrace(0, $maximumDepth);
        }

        // Remove elements to ignore, plus the first 2 elements that just show
        // calls to adodb_backtrace
        for ($elementsToIgnore += 2; $elementsToIgnore > 0; $elementsToIgnore--) {
            array_shift($traceArr);
        }
        $elements = sizeof($traceArr);

        foreach ($traceArr as $element) {
            // Function name with class prefix
            $functionName = $element['function'];
            if (isset($element['class'])) {
                $functionName = $element['class'] . '::' . $functionName;
            }

            // Function arguments
            $args = array();
            if (isset($element['args'])) {
                foreach ($element['args'] as $v) {
                    if (is_null($v)) {
                        $args[] = 'null';
                    } elseif (is_array($v)) {
                        $args[] = 'Array[' . sizeof($v) . ']';
                    } elseif (is_object($v)) {
                        $args[] = 'Object:' . get_class($v);
                    } elseif (is_bool($v)) {
                        $args[] = $v ? 'true' : 'false';
                    } else {
                        // Remove newlines and tabs, compress repeating spaces
                        $v = preg_replace('/\s+/', ' ', $v);

                        // Truncate if needed
                        if (strlen($v) > $MAXSTRLEN) {
                            $v = substr($v, 0, $MAXSTRLEN) . '...';
                        }

                        $args[] = $isHtml ? htmlspecialchars($v) : $v;
                    }
                }
            }

            // Shorten ADOdb paths ('/path/to/adodb/XXX' printed as '.../XXX')
            $file = str_replace(__DIR__, '...', $element['file'] ?? 'unknown file');

            $s .= sprintf($fmt,
                $elements--,
                $functionName . '(' . implode(', ', $args) . ')',
                $file,
                $element['line'] ?? 'unknown'
            );
        }

        if ($isHtml) {
            $s .= '</table>' . PHP_EOL . '</div>' . PHP_EOL;
        }

        if ($printOrArr) {
            ADOConnection::outp($s);
        }

        return $s;
    }

    /**
	* Replaces standard _execute when debug mode is enabled
	*
	* @param ADOConnection   $zthis    An ADOConnection object
	* @param string|string[] $sql      A string or array of SQL statements
	* @param string[]|null   $inputarr An optional array of bind parameters
	*
	* @return  mixed A handle to the executed query (actual type is driver-dependent)
	*/
	function _adodb_debug_execute($zthis, $sql, $inputarr)
	{
		// Execute the query, capturing any output
		ob_start();
		$queryId = $zthis->_query($sql, $inputarr);
		$queryOutput = ob_get_clean();

		// Get last error number and message if query execution failed
		if (!$queryId) {
			if ($zthis->databaseType == 'mssql') {
				// Alexios Fakios notes that ErrorMsg() must be called before ErrorNo() for mssql
				// because ErrorNo() calls Execute('SELECT @ERROR'), causing recursion
				// ErrorNo is a slow function call in mssql
				$errMsg = $zthis->ErrorMsg();
				if ($errMsg && ($errNo = $zthis->ErrorNo())) {
					$queryOutput .= $errNo . ': ' . $errMsg . "\n";
				}
			} else {
				$errNo = $zthis->ErrorNo();
				if ($errNo) {
					$queryOutput .= $errNo . ': ' . $zthis->ErrorMsg() . "\n";
				}
			}
		}

		// Driver name
		$driverName = $zthis->databaseType;
		if (!isset($zthis->dsnType)) {
			// Append the PDO driver name
			$driverName .= '-' . $zthis->dsnType;
		}

		// Prepare SQL statement for display (remove newlines and tabs, compress repeating spaces)
		$sqlText = preg_replace('/\s+/', ' ', is_array($sql) ? $sql[0] : $sql);

		// Unpack the bind parameters
		$bindParams = '';
		if ($inputarr) {
			$MAXSTRLEN = 64;
			foreach ($inputarr as $kk => $vv) {
				if (is_string($vv) && strlen($vv) > $MAXSTRLEN) {
					$vv = substr($vv, 0, $MAXSTRLEN) . '...';
				}
				if (is_null($vv)) {
					$bindParams .= "$kk=>null\n";
				} else {
					if (is_array($vv)) {
						$vv = sprintf("Array Of Values: [%s]", implode(',', $vv));
					}
					$bindParams .= "$kk=>'$vv'\n";
				}
			}
		}

		// check if running from browser or command-line
		$isHtml = isset($_SERVER['HTTP_USER_AGENT']);

		// Output format - sprintf parameters:
		// %1 = horizontal line, %2 = DB driver, %3 = SQL statement, %4 = Query params
		if ($isHtml) {
			$fmtSql = '<div class="adodb-debug">' . PHP_EOL
				. '<div class="adodb-debug-sql">' . PHP_EOL
				. '%1$s<table>' . PHP_EOL
				. '<tr><th>%2$s</th><td><code>%3$s</code></td></tr>' . PHP_EOL
				. '%4$s</table>%1$s' . PHP_EOL
				. '</div>' . PHP_EOL;
			$hr = $zthis->debug === -1 ? '' : '<hr>';
			$sqlText = htmlspecialchars($sqlText);
			if ($bindParams) {
				$bindParams = '<tr><th>Parameters</th><td><code>'
					. nl2br(htmlspecialchars($bindParams))
					. '</code></td></tr>' . PHP_EOL;
			}
			if ($queryOutput) {
				$queryOutput = '<div class="adodb-debug-errmsg">' . $queryOutput . '</div>' . PHP_EOL;
			}
		} else {
			// CLI output
			$fmtSql = '%1$s%2$s: %3$s%4$s%1$s';
			$hr = $zthis->debug === -1 ? '' : str_repeat('-', 78) . "\n";
			$sqlText .= "\n";
		}

		// Always output debug info if statement execution failed
		if (!$queryId || $zthis->debug !== -99) {
			printf($fmtSql, $hr, $driverName, $sqlText, $bindParams);
			if ($queryOutput) {
				echo $queryOutput . ($isHtml ? '' : "\n");
			}
		}

		// Print backtrace if query failed or forced
		if ($queryId === false || $zthis->debug === 99) {
			$this->_adodb_backtrace(true, 0, 0, $isHtml);
		}
		if ($isHtml && $zthis->debug !== -99) {
			echo '</div>' . PHP_EOL;
		}

		return $queryId;
	}


}