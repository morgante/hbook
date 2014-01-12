<?php

if ( !defined( 'HABARI_PATH' ) ) {
	die( 'No direct access' );
}

require_once('facebook-sdk/src/facebook.php');

class Hbook extends Plugin
{
	private $facebook;
	private $service = 'facebook';

	public function action_init()
	{
		if ($this->is_ready()) {
			// $this->facebook = new Facebook(array(
				// 'appId' => Options::get('hbook__fb_app_id'),
				// 'secret' => Options::get('hbook__fb_app_secret'),
				// 'cookie' => true,
			// ));

			$this->add_rule('"auth"/"facebook"/"add"', 'add_facebook_user');
			$this->add_rule('"auth"/"facebook"/"callback"', 'facebook_oauth_callback');

			// $this->check_user();

			// Stack::add( 'admin_header_javascript', URL::get_from_filesystem(__FILE__) . '/admin.js', 'hbook-admin', array('jquery') );
		}
	}

	/*
     * Add Facebook to the list of social services providing the socialauth feature
     */
    public function filter_socialauth_services($services = array())
    {
    	if ( $this->is_ready() ) {
    		$services[] = $this->service;
    	}
    	return $services;
    }

    /*
     * Provide auth link to the theme
     * @param string $service The service / social network the link is requested for.
     * @param array Accepts values for overriding the global options redirect_uri and scope and additional state
     */
    public function theme_socialauth_link($theme, $service, $params = array())
    {
		if($service == $this->service) {
			return $this->build_link('oauth', $params);
		}
    }

    private function build_link($type, $params)
    {
    	switch ($type) {
    		case 'oauth':
    			$url = "https://www.facebook.com/dialog/oauth?";
    			break;
    		case 'token':
    			$url = 'https://graph.facebook.com/oauth/access_token?';
    			break;
    	}

    	$url .= 'client_id=' . Options::get('hbook__fb_app_id');

    	if ($type == 'token') {
    		$url .= '&client_secret=' . Options::get('hbook__fb_app_secret');
    		$url .= '&code=' . $params['code'];
    	}

    	if ($type == 'oauth') {
    		if (isset($params['scope'])) {
	    		$scopes = $params['scope'];
	    	} else {
	    		$scopes = Options::get('hbook__fb_scopes');
	    	}

	    	if ($scopes !== null) {
	    		if (is_string($scopes)) {
	    			$scopes = str_replace(' ', '', $scopes);
	    			$scopes = explode(',', $scopes);
	    		}
	    		$scopes = implode(',', $scopes);
	    		$url.= '&scopes=' . $scopes;
	    	}
    	}

		$url .= "&redirect_uri=" . URL::get('facebook_oauth_callback');

		if(isset($params['state'])) {
			$url .= "&state=" . $params['state'];
		}

		return $url;
    }

    private function api( $path, $token )
    {
    	$base = 'https://graph.facebook.com';

    	$request = new RemoteRequest($base . $path . '?access_token=' . $token , "GET");
		$request->add_header('Accept: application/json');
		$request->execute();

		$response = $request->get_response_body();
		$response = json_decode($response);

		return $response;

    }

    private function get_portrait_url($user_id)
    {
    	return 'https://graph.facebook.com/' . $user_id . '/picture';
    }

    public function action_plugin_act_facebook_oauth_callback($handler)
    {
    	$code = $_GET['code'];
		$state =$_GET['state'];
		$opts = Options::get_group(__CLASS__);

		// Wrap in try/catch so we fail silently if user info not available
		try {
			/// Exchange code for token
			$request = new RemoteRequest($this->build_link( 'token', array( 'code' => $code )), "POST");
			$request->execute();

			if ( ! $request->executed() ) {
				throw new XMLRPCException( 16 );
			}

			parse_str($request->get_response_body(), $response);

			$token = $response['access_token'];
			
			// Offer the token to plugins that want to do something with the authenticated user
			Plugins::act('facebook_token', $token);

			$data = $this->api( '/me', $token);

			// The following is important, because it's part of the "socialauth" feature API
			$user = array("id" => $data->id);
			
			if(isset($data->name)) {
				$user['name'] = $data->name;
			} else {
				$user['name'] = $data->id;
			}

			$user['portrait_url'] = $this->get_portrait_url($data->id);

			if(isset($data->email) && !empty($data->email)) {
				$user['email'] = $data->email;
			} else {
				$user['email'] = $data->id . '@facebook.com';
			}

			// Pass the identification data to plugins
			Plugins::act('socialauth_identified', $this->service, $user, $state);
		} catch(Exception $e) {
			// don't care if it fails, the only consequence is that action_social_auth will not be triggered, which is correct
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