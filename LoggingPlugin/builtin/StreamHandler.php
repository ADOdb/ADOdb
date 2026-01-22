<?php
/**
* definition of a Streamhandler for the builtin. This clones a portion of
* the monolog stream handler
*
* This file is part of the ADOdb package.
*
* @copyright 2023-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\LoggingPlugin\builtin;

class StreamHandler
{
 
    public ?string $url;
    public ?int    $level;
    public bool    $bubble=false;

    function __construct(string $url, $logLevel,$bubble=false)
    {
        $this->url      = $url;
        $this->level    = $logLevel;
        $this->bubble   = $bubble;
    }
}
