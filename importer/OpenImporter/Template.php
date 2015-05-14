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
		if (file_exists(BASEDIR . '/Assets/index.css'))
			return file_get_contents(BASEDIR . '/Assets/index.css');
		else
			return '';
	}

	protected function fetchScripts()
	{
		if (file_exists(BASEDIR . '/Assets/scripts.js'))
		{
			$file = file_get_contents(BASEDIR . '/Assets/scripts.js');

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
			$this->renderErrors();

		$templates = $this->response->getTemplates();
		foreach ($templates as $template)
		{
			if (file_exists(BASEDIR . '/OpenImporter/Templates/' . $template['name'] . '.html'))
			{
				$replaces['template'] = $template['params'];

				$render = $this->twig->loadTemplate($template['name'] . '.html');
				echo $render->render($replaces);
			}
			else
				call_user_func_array(array($this, $template['name']), $template['params']);
		}

		if ($this->response->is_page)
			{
				$replaces['template'] = array('step' => $this->config->progress->current_step);
				$render = $this->twig->loadTemplate('footer.html');
				echo $render->render($replaces);
			}
	}

	public function step0(Form $form)
	{
		echo '
			<h2>', $this->lng->get('before_continue'), '</h2>
			<div class="content">
				<p>', sprintf($this->lng->get('before_details'), $this->response->source_name, $this->response->destination_name), '</p>
			</div>';
		$form->title = $this->lng->get('where');
		$form->description = $this->lng->get('locate_destination');
		$form->submit = array(
			'name' => 'submit_button',
			'value' => $this->lng->get('continue'),
		);
		$this->renderForm($form);

		echo '
			<div class="content">
				<h3>', $this->lng->get('not_this'),'</h3>
				<p>', $this->lng->get(array('pick_different', $this->response->scripturl . '?action=reset')), '</p>
			</div>';
	}

	public function renderForm(Form $form)
	{
		echo '
			<h2>', $form->title, '</h2>
			<div class="content">
				<form action="', $form->action_url, '" method="post">
					<p>', $form->description, '</p>
					<dl>';

		foreach ($form->options as $option)
		{
			if (empty($option))
			{
				echo '
					</dl>
					<div id="toggle_button">', $this->lng->get('advanced_options'), ' <span id="arrow_down" class="arrow">&#9660</span><span id="arrow_up" class="arrow">&#9650</span></div>
					<dl id="advanced_options">';
				continue;
			}

			switch ($option['type'])
			{
				case 'text':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>
							<input type="text" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" ', !empty($option['validate']) ? 'onblur="validateField(\'' . $option['id'] . '\')"' : '', ' class="text" />
							<div id="validate_', $option['id'], '" class="validate">', $option['correct'], '</div>
						</dd>';
					break;
				case 'checkbox':
					echo '
						<dt></dt>
						<dd>
							<label for="', $option['id'], '">', $option['label'], ':
								<input type="checkbox" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" ', $option['attributes'], '/>
							</label>
						</dd>';
					break;
				case 'password':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>
							<input type="password" name="', $option['id'], '" id="', $option['id'], '" class="text" />
							<div class="passwdcheck">', $option['correct'], '</div>
						</dd>';
					break;
				case 'steps':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>';
						foreach ($option['value'] as $key => $step)
							echo '
							<label><input type="checkbox" name="do_steps[', $key, ']" id="do_steps[', $key, ']" value="', $step['count'], '"', $step['mandatory'] ? ' readonly="readonly" ' : ' ', $step['checked'], ' /> ', $step['label'], '</label><br />';

					echo '
						</dd>';
					break;
			}
		}

		echo '
					</dl>
					<div class="button"><input id="submit_button" name="', $form->submit['name'], '" type="submit" value="', $form->submit['value'],'" class="submit" /></div>
				</form>
			</div>';
	}
}