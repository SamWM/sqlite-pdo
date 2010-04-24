<?php
// logging page hits
$hitcounterdb = $_SERVER['DOCUMENT_ROOT']."/data/hitcounter.sqlite";

// check if database file exists first
if(!file_exists($hitcounterdb))
{
	$logdb = new PDO("sqlite:".$hitcounterdb);
	$logdb->exec("CREATE TABLE hits(page VARCHAR(255) PRIMARY KEY, counter INTEGER)");
}
else
{
	$logdb = new PDO("sqlite:".$hitcounterdb);
}

$page = $_SERVER["SCRIPT_URL"];

// check if page is already in the hits table
$logdbstatement = $logdb->query("SELECT counter FROM hits WHERE page='$page'");
$logdbrecord = $logdbstatement->fetchAll();

// if a record is found
if(sizeof($logdbrecord) != 0)
{
	$counter = ++$logdbrecord[0]['counter'];
	$logdb->exec("UPDATE hits SET counter=$counter WHERE page='$page'");
	echo $counter;
}
else
{
	$logdb->exec("INSERT INTO hits(page, counter) VALUES ('$page', 1)");
	echo "1";
}

// close connection
$logdb = null;