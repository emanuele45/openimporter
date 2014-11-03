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
}
else
	echo 'You cannot create phar archives, read here how to fix it: http://silex.sensiolabs.org/doc/phar.html#php-configuration' . "\n";