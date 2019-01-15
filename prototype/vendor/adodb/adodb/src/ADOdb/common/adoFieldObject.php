<?php
namespace ADOdb\common;
use ADOdb;

/**
 * Helper class for FetchFields -- holds info on a column
 */
final class adoFieldObject 
{
	public $name;
	public $max_length=0;
	public $type;
	public $precision;
}