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

	/**
	 * Display a specific error message.
	 *
	 * @param string $error_message
	 * @param int|bool $trace
	 * @param int|bool $line
	 * @param string|bool $file
	 */
	public function error($error_message, $trace = false, $line = false, $file = false)
	{
		echo '
			<div class="error_message">
				<div class="error_text">
					', !empty($trace) ? $this->lng->get(array('error_message', $error_message)) : $error_message, '
				</div>';

		if (!empty($trace))
			echo '
				<div class="error_text">', $this->lng->get(array('error_trace', $trace)), '</div>';

		if (!empty($line))
			echo '
				<div class="error_text">', $this->lng->get(array('error_line', $line)), '</div>';

		if (!empty($file))
			echo '
				<div class="error_text">', $this->lng->get(array('error_file', $file)), '</div>';

		echo '
			</div>';
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

		if ($this->header_rendered === false)
		{
			$this->response->sendHeaders();

			if ($this->response->is_page)
				$this->header();

			$this->header_rendered = true;
		}

		if ($this->response->is_page)
			$this->renderErrors();

		$templates = $this->response->getTemplates();
		foreach ($templates as $template)
		{
			if (file_exists(BASEDIR . '/OpenImporter/Templates/' . $template['name'] . '.html'))
			{
				$replaces['language'] = $this->lng;
				$replaces['response'] = $this->response;
				$replaces['template'] = $template['params'];

				$render = $this->twig->loadTemplate($template['name'] . '.html');
				echo $render->render($replaces);
			}
			else
				call_user_func_array(array($this, $template['name']), $template['params']);
		}

		if ($this->response->is_page)
			$this->footer();
	}

	protected function renderErrors()
	{
		if ($this->response->template_error)
		{
			foreach ($this->response->getErrors() as $msg)
			{
				if (is_array($msg))
					call_user_func_array(array($this, 'error'), $msg);
				else
					$this->error($msg);
			}
		}
	}

	/**
	 * Show the footer.
	 */
	public function footer()
	{
		if ($this->response->step == 1 || $this->response->step == 2)
			echo '
				</p>
			</div>';
		echo '
		</div>
	</body>
</html>';
	}

	/**
	 * Show the header.
	 */
	public function header()
	{
		echo '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="', $this->lng->get('locale'), '" lang="', $this->lng->get('locale'), '">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $this->response->page_title, '</title>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
', $this->response->scripts, '
		// ]]></script>
		<style type="text/css">
', $this->response->styles, '
		</style>
	</head>
	<body>
		<div id="header">
			<h1>', $this->response->page_title, '</h1>
		</div>
		<div id="main">';

		if ($this->config->progress->current_step == 1 || $this->config->progress->current_step == 2)
			echo '
			<h2>', $this->lng->get('importing'), '...</h2>
			<div class="content"><p>';
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

	protected function renderStatuses()
	{
		echo '
		<span class="statuses">';

		foreach ($this->response->getStatuses() as $status)
			$this->status($status[0], $status[1]);

		echo '
		</span>';
	}

	/**
	 * Display notification with the given status
	 *
	 * @param int $status
	 * @param string $title
	 */
	public function status($status, $title)
	{
		if (!empty($title))
			echo '<span class="text">' . $title . '...</span> ';

		if ($status == 1)
			echo '<span class="success">&#x2714</span>';

		if ($status == 2)
			echo '<span class="disabled">&#x2714</span> (', $this->lng->get('skipped'),')';

		if ($status == 3)
			echo '<span class="failure">&#x2718</span> (', $this->lng->get('not_found_skipped'),')';

		if ($status != 0)
			echo '<br />';
	}

	/**
	 * Display last step UI, completion status and allow eventually
	 * to delete the scripts
	 *
	 * @param string $name
	 * @param bool $writable if the files are writable, the UI will allow deletion
	 */
	public function step3($name, $writable)
	{
		echo '
			</div>
			<h2>', $this->lng->get('complete'), '</h2>
			<div class="content">
			<p>', $this->lng->get('congrats'),'</p>';

		if ($writable)
			echo '
				<div class="notice">
					<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete()" />', $this->lng->get('check_box'), '</label>
				</div>';

		echo '
				<p>', sprintf($this->lng->get('all_imported'), $name), '</p>
				<p>', $this->lng->get('smooth_transition'), '</p>';
	}

	/**
	 * Display the progress bar,
	 * and inform the user about when the script is paused and re-run.
	 * @todo the url should be built in the PasttimeException, not here
	 *
	 * @param int $bar
	 * @param int $value
	 * @param int $max
	 * @param int $substep
	 * @param int $start
	 */
	public function timeLimit($bar, $value, $max, $substep, $start)
	{
		if (!empty($bar))
			echo '
			<div id="progressbar">
				<progress value="', $bar, '" max="100">', $bar, '%</progress>
			</div>';

		echo '
		</div>
		<h2>', $this->lng->get('not_done'),'</h2>
		<div class="content">
			<div class="progress"><span>', $this->lng->get('overall_progress'),'</span><progress value="', $value, '" max="', $max, '"></progress></div>
			<p>', $this->lng->get('importer_paused'), '</p>

			<form action="', $this->response->scripturl, '?step=', $this->response->step, '&amp;substep=', $substep, '&amp;start=', $start, '" method="post" name="autoSubmit">
				<div class="continue"><input name="b" type="submit" value="', $this->lng->get('continue'),'" /></div>
			</form>

			<script type="text/javascript"><!-- // --><![CDATA[
				var countdown = 3;
				window.onload = doAutoSubmit;
			// ]]></script>';
	}

	/**
	 * ajax response, whether the paths to the source and destination
	 * software are correctly set.
	 */
	public function validate()
	{
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<valid>', $this->response->valid ? 'true' : 'false' ,'</valid>';
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