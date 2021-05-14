<?php
/**
 * Class for storing data on user signup
 *
 * PHP version 7
 *
 * @category DEPLOY_VERSION
 *
 * @package Users
 *
 * @author Tushar Verma <tushar_v@techjoomla.com>
 *
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @link site www.osianama.com
 */

// No direct access.
defined('_JEXEC') or die('Restricted access');

require_once JPATH_SITE . '/components/com_api/vendors/php-jwt/src/JWT.php';

use Firebase\JWT\JWT;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

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
 * Class for storing data on user signup
 *
 * PHP version 7
 *
 * @category DEPLOY_VERSION
 *
 * @package Users
 *
 * @author Tushar Verma <tushar_v@techjoomla.com>
 *
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @link site www.osianama.com
 */
class UsersApiResourceRegister extends ApiResource
{
	public $core_fields_data = [
	[
	'id' => 'name',
	'name' => 'name',
	'title' => 'Full Name',
	'ordering' => 0,
	'fieldparams' => [
	"filter" => "",
	"maxlength" => ""
	],
	'type' => 'text',
	'default_value' => '',
	'label' => 'Full Name',
	'description' => '',
	'required' => true,
	'value' => '',
	'group_title' => 'core'
	],[
	'id' => 'email',
	'name' => 'email',
	'title' => 'E-mail',
	'ordering' => 0,
	'fieldparams' => [
	"filter" => "",
	"maxlength" => ""
	],
	'type' => 'email',
	'default_value' => '',
	'label' => 'E-mail',
	'description' => '',
	'required' => true,
	'value' => '',
	'group_title' => 'core',
	'pattern' => "^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,4}$"
	],
	['id' => 'username',
	'name' => 'username',
	'title' => 'Username',
	'ordering' => 0,
	'fieldparams' => [
	"filter" => "",
	"maxlength" => ""
	],
	'type' => 'text',
	'default_value' => '',
	'label' => 'Username',
	'description' => '',
	'required' => true,
	'value' => '',
	'group_title' => 'core'
	],
	[
	'id' => 'password',
	'name' => 'password',
	'title' => 'Password',
	'ordering' => 0,
	'fieldparams' => [
	"filter" => "",
	"maxlength" => ""
	],
	'type' => 'password',
	'default_value' => '',
	'label' => 'Password',
	'description' => '',
	'required' => true,
	'value' => '',
	'group_title' => 'core'
	],[
	'id' => 'confirm_password',
	'name' => 'confirm_password',
	'title' => 'Confirm password',
	'ordering' => 0,
	'fieldparams' => [
	"filter" => "",
	"maxlength" => ""
	],
	'type' => 'password',
	'default_value' => '',
	'label' => 'Confirm password',
	'description' => '',
	'required' => true,
	'value' => '',
	'group_title' => 'core'
	]];

	public $shouldSendMail = 0;

		/**
	 * Constructor
	 *
	 * @param   array  $config  An array
	 *
	 * @since   1.0
	 */

	public function __construct($config = array())
	{
		parent::__construct($config);

		$plugin = JPluginHelper::getPlugin('api', 'users');
		$this->params = new Registry($plugin->params);

		JModelLegacy::addIncludePath(
			JPATH_ADMINISTRATOR . '/components/com_fields/models',
			'FieldsModel'
		);
		$this->fieldModel = JModelLegacy::getInstance(
			'Field', 'FieldsModel',
			array('ignore_request' => true)
		);
	}

