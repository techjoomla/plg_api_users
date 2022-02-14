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
class UsersApiResourceImpersonateLogin extends ApiResource
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
	 * @return  object|boolean
	 */
	public function keygen()
	{
		// Init variables
		$obj              = new stdclass;
		$jinput           = JFactory::getApplication()->input;
		$xImpersonate     = $jinput->server->get('X-Impersonate', '', 'STRING');
		$httpXImpersonate = $jinput->server->get('HTTP_X_IMPERSONATE', '', 'STRING');

		if (!empty($xImpersonate))
		{
			$userToImpersonate = $xImpersonate;
		}
		elseif (!empty($httpXImpersonate))
		{
			$userToImpersonate = $httpXImpersonate;
		}

		if (preg_match('/email:(\S+)/', $userToImpersonate, $matches))
		{
			$id = $matches[1];
		}
		elseif (preg_match('/username:(\S+)/', $userToImpersonate, $matches))
		{
			$id = $matches[1];
		}
		elseif (is_numeric($userToImpersonate))
		{
			$id = $userToImpersonate;
		}
		else
		{
			ApiError::raiseError("403", JText::_('PLG_API_USERS_BAD_REQUEST_MESSAGE'));

			return false;
		}

		$userId = JUserHelper::getUserId($id);

		if ($userId == null)
		{
			$model  = FD::model('Users');
			$userId = $model->getUserId('email', $userId);
		}

		if (!$userId)
		{
			ApiError::raiseError("403", JText::_('PLG_API_USERS_BAD_REQUEST_MESSAGE'));

			return;
		}

		// Init vars
		$keyModel  = new ApiModelKey;
		$keysModel = new ApiModelKeys;
		$key       = null;

		// Get login user hash
		// $keyModel->setState('user_id', $user->id);

		// $keyModel->setState('user_id', $id);
		// $log_hash = $keyModel->getList();
		$keysModel->setState('user_id', $userId);
		$log_hash = $keysModel->getItems();

		$log_hash = (!empty($log_hash)) ? $log_hash[count($log_hash) - count($log_hash)] : $log_hash;

		if (!empty($log_hash))
		{
			$key = $log_hash->hash;
		}
		elseif ($key == null || empty($key))
		{
			// Create new key for user
			$data = array (
				'userid' => $userId,
				'domain' => '' ,
				'state'  => 1,
				'id'     => '',
				'task'   => 'save',
				'c'      => 'key',
				'ret'    => 'index.php?option=com_api&view=keys',
				'option' => 'com_api',
				JSession::getFormToken() => 1
			);

			$result = $keyModel->save($data);

			// $key  = $result->hash;

			if (!$result)
			{
				return false;
			}

			// Load api key table
			JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_api/tables');
			$table = JTable::getInstance('Key', 'ApiTable');
			$table->load(array('userid' => $userId));
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
			// $obj->id = $id;

			// Set user details for response
			$obj->id       = $userId;
			$obj->name     = JFactory::getUser($userId)->name;
			$obj->username = JFactory::getUser($userId)->username;
			$obj->email    = JFactory::getUser($userId)->email;

			// Generate claim for jwt
			$data = [
				"id" => trim($userId),

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

		$keysModel = FD::model('Users');
		$id        = $keysModel->getUserId('username', $user->username);
		$user      = FD::user($id);

		$user->alias = $user->username;
		$user->auth  = $key;
		$user->store();

		return $id;
	}
}
