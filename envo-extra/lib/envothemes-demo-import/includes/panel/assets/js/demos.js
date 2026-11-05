( function ( $ ) {

    "use strict";

    $( document ).ready( function () {
        EnvoDemoImport.init();
    } );

    var EnvoDemoImport = {
        importData: { },
        importDataRequest: null,
        allowPopupClosing: true,
        filters: {
            type: 'all',
            category: 'all',
            search: ''
        },
        init: function () {
            var that = this;

            // Filters: type (free/PRO), category and search work together
            $( '.envo-di-type-btn' ).on( 'click', function () {
                $( this ).addClass( 'is-active' ).siblings().removeClass( 'is-active' );
                that.filters.type = $( this ).data( 'type' );
                that.applyFilters();
            } );

            $( '.envo-navigation-link' ).on( 'click', function ( e ) {
                e.preventDefault();
                $( this ).parent().addClass( 'active' ).siblings().removeClass( 'active' );
                that.filters.category = this.hash.slice( 1 );
                that.applyFilters();
            } );

            $( '.envo-search-input' ).on( 'input', function () {
                that.filters.search = $.trim( $( this ).val() ).toLowerCase();
                that.applyFilters();
            } );

            $( '.envo-di-reset' ).on( 'click', function () {
                that.filters = { type: 'all', category: 'all', search: '' };
                $( '.envo-search-input' ).val( '' );
                $( '.envo-di-type-btn[data-type="all"]' ).addClass( 'is-active' ).siblings().removeClass( 'is-active' );
                $( '.envo-navigation li' ).removeClass( 'active' ).first().addClass( 'active' );
                that.applyFilters();
            } );

            // Prevent the popup from showing when the live preview button
            $( '.envo-demo-wrap .theme-actions a.button' ).on( 'click', function ( e ) {
                e.stopPropagation();
            } );

            // Open the demo (mouse and keyboard)
            $( '.envo-open-popup' ).on( 'click keydown', function ( e ) {
                if ( 'keydown' === e.type && 13 !== e.which && 32 !== e.which ) {
                    return;
                }
                e.preventDefault();

                var demo = $( this ).data( 'demo-id' );

                $( '.preview-' + demo + ', .preview-all-' + demo ).show();

                that.getDemoData( demo );
            } );

            // Close the popup
            $( '.envo-demo-popup-close, .envo-demo-popup-overlay' ).on( 'click', function ( e ) {
                e.preventDefault();
                if ( that.allowPopupClosing === true ) {
                    that.closePopup();
                }
            } );

            $( document ).on( 'keydown', function ( e ) {
                if ( 27 === e.which && $( '#envo-demo-popup-wrap' ).is( ':visible' ) && that.allowPopupClosing === true ) {
                    that.closePopup();
                }
            } );

            // Demo server
            $( '#envo-di-server-select' ).on( 'change', this.saveServer );

            // Remove the content of an imported demo
            $( '.envo-di-remove-demo' ).on( 'click', function () {
                var $item = $( this ).closest( '.envo-di-history-item' );

                if ( !window.confirm( $( '<div/>' ).html( envoDemos.remove_confirm ).text() ) ) {
                    return;
                }
                $( this ).prop( 'disabled', true ).addClass( 'updating-message' );
                that.removeDemo( $item );
            } );

            $( document ).on( 'click', '.install-now', this.installNow );
            $( document ).on( 'click', '.activate-now', this.activatePlugins );
            $( document ).on( 'wp-plugin-install-success', this.installSuccess );
            $( document ).on( 'wp-plugin-installing', this.pluginInstalling );
            $( document ).on( 'wp-plugin-install-error', this.installError );

        },
        // Show the demos matching all filters.
        applyFilters: function () {
            var filters = this.filters,
                visible = 0;

            $( '.envo-demo-wrap .theme-wrap' ).each( function () {
                var $item = $( this ),
                    categories = ( $item.data( 'categories' ) || '' ).toString().split( ',' ),
                    show = ( 'all' === filters.type || $item.data( 'type' ) === filters.type )
                    && ( 'all' === filters.category || -1 !== $.inArray( filters.category, categories ) )
                    && ( '' === filters.search || -1 !== String( $item.data( 'name' ) ).indexOf( filters.search ) );

                if ( show ) {
                    visible++;
                    if ( $item.prop( 'hidden' ) ) {
                        $item.prop( 'hidden', false ).addClass( 'envo-is-fadein' );
                    }
                } else {
                    $item.prop( 'hidden', true ).removeClass( 'envo-is-fadein' );
                }
            } );

            $( '.envo-di-empty' ).prop( 'hidden', visible > 0 );
        },
        // Remove an imported demo, the server works in batches.
        removeDemo: function ( $item ) {
            var that = this,
                $status = $item.find( '.envo-di-history-status' ).removeClass( 'is-error' ).text( envoDemos.removing );

            $.post( envoDemos.ajaxurl, {
                action: 'envo_ajax_remove_demo',
                demo: $item.data( 'demo' ),
                security: $( '.envo-di-history' ).data( 'nonce' )
            } ).done( function ( response ) {
                if ( response && response.success && !response.data.done ) {
                    that.removeDemo( $item );
                } else if ( response && response.success ) {
                    $item.addClass( 'is-removed' ).find( '.envo-di-remove-demo' ).remove();
                    $status.text( envoDemos.removed );
                } else {
                    $status.addClass( 'is-error' ).text( ( response && response.data && response.data.message ) || envoDemos.remove_error );
                    $item.find( '.envo-di-remove-demo' ).prop( 'disabled', false ).removeClass( 'updating-message' );
                }
            } ).fail( function () {
                $status.addClass( 'is-error' ).text( envoDemos.remove_error );
                $item.find( '.envo-di-remove-demo' ).prop( 'disabled', false ).removeClass( 'updating-message' );
            } );
        },
        // Save the demo server.
        saveServer: function () {
            var $select = $( this ),
                $status = $( '.envo-di-server-status' );

            $status.removeClass( 'is-success is-error' ).text( envoDemos.server_saving );
            $select.prop( 'disabled', true );

            $.post( envoDemos.ajaxurl, {
                action: 'save_domain_switcher',
                domain: $select.val(),
                security: $select.data( 'nonce' )
            } ).done( function ( response ) {
                if ( response && response.success ) {
                    $status.addClass( 'is-success' ).text( envoDemos.server_saved );
                } else {
                    $status.addClass( 'is-error' ).text( ( response && response.data ) || envoDemos.server_error );
                }
            } ).fail( function () {
                $status.addClass( 'is-error' ).text( envoDemos.server_error );
            } ).always( function () {
                $select.prop( 'disabled', false );
            } );
        },
        // Get demo data.
        getDemoData: function ( demo_name ) {
            var that = this;

            this.currentDemo = demo_name;

            // Get import data
            this.importDataRequest = $.ajax( {
                url: envoDemos.ajaxurl,
                type: 'get',
                data: {
                    action: 'envo_ajax_get_import_data',
                    demo_name: demo_name,
                    security: envoDemos.envo_import_data_nonce
                },
                dataType: 'text',
                success: function ( response ) {
                    that.importData = $.parseJSON( response );
                }
            } );

            // Get the popup
            $.ajax( {
                url: envoDemos.ajaxurl,
                type: 'get',
                data: {
                    action: 'envo_ajax_get_demo_data',
                    demo_name: demo_name,
                    demo_data_nonce: envoDemos.demo_data_nonce
                },
                complete: function ( data ) {
                    $( '.preview-' + demo_name + ', .preview-all-' + demo_name ).hide();

                    if ( 200 !== data.status ) {
                        window.alert( envoDemos.popup_error );
                        return;
                    }
                    that.runPopup( data );
                }
            } );

        },
        // Load the popup of the current demo again.
        reloadPopup: function () {
            var that = this;

            $.get( envoDemos.ajaxurl, {
                action: 'envo_ajax_get_demo_data',
                demo_name: this.currentDemo,
                demo_data_nonce: envoDemos.demo_data_nonce
            } ).done( function ( html ) {
                $( '#envo-demo-popup-content' ).html( html );
                that.bindPopup();
            } );
        },
        // Run popup.
        runPopup: function ( data ) {
            var that = this;

            var innerWidth = $( 'html' ).innerWidth();
            $( 'html' ).css( 'overflow', 'hidden' );
            var hiddenInnerWidth = $( 'html' ).innerWidth();
            $( 'html' ).css( 'margin-right', hiddenInnerWidth - innerWidth );

            // Show popup
            $( '#envo-demo-popup-content' ).html( data.responseText );
            $( '#envo-demo-popup-wrap' ).fadeIn( 150 );
            $( '.envo-demo-popup-close' ).trigger( 'focus' );

            this.bindPopup();
        },
        // Events of the popup content.
        bindPopup: function () {
            var that = this;

            this.updateNextStep();

            // Required plugins missing - user may continue anyway
            $( '.envo-di-skip' ).on( 'click', function ( e ) {
                e.preventDefault();
                $( '.envo-plugins-next' ).removeClass( 'is-disabled' );
                that.showStep( 'content' );
            } );

            // Display the step two
            $( '.envo-plugins-next' ).on( 'click', function ( e ) {
                e.preventDefault();
                if ( !$( this ).hasClass( 'is-disabled' ) ) {
                    that.showStep( 'content' );
                }
            } );

            // Optional deletion of the existing content: warning and button label
            $( '#envo_reset_content' ).on( 'change', function () {
                var $submit = $( '#envo-demo-import-form .envo-import' ),
                    reset = $( this ).is( ':checked' );

                $( '.envo-di-warning' ).prop( 'hidden', !reset );
                $submit.toggleClass( 'is-danger', reset ).val( $submit.data( reset ? 'label-reset' : 'label' ) );
            } );

            // Back to the plugins
            $( '.envo-di-back' ).on( 'click', function ( e ) {
                e.preventDefault();
                that.showStep( 'plugins' );
            } );

            // if clicked on import data button
            $( '#envo-demo-import-form' ).on( 'submit', function ( e ) {
                e.preventDefault();

                // Vars
                var demo = $( this ).find( '[name="envo_import_demo"]' ).val(),
                    nonce = $( this ).find( '[name="envo_import_demo_data_nonce"]' ).val(),
                    contentToImport = [ ];

                // Check what need to be imported
                $( this ).find( 'input[type="checkbox"]' ).each( function () {
                    if ( $( this ).is( ':checked' ) === true ) {
                        contentToImport.push( $( this ).attr( 'name' ) );
                    }
                } );

                if ( !contentToImport.length ) {
                    return;
                }

                // Deleting content cannot be undone
                if ( -1 !== $.inArray( 'envo_reset_content', contentToImport ) && !window.confirm( $( '<div/>' ).html( envoDemos.reset_confirm ).text() ) ) {
                    return;
                }

                that.showStep( 'import' );

                // The list of import steps needs the import data
                $.when( that.importDataRequest ).always( function () {
                    that.renderImportSteps( contentToImport );

                    // Start importing the content
                    that.importContent( {
                        demo: demo,
                        nonce: nonce,
                        contentToImport: contentToImport,
                        total: contentToImport.length,
                        isXML: $( '#envo_import_xml' ).is( ':checked' )
                    } );
                } );
            } );

        },
        // Switch the popup step.
        showStep: function ( step ) {
            var steps = [ 'plugins', 'content', 'import' ],
                index = $.inArray( step, steps );

            $( '#envo-demo-plugins' ).toggle( 'plugins' === step );
            $( '#envo-demo-import-form' ).toggle( 'content' === step );
            $( '.envo-loader' ).toggle( 'import' === step );

            $( '.envo-di-stepper li' ).each( function ( i ) {
                $( this ).toggleClass( 'is-active', i === index ).toggleClass( 'is-done', i < index );
            } );
        },
        // "Next" is available once all required plugins are active.
        updateNextStep: function () {
            var $next = $( '.envo-plugins-next' ),
                missing = $( '.envo-required-plugins[data-group="required"] .envo-plugin' ).not( '.is-active' ).length;

            $next.toggleClass( 'is-disabled', missing > 0 );
            $( '.envo-di-missing' ).prop( 'hidden', 0 === missing );
        },
        // List of import steps with their state.
        renderImportSteps: function ( contentToImport ) {
            var $status = $( '.envo-import-status' ).empty();

            $.each( this.importData, function ( key, item ) {
                if ( -1 !== $.inArray( item.input_name, contentToImport ) ) {
                    $( '<p/>' ).attr( 'data-input', item.input_name ).text( item.loader ).appendTo( $status );
                }
            } );
        },
        // Update the progress bar.
        setProgress: function ( importData ) {
            var done = importData.total - importData.contentToImport.length,
                percent = importData.total ? Math.round( done / importData.total * 100 ) : 100;

            $( '.envo-di-progress' ).attr( 'aria-valuenow', percent ).find( 'span' ).css( 'width', percent + '%' );
        },
        // importing the content.
        importContent: function ( importData ) {
            var that = this,
                currentContent,
                importingLimit,
                ajaxData = {
                    envo_import_demo: importData.demo,
                    envo_import_demo_data_nonce: importData.nonce
                };

            this.allowPopupClosing = false;
            $( '.envo-demo-popup-close' ).fadeOut();

            // When all the selected content has been imported
            if ( importData.contentToImport.length === 0 ) {

                this.setProgress( importData );

                // Notify the server that the importing process is complete
                $.ajax( {
                    url: envoDemos.ajaxurl,
                    type: 'post',
                    data: {
                        action: 'envo_after_import',
                        envo_import_demo: importData.demo,
                        envo_import_demo_data_nonce: importData.nonce,
                        envo_import_is_xml: importData.isXML
                    },
                    complete: function () {
                        // Show the imported screen
                        setTimeout( function () {
                            $( '.envo-loader' ).hide();
                            $( '.envo-di-stepper li' ).removeClass( 'is-active' ).addClass( 'is-done' );
                            $( '.envo-last' ).show();
                        }, 400 );

                        that.allowPopupClosing = true;
                        $( '.envo-demo-popup-close' ).fadeIn();
                    }
                } );

                return;
            }

            // Check the content that was selected to be imported.
            for ( var key in this.importData ) {

                // Check if the current item in the iteration is in the list of importable content
                var contentIndex = $.inArray( this.importData[ key ][ 'input_name' ], importData.contentToImport );

                // If it is:
                if ( contentIndex !== -1 ) {

                    // Get a reference to the current content
                    currentContent = key;

                    // Remove the current content from the list of remaining importable content
                    importData.contentToImport.splice( contentIndex, 1 );

                    // Get the AJAX action name that corresponds to the current content
                    ajaxData.action = this.importData[ key ]['action'];

                    // After an item is found get out of the loop and execute the rest of the function
                    break;
                }
            }

            // Unknown item (import data not loaded) - stop
            if ( undefined === currentContent ) {
                this.importFailed( null, envoDemos.popup_error );
                return;
            }

            // Tell the user which content is currently being imported
            var $row = $( '.envo-import-status p[data-input="' + this.importData[ currentContent ][ 'input_name' ] + '"]' );
            $row.addClass( 'envo-importing' );

            // Tell the server to import the current content
            var ajaxRequest = $.ajax( {
                url: envoDemos.ajaxurl,
                type: 'post',
                data: ajaxData,
                dataType: 'text',
                complete: function ( data ) {
                    clearTimeout( importingLimit );

                    // Indicates if the importing of the content can continue
                    var continueProcess = true,
                        responseText = data.responseText || '';

                    // Check if the importing of the content was successful or if there was any error
                    if ( data.status >= 500 || 0 === data.status ) {
                        that.importFailed( $row, envoDemos.content_importing_error + ' ' + data.status );
                        continueProcess = false;
                    } else if ( responseText.indexOf( 'envo-reset-continue' ) !== -1 ) {
                        // More content to delete - run the same step again
                        importData.contentToImport.unshift( that.importData[ currentContent ][ 'input_name' ] );
                        $row.removeClass( 'envo-importing' );
                    } else if ( responseText.indexOf( 'successful import' ) !== -1 ) {
                        $row.addClass( 'envo-imported' ).removeClass( 'envo-importing' );
                    } else {
                        var errors = {},
                            errorMessage = '';

                        try {
                            errors = $.parseJSON( responseText );
                        } catch ( err ) {
                            errors = { xml_import_error: responseText };
                        }

                        // wp_send_json_error() response - stop the process
                        if ( errors && typeof errors.success !== 'undefined' ) {
                            errors = { xml_import_error: errors.data ? ( errors.data.message || errors.data.errorMessage || '' ) : '' };
                        }

                        // Iterate through the list of errors
                        for ( var error in errors ) {
                            errorMessage += $( '<div/>' ).html( errors[ error ] ).text() + ' ';

                            // If there was an error with the importing of the XML file, stop the process
                            if ( error === 'xml_import_error' ) {
                                continueProcess = false;
                            }
                        }

                        if ( continueProcess ) {
                            $row.removeClass( 'envo-importing' ).addClass( 'envo-importing-failed' )
                                .append( $( '<span class="envo-di-error-detail"/>' ).text( errorMessage ) );
                        } else {
                            that.importFailed( $row, errorMessage );
                        }
                    }

                    that.setProgress( importData );

                    // Continue with the loading only if an important error was not encountered
                    if ( continueProcess === true ) {

                        // Load the next content in the list
                        that.importContent( importData );
                    }

                }
            } );

            // Set a time limit of 15 minutes for the importing process.
            importingLimit = setTimeout( function () {

                // Abort the AJAX request
                ajaxRequest.abort();

                that.importFailed( $row, envoDemos.content_importing_error );
            }, 15 * 60 * 1000 );

        },
        // Stop the import with an error.
        importFailed: function ( $row, message ) {
            if ( $row && $row.length ) {
                $row.removeClass( 'envo-importing' ).addClass( 'envo-importing-failed' )
                    .append( $( '<span class="envo-di-error-detail"/>' ).text( message ) );
            } else {
                $( '<p class="envo-importing-failed"/>' ).text( message ).appendTo( '.envo-import-status' );
            }

            $( '.envo-loader' ).addClass( 'is-failed' );
            $( '.envo-di-loader-note' ).text( envoDemos.import_failed );

            this.allowPopupClosing = true;
            $( '.envo-demo-popup-close' ).fadeIn();
        },
        // Close demo popup.
        closePopup: function () {
            $( 'html' ).css( {
                'overflow': '',
                'margin-right': ''
            } );

            // Hide loader
            $( '.preview-icon' ).hide();
            $( '.preview-all' ).hide();

            // Hide demo popup
            $( '#envo-demo-popup-wrap' ).fadeOut( 150 );

            // Remove content in the popup
            setTimeout( function () {
                $( '#envo-demo-popup-content' ).html( '' );
            }, 200 );
        },
        // Mark a plugin row as active.
        markPluginActive: function ( $button ) {
            $button.removeClass( 'button-primary install-now activate-now updating-message' )
                .attr( 'disabled', 'disabled' )
                .addClass( 'disabled' )
                .text( envoDemos.button_active );

            var $row = $button.closest( '.envo-plugin' );
            $row.removeClass( 'is-missing is-inactive' ).addClass( 'is-active' );

            // A PRO plugin decides about the license and the next step - load the popup again
            if ( $row.data( 'premium' ) ) {
                EnvoDemoImport.reloadPopup();
                return;
            }

            EnvoDemoImport.updateNextStep();
        },
        // Install required plugins.
        installNow: function ( e ) {
            e.preventDefault();

            // Vars
            var $button = $( e.target ),
                $document = $( document );

            if ( $button.hasClass( 'updating-message' ) || $button.hasClass( 'button-disabled' ) ) {
                return;
            }

            if ( wp.updates.shouldRequestFilesystemCredentials && !wp.updates.ajaxLocked ) {
                wp.updates.requestFilesystemCredentials( e );

                $document.on( 'credential-modal-cancel', function () {
                    var $message = $( '.install-now.updating-message' );

                    $message
                        .removeClass( 'updating-message' )
                        .text( envoDemos.button_install );

                    wp.a11y.speak( wp.updates.l10n.updateCancel, 'polite' );
                } );
            }

            wp.updates.installPlugin( {
                slug: $button.data( 'slug' )
            } );
        },
        // Activate required plugins.
        activatePlugins: function ( e ) {
            e.preventDefault();

            // Vars
            var $button = $( e.target ),
                $init = $button.data( 'init' );

            if ( $button.hasClass( 'updating-message' ) || $button.hasClass( 'button-disabled' ) ) {
                return;
            }

            $button.addClass( 'updating-message button-primary' ).html( envoDemos.button_activating );

            $.ajax( {
                url: envoDemos.ajaxurl,
                type: 'POST',
                data: {
                    action: 'envo_ajax_required_plugins_activate',
                    init: $init,
                    security: envoDemos.envo_import_data_nonce
                },
            } ).done( function ( result ) {

                if ( result.success ) {
                    EnvoDemoImport.markPluginActive( $button );
                } else {
                    $button.removeClass( 'updating-message' ).text( envoDemos.button_activate );
                }

            } );
        },
        // Install success.
        installSuccess: function ( e, response ) {
            e.preventDefault();

            var $message = $( '.envo-plugin-' + response.slug ).find( '.button' );

            // Transform the 'Install' button into an 'Activate' button.
            var $init = $message.data( 'init' );

            $message.removeClass( 'install-now installed button-disabled updated-message' )
                .addClass( 'updating-message' )
                .html( envoDemos.button_activating );

            // WordPress adds "Activate" button after waiting for 1000ms. So we will run our activation after that.
            setTimeout( function () {

                $.ajax( {
                    url: envoDemos.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'envo_ajax_required_plugins_activate',
                        init: $init,
                        security: envoDemos.envo_import_data_nonce
                    },
                } ).done( function ( result ) {

                    if ( result.success ) {
                        EnvoDemoImport.markPluginActive( $message );
                    } else {
                        $message.removeClass( 'updating-message' );
                    }

                } );

            }, 1200 );
        },
        // Plugin installing.
        pluginInstalling: function ( e, args ) {
            e.preventDefault();

            var $card = $( '.envo-plugin-' + args.slug ),
                $button = $card.find( '.button' );

            $button.addClass( 'updating-message' );
        },
        // Plugin install error.
        installError: function ( e, response ) {
            e.preventDefault();

            var $button = $( '.envo-plugin-' + response.slug ).find( '.button' );

            $button.removeClass( 'button-primary updating-message' ).addClass( 'disabled' ).text( envoDemos.install_failed );
        }

    };

} )( jQuery );
