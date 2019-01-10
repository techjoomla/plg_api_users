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

use Firebase\JWT\JWT;

jimport('joomla.plugin.plugin');
jimport('joomla.html.html');
jimport('joomla.application.component.controller');
jimport('joomla.application.component.model');
jimport('joomla.user.helper');
jimport('joomla.user.user');
jimport('joomla.application.component.helper');

JModelLegacy::addIncludePath(JPATH_SITE . 'components/com_api/models');
require_once JPATH_SITE . '/components/com_api/libraries/authentication/user.php';
require_once JPATH_SITE . '/components/com_api/libraries/authentication/login.php';
require_once JPATH_ADMINISTRATOR . '/components/com_api/models/key.php';
require_once JPATH_ADMINISTRATOR . '/components/com_api/models/keys.php';

/**
 * Login API resource class
 *
 * @package  API
 * @since    1.6.0
 */
class UsersApiResourceLogin extends ApiResource
{
	/**
	 * Get method
	 *
	 * @return  object
	 */
	public function get()
	{
		$this->plugin->setResponse(JText::_('PLG_API_USERS_GET_METHOD_NOT_ALLOWED_MESSAGE'));
	}

	/**
	 * Post method
	 *
	 * @return  object
	 */
	public function post()
	{
		$this->plugin->setResponse($this->keygen());
	}

	/**
	 * Generate key method
	 *
	 * @return  object
	 */
	public function keygen()
	{
		// Init variable
		$obj    = new stdclass;
		$umodel = new JUser;
		$user   = $umodel->getInstance();

		$app      = JFactory::getApplication();
		$username = $app->input->get('username', 0, 'STRING');

		$user = JFactory::getUser();
		$id   = JUserHelper::getUserId($username);

		if ($id == null)
		{
			$model = FD::model('Users');
			$id    = $model->getUserId('email', $username);
		}

		$kmodel = new ApiModelKey;
		$model  = new ApiModelKeys;
		$key    = null;

		// Get login user hash
		// $kmodel->setState('user_id', $user->id);

		// $kmodel->setState('user_id', $id);
		// $log_hash = $kmodel->getList();
		$model->setState('user_id', $id);
		$log_hash = $model->getItems();

		$log_hash = (!empty($log_hash)) ? $log_hash[count($log_hash) - count($log_hash)] : $log_hash;

		if (!empty($log_hash))
		{
			$key = $log_hash->hash;
		}
		elseif ($key == null || empty($key))
		{
			// Create new key for user
			$data = array (
				'userid' => $user->id,
				'domain' => '' ,
				'state'  => 1,
				'id'     => '',
				'task'   => 'save',
				'c'      => 'key',
				'ret'    => 'index.php?option=com_api&view=keys',
				'option' => 'com_api',
				JSession::getFormToken() => 1
			);

			$result = $kmodel->save($data);

			// $key  = $result->hash;

			if (!$result)
			{
				return false;
			}

			// Load api key table
			JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_api/tables');
			$table = JTable::getInstance('Key', 'ApiTable');
			$table->load(array('userid' => $user->id));
			$key = $table->hash;

			// Add new key in easysocial table
			$easyblog = JPATH_ROOT . '/administrator/components/com_easyblog/easyblog.php';

			if (JFile::exists($easyblog) && JComponentHelper::isEnabled('com_easysocial', true))
			{
				$this->updateEauth($user, $key);
			}
		}

		if (!empty($key))
		{
			$obj->auth = $key;
			$obj->code = '200';

			// $obj->id = $user->id;

			$obj->id = $id;

			// Generate claim for jwt
			$data = [
				"id" => trim($id),
				/*"iat" => '',
				"exp" => '',
				"aud" => '',
				"sub" => ''"*/
			];

			// Using HS256 algo to generate JWT
			$jwt = JWT::encode($data, trim($key), 'HS256');

			if (isset($jwt) && $jwt != '')
			{
				$obj->jwt = $jwt;
			}
			else
			{
				$obj->jwt = false;
			}
		}
		else
		{
			$obj->code = 403;
			$obj->message = JText::_('PLG_API_USERS_BAD_REQUEST_MESSAGE');
		}

		return ($obj);
	}

	/**
	 * Method to update Easyblog auth keys
	 *
	 * @param   mixed  $user  User object
	 * @param   mixed  $key   Key
	 *
	 * @return  integer
	 *
	 * @since   1.6
	 */
	public function updateEauth ($user = null, $key = null)
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_easysocial/includes/foundry.php';

		$model = FD::model('Users');
		$id    = $model->getUserId('username', $user->username);
		$user  = FD::user($id);
		$user->alias = $user->username;
		$user->auth  = $key;
		$user->store();

		return $id;
	}
}
