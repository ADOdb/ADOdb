<?php
/**
* Object holding a recordset to be pushed to the cache
*
* This file is part of the ADOdb package.
*
* @copyright 2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\CachingPlugin;

/**
* Defines the attributes passed to the monolog interface
*/
class ADOCacheRecordset
{	
	
    /*
    *   Service flag.
    *   0 = Not yet set
    *   1 = REcordset to be cached
    *  -1 = Not an insertable SQL statement
    */
    public int $operation = 0;

    
    /*
	* Expiry time in seconds
	*/
	public int $ttl = 2400;
	
    /*
    *  The SQL that generated the recordset
    */
    public string $sql = '';

    /*
    * The database type
    */
    public string $databaseType = '';

    /*
     * Affected rows if relevant
    */ 
    public int $affectedRows = 0;

    /*
    * The last auto-geenerated ID
    */
    public int $insertID = 0;

    /*
    * Creation timestamp
    */
    public int $timeCreated = 0;            
    
    /*
    * The class name of the recordset
    */
    public string $className = '';

    /*
    * The recordset to be cached
    */
    public string $recordSet = '';
		
}