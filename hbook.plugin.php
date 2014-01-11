<?php

if ( !defined( 'HABARI_PATH' ) ) {
	die( 'No direct access' );
}

require_once('facebook-sdk/src/facebook.php');

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

			$this->add_rule('"auth"/"login"/"facebook"', 'login_facebook');

			Stack::add( 'admin_header_javascript', URL::get_from_filesystem(__FILE__) . '/facebook.js', 'facebook', array('jquery') );

			$this->check_user();
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

			if (count($users) > 0) {
				$users[0]->remember();
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
		echo '<button data-fb-login="'. Options::get('hbook__fb_app_id') . '">Log in with Facebook</button>';
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