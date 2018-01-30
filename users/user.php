<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_trading
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access.
defined('_JEXEC') or die;

jimport('joomla.user.helper');

/**
 * User Api.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_api
 *
 * @since       1.0
 */
class UsersApiResourceUser extends ApiResource
{
	/**
	 * Function post for create user record.
	 *
	 * @return void
	 */
	public function post()
	{
		$app = JFactory::getApplication();
		
		$returnData = new stdClass;
		$returnData->result = new stdClass;
		
		$identifier = $app->input->server->get('HTTP_X_IDENTIFIER');

		$data = array();

		if (!$identifier || $identifier == 'id')
		{
			$data['id'] = $app->input->getInt('id', 0, 'INT');
		}
		else
		{
			if (!in_array($identifier, array('id', 'email', 'username')))
			{
				ApiError::raiseError("400", JText::_('PLG_API_USERS_INCORRECT_IDENTIFIER'), 'APIValidationException');

				return $returnData;
			}
			
			$temp =  $app->input->getString('id');

			if ($identifier == 'username')
			{
				$data['id'] =  JUserHelper::getUserId($temp);
			}
			elseif ($identifier == 'email')
			{
				$data['id'] =  $this->getUserId($temp);
			}
		}
		
		$groups = array(2);

		$user = new JUser($data['id']);

		if ($user->id)
		{
			$groups = $app->input->get('groups', array(), 'ARRAY');

			if (empty($groups))
			{
				$groups = $user->groups;
			}
		}
		/*else
		{
			ApiError::raiseError("400", JText::_('PLG_API_USERS_USER_DOES_NOT_EXISTS'), 'APIValidationException');

			return $returnData;
		}*/
		

		$data['username'] = $app->input->getString('username', $user->get('username'));
		$data['name'] = $app->input->getString('name', $user->get('name'));
		$data['password'] = $app->input->getString('password', '');
		$data['email'] = $app->input->getString('email', $user->get('email'));
		$data['groups'] = $app->input->get('groups', $groups, 'ARRAY');
		$data['block'] = $app->input->getInt('block',  $user->get('block'));
		$data['fields'] = $app->input->get('fields', '', 'ARRAY');

		$newUser = false;

		if ($data['id'] == '' && ($data['name'] == '' || $data['email'] == ''))
		{
			ApiError::raiseError("400", JText::_('PLG_API_USERS_REQUIRED_DATA_EMPTY_MESSAGE'), 'APIValidationException');

			return $returnData;
		}


		if (!$data['username'])
		{
			$data['username'] = $data['email'];
		}

		// Check new or old user
		if (!$user->id)
		{
			$newUser = true;
		}
		//echo $newUser;
		//print_r($user);
		if ($user->bind($data))
		{
			// If $newUser is true it will update the user else create new user
			//print_r($user);die;

			if ($user->save())
			{
				if ($data['fields'])
				{
					$libraryObject = $this->getSocialLibraryObject();
					$libraryObject->addUserFields($data['fields'], $user->id);
				}

				unset($data['password']);
				unset($data['password2']);

				$data['id'] = $user->id;
				$returnData->result = $data;
				$this->plugin->setResponse($returnData);

				return $returnData;
			}
		}

		ApiError::raiseError("400", $user->getError());

		return $returnData;
	}

	/**
	 * Returns userid if a user exists
	 *
	 * @param   string  $email  The email to search on.
	 *
	 * @return  integer  The user id or 0 if not found.
	 *
	 * @since   11.1
	 */
	private function getUserId($email)
	{
		// Initialise some variables
		$db = \JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = ' . $db->quote($email));
		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}
	/**
	 * Get social library object depending on the integration set.
	 *
	 * @return  Soical library object
	 *
	 * @since 1.0.0
	 */
	public function getSocialLibraryObject()
	{
		$plugin = JPluginHelper::getPlugin('api', 'users');
		$params = new JRegistry($plugin->params);
		$socialIntegration = $params->get('social_integration', 'joomla');

		if ($socialIntegration == 'joomla')
		{
			jimport('techjoomla.jsocial.joomla');
			$SocialLibraryObject = new JSocialJoomla;
		}
		elseif ($socialIntegration == 'jomsocial')
		{
			jimport('techjoomla.jsocial.jomsocial');
			$SocialLibraryObject = new JSocialJomSocial;
		}
		elseif ($socialIntegration == 'easysocial')
		{
			jimport('techjoomla.jsocial.easysocial');
			$SocialLibraryObject = new JSocialEasySocial;
		}

		return $SocialLibraryObject;
	}
}