	/**
	 * Get method
	 *
	 * @return object
	 */
	public function get()
	{
		$input = Factory::getApplication()->input;
		$user = Factory::getUser();
		$notEditFields = false;

		$lockfields = $this->params->get('locking_fields') ? $this->params->get('locking_fields') : '';
		$corelockingfields = $this->params->get('core_locking_fields') ? $this->params->get('core_locking_fields') : '';
		$helperClass = $this->params->get('helper_class') ? trim($this->params->get('helper_class')) : '';
		$locking_hrs = $this->params->get('locking_hrs') ? (int) $this->params->get('locking_hrs') : '';

		if ($user->id != 0 && $locking_hrs && $helperClass)
		{
			$notEditFields = $helperClass($locking_hrs);
		}

		JLoader::register(
			'FieldsHelper',
			JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php'
		);

		$customFields = FieldsHelper::getFields(
			'com_users.user', $user,
			true
		);

		$allFields = [];

		foreach ($this->core_fields_data as $key => $fields)
		{
			if ($fields['name'] !== 'password' && $fields['name'] !== 'confirm_password')
			{
				$fields['value'] = $user->{$fields['name']};
			}
			else
			{
				if ($user->id !== 0)
				{
					$fields['required'] = false;
				}
			}

			if ($notEditFields && in_array($fields['name'], $corelockingfields))
			{
				$fields['disabled'] = true;
			}

			$allFields[$fields['group_title']][] = $fields;
		}

		if ($notEditFields)
		{
			$allFields['lockFields'] = $notEditFields;
		}

		foreach ($customFields as $key => $fields)
		{
			if ($fields->ordering > 0)
			{
				$data = [];
				$data['id'] = $fields->id;
				$data['title'] = $fields->title;
				$data['name'] = $fields->name;
				$data['ordering'] = (int) $fields->ordering;
				$data['fieldparams'] = $fields->fieldparams;
				$data['type'] = $fields->type;
				$data['default_value'] = $fields->default_value;
				$data['label'] = $fields->label;
				$data['description'] = $fields->description;
				$data['required'] = $fields->required == 1 ? true : false;
				$data['value'] = $user->id !== 0 ? $fields->value : '';
				$data['group_title'] = $fields->group_title;

				if ($notEditFields && in_array($fields->id, $lockfields))
				{
					$data['disabled'] = true;
				}

				$group = isset($fields->group_title) ?
				strtolower(str_replace(" ", "_", $fields->group_title)) :
				'ungrouped_fields';
				unset($customFields[$key]);
				$allFields[$group][] = $data;
			}
		}

		$this->plugin->setResponse($allFields);
	}

	/**
	 * Post method
	 *
	 * @return object
	 */
	public function post()
	{
		$app = JFactory::getApplication();
		$data = $app->input->getArray();
		$notEditFields = false;

		$userObject = JFactory::getUser();
		$lockfields = $this->params->get('locking_fields') ? $this->params->get('locking_fields') : '';
		$corelockingfields = $this->params->get('core_locking_fields') ? $this->params->get('core_locking_fields') : '';
		$helperClass = $this->params->get('helper_class') ? trim($this->params->get('helper_class')) : '';
		$locking_hrs = $this->params->get('locking_hrs') ? (int) $this->params->get('locking_hrs') : '';

		if ($userObject->id != 0 && $locking_hrs && $helperClass)
		{
			$notEditFields = $helperClass($locking_hrs);
		}

		$params = JComponentHelper::getParams("com_users");
		$response = new stdClass;

		$xidentifier = $app->input->server->get('HTTP_IDENTIFIER');
		$fidentifier = $app->input->server->get('HTTP_FORCECREATE');

		$formData = [];
		$customData = [];

		$updatedData = json_decode($data['updateddata']);

		foreach ($updatedData as $group => $fields)
		{
			foreach ($fields as $key => $field)
			{
				if ($fields->required == 1 && ($field->value == '' || $field->value == null || !isset($field->value)))
				{
					ApiError::raiseError(
						400,
						JText::_('PLG_API_USERS_REQUIRED_DATA_EMPTY_MESSAGE')
					);

					return;
				}
				elseif ($field->name == 'name' || $field->name == 'username' || $field->name == 'email' || $field->name == 'password')
				{
					if ($userObject->id != 0 && $notEditFields && in_array($field->name, $corelockingfields))
					{
						$formData[$field->name] = $userObject->{$field->name};
					}
					else
					{
						$formData[$field->name] = $field->value;
					}
				}
				else
				{
					if ($field->name != 'confirm_password')
					{
						if ($userObject->id != 0 && $notEditFields && in_array($field->id, $lockfields))
						{
							$formData['com_fields'][$field->name] = $this->fieldModel->getFieldValue($field->id, $userObject->id);
						}
						else
						{
							$formData['com_fields'][$field->name] = $field->value;
						}
					}
				}

				// Every Id need activation not taking input about activation from frontend.

				$formData['activation'] = $userObject->id != 0  ? $userObject->activation : $this->_generateRandomString();
				$formData['block'] = $userObject->id != 0 ? $userObject->block : 1;
				$formData['groups'] = $userObject->id != 0 ? $userObject->groups : array($params->get("new_usertype", 2));
			}
		}

		if ($formData['password'] == '' && $userObject->id == 0)
		{
			ApiError::raiseError(
				400,
				JText::_('PLG_API_USERS_REQUIRED_DATA_EMPTY_MESSAGE')
			);

			return;
		}

		// Get a blank user object
		$user = new Joomla\CMS\User\User;

		// Create new user.
		if ($userObject->id != 0)
		{
			$formData['id'] = $userObject->id;
			$response = $this->_storeUser($user, $formData, false);
		}
		else
		{
			$response = $this->_storeUser($user, $formData, true);
		}

		if ($this->shouldSendMail)
		{
			$mail_sent = $this->sendRegisterEmail($formData);
		}

		$this->plugin->setResponse($response);

		return;
	}

