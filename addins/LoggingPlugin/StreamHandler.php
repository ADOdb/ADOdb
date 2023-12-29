<?php
/**
* definition of a streamhandler for the builtin
*
* This file is part of the ADOdb package.
*
* @copyright 2023 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\LoggingPlugin;

class StreamHandler
{
 
    public ?string $url;

    function __construct(string $url, $logLevel,$bubble)
    {
        $this->url = $url;
    }
}
