<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * class ImportException extends the build-in Exception class and
 * catches potential errors
 */
class ImportException extends \Exception
{
	protected static $template = null;

	public function doExit($template = null)
	{
		self::exceptionHandler($this, $template);
	}

	public static function setImportManager(Template $template)
	{
		self::$template = $template;
	}

	public static function errorHandlerCallback($code, $string, $file, $line)
	{
		if (error_reporting() == 0)
			return;

		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}

	/**
	 * @param \Exception $exception
	 */
	public static function exceptionHandler($exception, $template = null)
	{
		if (error_reporting() == 0)
			return;

		if ($template === null)
		{
			if (!empty(self::$template))
				$template = self::$template;
			else
			{
				$twig = new Twig_Environment(new Twig_Loader_Filesystem(BASEDIR . '/OpenImporter/Templates'));
				$filter = new Twig_SimpleFilter('pregCleanInput', function ($string) {
					return preg_replace('~[^\w\d]~', '_', $string);
				});
				$twig->addFilter($filter);

				$template = new Template($twig, new DummyLang(), new Configurator());
			}
		}
		$message = $exception->getMessage();
		$trace = $exception->getTrace();
		$line = $exception->getLine();
		$file = $exception->getFile();
		$template->error($message, isset($trace[0]['args'][1]) ? $trace[0]['args'][1] : null, $line, $file);
	}
}