	/**
	 * Funtion for bind and save data and return response.
	 *
	 * @param Object  $user     The user object.
	 * @param Array   $formData Array of user data to be added or updated.
	 * @param Boolean $isNew    Flag to differentiate the update of create action.
	 *
	 * @return object|void  $response
	 *
	 * @since 2.0
	 */
	private function _storeUser($user, $formData, $isNew = false)
	{
		$response = new stdClass;

		if (!$user->bind($formData))
		{
			ApiError::raiseError(400, $user->getError());

			return;
		}

		if (!$user->save(!$isNew))
		{
			ApiError::raiseError(400, $user->getError());

			return;
		}

		$response->id = $user->id;

		$uParams = JComponentHelper::getParams('com_users');

		if ($isNew)
		{
			if ($uParams->get('useractivation') == 0 )
			{
				$response->message = JText::_('PLG_API_USERS_ACCOUNT_CREATED_SUCCESSFULLY_MESSAGE');
			}
			else
			{
				$this->shouldSendMail = 1;
				$response->message = JText::_('PLG_API_USERS_ACCOUNT_CREATED_SUCCESSFULLY_MESSAGE_WITH_ACTIVATION_LINK');
			}
		}
		else
		{
			$response
			->message = JText::_('PLG_API_USERS_ACCOUNT_UPDATED_SUCCESSFULLY_MESSAGE');
		}

		return $response;
	}

	/**
	 * Send registration mail
	 *
	 * @param array $base_dt this contains data about custom fields.
	 *
	 * @deprecated 2.0 This will be move in the Easysocial API
	 *
	 * @return object
	 */
	public function sendRegisterEmail($base_dt)
	{
		$data = array();
		$config = JFactory::getConfig();
		$params = JComponentHelper::getParams('com_users');
		$sendpassword = $params->get('sendpassword', 0);

		$lang = JFactory::getLanguage();
		$lang->load('com_users', JPATH_SITE, '', true);

		$data['fromname'] = $config->get('fromname');
		$data['mailfrom'] = $config->get('mailfrom');
		$data['sitename'] = $config->get('sitename');
		$data['siteurl'] = JUri::root();
		$data['activation'] = $base_dt['activation'];

		// Handle account activation/confirmation emails.

		if ($data['activation'] === 0)
		{
			// Set the link to confirm the user email.
			$uri = JUri::getInstance();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('/index.php?option=com_api&app=users&resource=activateprofile&token=' . $data['activation'], false);

			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$base_dt['name'],
				$data['sitename']
			);

			if ($sendpassword)
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY',
					$base_dt['name'],
					$data['sitename'],
					$base_dt['app'],
					$base_dt['username'],
					$base_dt['password']
				);
			}
			else
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY_NOPW',
					$base_dt['name'],
					$data['sitename'],
					$base_dt['app'],
					$base_dt['username']
				);
			}
		}
		else
		{
			// Set the link to activate the user account.
			$uri = JUri::getInstance();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('/index.php?option=com_api&app=users&resource=activateprofile&token=' . $data['activation'], false);

			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$base_dt['name'],
				$data['sitename']
			);

			if ($sendpassword)
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY',
					$base_dt['name'],
					$data['sitename'],
					$data['activate'],
					$base_dt['app'],
					$base_dt['username'],
					$base_dt['password']
				);
			}
			else
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY_NOPW',
					$base_dt['name'],
					$data['sitename'],
					$base_dt['app'],
					$base_dt['username']
				);
			}
		}
		// Send the registration email.
		$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $base_dt['email'], $emailSubject, $emailBody);

		return $return;
	}

	/**
	 * Generate activation string
	 *
	 * @param  integer  $length this contains data about custom fields.
	 *
	 * @deprecated 2.0 This will be move in the Easysocial API
	 *
	 * @return object
	 */
	private function _generateRandomString($length = 32)
	{
		return substr(
			str_shuffle(
				str_repeat(
					$x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x))
				)
			), 1, $length
		);
	}
}
