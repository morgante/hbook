<?php

if ( !defined( 'HABARI_PATH' ) ) {
	die( 'No direct access' );
}

class Hbook extends Plugin
{
	private $service = 'Facebook';

	/**
	 * If we're ready, add our rewrite rules
	 */
	public function action_init()
	{
		if ($this->is_ready()) {
			$this->add_rule('"auth"/"facebook"/"add"', 'add_facebook_user');
			$this->add_rule('"auth"/"facebook"/"callback"', 'facebook_oauth_callback');

			Stack::add( 'admin_header_javascript', URL::get_from_filesystem(__FILE__) . '/admin.js', 'hbook', array('jquery') );
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

    /**
     * Handle the oauth callback Facebook directs us to and forward it to socialauth
     * @return void
     */
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

    /**
     * Handle adding a new user via their Facebook ID
     * @return void
     */
	public function action_plugin_act_add_facebook_user()
	{
		if (!User::identify()->can( 'manage_users' )) {
			echo 'Access denied';
			return;
		}

		$fbid = $_GET['user'];

		$data = $this->api('/' . $fbid, Options::get('hbook__fb_app_token'));
		$fbid = $data->id; // normalize ID

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

		if(isset($data->username)) {
			$user['username'] = $data->username;
		} else {
			$user['username'] = $data->id;
		}

		// Pass the identification data to plugins
		Plugins::act('socialauth_identified', $this->service, $user, 'usercreate');
		
		// Assign them to the correct group, if any
		if ($_GET['group']) {
			$group = UserGroup::get_by_id( $_GET['group'] );
			$user = Plugins::filter('socialauth_user', $this->service, $fbid);

			if ( $user ) {
				$group->add( $user );
			}

			Utils::redirect( URL::get( 'admin', 'page=group' ) . '?id=' . $group->id );

		} else {
			// redirect somewhere
			Utils::redirect( URL::get( 'admin', 'page=group' ) );
		}
	}

	/**
	 * Build main plugin configuration form
	 * @return void
	 */
	public function configure()
	{
		$ui = new FormUI( 'Hbook' );

		$ui->append('text', 'fb_app_id', 'option:hbook__fb_app_id', _t('Facebook App ID', 'hbook') );
		$ui->append('text', 'fb_app_secret', 'option:hbook__fb_app_secret', _t('Facebook App Secret', 'hbook') );
		$ui->append('text', 'fb_scopes', 'option:hbook__fb_scopes', _t('Facebook Scopes', 'hbook') );

		$ui->append( 'submit', 'save', _t( 'Save' ) );

		$ui->on_success('save_facebook_credentials');

		$ui->out();
	}

	/**
	 * Handle saving Facebook credentials
	 * Generate an app auth token
	 */
	public function filter_save_facebook_credentials($b, $form)
	{
		try {
			$request = new RemoteRequest($this->build_link( 'app_token', array(
				'client_id' => $form->fb_app_id->value,
				'client_secret' => $form->fb_app_secret->value
			)));
			$request->execute();

			if ( !$request->executed() ) {
				Session::error('Facebook could not be reached to authenticate your changes');
			} else {
				parse_str($request->get_response_body(), $response);

				Options::set('hbook__fb_app_token', $response['access_token']);
			}

			// Do the normal saving
			$form->save();
			
		} catch (Exception $e) {
			Session::error('There was an error authenticating those credentials.');
		}
	}

	/**
	 * Test whether we're ready to offer authentication
	 */
	private function is_ready()
	{
		if (Options::get('hbook__fb_app_id') && Options::get('hbook__fb_app_secret')) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Build the authentication link
	 * @param  string $type   Either 'oauth' or 'token'
	 * @param  array $params  Optional parameters
	 * @return string
	 */
	private function build_link($type, $params)
    {
    	switch ($type) {
    		case 'oauth':
    			$url = "https://www.facebook.com/dialog/oauth?";
    			break;
    		case 'app_token':
    		case 'token':
    			$url = 'https://graph.facebook.com/oauth/access_token?';
    			break;
    	}

    	if ( isset( $params['client_id'] ) ) {
    		$url .= 'client_id=' . $params['client_id'];
    	} else {
    		$url .= 'client_id=' . Options::get('hbook__fb_app_id');
    	}

    	if ($type == 'token' || $type == 'app_token') {
    		if ( isset( $params['client_secret'] ) ) {
	    		$url .= '&client_secret=' . $params['client_secret'];
	    	} else {
	    		$url .= '&client_secret=' . Options::get('hbook__fb_app_secret');
	    	}

    		if ( isset( $params['code'] ) ) {
    			$url .= '&code=' . $params['code'];
    		}
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

    	if ( $type == 'oauth' || $type == 'token' ) {
    		$url .= "&redirect_uri=" . URL::get('facebook_oauth_callback');
    	}
		
		if ( $type == 'app_token' ) {
			$url .= '&grant_type=client_credentials';
		}

		if(isset($params['state'])) {
			$url .= "&state=" . $params['state'];
		}

		return $url;
    }

    /**
     * Make an API request to Facebook
     * @param  string $path  The path to request, including leading /
     * @param  string $token The access_token to use
     * @return object        The response, decoded
     */
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

    /**
     * Generate the profile URL for a given user
     * @param  string $user_id The user to generate for
     * @return string          The URL
     */
    private function get_portrait_url($user_id)
    {
    	return 'https://graph.facebook.com/' . $user_id . '/picture';
    }

}

?>