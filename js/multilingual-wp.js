(function($){
	$(document).ready(function(){
		if ( $('#mlwp-editors').length ) {
			var $editors_cont = $('#mlwp-editors').remove().appendTo( $('#post-body-content') ).css( { 'visibility': 'hidden' } ).show();

			// See if the user's using the classic colorscheme or not
			if ( $('#colors-css').length && $('#colors-css').attr('href').match(/colors-classic/i) ) {
				$('#mlwp-editors').addClass('colors-classic');
			};

			$(window).load(function(){
				init_js_tabs( $editors_cont );

				$editors_cont.css( { 'visibility': 'visible' } );
			});

			// Set-up word counting
			$('textarea.wp-editor-area', $editors_cont).each(function(){
				var th = $(this), last = false, parent_id = th.parents('.js-tab').attr('id');
				$(document).triggerHandler('wpcountwords', [ th.val(), undefined, parent_id ]);

				th.keyup( function(e) {
					var k = e.keyCode || e.charCode;

					if ( k == last )
						return true;

					if ( 13 == k || 8 == last || 46 == last )
						$(document).triggerHandler('wpcountwords', [ th.val(), undefined, parent_id ]);

					last = k;
					return true;
				});
			})

			$('#content').unbind('keyup');
			$(document).unbind( 'wpcountwords' );
			$(document).bind( 'wpcountwords', function(e, txt, type, parent) {
				wpWordCount.wc(txt, type, parent);
			});

			wpWordCount.block = {};

			// Override WP's wordcounting function
			wpWordCount.wc = function(tx, type, parent) {
				var parent = typeof parent == 'undefined' ? 'postdivrich' : parent;
				var t = this, w = $( '.word-count', $('#' + parent) ), tc = 0;

				if ( type === undefined )
					type = wordCountL10n.type;
				if ( type !== 'w' && type !== 'c' )
					type = 'w';

				if ( typeof t.block[ parent ] != 'undefined' && t.block[ parent ] )
					return;

				t.block[ parent ] = 1;

				setTimeout( function() {
					if ( tx ) {
						tx = tx.replace( t.settings.strip, ' ' ).replace( /&nbsp;|&#160;/gi, ' ' );
						tx = tx.replace( t.settings.clean, '' );
						tx.replace( t.settings[type], function(){tc++;} );
					}
					w.html(tc.toString());

					setTimeout( function() { t.block[ parent ] = 0; }, 2000 );
				}, 1 );
			}

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
		};
	})

})(jQuery)