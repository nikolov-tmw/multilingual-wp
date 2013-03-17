(function($){
	$(document).ready(function(){
		var editors_cont = $('#mlwp-editors').remove().appendTo( $('#post-body-content') ).css( { 'visibility': 'hidden' } ).show();

		// Update the content of the current post
		$(document).on('submit', '#post', function(){
			$('#postdivrich #wp-content-wrap .wp-editor-area').val( $('.js-tab.mlwp-deflang .wp-editor-area').val() );

			$('#titlewrap input#title').val( $('.js-tab.mlwp-deflang .mlwp-title').val() );
		})

		$(window).load(function(){
			init_js_tabs( editors_cont );

			editors_cont.css( { 'visibility': 'visible' } );
		})
	})

	function init_js_tabs( parent ) {
		if( $(".js-tab", parent).length ) {
			var tabs = [];

			$(".js-tab").each(function(){
				tabs.push({
					id:    $(this).attr("id"),
					title: $(this).attr("title")
				});

				$(this).attr("title", "");
			});

			var nav = $("> h2", parent);
			nav.addClass("nav-tab-wrapper").addClass("js-tabs-nav");
			nav.append("<span>&nbsp;&nbsp;</span>");

			for(i in tabs) {
				el = '<a href="#' + tabs[i].id + '" class="button button-' + ( $('#' + tabs[i].id).hasClass('mlwp-deflang') ? 'primary' : 'secondary' ) + '">' + tabs[i].title + '</a>';
				nav.append(el);
			}
			nav.append('<div class="clear"></div>');

			$(".js-tabs-nav a", parent).click(function() {
				var th = $(this);
				$(".js-tab").hide();
				$( th.attr("href") ).show();

				th.addClass("button-primary").siblings().removeClass("button-primary");

				return false;
			})
			$(".js-tabs-nav a.button-primary", parent).click();
		}
	}
})(jQuery)