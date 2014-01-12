(function($) {
	var $button;
	var appId;

	var fbAsyncInit = function() {
		FB.init({
			appId: appId,
			status: true,
			cookie: true,
			xfbml: false
		});
	};

	var load = function(d, s, id) {
		var js, fjs = d.getElementsByTagName(s)[0];
		if (d.getElementById(id)) {return;}
		js = d.createElement(s); js.id = id;
		js.src = "//connect.facebook.net/en_US/all.js";
		fjs.parentNode.insertBefore(js, fjs);
	};

	var login = function() {
		FB.login(function(response) {
			if (response.authResponse) {
				window.location = '/admin';
			} else {
			// The person cancelled the login dialog
			}
		});
	};

	var init = function() {
		$button = $('[data-fb-login]');

		if ($button.length > 0) {
			appId = $button.data('fb-login');

			// Load the SDK
			load(document, 'script', 'facebook-jssdk');

			$button.click(function(evt) {
				evt.preventDefault();
				login();
			});

		}
	};

	$(init);

	window.fbAsyncInit = fbAsyncInit;

}(jQuery));