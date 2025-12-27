/**
 * Content Freshness Monitor - Gutenberg Sidebar Panel
 *
 * Displays content freshness status in the block editor sidebar.
 */

( function( wp ) {
    'use strict';

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var Fragment = wp.element.Fragment;
    var createElement = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var PanelBody = wp.components.PanelBody;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Icon = wp.components.Icon;
    var useSelect = wp.data.useSelect;

    // Get localized data
    var data = window.cfmGutenberg || {};
    var i18n = data.i18n || {};

    /**
     * Freshness status icon
     */
    function FreshnessIcon( props ) {
        var status = props.status;
        var iconPath;

        if ( status === 'cfm-fresh' ) {
            // Checkmark icon for fresh
            iconPath = 'M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z';
        } else if ( status === 'cfm-aging' ) {
            // Clock icon for aging
            iconPath = 'M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8zm0 14c-3.3 0-6-2.7-6-6s2.7-6 6-6 6 2.7 6 6-2.7 6-6 6zm.5-6V8h-1.5v5l4.3 2.5.7-1.2-3.5-2.3z';
        } else {
            // Warning icon for stale
            iconPath = 'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z';
        }

        return createElement( 'svg', {
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: '0 0 24 24',
            width: 24,
            height: 24,
            className: 'cfm-sidebar-icon ' + status
        }, createElement( 'path', { d: iconPath } ) );
    }

    /**
     * Status badge component
     */
    function StatusBadge( props ) {
        return createElement( 'span', {
            className: 'cfm-status-badge ' + props.statusClass
        }, props.label );
    }

    /**
     * Main sidebar panel component
     */
    function ContentFreshnessPanel() {
        var postModified = useSelect( function( select ) {
            return select( 'core/editor' ).getEditedPostAttribute( 'modified' );
        }, [] );

        var _useState = useState( data.daysOld );
        var daysOld = _useState[0];
        var setDaysOld = _useState[1];

        var _useState2 = useState( data.status );
        var status = _useState2[0];
        var setStatus = _useState2[1];

        var _useState3 = useState( data.statusClass );
        var statusClass = _useState3[0];
        var setStatusClass = _useState3[1];

        var _useState4 = useState( data.lastReviewed );
        var lastReviewed = _useState4[0];
        var setLastReviewed = _useState4[1];

        var _useState5 = useState( false );
        var isReviewing = _useState5[0];
        var setIsReviewing = _useState5[1];

        var _useState6 = useState( false );
        var justReviewed = _useState6[0];
        var setJustReviewed = _useState6[1];

        // Update freshness when post is modified
        useEffect( function() {
            if ( postModified ) {
                var modified = new Date( postModified );
                var now = new Date();
                var diffDays = Math.floor( ( now - modified ) / ( 1000 * 60 * 60 * 24 ) );
                setDaysOld( diffDays );

                // Update status based on new days
                var threshold = data.threshold;
                if ( diffDays < threshold * 0.5 ) {
                    setStatus( i18n.fresh );
                    setStatusClass( 'cfm-fresh' );
                } else if ( diffDays < threshold ) {
                    setStatus( i18n.aging );
                    setStatusClass( 'cfm-aging' );
                } else {
                    setStatus( i18n.stale );
                    setStatusClass( 'cfm-stale' );
                }
            }
        }, [ postModified ] );

        // Get status description
        function getStatusDescription() {
            if ( statusClass === 'cfm-fresh' ) {
                return i18n.freshDesc;
            } else if ( statusClass === 'cfm-aging' ) {
                return i18n.agingDesc;
            }
            return i18n.staleDesc;
        }

        // Mark as reviewed handler
        function handleMarkReviewed() {
            setIsReviewing( true );

            var formData = new FormData();
            formData.append( 'action', 'cfm_mark_reviewed' );
            formData.append( 'nonce', data.nonce );
            formData.append( 'post_id', data.postId );

            fetch( data.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            } )
            .then( function( response ) {
                return response.json();
            } )
            .then( function( result ) {
                setIsReviewing( false );
                if ( result.success ) {
                    setLastReviewed( result.data.date );
                    setJustReviewed( true );
                    setTimeout( function() {
                        setJustReviewed( false );
                    }, 2000 );
                }
            } )
            .catch( function() {
                setIsReviewing( false );
            } );
        }

        // Format date for display
        function formatDate( dateStr ) {
            if ( ! dateStr ) {
                return i18n.never;
            }
            var date = new Date( dateStr.replace( ' ', 'T' ) );
            return date.toLocaleDateString( undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            } );
        }

        return createElement( Fragment, {},
            createElement( PluginSidebarMoreMenuItem, {
                target: 'cfm-sidebar',
                icon: createElement( FreshnessIcon, { status: statusClass } )
            }, i18n.title ),
            createElement( PluginSidebar, {
                name: 'cfm-sidebar',
                title: i18n.title,
                icon: createElement( FreshnessIcon, { status: statusClass } )
            },
                createElement( PanelBody, {
                    title: i18n.status,
                    initialOpen: true
                },
                    createElement( 'div', { className: 'cfm-sidebar-status' },
                        createElement( FreshnessIcon, { status: statusClass } ),
                        createElement( 'div', { className: 'cfm-sidebar-status-text' },
                            createElement( StatusBadge, {
                                label: status,
                                statusClass: statusClass
                            } ),
                            createElement( 'p', { className: 'cfm-sidebar-description' },
                                getStatusDescription()
                            )
                        )
                    )
                ),
                createElement( PanelBody, {
                    title: i18n.lastModified,
                    initialOpen: true
                },
                    createElement( 'div', { className: 'cfm-sidebar-info' },
                        createElement( 'div', { className: 'cfm-info-row' },
                            createElement( 'span', { className: 'cfm-info-label' }, i18n.daysOld + ':' ),
                            createElement( 'span', { className: 'cfm-info-value cfm-days-value ' + statusClass },
                                daysOld + ' ' + i18n.days
                            )
                        ),
                        createElement( 'div', { className: 'cfm-info-row' },
                            createElement( 'span', { className: 'cfm-info-label' }, i18n.threshold + ':' ),
                            createElement( 'span', { className: 'cfm-info-value' },
                                data.threshold + ' ' + i18n.days
                            )
                        ),
                        createElement( 'div', { className: 'cfm-info-row' },
                            createElement( 'span', { className: 'cfm-info-label' }, i18n.lastReviewed + ':' ),
                            createElement( 'span', { className: 'cfm-info-value' },
                                formatDate( lastReviewed )
                            )
                        )
                    )
                ),
                createElement( PanelBody, {
                    title: '',
                    initialOpen: true
                },
                    createElement( 'div', { className: 'cfm-sidebar-actions' },
                        createElement( Button, {
                            isPrimary: true,
                            isBusy: isReviewing,
                            disabled: isReviewing || justReviewed,
                            onClick: handleMarkReviewed,
                            className: 'cfm-review-button' + ( justReviewed ? ' cfm-reviewed' : '' )
                        },
                            isReviewing ? i18n.reviewing :
                            ( justReviewed ? i18n.reviewed : i18n.markReviewed )
                        ),
                        createElement( Button, {
                            isSecondary: true,
                            href: data.settingsUrl,
                            className: 'cfm-settings-button'
                        }, i18n.viewSettings )
                    )
                )
            )
        );
    }

    // Register the plugin
    registerPlugin( 'content-freshness-monitor', {
        render: ContentFreshnessPanel,
        icon: null
    } );

} )( window.wp );
