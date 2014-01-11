// window.fbAsyncInit = function() {
//     // init the FB JS SDK
//     FB.init({
//       appId      : 'YOUR_APP_ID',                        // App ID from the app dashboard
//       status     : true,                                 // Check Facebook Login status
//       xfbml      : true                                  // Look for social plugins on the page
//     });

//     // Additional initialization code such as adding Event Listeners goes here
//   };

//   // Load the SDK asynchronously
//   (function(d, s, id){
//      var js, fjs = d.getElementsByTagName(s)[0];
//      if (d.getElementById(id)) {return;}
//      js = d.createElement(s); js.id = id;
//      js.src = "//connect.facebook.net/en_US/all.js";
//      fjs.parentNode.insertBefore(js, fjs);
//    }(document, 'script', 'facebook-jssdk'));

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