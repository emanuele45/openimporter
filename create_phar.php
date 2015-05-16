<?php

if (Phar::canWrite())
{
	$p = new Phar('openimporter.phar', 0, 'openimporter.phar');

	// create transaction - nothing is written to newphar.phar
	// until stopBuffering() is called, although temporary storage is needed
	$p->startBuffering();

	// add all files in /path/to/project, saving in the phar with the prefix "project"
	$p->buildFromDirectory(__DIR__ . '/importer');

	$p['index.php'] = '<?php
define("IN_PHAR", 1);
require_once("phar://openimporter.phar/import.php");';

	$p->setMetadata(array('bootstrap' => 'index.php'));

	// save the phar archive to disk
	$p->stopBuffering();

	$archive = gzdeflate(file_get_contents('openimporter.phar'));
	file_put_contents('import.php', '<?php
error_reporting(E_ALL);

if (!file_exists(\'openimporter.phar\'))
	file_put_contents(\'openimporter.phar\', gzinflate(base64_decode(\'' . base64_encode($archive) . '\')));

if (file_exists(\'openimporter.phar\'))
	require_once(__DIR__ . \'/openimporter.phar\');
else
	echo \'It is not possible to create files on the disk, please check your server configuration.\'');
}
else
	echo 'You cannot create phar archives, read here how to fix it: http://silex.sensiolabs.org/doc/phar.html#php-configuration' . "\n";