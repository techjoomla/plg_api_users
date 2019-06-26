<?php
/**
 * @package     API
 * @subpackage  plg_api_users
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2019 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// No direct access.
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

/**
 * Users plgAPI class
 *
 * @since  1.0.0
 */
class PlgAPIUsers extends ApiPlugin
{
	/**
	 * Constructor
	 *
	 * @param   string  &$subject  subject
	 * @param   string  $config    config
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config = array());

		ApiResource::addIncludePath(dirname(__FILE__) . '/users');

		// Load language file for plugin frontend
		$lang = JFactory::getLanguage();
		$lang->load('plg_api_users', JPATH_ADMINISTRATOR, '', true);

		// Set the login resource to be public
		$this->setResourceAccess('login', 'public', 'get');
		$this->setResourceAccess('users', 'public', 'post');
		$this->setResourceAccess('config', 'public', 'get');
		$this->setResourceAccess('user', 'public', 'post');
	}
}
