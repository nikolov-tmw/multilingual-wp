(function($){
	var wait_timeout = 5;
	var countdown_interval = null;
	var current_time;
	var batch_disabled = false;
	var total_attachments = 0;
	var attachments_to_edit = 0;

	$(document).ready(function(){
		var $form = $('#update_posts_form');
		var $waiting = $form.find('.waiting');
		var $msg_cont = $('#update_results');

		total_attachments = parseInt( $('#total_attachments').text() );

		current_time = wait_timeout;
		setup_waiting_interval = function(){
			if ( batch_disabled == true ) {
				clearInterval(countdown_interval);
				return false;
			};
			current_time --;
			$waiting.find('span').text(current_time);
			if ( current_time <= 0 ) {
				clearInterval(countdown_interval);
				current_time = wait_timeout;
				$waiting.hide();
				$form.find('input.action').removeAttr('disabled');
				$form.submit();
			};
		}

		$form.submit(function(){
			$form.find('input.action').attr('disabled', 'disabled');
			$form.find('.loading').show();
			wait_timeout = parseInt($('#wait_timeout').val());

			// Get form data
			var data = $form.serialize();
			// Append the ajax flag
			data += '&ajax_update=1';

			attachments_to_edit = parseInt( $form.find('input[name="posts_per_batch"]').val() );

			$.post( window.location.pathname + window.location.search, data, function(d, status){
				total_attachments = ( total_attachments - attachments_to_edit < 0 )? 0 : total_attachments - attachments_to_edit;
				$form.find('.loading').hide();
				$msg_cont.show().prepend(d.message);
				if ( d.success == true && d.end == false ) {
					$form.find('.nonce').html(d.nonce);
					$waiting.find('span').text(current_time);
					$waiting.show();
					countdown_interval = setInterval(setup_waiting_interval, 1000);
				} else if ( d.success == true && d.end == true ) {
					$msg_cont.removeClass('mlwp-notice').addClass('mlwp-success');
				}
				$('#total_attachments').text(total_attachments);
			}, 'json');

			return false;
		})

		$('#stop_continue').click(function(){
			if ( $(this).hasClass('disable') ) {
				batch_disabled = true;
				$(this).removeClass('disable').addClass('enable').text('Continue');
			} else {
				batch_disabled = false;
				$(this).removeClass('enable').addClass('disable').text('Stop!');
				countdown_interval = setInterval(setup_waiting_interval, 1000);
			};
		})
	})
})(jQuery)