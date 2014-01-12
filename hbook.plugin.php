<?php

namespace Habari;

if ( !defined( 'HABARI_PATH' ) ) {
	die( 'No direct access' );
}

require_once('facebook-sdk/src/facebook.php');
use Facebook;

class Hbook extends Plugin
{
	private $facebook;

	public function action_init()
	{
		if ($this->is_ready()) {
			$this->facebook = new Facebook(array(
				'appId' => Options::get('hbook__fb_app_id'),
				'secret' => Options::get('hbook__fb_app_secret'),
				'cookie' => true,
			));

			$this->add_rule('"auth"/"facebook"/"add"', 'add_facebook_user');

			$this->check_user();

			Stack::add( 'admin_header_javascript', URL::get_from_filesystem(__FILE__) . '/admin.js', 'hbook-admin', array('jquery') );
		}
	}

	public function action_plugin_act_add_facebook_user()
	{
		if (!User::identify()->can( 'manage_users' )) {
			echo 'Access denied';
			return;
		}

		$fbid = Controller::get_var('user');

		$info = $this->facebook->api('/' . $fbid);
		$fbid = $info['id']; // normalize ID

		// make sure a user like that doesn't already exist
		$users = Users::get_by_info('facebook_id', $fbid);

		if (isset($users[0])) {
			$user = $users[0];
		} else {
			if (isset($info['username'])) {
				$username = $info['username'];
			} else {
				$username = $fbid;
			}

			$email = $fbid . '@facebook.com'; // this should work

			$password = UUID::get();

			$user = new User( array(
				'username' => $username,
				'email' => $email,
				'password' => Utils::crypt($password)
			));

			if (isset($info['name'])) {
				$user->info->displayname = $info['name'];
			}

			$user->info->facebook_id = $fbid;

			$user->info->imageurl = 'http://graph.facebook.com/' . $fbid . '/picture';

			$user->insert();
		}

		// Assign them to the correct group, if any
		if (Controller::get_var('group')) {
			$group = UserGroup::get_by_id( Controller::get_var('group') );

			$group->add( $user );

			Utils::redirect( URL::get( 'admin', 'page=group' ) . '?id=' . $group->id );

		} else {
			// redirect somewhere
			Utils::redirect( URL::get( 'admin', 'page=group' ) );
		}
	}

	public function check_user()
	{
		// No sense in trying if we're already logged in
		if (User::identify()->loggedin) {
			return;
		}

		if ($this->facebook->getUser()) {

			$users = Users::get_by_info('facebook_id', $this->facebook->getUser());

			if (isset($users[0])) {
				$user = $users[0];

				// Remember them
				$user->remember();

				// Store their token
				$this->facebook->setExtendedAccessToken();
				$user->info->hbook__facebook_token = $this->facebook->getAccessToken();
				$user->update();
			}
		}
	}

	private function is_ready()
	{
		if (Options::get('hbook__fb_app_id') && Options::get('hbook__fb_app_secret')) {
			return true;
		} else {
			return false;
		}
	}

	public function action_theme_loginform_controls()
	{
		Stack::add( 'admin_footer_javascript', URL::get_from_filesystem(__FILE__) . '/facebook.js', 'facebook', array('jquery') );

		$button = '<button class="login facebook" data-fb-login="'. Options::get('hbook__fb_app_id') . '">Log in with Facebook</button>';
		$button = Plugins::filter('login_button_facebook', $button, Options::get('hbook__fb_app_id'));

		echo $button;
	}

	/**
	 * Build main plugin configuration form
	 * @return void
	 */
	public function configure()
	{
		$ui = new FormUI( 'Hbook' );

		$ui->append('text', 'fb_app_id', 'option:hbook__fb_app_id', _t('Facebook App ID', 'hbook'));
		$ui->append('text', 'fb_app_secret', 'option:hbook__fb_app_secret', _t('Facebook App Secret', 'hbook'));

		$ui->append( 'submit', 'save', _t( 'Save' ) );
		$ui->out();
	}

	/**
     * Add the configuration to the user page 
     **/
    public function action_form_user( $form, $user )
    {

    	$form->user_info->append('text', 'facebook_id', $user, _t('Facebook User ID', 'hbook'), 'optionscontrol_text');
    	$form->user_info->facebook_id->class[] = 'item';
    	$form->user_info->facebook_id->class[] = 'clear';
            
    }

}

?>