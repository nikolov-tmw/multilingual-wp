(function($){
	$(document).ready(function(){
		tb_position();
		$(window).resize(function(){
			tb_position();
		});

		init_js_tabs();

		$('.wrap .postbox .handlediv,.wrap .postbox .hndle').on('click', function(){
			$(this).siblings(".inside").toggle();

			return false;
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
			})
			
			flag_select.slideDown().data('rel_input', th.attr('name'));
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
			th.parent('label').find('img').animate( { 'width': val, 'height': val } ).attr( 'src', ( th.parent('label').find('img').attr('src').replace( /flags\/\d\d/, 'flags/' + val ) ) );
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
				el = '<a href="#' + tabs[i].id + '" class="nav-tab">' + tabs[i].title + '</a>';
				nav.append(el);
			}

			$(".js-tabs-nav a").click(function() {
				var th = $(this);
				$(".js-tab").hide();
				$( th.attr("href") ).show();

				th.addClass("nav-tab-active").siblings().removeClass("nav-tab-active");
				$('.mlwp-wrap form').attr('action', th.attr('href') );

				// return false;
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

	function hide_lang_select( input ) {
		var fs = $('#mlwp_flag_select');
		var selected = $('input[type="radio"]:checked', fs);
		if ( selected.length ) {
			var input = $('input[name="' + fs.data('rel_input') + '"]');
			input.val( selected.val() )
			input.parent('label').find('img.lang_icon').attr( 'src', ( input.parent('label').find('img.lang_icon').attr('src').replace( /(flags\/24\/).*/, '$1' + selected.val() ) ) );
		};
		fs.stop().slideUp(function(){
			$('input[type="radio"]', fs).removeAttr('checked');
		});
	}

})(jQuery)