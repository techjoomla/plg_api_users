<?php
/**
 * @package     Joomla.API.Plugin
 * @subpackage  PlgAPIUsers
 *
 * @copyright   Copyright (C) 2009 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

/**
 * API User Plugin
 *
 * @package     PlgAPIUsers
 * @subpackage  User creation/registration
 * @since       1.0
 */
class PlgAPIUsers extends ApiPlugin
{
	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   1.6
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config = array());

		ApiResource::addIncludePath(dirname(__FILE__) . '/users');

		/*load language file for plugin frontend*/
		$lang = JFactory::getLanguage();
		$lang->load('plg_api_users', JPATH_ADMINISTRATOR, '', true);
		$this->setResourceAccess('login', 'public','get');
		$this->setResourceAccess('user', 'public', 'post');
		$this->setResourceAccess('config', 'public', 'get');
	}
}
