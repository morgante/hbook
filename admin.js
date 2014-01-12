(function($) {

	var group = {
		init: function() {
			var $span = $('<span>or </span>');
			var $field = $('<input type="text" name="fbid" id="fbid" placeholder="Facebook ID">');
			var $target = $('#add_users span').first();
			var groupId = $('#id').val();

			$span.append($field);

			$span.insertAfter($target);

			$('#add_user').click(function(evt) {
				if ($field.val().length > 0) {
					// don't fire normal handler
					evt.stopImmediatePropagation();

					window.location='/auth/facebook/add?user=' + $field.val() + '&group=' + groupId;
				}
			});
		}
	};

	$(group.init);
}(jQuery));