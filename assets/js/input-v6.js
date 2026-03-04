(function($){
	function update_preview( value, parent ) {
		if ( ! value ) {
			$( '.acf-field-setting-fa_live_preview .acf-input', parent ).empty();
			$( '.icon_preview', parent ).empty();
		} else {
			try {
				var iconJson = $.parseJSON( value );
				if ( 'object' === typeof iconJson ) {
					$( '.acf-field-setting-fa_live_preview .acf-input', parent ).html( acf.escHtml('<i class="fa-' + iconJson.family + ' fa-' + iconJson.style + ' fa-' + iconJson.id + ' fa-fw" aria-hidden="true"></i>') );
					$( '.icon_preview', parent ).html( acf.escHtml('<i class="fa-' + iconJson.family + ' fa-' + iconJson.style + ' fa-' + iconJson.id + ' fa-fw" aria-hidden="true"></i>') );
					$( '.icon_preview', parent ).removeClass('v5-compat-alert show-alert');
				}
			} catch( err ) {
				return false;
			}
		}
	}

	function select2_init_args( element, parent ) {
		return {
			key			: $( parent ).data('key'),
			allowNull	: $( element ).data('allow_null'),
			ajax		: 1,
			ajaxAction	: 'acf/fields/font-awesome/query'
		}
	}

	function prefixLookup( prefix ) {
		switch ( prefix ) {
			case 'fas':
				prefix = 'solid';
				break;

			case 'fass':
				prefix = 'sharp_solid';
				break;

			case 'far':
				prefix = 'regular';
				break;

			case 'fasr':
				prefix = 'sharp_regular';
				break;

			case 'fal':
				prefix = 'light';
				break;

			case 'fasl':
				prefix = 'sharp_light';
				break;

			case 'fat':
				$prefix = 'thin';
				break;

			case 'fast':
				$prefix = 'sharp_thin';
				break;

			case 'fasds':
				$prefix = 'sharp-duotone';

			case 'fad':
				prefix = 'duotone';
				break;

			case 'fab':
				prefix = 'brands';
				break;

			case 'fak':
				prefix = 'kit_custom';
				break;

			case 'fakd':
				prefix = 'kit-duotone_custom';
				break;
		}

		return prefix;
	}

	function iconLookup( icon ) {
		if ( 'FontAwesome' in window ) {

			if ( 0 === icon.indexOf( 'fa-' ) ) {
				var name = icon.split( 'fa-' ).pop();

				if ( name.indexOf( '-o' ) > -1 ) {
					if ( name.lastIndexOf( '-o' ) === name.length - 2 ) {
						name = name.slice( 0, -2 );
					}
				}

				var iconDetails = window.FontAwesome.findIconDefinition({iconName: name });

				if ( undefined === iconDetails ) {
					var iconDetails = window.FontAwesome.findIconDefinition({prefix: 'fab', iconName: name });
				}

				if ( undefined !== iconDetails ) {
					var prefix	= prefixLookup( iconDetails.prefix ),
						label	= iconDetails.iconName.replace( '-', ' ' ),
						unicode = iconDetails.icon[3];

					return {
						value : '{"style" : "' + prefix + '", "id" : "' + iconDetails.iconName + '", "label" : "' + label + '", "unicode" : "' + unicode + '"}',
						label : label
					};
				}
			} else {
				var prefix	= icon.substring( 0, 3 ),
					name	= icon.split( 'fa-' ).pop();

				if ( 'fal' == prefix || 'fad' == prefix ) {
					return;
				}

				var iconDetails = window.FontAwesome.findIconDefinition({prefix: prefix, iconName: name });

				if ( undefined !== iconDetails ) {
					var prefix	= prefixLookup( iconDetails.prefix ),
						label	= iconDetails.iconName.replace( '-', ' ' ),
						unicode = iconDetails.icon[3];

					return {
						value : '{"style" : "' + prefix + '", "id" : "' + iconDetails.iconName + '", "label" : "' + label + '", "unicode" : "' + unicode + '"}',
						label : label
					};
				}
			}
		}
	}

	function select2_init( fa_field ) {
		if ( ACFFA.v5_compat_mode && '[v5-compat-lookup]' == $( 'option:selected', fa_field ).text() ) {
			var iconData = iconLookup( $( fa_field ).val() );

			if ( iconData ) {
				$( 'option:selected', fa_field ).val( iconData.value );
				$( 'option:selected', fa_field ).text( iconData.label );
				$( fa_field ).val( iconData.value );
				$( fa_field ).siblings('.v5-compat-message').hide();
				$( fa_field ).siblings('.icon_preview').removeClass('v5-compat-alert show-alert');
			} else {
				$( 'option:selected', fa_field ).val( false );
				$( 'option:selected', fa_field ).text( '' );
			}
		}
		var $select = $( fa_field );
		var parent = $( $select ).closest('.acf-field-font-awesome');

		update_preview( $select.val(), parent );

		acf.select2.init( $select, select2_init_args( fa_field, parent ), parent );
	}

	acf.add_action( 'select2_init', function( $input, args, settings, $field ) {
		if ( $field instanceof jQuery && $field.hasClass('fontawesome-edit') ) {
			$field.addClass('select2_initalized');
		}
	});

	// Adjust FontAwesome select2 field settings
	acf.add_filter( 'select2_args', function( args, $select, settings, $field ) {
		if ( $select.hasClass('select2-fontawesome') ) {
			args.escapeMarkup = function( markup ) {
				if (typeof markup !== 'string') {
					return markup;
				}
				return acf.escHtml( markup ); 
			}
			args.ajax.delay = 400;

			if ( ! $select.hasClass('custom-icon-set') ) {
				args.minimumInputLength = 1;
			}
		}

		return args;
	});

	// Update FontAwesome field previews in field create area
	acf.add_action( 'open_field/type=font-awesome change_field_type/type=font-awesome', function( $el ) {
		var $field_objects = $('.acf-field-object[data-type="font-awesome"]');

		$field_objects.each( function( index, field_object ) {
			update_preview( $( 'select.fontawesome-create', field_object ).val(), field_object );

			if ( $( '.acf-field[data-name="icon_sets"] input[type="checkbox"][value="custom"]:checked', field_object ).length ) {
				$( '.acf-field-setting-custom_icon_set', field_object ).show();
			} else {
				$( '.acf-field-setting-custom_icon_set', field_object ).hide();
			}

		});
	});

	// Uncheck standard icon set choices if 'custom icon set' is checked, and show the custom icon set select box
	$( document ).on( 'change', '.acf-field[data-name="icon_sets"] input[type="checkbox"]', function() {
		var parent = $( this ).closest('.acf-field-object-font-awesome');
		if ( $( this ).is('[value="custom"]') && $( this ).is(':checked') ) {
			$( 'input[type="checkbox"]:not([value="custom"])', parent ).prop('checked', false);
			$( '.acf-field-setting-custom_icon_set', parent ).show();
		} else {
			$( 'input[type="checkbox"][value="custom"]', parent ).prop('checked', false);
			$( '.acf-field-setting-custom_icon_set', parent ).hide();
		}
	});

	// Handle new menu items with FontAwesome fields assigned to them
	$( document ).on( 'menu-item-added', function( event, $menuMarkup ) {
		var $fa_fields = $( 'select.fontawesome-edit:not(.select2_initalized)', $menuMarkup );

		if ( $fa_fields.length ) {
			$fa_fields.each( function( index, fa_field ) {
				select2_init( fa_field );
			});
		}
	});

	// Update FontAwesome field previews and init select2 in field edit area
	acf.add_action( 'ready_field/type=font-awesome append_field/type=font-awesome show_field/type=font-awesome new_field/type=font-awesome', function( $el ) {
		var $fa_fields = $( 'select.fontawesome-edit:not(.select2_initalized)', $el );

		if ( $fa_fields.length ) {
			$fa_fields.each( function( index, fa_field ) {
				select2_init( fa_field );
			});
		}
	});

	// Update FontAwesome field previews when value changes
	$( document ).on( 'select2:select select2:clear', 'select.select2-fontawesome', function() {
		var $input = $( this );

		if ( $input.hasClass('fontawesome-create') ) {
			update_preview( $input.val(), $input.closest('.acf-field-object') );
			$('.acf-field-setting-default_label input').val( $( 'option:selected', $input ).html() );
		}

		if ( $input.hasClass('fontawesome-edit') ) {
			update_preview( $input.val(), $input.closest('.acf-field-font-awesome') );
		}
	});

})(jQuery);
