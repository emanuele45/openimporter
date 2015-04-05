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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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

$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(BASEDIR));
$loader->load('services.yml');

$OI_configurator = $container->get('configurator');
$OI_configurator->lang_dir = BASEDIR . DIRECTORY_SEPARATOR . 'Languages';
$OI_configurator->importers_dir = BASEDIR . DIRECTORY_SEPARATOR . 'Importers';

$template = $container->get('template');

global $import;

try
{
	$import = $container->get('import_manager');

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