<?php
/**
 * com_leaudit — minimal administrator-side entry.
 *
 * The scanner runs entirely from the front end.
 * This file satisfies Joomla's requirement that every component has an
 * administrator entry point; it simply redirects back to the admin dashboard.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

Factory::getApplication()->redirect('index.php');
