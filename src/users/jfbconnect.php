<?php
/**
 * @package     API
 * @subpackage  plg_api_users
 *
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (C) 2009 - 2019 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

/**
 * @package         JFBConnect
 * @copyright (c)   2009-2019 by SourceCoast - All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @version         Release v8.1.0
 * @build-date      2019/04/03
 */

// No direct access.
defined('_JEXEC') or die('Restricted access');

require_once JPATH_SITE . '/components/com_api/vendors/php-jwt/src/JWT.php';

use Firebase\JWT\JWT;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

JModelLegacy::addIncludePath(JPATH_SITE . 'components/com_api/models');
require_once JPATH_ADMINISTRATOR . '/components/com_api/models/key.php';
require_once JPATH_ADMINISTRATOR . '/components/com_api/models/keys.php';

/**
 * UsersApiResourceJfbconnect class
 *
 * @since  2.0.1
 */
class UsersApiResourceJfbconnect extends ApiResource
{
	public $provider = '';

	public $accessToken = '';

	/**
	 * GET method for this resource
	 *
	 * @return mixed
	 *
	 * @since  2.0.1
	 */
	public function get()
	{
		// Validate if JFB is installed
		$this->validateInstall();

		// $this->plugin->setResponse(JText::_('PLG_API_USERS_UNSUPPORTED_METHOD'));

		ApiError::raiseError(405, JText::_('PLG_API_USERS_UNSUPPORTED_METHOD'));
	}

	/**
	 * GET method for this resource
	 *
	 * @return mixed
	 *
	 * @since  2.0.1
	 */
	public function post()
	{
		// Validate if JFB is installed
		$this->validateInstall();

		// Init vars
		$app          = JFactory::getApplication();
		$providerName = $app->input->json->get('provider', '', 'STRING');
		$accessToken  = $app->input->json->get('access_token', '', 'STRING');

		if (empty($providerName))
		{
			ApiError::raiseError(400, JText::_('PLG_API_USERS_JFBCONNECT_MISSING_PROVIDER'));
		}

		if (empty($accessToken))
		{
			ApiError::raiseError(400, JText::_('PLG_API_USERS_JFBCONNECT_MISSING_ACCESS_TOKEN'));
		}

		// Get provider object
		$provider = $this->jfbGetProvider($providerName);

		// Based on: JFB code from components/com_jfbconnect/controllers/authenticate.php callback()

		/*try
		{
			$provider->client->authenticate();
		}
		catch (Exception $e)
		{
			ApiError::raiseError(400, JText::_('api auth error'));
		}*/

		/*echo '<br/> provider class is: ' . get_class($provider);
		$methods = get_class_methods($provider);
		foreach($methods as $method) { echo $method; echo "<br>";}
		*/

		// Look for if JFB user mapping exists, get jUserId
		$jUserId = $this->jfbGetJoomlaUserId($provider, $accessToken);

		// If user not found, try registering new user
		if (!$jUserId)
		{
			$jUserId = $this->jfbRegisterUser($provider, $accessToken);
		}

		$this->plugin->setResponse($this->generateApiToken($jUserId));
	}

	/**
	 * Validates if JFBConnect is installed and enabled
	 *
	 * @return  boolean
	 *
	 * @since  v2.0.1
	 */
	private function validateInstall()
	{
		jimport('joomla.filesystem.file');

		// Check if JFB is installed and enabled
		if (JFile::exists(JPATH_ROOT . '/components/com_jfbconnect/jfbconnect.php')
			&& JComponentHelper::isEnabled('com_jfbconnect', true))
		{
			return true;
		}

		ApiError::raiseError(500, JText::_('PLG_API_USERS_JFBCONNECT_NOT_INSTALLED'));

		return false;
	}

	/**
	 * Returns JFBConnect provider class object
	 *
	 * @param   string  $providerName  Provider name eg - google / facebook
	 *
	 * @return  object
	 *
	 * @since  2.0.1
	 */
	private function jfbGetProvider($providerName)
	{
		// Based on: JFB code from components/com_jfbconnect/controllers/authenticate.php getProvider()
		if ($providerName)
		{
			$provider = JFBCFactory::provider($providerName);

			if (empty($provider->name))
			{
				ApiError::raiseError(500, JText::_('Invalid provider'));
			}

			return $provider;
		}

		return;
	}

	/**
	 * Returns Joomla user id from jfb user mapping
	 *
	 * @param   object  $provider     JFBCOnnect provider class object
	 *
	 * @param   string  $accessToken  Provider access token
	 *
	 * @return  int
	 *
	 * @since  2.0.1
	 */
	public function jfbGetJoomlaUserId($provider, $accessToken)
	{
		$jUserId = 0;

		if (strtolower($provider->name) == 'google')
		{
			// Based on: JFB code from components/com_jfbconnect/libraries/provider/google.php -> setupAuthentication()
			// Google client needs access token as array
			$accessToken = array('access_token' => $accessToken);
			$provider->client->setToken($accessToken);
		}
		elseif (strtolower($provider->name) == 'facebook')
		{
			// Based on: JFB code from administrator/assets/facebook-api/base_facebook.php -> setAccessToken()
			$provider->client->setAccessToken($accessToken);
		}

		// Based on: JFB code from components/com_jfbconnect/controllers/login.php login()
		$providerUserId = $provider->getProviderUserId();
		$userMapModel   = JFBCFactory::usermap();

		// Check if they have a Joomla user and log that user in. If not, create them one
		$jUserId = $userMapModel->getJoomlaUserId($providerUserId, strtolower($provider->name));

		return $jUserId;
	}

