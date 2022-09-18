<?php
/**
* This sample demonstrates a basic attachment of the logging plugin 
* to the core outp() system directing it to a text file using the 
* builtin plugin. It can be used an as-is replacement to the normal
* ADOdb error logging
*/
include __DIR__ .'/../loader/adodb-addins-loader.inc.php';

DEFINE ('ADODB_LOG_DIR','/dev/github/logs/core');

$debugStreamHandler	   = ADODB_LOG_DIR . '/debug.log';
$noticeStreamHandler   = ADODB_LOG_DIR . '/notice.log';
$criticalStreamHandler = ADODB_LOG_DIR . '/critical.log';

$streamHandlers = array(100=>$debugStreamHandler,
					    250=>$noticeStreamHandler,
						500=>$criticalStreamHandler);

$loggingDefinition = new ADOdb\addins\logger\plugins\builtin\ADOLoggingDefinitions;
$loggingDefinition->loggingTag = 'CORE';
$loggingDefinition->logBacktrace = true;
$loggingDefinition->streamHandlers = $streamHandlers;

$coreLoggingObject = new ADOdb\addins\logger\ADOCoreLogger($loggingDefinition);

$coreLoggingObject->log(100,'---------------- RUN STARTS -----------------');
$coreLoggingObject->log(250,'---------------- RUN STARTS -----------------');
$coreLoggingObject->log(500,'---------------- RUN STARTS -----------------');

$loggingDefinition = new ADOdb\addins\logger\plugins\builtin\ADOLoggingDefinitions;
$loggingDefinition->streamHandlers = $streamHandlers;
$loggingDefinition->loggingTag = 'FEATURE';

$featureLoggingObject = new ADOdb\addins\logger\ADOLogger($loggingDefinition);

$featureLoggingObject->log(100,'---------------- RUN STARTS -----------------');
$featureLoggingObject->log(250,'---------------- RUN STARTS -----------------');
$featureLoggingObject->log(500,'---------------- RUN STARTS -----------------');


