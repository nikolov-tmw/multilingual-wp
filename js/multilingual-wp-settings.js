(function($){
	var wp_35_media = typeof wp == 'undefined' ? false : true,
		base_url;

	$(document).ready(function(){
		// Since WordPress will move everything that has "updated" or "error" classes
		// we add those with JS after the page loads
		setTimeout(function(){
			$('.mlwp-box').addClass('updated');
		}, 300);

		$('.mlwp-box.fade').each(function(){
			var th = $(this);
			if ( ! th.hasClass('nofade') ) {
				var timeout = 8;
				if ( th.attr('data-fade') ) {
					timeout = parseInt( th.attr('data-fade') );
				};
				setTimeout( function(){
					th.slideUp( function(){
						th.remove();
					} );
				}, timeout * 1000 );
			};
		})

		base_url = $('img.lang_icon:eq(0)').attr('src').replace(/(.*flags\/24\/).*/, '$1');
		if ( ! wp_35_media ) {
			tb_position();
			$(window).resize(function(){
				tb_position();
			});
		} else {
			$('.add_media').click(function(e) {
				var button = $(this);

				wp.media.editor.send.attachment = function(props, attachment) {
					hide_lang_select( attachment.url );
				}

				wp.media.editor.open(button);
				e.preventDefault();
			});
		};

		init_js_tabs();

		$('.wrap .postbox .handlediv,.wrap .postbox .hndle').on('click', function(e){
			$(this).siblings(".inside").toggle();

			e.preventDefault();
		});

		var forms = document.getElementsByTagName('form');
		for (var i = 0; i < forms.length; i++) {
			forms[i].reset();
		}

		$('.mlwp_flag_input').focus(function(){
			var th = $(this),
				flag_select = $('#mlwp_flag_select'),
				left = th.offset().left,
				top = th.offset().top,
				fs_h = flag_select.outerHeight(),
				fs_w = flag_select.outerWidth(),
				sel_input = $('#mlwp_flag_select input[value="' + th.val() + '"]');

			if ( $('#mlwp_flag_select:visible').length ) {
				flag_select.hide();
			};
			$('#mlwp_flag_select label').removeClass('selected');
			if ( sel_input.length ) {
				sel_input.attr('checked', 'checked').parent('label').addClass('selected');
			};
			
			flag_select.css({
				"left": ( left - ( fs_w / 2 ) ) + 'px',
				"top": top + 'px'
			});
			
			flag_select.slideDown(function(){
				if ( sel_input.length ) {
					var st = sel_input.parent('label').position();
					$('.postbox .inside', flag_select).animate( { 'scrollTop': st.top }, 700 );
				};
			}).data('rel_input', th.attr('name'));
		});

		$('.lang_radio').on('change', function(){
			$('#mlwp_flag_select label').removeClass('selected');
			$(this).parent('label').addClass('selected');
			hide_lang_select();
		})

		$(window).on('click', function(e){
			if ( ! $(e.target).hasClass('mlwp_flag_input') ) {
				if ( ! $(e.target).parents('#mlwp_flag_select').length && $('#mlwp_flag_select:visible').length ) {
					hide_lang_select();
				};
			};
		})

		$('#flag_size_select').on('change', function(){
			var th = $(this),
				val = th.val();
			th.parent('label').find('img').animate( { 'width': val } ).attr( 'src', ( th.parent('label').find('img').attr('src').replace( /flags\/\d\d/, 'flags/' + val ) ) );
		})
	})

	function init_js_tabs() {
		if( $(".js-tab").size() ) {
			var tabs = [];

			$(".js-tab").each(function(){
				tabs.push({
					id:    $(this).attr("id"),
					title: $(this).attr("title")
				});

				$(this).attr("title", "");
			});

			var nav = $(".wrap > h2");
			nav.addClass("nav-tab-wrapper").addClass("js-tabs-nav");
			nav.append("<span>&nbsp;&nbsp;</span>");

			for(i in tabs) {
				el = '<a href="#mlwp_' + tabs[i].id + '" class="nav-tab">' + tabs[i].title + '</a>';
				nav.append(el);
			}

			$(".js-tabs-nav a").click(function(e) {
				var th = $(this);
				$(".js-tab").hide();
				$( th.attr("href").replace(/mlwp_/, '') ).show();

				th.addClass("nav-tab-active").siblings().removeClass("nav-tab-active");
				$('.mlwp-wrap form').attr('action', th.attr('href') );

				// return e.preventDefault();
			}).eq(0).click();
		}
		if ( window.location.hash ) {
			if ( $('a.nav-tab[href="' + window.location.hash + '"]').length ) {
				$('a.nav-tab[href="' + window.location.hash + '"]').click();
			};
		};
	}

	function tb_position() {
		var f = $("#TB_window"),
			e = $(window).width(),
			d = $(window).height(),
			c = ( 720 < e ) ? 720 : e,
			b = 0;

		if ( $("body.admin-bar").length ) {
			b = 28
		}
		if ( f.size() ) {
			f.width( c - 50 ).height( d - 45 - b );
			$("#TB_iframeContent").width( c - 50 ).height( d - 75 - b );
			f.css( { "margin-left" : "-" + parseInt( ( ( c - 50 ) / 2 ), 10 ) + "px" } );
			if ( typeof document.body.style.maxWidth != "undefined" ) {
				f.css( { top : 20 + b + "px", "margin-top" : "0" } );
			}
		}
		return $("a.thickbox").each(function(){
			var g = $(this).attr("href");
			if(!g) {
				return
			}
			g = g.replace( /&width=[0-9]+/g, "" );
			g = g.replace( /&height=[0-9]+/g,"" );
			$(this).attr( "href", g + "&width=" + ( c - 80 ) + "&height=" + ( d - 85 - b ) )
		})
	};

	function hide_lang_select( media_url ) {
		var fs = $('#mlwp_flag_select');
		var selected = $('input[type="radio"]:checked', fs);
		if ( typeof media_url != 'undefined' && media_url ) {
			var input = $('input[name="' + fs.data('rel_input') + '"]');
			input.val( media_url );
			input.parent('label').find('img.lang_icon').attr( 'src', media_url );
		} else if ( selected.length ) {
			var input = $('input[name="' + fs.data('rel_input') + '"]');
			input.val( selected.val() );
			input.parent('label').find('img.lang_icon').attr( 'src', ( base_url + selected.val() ) );
		};
		fs.stop().slideUp(function(){
			$('input[type="radio"]', fs).removeAttr('checked');

			// Work-around for jQuery not being able to set scrollTop() on hidden elements :)
			fs.css( { 'left': '-9999px', 'display': 'block' } );
			$('.postbox .inside', fs).scrollTop('0');
			fs.css( { 'display': 'none', 'left': '0' } );
		});
	}

})(jQuery)