(function($){
	var wait_timeout = 5,
		countdown_interval = null,
		current_time,
		batch_disabled = false,
		total_posts = 0,
		posts_to_edit = 0,
		doing_ajax = false;

	var t_wait_timeout = 5,
		t_countdown_interval = null,
		t_current_time,
		t_batch_disabled = false,
		total_terms = 0,
		terms_to_edit = 0,
		t_doing_ajax = false;

	$(document).ready(function(){
		var $form = $('#update_posts_form');
		var $waiting = $form.find('.waiting');
		var $msg_cont = $('#update_results');

		total_posts = parseInt( $('#total_posts').text() );

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

		$form.submit(function(e){
			if ( ! doing_ajax ) {
				doing_ajax = true;
				$form.find('input.action').attr('disabled', 'disabled');
				$form.find('.loading').show();
				wait_timeout = parseInt($('#wait_timeout').val());
				wait_timeout = wait_timeout > 0 ? wait_timeout : 1;
				current_time = wait_timeout;
				$msg_cont.remove().appendTo( $form.closest('.inside') );

				// Get form data
				var data = $form.serialize();
				// Append the ajax flag
				data += '&ajax_update=1';

				posts_to_edit = parseInt( $form.find('input[name="posts_per_batch"]').val() );

				$.post( window.location.pathname + window.location.search, data, function(d, status){
					total_posts = ( total_posts - d.updated < 0 )? 0 : total_posts - d.updated;
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
					$('#total_posts').text(total_posts);
					doing_ajax = false;
				}, 'json');
			};

			e.preventDefault();
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

		var $t_form = $('#update_terms_form');
		var $t_waiting = $t_form.find('.waiting');
		var $t_msg_cont = $('#t_update_results');

		total_terms = parseInt( $('#total_terms').text() );

		t_current_time = t_wait_timeout;
		t_setup_waiting_interval = function(){
			if ( t_batch_disabled == true ) {
				clearInterval( t_countdown_interval );
				return false;
			};
			t_current_time --;
			$t_waiting.find('span').text( t_current_time );
			if ( t_current_time <= 0 ) {
				clearInterval(t_countdown_interval);
				t_current_time = t_wait_timeout;
				$t_waiting.hide();
				$t_form.find('input.action').removeAttr('disabled');
				$t_form.submit();
			};
		}

		$t_form.submit(function(e){
			if ( ! t_doing_ajax ) {
				t_doing_ajax = true;
				$t_form.find('input.action').attr('disabled', 'disabled');
				$t_form.find('.loading').show();
				$t_msg_cont.remove().appendTo( $t_form.closest('.inside') );
				t_wait_timeout = parseInt($('#t_wait_timeout').val());
				t_wait_timeout = t_wait_timeout > 0 ? t_wait_timeout : 1;

				// Get form data
				var data = $t_form.serialize();
				// Append the ajax flag
				data += '&ajax_update=1';

				terms_to_edit = parseInt( $t_form.find('input[name="terms_per_batch"]').val() );

				$.post( window.location.pathname + window.location.search, data, function(d, status){
					total_terms = ( total_terms - d.updated < 0 )? 0 : total_terms - d.updated;
					$t_form.find('.loading').hide();
					$t_msg_cont.show().prepend(d.message);
					if ( d.success == true && d.end == false ) {
						$t_form.find('.nonce').html(d.nonce);
						$t_waiting.find('span').text(t_current_time);
						$t_waiting.show();
						t_countdown_interval = setInterval( t_setup_waiting_interval, 1000 );
					} else if ( d.success == true && d.end == true ) {
						$t_msg_cont.removeClass('mlwp-notice').addClass('mlwp-success');
					}
					$('#total_terms').text(total_terms);
					t_doing_ajax = false;
				}, 'json');
			}

			e.preventDefault();
		})

		$('#t_stop_continue').on('click', function(){
			if ( $(this).hasClass('disable') ) {
				t_batch_disabled = true;
				$(this).removeClass('disable').addClass('enable').text('Continue');
			} else {
				t_batch_disabled = false;
				$(this).removeClass('enable').addClass('disable').text('Stop!');
				t_countdown_interval = setInterval(t_setup_waiting_interval, 1000);
			};
		})


		$('.wrap .postbox .handlediv,.wrap .postbox .hndle').on('click', function(e){
			$(this).siblings(".inside").toggle();

			e.preventDefault();
		});
	})
})(jQuery)