	/**
	 * Register new user using JFB
	 *
	 * @param   object  $provider  JFBCOnnect provider class object
	 *
	 * @return  int
	 *
	 * @since  2.0.1
	 */
	private function jfbRegisterUser($provider)
	{
		// Declare vars needed for JFB code to work
		BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_jfbconnect/models');
		$loginRegisterModel = JModelLegacy::getInstance('LoginRegister', 'JFBConnectModel');
		$userMapModel       = JFBCFactory::usermap();
		$app                = JFactory::getApplication();
		$providerUserId     = $provider->getProviderUserId();
		$jUserId            = 0;

		// START - Use JFB code
		// Based on: JFB code from components/com_jfbconnect/controllers/login.php login()

		$profile       = $provider->profile->fetchProfile($providerUserId, array('email'));
		$providerEmail = $profile->get('email', null);

		// Check if automatic email mapping is allowed, and see if that email is registered
		// AND the Facebook user doesn't already have a Joomla account
		if (!$provider->initialRegistration && JFBCFactory::config()->getSetting('facebook_auto_map_by_email'))
		{
			if ($providerEmail != null)
			{
				$jUserEmailId = $userMapModel->getJoomlaUserIdFromEmail($providerEmail);

				if (!empty($jUserEmailId))
				{
					// Found a user with the same email address
					// do final check to make sure there isn't a FB account already mapped to it
					$tempId = $userMapModel->getProviderUserId($jUserEmailId, strtolower($provider->name));

					if (!$tempId)
					{
						JFBConnectUtilities::clearJFBCNewMappingEnabled();

						if ($userMapModel->map($jUserEmailId, $providerUserId, strtolower($provider->name), $provider->client->getToken()))
						{
							JFBCFactory::log(JText::sprintf('COM_JFBCONNECT_MAP_USER_SUCCESS', $provider->name));

							// Update the temp jId so that we login below
							$jUserId = $jUserEmailId;
						}
						else
						{
							JFBCFactory::log(JText::sprintf('COM_JFBCONNECT_MAP_USER_FAIL', $provider->name));
						}
					}
				}
			}
		}

		/*
		 * check if user registration is turn off
		 * !allowUserRegistration and !social_registration => registration not allowed
		 * !allowUserRegistration and social_registration => registration allowed
		 * allowUserRegistration and !social_registration => registration not allowed
		 * JComponentHelper::getParams('com_users')->get('allowUserRegistration') check is not needed since
		 * we prioritized the JFBConnect social registration config
		*/

		if (JFBCFactory::config()->getSetting('social_registration') == 0 && !$jUserId)
		{
			JFBCFactory::log(JText::_('COM_JFBCONNECT_MSG_USER_REGISTRATION_DISABLED'), 'notice');

			// Commmented code below for com_api plugin
			// $app->redirect(JRoute::_('index.php?option=com_users&view=login', false));

			return false;
		}

		// Check if no mapping, and Automatic Registration is set. If so, auto-create the new user.
		if (!$jUserId && JFBCFactory::config()->getSetting('automatic_registration'))
		{
			// User is not in system, should create their account automatically
			if ($loginRegisterModel->autoCreateUser($providerUserId, $provider))
			{
				$jUserId = $userMapModel->getJoomlaUserId($providerUserId, strtolower($provider->name));
			}
		}

		// END - use JFB code

		return $jUserId;
	}

	/**
	 * Generate API token
	 *
	 * @param   INT  $userId  user id
	 *
	 * @return  mixed
	 *
	 * @since  2.0.1
	 */
	private function generateApiToken($userId)
	{
		// Validate
		$obj = new stdclass;

		if ($userId == null)
		{
			$obj->code    = 403;
			$obj->message = JText::_('PLG_API_USERS_USER_NOT_FOUND_MESSAGE');

			return $obj;
		}

		// Init vars
		$app       = JFactory::getApplication();
		$keyModel  = new ApiModelKey;
		$keysModel = new ApiModelKeys;
		$key       = null;

		// Get existing key for $userId user
		$keysModel->setState('user_id', $userId);
		$existingKey = $keysModel->getItems();
		$existingKey = (!empty($existingKey)) ? $existingKey[count($existingKey) - count($existingKey)] : $existingKey;

		if (!empty($existingKey))
		{
			$key = $existingKey->hash;
		}
		// If key not found, create new
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

			if (!$result)
			{
				return false;
			}

			// Load api key table
			JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_api/tables');
			$table = JTable::getInstance('Key', 'ApiTable');
			$table->load(array('userid' => $userId));
			$key = $table->hash;
		}

		if (!empty($key))
		{
			$obj->auth = $key;
			$obj->code = '200';

			// Set user details for response
			$obj->id       = $userId;
			$obj->name     = JFactory::getUser($userId)->name;
			$obj->username = JFactory::getUser($userId)->username;
			$obj->email    = JFactory::getUser($userId)->email;

			// Generate claim for jwt
			$data = [
				"id" => trim($userId)

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
}
