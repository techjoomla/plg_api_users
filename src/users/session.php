<?php
/**
 * @package     API
 * @subpackage  plg_api_users
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2019 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license     GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 */

// No direct access.
defined('_JEXEC') or die('Restricted access');

require_once JPATH_SITE . '/components/com_api/vendors/php-jwt/src/JWT.php';


/**
 * Login API resource class
 *
 * @package  API
 * @since    1.6.0
 */
class UsersApiResourceSession extends ApiResource
{
	/**
	 * Get method
	 *
	 * @return  object
	 */
	public function get()
	{
		$obj = new stdclass;

		$obj->csrftoken = JSession::getFormToken();
		$obj->code = '200';
		$this->plugin->setResponse($obj);
	}
}
