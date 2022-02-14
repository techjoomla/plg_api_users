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

/**
 * User Api.
 * Creates a new user, updates an existing user and gets data of an user
 *
 * @package  Com.Api
 *
 * @since    2.0
 */
class UsersApiResourceUser extends ApiResource
{
	/**
	 * Function to create and edit user record.
	 *
	 * @return object|void User details on success. raise error on failure.
	 *
	 * @since   2.0
	 */
	public function post()
	{
		$app = JFactory::getApplication();
		$userIdentifier = $app->input->get('id', 0, 'String');
		$formData = $app->input->getArray();
		$params = JComponentHelper::getParams("com_users");
		$response = new stdClass;

		$xidentifier = $app->input->server->get('HTTP_X_IDENTIFIER');
		$fidentifier = $app->input->server->get('HTTP_FORCECREATE');

		if ($formData['username'] == '' || $formData['name'] == '' || $formData['email'] == '')
		{
			ApiError::raiseError(400, JText::_('PLG_API_USERS_REQUIRED_DATA_EMPTY_MESSAGE'));

			return;
		}

		// Get current logged in user.
		$my = JFactory::getUser();

		// Check if $userIdentifier is not set
		if (empty($userIdentifier))
		{
			if ($formData['password'] == '')
			{
				ApiError::raiseError(400, JText::_('PLG_API_USERS_REQUIRED_DATA_EMPTY_MESSAGE'));

				return;
			}

			// Set default group if nothing is passed for group.
			if (empty($formData['groups']))
			{
				$formData['groups'] = array($params->get("new_usertype", 2));
			}

			// Get a blank user object
			$user = new JUser;

			// Create new user.
			$response = $this->storeUser($user, $formData, 1);
			$this->plugin->setResponse($response);

			return;
		}
		else
		{
			// Get a user object
			$user = $this->retriveUser($xidentifier, $userIdentifier);
			$passedUserGroups = array();

			// If user is already present then update it according to access.
			if (!empty($user->id))
			{
				$iAmSuperAdmin	= $my->authorise('core.admin');

				// Check if regular user is tring to update himself.
				if ($my->id == $user->id || $iAmSuperAdmin)
				{
					// If present then update or else dont include.
					if (!empty($formData['password']))
					{
						$formData['password2'] = $formData['password'];
					}

					// Add newly added groups and keep the old one as it is.
					if (!empty($formData['groups']))
					{
						$passedUserGroups['groups'] = array_unique(array_merge($user->groups, $formData['groups']));
					}

					$response = $this->storeUser($user, $passedUserGroups);
					$this->plugin->setResponse($response);

					return;
				}
				else
				{
					ApiError::raiseError(400, JText::_('JERROR_ALERTNOAUTHOR'));

					return;
				}
			}
			else
			{
				if ($fidentifier)
				{
					$user = new JUser;

					if ($formData['password'] == '')
					{
						ApiError::raiseError(400, JText::_('PLG_API_USERS_REQUIRED_DATA_EMPTY_MESSAGE'));

						return;
					}

					// Set default group if nothing is passed for group.
					if (empty($formData['groups']))
					{
						$formData['groups'] = array($params->get("new_usertype", 2));
					}

					// Create new user.
					$response = $this->storeUser($user, $formData, 1);
					$this->plugin->setResponse($response);

					return;
				}
				else
				{
					ApiError::raiseError(400, JText::_('PLG_API_USERS_USER_ABSENT_MESSAGE'));

					return;
				}
			}
		}
	}

	/**
	 * Function get for user record.
	 *
	 * @return object|void User details on success otherwise raise error
	 *
	 * @since   2.0
	 */
	public function get()
	{
		$input = JFactory::getApplication()->input;
		$id = $input->get('id', 0, 'int');
		$xidentifier	= $input->server->get('HTTP_X_IDENTIFIER', '', 'String');

		/*
		 * If we have an id try to fetch the user
		 * @TODO write user field mapping logic here
		 */
		if ($id)
		{
			// Get a user object
			$user = $this->retriveUser($xidentifier, $id);

			if (! $user->id)
			{
				ApiError::raiseError(400, JText::_('PLG_API_USERS_USER_NOT_FOUND_MESSAGE'));

				return;
			}

			$this->plugin->setResponse($user);
		}
		else
		{
			$user = JFactory::getUser();

			if ($user->guest)
			{
				ApiError::raiseError(400, JText::_('JERROR_ALERTNOAUTHOR'));
			}

			$this->plugin->setResponse($user);
		}
	}

