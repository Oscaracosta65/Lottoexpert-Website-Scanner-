<?php
/**
 * com_leaudit — front-end entry point.
 *
 * Joomla bootstraps this file when the URL contains option=com_leaudit.
 * We instantiate the legacy MVC controller, execute the requested task
 * (defaults to "display"), then let Joomla handle any redirect.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

$controller = BaseController::getInstance('Leaudit', ['base_path' => JPATH_COMPONENT]);
$controller->execute(Factory::getApplication()->input->getCmd('task', 'display'));
$controller->redirect();
