<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

use Symfony\Component\ClassLoader\Psr4ClassLoader;
use OpenImporter\Core\Configurator;
use OpenImporter\Core\ImportException;
use OpenImporter\Core\PasttimeException;
use Pimple\Container;

define('BASEDIR', __DIR__);
// A shortcut
define('DS', DIRECTORY_SEPARATOR);

// Composer stuff
require_once(BASEDIR . '/vendor/autoload.php');
require_once(BASEDIR . '/OpenImporter/Utils.php');

$loader = new Psr4ClassLoader();
$loader->addPrefix('OpenImporter\\Core\\', BASEDIR . '/OpenImporter');
$loader->addPrefix('OpenImporter\\Importers\\', BASEDIR . '/Importers');
$loader->register();

@set_time_limit(600);
@set_exception_handler(array('ImportException', 'exception_handler'));
@set_error_handler(array('ImportException', 'error_handler_callback'), E_ALL);

error_reporting(E_ALL);
ignore_user_abort(true);
umask(0);

ob_start();

// disable gzip compression if possible
if (is_callable('apache_setenv'))
	apache_setenv('no-gzip', '1');

if (@ini_get('session.save_handler') == 'user')
	@ini_set('session.save_handler', 'files');
@session_start();

// Add slashes, as long as they aren't already being added.
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	$_POST = stripslashes_recursive($_POST);

$container = new Container();
$OI_configurator = $container['configurator'] = new Configurator();
$container['configurator']->lang_dir = BASEDIR . DIRECTORY_SEPARATOR . 'Languages';
$container['configurator']->importers_dir = BASEDIR . DIRECTORY_SEPARATOR . 'Importers';
$container['template'] = function ($c) {
	return 'OpenImporter\\Core\\Template';
};
$container['template_obj'] = function ($c) {
	return new $c['template']($c['http_response_obj'], $c['lang_obj']);
};
$container['lang'] = function ($c) {
	return 'OpenImporter\\Core\\Lang';
};
$container['lang_obj'] = function ($c) {
	return new $c['lang']($c['configurator']->lang_dir);
};
$container['response_header'] = function ($c) {
	return 'OpenImporter\\Core\\ResponseHeader';
};
$container['response_header_obj'] = function ($c) {
	return new $c['response_header']();
};
$container['cookie'] = function ($c) {
	return 'OpenImporter\\Core\\Cookie';
};
$container['cookie_obj'] = function ($c) {
	return new $c['cookie']();
};
$container['http_response'] = function ($c) {
	return 'OpenImporter\\Core\\HttpResponse';
};
$container['http_response_obj'] = function ($c) {
	return new $c['http_response']($c['response_header_obj']);
};
$container['importer'] = function ($c) {
	return 'OpenImporter\\Core\\Importer';
};
$container['importer_obj'] = function ($c) {
	return new $c['importer']($c['configurator'], $c['lang_obj'], $c['template_obj']);
};
$container['import_manager'] = function ($c) {
	return 'OpenImporter\\Core\\ImportManager';
};
$container['import_manager_obj'] = function ($c) {
	return new $c['import_manager'](
		$c['configurator'],
		$c['importer_obj'],
		$c['template_obj'],
		$c['cookie_obj'],
		$c['http_response_obj']
	);
};

$template = $container['template_obj'];

global $import;

try
{
	$import = $container['import_manager_obj'];

	$import->process();
}
catch (ImportException $e)
{
	$e->doExit($template);
}
catch (PasttimeException $e)
{
	$e->doExit();
}
catch (StepException $e)
{
	$e->doExit();
}
catch (\Exception $e)
{
	// Debug, remember to remove before PR
	echo '<br>' . $e->getMessage() . '<br>';
	echo $e->getFile() . '<br>';
	echo $e->getLine() . '<br>';
	// If an error is not catched, it means it's fatal and the script should die.
}