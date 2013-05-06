(function($){
	$(document).ready(function(){
		if ( $('.mlwp-lang-row').length ) {
			// Hide default ones
			$('input#name,input#slug,select#parent,textarea#description').closest('tr').hide();

			$('form#edittag').prepend('<div id="mlwp-lang-tabs"></div>');
			var $tabs_cont = $('#mlwp-lang-tabs');
			$('#mlwp-languages-title').remove().appendTo( $tabs_cont );
			$('.mlwp-lang-row').each(function(){
				var $th = $(this);
				$tabs_cont.append('<a data-lang="' + $th.attr('mlwp-lang') + '" href="#" class="button button-' + ( $th.hasClass('mlwp-deflang') ? 'primary' : 'secondary' ) + '">' + $('h3', $th).text() + '</a>');
			})

			$('a', $tabs_cont).on('click', function(e) {
				var $th = $(this);
				$('tr.mlwp-term-lang').hide();
				$( 'tr[mlwp-lang="' + $th.attr('data-lang') + '"]' ).show();

				$th.addClass("button-primary").siblings().removeClass("button-primary");

				e.preventDefault();
			})
			$('a.button-primary', $tabs_cont).click();

			$('form#edittag').on('submit', function(e){
				var $th = $(this);
				var prevent = false;
				$('tr.mlwp-deflang.form-field', $th).each(function(){
					var $el = $('input,textarea,select', $(this));

					if ( $el.length ) {
						$( '#' + ( $el.attr('id').replace(/_\w{2}$/, '') ) ).val( $el.val() );
					};
				})

				$('tr.form-field.form-required.mlwp-term-lang', $th).each(function(){
					var $th = $(this);
					var $el = $('input,textarea,select', $th);
					if ( ! $el.length || ! $el.val() ) {
						var button = $('a[data-lang="' + $th.attr('mlwp-lang') + '"]');
						button.click();
						alert('Please enter a ' + ( $th.find('th').text() ) + ' for ' + button.text() + '!');
						prevent = true;
						return false;
					};
				})

				if ( prevent ) {
					e.preventDefault();
				};
				// e.preventDefault();
			})
		};
	})

})(jQuery)