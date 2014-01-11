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
				'code' => $_REQUEST['code']
			));

			$this->add_rule('"auth"/"login"/"facebook"', 'login_facebook');
		}
	}

	public function action_plugin_act_login_facebook()
	{
		Utils::debug($_REQUEST);
		Utils::debug($this->facebook->getAccessToken());
		$this->facebook->setAccessToken($this->facebook->getAccessToken());
		Utils::debug($this->facebook->api('/me'));
		exit;
	}

	private function is_ready()
	{
		if (Options::get('hbook__fb_app_id') && Options::get('hbook__fb_app_secret')) {
			return true;
		} else {
			return false;
		}
	}

	private function get_login_url()
	{
		$params = array(
			// 'scope' => 'read_stream, friends_likes',
			// 'redirect_uri' => URL::get('login_facebook')
		);

		return $this->facebook->getLoginUrl();
	}

	public function action_theme_loginform_controls()
	{
		echo '<a href="' . $this->get_login_url() . '">Log in with Facebook</a>';
				Utils::debug($_REQUEST);

		Utils::debug($this->facebook->getUser());
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