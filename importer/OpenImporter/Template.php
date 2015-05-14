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
 * this is our UI
 *
 */
class Template
{
	protected $response = null;
	protected $replaces = array();
	protected $lng = null;
	protected $twig = null;
	protected $config = null;
	protected $header_rendered = false;

	public function __construct(\Twig_Environment $twig, Lang $lng, Configurator $config)
	{
		$this->twig = $twig;
		$this->lng = $lng;
		$this->config = $config;
	}

	public function setResponse($response)
	{
		$this->response = $response;
		$this->initReplaces();
		$this->response->styles = $this->fetchStyles();
		$this->response->scripts = $this->fetchScripts();
	}

	protected function fetchStyles()
	{
		if (file_exists($this->response->assets_dir . '/index.css'))
			return file_get_contents($this->response->assets_dir . '/index.css');
		else
			return '';
	}

	protected function fetchScripts()
	{
		if (file_exists($this->response->assets_dir . '/scripts.js'))
		{
			$file = file_get_contents($this->response->assets_dir . '/scripts.js');

			return strtr($file, $this->replaces);
		}
		else
			return '';
	}

	protected function initReplaces()
	{
		$this->replaces = array();
		foreach($this->response->getAll() as $key => $val)
		{
			$this->replaces['{{response->' . $key . '}}'] = $val;
		}
		foreach($this->lng->getAll() as $key => $val)
		{
			$this->replaces['{{language->' . $key . '}}'] = $val;
		}
	}

	public function render($response = null)
	{
		if ($response !== null)
			$this->setResponse($response);

		// No text? ... so sad. :(
		if ($this->response->no_template)
			return;

		$replaces = array(
			'language' => $this->lng,
			'response' => $this->response,
		);

		if ($this->header_rendered === false)
		{
			$this->response->sendHeaders();

			if ($this->response->is_page)
			{
				$replaces['template'] = array('step' => $this->config->progress->current_step);
				$render = $this->twig->loadTemplate('header.html');
				echo $render->render($replaces);
			}

			$this->header_rendered = true;
		}

		if ($this->response->is_page && $this->response->template_error)
		{
			$replaces['template'] = array('step' => $this->config->progress->current_step);
			$render = $this->twig->loadTemplate('renderErrors.html');
			echo $render->render($replaces);
		}

		$templates = $this->response->getTemplates();
		foreach ($templates as $template)
		{
			$replaces['template'] = $template['params'];

			$render = $this->twig->loadTemplate($template['name'] . '.html');
			echo $render->render($replaces);
		}

		if ($this->response->is_page)
		{
			$replaces['template'] = array('step' => $this->config->progress->current_step);
			$render = $this->twig->loadTemplate('footer.html');
			echo $render->render($replaces);
		}
	}
}