	/**
	 * Function to return userid if a user exists depending on email
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  integer  The user id or 0 if not found.
	 *
	 * @since   2.0
	 */
	private function getUserId($email)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = ' . $db->quote($email));
		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}

	/**
	 * Funtion for bind and save data and return response.
	 *
	 * @param   Object   $user      The user object.
	 * @param   Array    $formData  Array of user data to be added or updated.
	 * @param   Boolean  $isNew     Flag to differentiate the update of create action.
	 *
	 * @return  object|void  $response  the response object created on after user saving. void and raise error
	 *
	 * @since   2.0
	 */
	private function storeUser($user, $formData, $isNew = 0)
	{
		$response = new stdClass;

		if (!$user->bind($formData))
		{
			ApiError::raiseError(400, $user->getError());

			return;
		}

		if (!$user->save())
		{
			ApiError::raiseError(400, $user->getError());

			return;
		}

		$response->id = $user->id;

		if ($isNew)
		{
			$response->message = JText::_('PLG_API_USERS_ACCOUNT_CREATED_SUCCESSFULLY_MESSAGE');
		}
		else
		{
			$response->message = JText::_('PLG_API_USERS_ACCOUNT_UPDATED_SUCCESSFULLY_MESSAGE');
		}

		return $response;
	}

	/**
	 * Function delete is used to delete the respective user record.
	 *
	 * @return void
	 *
	 * @since   2.0
	 */
	public function delete()
	{
		$app = JFactory::getApplication();
		$userIdentifier = $app->input->get('id', 0, 'STRING');
		$xidentifier = $app->input->server->get('HTTP_X_IDENTIFIER', '', 'String');

		$loggedUser = JFactory::getUser();

		// Check if I am a Super Admin
		$iAmSuperAdmin = $loggedUser->authorise('core.admin');

		$userToDelete = $this->retriveUser($xidentifier, $userIdentifier);

		if (!$userToDelete->id)
		{
			ApiError::raiseError(400, JText::_('PLG_API_USERS_USER_NOT_FOUND_MESSAGE'));

			return;
		}

		if ($loggedUser->id == $userToDelete->id)
		{
			ApiError::raiseError(400, JText::_('COM_USERS_USERS_ERROR_CANNOT_DELETE_SELF'));

			return;
		}

		// Access checks.
		$allow = $loggedUser->authorise('core.delete', 'com_users');

		// Don't allow non-super-admin to delete a super admin
		$allow = (!$iAmSuperAdmin && JAccess::check($userToDelete->id, 'core.admin')) ? false : $allow;

		if ($allow)
		{
			if (!$userToDelete->delete())
			{
				ApiError::raiseError(400, $userToDelete->getError());

				return;
			}
		}
		else
		{
			ApiError::raiseError(403, JText::_('JERROR_CORE_DELETE_NOT_PERMITTED'));

			return;
		}

		$response = new stdClass;
		$response->message = JText::_('PLG_API_USERS_USER_DELETE_MESSAGE');
		$this->plugin->setResponse($response);

		return;
	}

	/**
	 * Function retriveUser for get user details depending upon the identifier.
	 *
	 * @param   string  $xidentifier     Flag to differentiate the column value.
	 *
	 * @param   string  $userIdentifier  username
	 *
	 * @return  object  $user  Juser object if user exist otherwise std class.
	 *
	 * @since   2.0
	 */
	private function retriveUser($xidentifier, $userIdentifier)
	{
		$user = new stdClass;

		switch ($xidentifier)
		{
			case 'username':
				$userId = JUserHelper::getUserId($userIdentifier);

				if (!empty($userId))
				{
					$user = JFactory::getUser($userId);
				}
				break;

			case 'email':
				$userId = $this->getUserId($userIdentifier);

				if (!empty($userId))
				{
					$user = JFactory::getUser($userId);
				}
			break;

			default:
				$user = JFactory::getUser($userIdentifier);
				break;
		}

		return $user;
	}
}
