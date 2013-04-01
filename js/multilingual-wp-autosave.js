(function($){
	var $editors_cont, $default_lang_title, $default_lang_content;
	MLWP_autosaveLast = ''

	MLWP_autosave = {
		languages: [],
		// Backup WP's autosave
		old_autosave: autosave,
		autosave: function(){
			// Copy content from default language to built-in editor
			update_default_lang();

			// Listen for the ajaxComplete event that will be triggered by WP's autosave function
			// This will allow us to do our autosave after the original post has been updated
			$(document).on('ajaxComplete', MLWP_autosave.autosave_complete);

			// Do the WP autosave
			MLWP_autosave.old_autosave();
		},
		autosave_complete: function( event, request, options ){
			var th = MLWP_autosave,
				data = wpAjax.unserialize( options.data );

			if ( typeof data.action != 'undefined' && data.action == 'autosave' ) {
				// Remove ajaxComplete handler
				$(document).off('ajaxComplete', MLWP_autosave.autosave_complete);
				
				var post_data, doAutoSave, ed, origStatus, successCallback, curr_cont = '';
				blockSave = true;

				post_data = {
					action: "mlwp_autosave",
					post_ID: jQuery("#post_ID").val() || 0,
					autosavenonce: jQuery('#autosavenonce').val(),
					post_type: jQuery('#post_type').val() || "",
					autosave: 1
				};

				autosave_disable_buttons();

				// We always send the ajax request in order to keep the post lock fresh.
				// This (bool) tells whether or not to write the post to the DB during the ajax request.
				doAutoSave = true;

				// No autosave while thickbox is open (media buttons)
				if ( jQuery("#TB_window").css('display') == 'block' )
					doAutoSave = false;

				$.each(th.languages, function( i, lang ){
					post_data[ 'title_' + lang ] = $('#title_' + lang, $editors_cont).val();
					post_data[ 'post_name_' + lang ] = $('#post_name_' + lang, $editors_cont).val();
					ed = get_tmce( 'content_' + lang );
					if ( ed && doAutoSave ) {
						// Don't run while the tinymce spellcheck is on. It resets all found words.
						if ( ed.plugins.spellchecker && ed.plugins.spellchecker.active ) {
							doAutoSave = false;
						} else {
							tinymce.triggerSave();
						}
					}
					post_data[ 'content_' + lang ] = $('#content_' + lang, $editors_cont).val();

					curr_cont += post_data[ 'title_' + lang ] + post_data[ 'post_name_' + lang ] + post_data[ 'content_' + lang ];
				});

				// Nothing to save or no change.
				if ( curr_cont == '' || curr_cont == MLWP_autosaveLast ) {
					doAutoSave = false;
				}

				if ( doAutoSave ) {
					MLWP_autosaveLast = curr_cont;
					$.each(th.languages, function( i, lang ){
						jQuery(document).triggerHandler( 'wpcountwords', [ post_data[ 'content_' + lang ], undefined, 'mlwp_tab_lang_' + lang ] );
					});
				} else {
					post_data['autosave'] = 0;
				}

				successCallback = th.autosave_saved; // pre-existing post

				autosaveOldMessage = jQuery('#autosave').html();
				jQuery.ajax({
					data: post_data,
					beforeSend: doAutoSave ? autosave_loading : null,
					type: "POST",
					url: ajaxurl,
					success: successCallback
				});
			};
		}, 
		autosave_saved: function( response ){
			var _response = wpAjax.parseAjaxResponse( response, 'autosave' ),
				data = _response && _response.responses && _response.responses.length && _response.responses[0].data ? _response.responses[0].data : false,
				_data = data ? data.split('|||') : false,
				_val, lang;
			
			if ( _data && data.length > 1 ) {
				$.each(_data, function(i, val){
					var _val = val.split('=');
					if ( _val[0].match(/[a-z]{2}_slug/) && typeof _val[1] != 'undefined' ) {
						lang = _val[0].replace(/_slug/, '');
						if ( $('#post_name_' + lang, $editors_cont).length ) {
							$('#post_name_' + lang, $editors_cont).val( _val[1] );
						};
					};
				})
				if ( typeof $('response_data', response)[0] != 'undefined' && typeof $('response_data', response)[0].childNodes[0].data != 'undefined' ) {
					$('response_data', response)[0].childNodes[0].data = $('response_data', response)[0].childNodes[0].data.replace(/data=(.*?)\|\|\|.*$/, '$1');
				};
			};

			autosave_saved( response );
		}
	}

	$(document).ready(function(){
		$editors_cont = $('#mlwp-editors').remove().appendTo( $('#post-body-content') ).css( { 'visibility': 'hidden' } ).show();
		$default_lang_title = $('.js-tab.mlwp-deflang .mlwp-title');
		$default_lang_content = $('.js-tab.mlwp-deflang .wp-editor-area');

		if ( $editors_cont.length ) {
			// Kidnap WP's autosave function >:]
			autosave = MLWP_autosave.autosave;
			
			$('.js-tab', $editors_cont).each(function(){
				MLWP_autosave.languages.push( $(this).attr('mlwp-lang') );
			})

			// Update the content of the current post
			$(document).on('submit', '#post', function(){
				update_default_lang();
			})
		};
	})

	function update_default_lang() {
		var tmce_ed = get_tmce( $default_lang_content.attr('id') );
		var content = '';
		if ( tmce_ed ) {
			$default_lang_content.val( tmce_ed.getContent({format : 'raw'}) );
		};

		// Update the default rich text editor
		tmce_ed = get_tmce( 'content' );
		if ( tmce_ed ) {
			tmce_ed.setContent( $default_lang_content.val(), {format : 'raw'} );
		} else {
			$('#postdivrich #wp-content-wrap .wp-editor-area').val( $default_lang_content.val() );
		};

		// Update the default post title
		$('#titlewrap input#title').val( $default_lang_title.val() );
	}

	function get_tmce( id ) {
		return typeof tinyMCE != 'undefined' && ! tinyMCE.get( id ).isHidden() ? tinyMCE.get( id ) : false;
	}
})(jQuery)