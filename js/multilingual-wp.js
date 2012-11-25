(function($){
	$(document).ready(function(){
		var editors_cont = $('#mlwp-editors').remove().appendTo( $('#post-body-content') ).css( { 'visibility': 'hidden' } ).show();

		// Update the content of the current post
		$(document).on('submit', '#post', function(){
			$('#postdivrich #wp-content-wrap .wp-editor-area').val( $('.js-tab.wpml-deflang .wp-editor-area').val() );

			$('#titlewrap input#title').val( $('.js-tab.wpml-deflang .wpml-title').val() );
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
				el = '<a href="#' + tabs[i].id + '" class="nav-tab' + ( $('#' + tabs[i].id).hasClass('wpml-deflang') ? ' active' : '' ) + '">' + tabs[i].title + '</a>';
				nav.append(el);
			}
			nav.append('<div class="clear"></div>');

			$(".js-tabs-nav a", parent).click(function() {
				var th = $(this);
				$(".js-tab").hide();
				$( th.attr("href") ).show();

				th.addClass("nav-tab-active").siblings().removeClass("nav-tab-active");

				return false;
			})
			$(".js-tabs-nav a.active", parent).click();
		}
	}
})(jQuery)