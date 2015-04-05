<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers;

/**
 * This abstract class is the base for any php destination file.
 *
 * It provides some common necessary methods and some default properties
 * so that Importer can do its job without having to test for existinance
 * of methods every two/three lines of code.
 */
abstract class AbstractDestinationImporter implements DestinationImporterInterface
{
	protected $setting_file = '';

	protected $path = null;

	protected $db = null;
	protected $config = null;

	public function setUtils($db, $config)
	{
		$this->db = $db;
		$this->config = $config;
	}

	abstract public function getName();

	abstract public function getDestinationURL($path);

	abstract public function getFormFields($path_to = '', $scriptname = '');

	abstract public function verifyDbPass($pwd_to_verify);

	abstract public function dbConnectionData();

	abstract public function getDbPrefix();

	public function checkSettingsPath($path)
	{
		$found = file_exists($path . $this->setting_file);

		if ($found && $this->path === null)
			$this->path = $path;

		return $found;
	}
}