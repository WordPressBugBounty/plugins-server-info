( function () {
	'use strict';

	var checkoutLoader;

	function loadCheckout( config ) {
		if ( window.FS && window.FS.Checkout ) {
			return window.Promise.resolve();
		}

		if ( checkoutLoader ) {
			return checkoutLoader;
		}

		checkoutLoader = new window.Promise( function ( resolve, reject ) {
			var script = document.createElement( 'script' );
			script.src = config.checkoutUrl;
			script.async = true;
			script.dataset.serverInfoCheckout = 'true';
			script.onload = function () {
				if ( window.FS && window.FS.Checkout ) {
					resolve();
					return;
				}

				reject( new Error( 'checkout_unavailable' ) );
			};
			script.onerror = function () {
				reject( new Error( 'checkout_unavailable' ) );
			};
			document.head.appendChild( script );
		} );

		return checkoutLoader;
	}

	function initSupportCheckout() {
		var config = window.serverInfoAdmin;
		var triggers = Array.prototype.slice.call( document.querySelectorAll( '[data-server-info-support-open]' ) );
		var panel = document.getElementById( 'server-info-support-panel' );
		var closeButtons = Array.prototype.slice.call( document.querySelectorAll( '[data-server-info-support-close]' ) );
		var closeButton = document.querySelector( '.si-support-panel-close' );
		var planButtons = Array.prototype.slice.call( document.querySelectorAll( '[data-server-info-support-plan]' ) );
		var message = document.getElementById( 'server-info-support-message' );
		var returnFocus;

		if ( ! config || ! panel || ! triggers.length || ! closeButton || ! planButtons.length ) {
			return;
		}

		function setMessage( text, isError ) {
			if ( ! message ) {
				return;
			}

			message.textContent = text;
			message.classList.toggle( 'is-error', Boolean( isError ) );
		}

		function resetPlans() {
			planButtons.forEach( function ( button ) {
				button.disabled = false;
				button.classList.remove( 'is-loading' );
			} );
		}

		function openPanel( trigger ) {
			returnFocus = trigger;
			panel.hidden = false;
			panel.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'server-info-support-open' );
			window.requestAnimationFrame( function () {
				closeButton.focus();
			} );
		}

		function closePanel() {
			panel.hidden = true;
			panel.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'server-info-support-open' );
			setMessage( '', false );
			resetPlans();

			if ( returnFocus && document.contains( returnFocus ) ) {
				returnFocus.focus();
			}
		}

		triggers.forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				openPanel( trigger );
			} );
		} );

		closeButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				closePanel();
			} );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			var focusable;
			var first;
			var last;

			if ( panel.hidden ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closePanel();
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			focusable = [ closeButton ].concat( planButtons.filter( function ( button ) {
				return ! button.disabled;
			} ) );
			first = focusable[ 0 ];
			last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		planButtons.forEach( function ( planButton ) {
			planButton.addEventListener( 'click', function () {
				var planId = planButton.dataset.serverInfoSupportPlan;

				if ( ! planId ) {
					return;
				}

				planButtons.forEach( function ( button ) {
					button.disabled = true;
				} );
				planButton.classList.add( 'is-loading' );
				setMessage( config.loadingLabel, false );

				loadCheckout( config )
					.then( function () {
						var handler = new window.FS.Checkout( {
							product_id: config.productId,
							public_key: config.publicKey
						} );

						handler.open( {
							name: config.productName,
							plan_id: planId,
							licenses: 1,
							billing_cycle: 'lifetime',
							purchaseCompleted: function () {
								setMessage( config.thankYouLabel, false );
							},
							success: function () {
								resetPlans();
								setMessage( config.thankYouLabel, false );
							},
							cancel: function () {
								resetPlans();
								setMessage( '', false );
							}
						} );
					} )
					.catch( function () {
						checkoutLoader = null;
						resetPlans();
						setMessage( config.unavailableLabel, true );
					} );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initSupportCheckout );
	} else {
		initSupportCheckout();
	}
}() );
