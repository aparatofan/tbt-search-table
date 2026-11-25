/**
 * TBT Search Table admin: All rows, Import from table 185, Missing CEF tag,
 * Settings. Data comes from window.TBTST; every change goes through the
 * REST routes and the screen re-renders from what they return. Every value
 * is inserted as text.
 */
( function () {
	'use strict';

	var D = window.TBTST;
	if ( ! D ) {
		return;
	}
	var PER = 50;

	/* ---------- Helpers ---------- */

	function $( s, root ) {
		return ( root || document ).querySelector( s );
	}

	function fold( s ) {
		s = String( s == null ? '' : s ).toLowerCase().replace( /ł/g, 'l' );
		if ( s.normalize ) {
			s = s.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}
		return s.replace( /[\s ]+/g, ' ' ).trim();
	}

	// el( 'td', { className: 'x', 'data-k': 1 }, [ 'text', node ] )
	function el( tag, attrs, kids ) {
		var e = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ];
			if ( v === null || v === undefined || v === false ) {
				return;
			}
			if ( k === 'className' || k === 'textContent' || k === 'value' || k === 'checked' || k === 'disabled' || k === 'type' || k === 'hidden' || k === 'htmlFor' || k === 'href' || k === 'target' || k === 'rel' || k === 'selected' ) {
				e[ k ] = v;
			} else {
				e.setAttribute( k, v === true ? '' : String( v ) );
			}
		} );
		( Array.isArray( kids ) ? kids : kids == null ? [] : [ kids ] ).forEach( function ( c ) {
			if ( c === null || c === undefined || c === false ) {
				return;
			}
			e.appendChild( typeof c === 'string' || typeof c === 'number' ? document.createTextNode( String( c ) ) : c );
		} );
		return e;
	}

	function clear( node ) {
		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}
		return node;
	}

	function plural( n, one, many ) {
		return n + ' ' + ( n === 1 ? one : many || one + 's' );
	}

	function api( method, path, body ) {
		var real = method;
		var headers = { 'X-WP-Nonce': D.nonce, Accept: 'application/json' };
		// Some hosts block PATCH and DELETE; WordPress reads the override.
		if ( method === 'PATCH' || method === 'DELETE' ) {
			headers[ 'X-HTTP-Method-Override' ] = method;
			real = 'POST';
		}
		var opts = { method: real, headers: headers, credentials: 'same-origin' };
		if ( body !== undefined ) {
			headers[ 'Content-Type' ] = 'application/json';
			opts.body = JSON.stringify( body );
		}
		return fetch( D.rest + path, opts ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! res.ok ) {
					throw new Error( ( data && data.message ) || 'Something went wrong (' + res.status + '). Try again.' );
				}
				return data;
			} );
		} );
	}

	function rowPath( key ) {
		return 'rows/' + String( key ).replace( ':', '/' );
	}

	var toastTimer;
	function toast( msg, isErr ) {
		var t = $( '#tbtst-toast' );
		t.textContent = msg;
		t.className = 'tbtst-toast' + ( isErr ? ' tbtst-toast-err' : '' );
		t.hidden = false;
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () {
			t.hidden = true;
		}, isErr ? 4000 : 2600 );
	}

	/* ---------- Modal ---------- */

	var ov = $( '#tbtst-ov' );
	var lastFocus = null;

	function openModal( box, focus ) {
		lastFocus = document.activeElement;
		clear( ov ).appendChild( box );
		ov.hidden = false;
		var f = focus ? $( focus, ov ) : null;
		if ( f ) {
			f.focus();
		}
	}

	function closeModal() {
		ov.hidden = true;
		clear( ov );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	ov.addEventListener( 'click', function ( e ) {
		if ( e.target === ov || e.target.closest( '[data-close]' ) ) {
			closeModal();
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && ! ov.hidden ) {
			closeModal();
		}
	} );

	function confirmBox( title, text, yes, onYes ) {
		var btn = el( 'button', { type: 'button', className: 'button button-primary tbtst-danger', id: 'tbtst-yes' }, yes );
		var box = el( 'div', { className: 'tbtst-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'tbtst-ch' }, [
			el( 'h2', { id: 'tbtst-ch' }, title ),
			el( 'p', null, text ),
			el( 'div', { className: 'tbtst-mact' }, [
				el( 'button', { type: 'button', className: 'button', 'data-close': true }, 'Cancel' ),
				btn
			] )
		] );
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			onYes( btn );
		} );
		openModal( box, '#tbtst-yes' );
	}

	/* ---------- Categories ---------- */

	function catNames() {
		var c = D.categories;
		return c.tools.concat( c.extras, c.wp );
	}

	function isKnown( name ) {
		return catNames().indexOf( name ) > -1;
	}

	function catSelect( selected, blank, attrs ) {
		var known = isKnown( selected );
		var sel = el( 'select', attrs || {} );
		if ( blank || ! known ) {
			sel.appendChild( el( 'option', { value: '' }, 'Pick a category…' ) );
		}
		[ [ 'Activities (tools)', D.categories.tools ], [ 'Other', D.categories.extras ], [ 'WordPress categories', D.categories.wp ] ].forEach( function ( g ) {
			if ( ! g[ 1 ].length ) {
				return;
			}
			var og = el( 'optgroup', { label: g[ 0 ] } );
			g[ 1 ].forEach( function ( n ) {
				og.appendChild( el( 'option', { value: n, selected: known && n === selected }, n ) );
			} );
			sel.appendChild( og );
		} );
		sel.value = known ? selected : '';
		return sel;
	}

	/* ---------- Data updates ---------- */

	function applyPayload( p ) {
		if ( p.rows ) {
			D.rows = p.rows;
		}
		if ( p.categories ) {
			D.categories = p.categories;
		}
		if ( p.cef ) {
			D.cef = p.cef;
		}
		if ( p.odd ) {
			D.odd = p.odd;
		}
		if ( 'last' in p ) {
			D.last = p.last;
		}
		renderRows();
		renderCef();
	}

	function applyRow( p ) {
		var i = D.rows.findIndex( function ( r ) {
			return r.key === p.row.key;
		} );
		if ( i > -1 ) {
			D.rows[ i ] = p.row;
		} else {
			D.rows.push( p.row );
			D.rows.sort( function ( a, b ) {
				return fold( a.title ) < fold( b.title ) ? -1 : fold( a.title ) > fold( b.title ) ? 1 : 0;
			} );
		}
		applyPayload( { cef: p.cef, odd: p.odd } );
	}

	/* ---------- Tabs ---------- */

	var tabs = Array.prototype.slice.call( document.querySelectorAll( '.tbtst-tabs [role="tab"]' ) );
	tabs.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			tabs.forEach( function ( x ) {
				x.setAttribute( 'aria-selected', String( x === b ) );
			} );
			document.querySelectorAll( '.tbtst-admin section[data-p]' ).forEach( function ( p ) {
				p.hidden = p.getAttribute( 'data-p' ) !== b.getAttribute( 'data-t' );
			} );
			if ( b.getAttribute( 'data-t' ) === 'import' && ! imp.scanned && ! imp.busy ) {
				scan();
			}
		} );
	} );

	/* ---------- All rows ---------- */

	var fSrc = $( '#tbtst-f-src' ), fHid = $( '#tbtst-f-hid' ), fQ = $( '#tbtst-f-q' );
	var rowsPage = 1;
	Object.keys( D.labels ).forEach( function ( k ) {
		fSrc.appendChild( el( 'option', { value: k }, D.labels[ k ] ) );
	} );

	function filteredRows() {
		var s = fSrc.value, h = fHid.value, q = fold( fQ.value );
		return D.rows.filter( function ( r ) {
			return ( ! s || r.source === s ) &&
				( ! h || ( h === 'hidden' ) === !! r.hidden ) &&
				( ! q || fold( [ r.title, r.category, r.tags.join( ' ' ), r.level, r.url ].join( ' ' ) ).indexOf( q ) > -1 );
		} );
	}

	function actionButton( act, key, label, cls ) {
		return el( 'button', { type: 'button', className: cls || '', 'data-a': act, 'data-key': key }, label );
	}

	function renderRows() {
		var list = filteredRows();
		var pages = Math.max( 1, Math.ceil( list.length / PER ) );
		rowsPage = Math.min( rowsPage, pages );
		var s = ( rowsPage - 1 ) * PER;
		var slice = list.slice( s, s + PER );
		$( '#tbtst-count' ).textContent = plural( list.length, 'row' );
		var body = clear( $( '#tbtst-rows' ) );
		if ( ! slice.length ) {
			body.appendChild( el( 'tr', null, el( 'td', { colspan: 5 }, 'No rows match.' ) ) );
		}
		slice.forEach( function ( r ) {
			var manual = r.source === 'manual';
			var acts = [ actionButton( 'edit', r.key, 'Edit' ), ' | ', actionButton( 'hide', r.key, r.hidden ? 'Show' : 'Hide' ) ];
			if ( ! manual && r.edited ) {
				acts.push( ' | ', actionButton( 'reset', r.key, 'Reset' ) );
			}
			if ( manual ) {
				acts.push( ' | ', actionButton( 'del', r.key, 'Delete', 'tbtst-del' ) );
			}
			if ( r.url ) {
				acts.push( ' | ', el( 'a', { href: r.url, target: '_blank', rel: 'noopener' }, 'Open' ) );
			}
			body.appendChild( el( 'tr', { className: r.hidden ? 'tbtst-is-hidden' : '' }, [
				el( 'td', { 'data-l': 'Title' }, [
					el( 'span', { className: 'tbtst-rt' }, r.title ),
					r.edited ? el( 'span', { className: 'tbtst-flag' }, 'Edited' ) : null,
					r.hidden ? el( 'span', { className: 'tbtst-flag tbtst-flag-hid' }, 'Hidden' ) : null,
					isKnown( r.category ) ? null : el( 'span', { className: 'tbtst-flag tbtst-flag-bad' }, 'Unknown category' ),
					el( 'div', { className: 'tbtst-ra' }, acts )
				] ),
				el( 'td', { 'data-l': 'Source' }, el( 'span', { className: 'tbtst-src' + ( manual ? ' tbtst-src-manual' : '' ) }, D.labels[ r.source ] || r.source ) ),
				el( 'td', { 'data-l': 'Category' }, r.category ),
				el( 'td', { 'data-l': 'Tags' }, r.tags.join( ', ' ) ),
				el( 'td', { 'data-l': 'Level' }, r.level )
			] ) );
		} );
		pager( $( '#tbtst-rows-pager' ), rowsPage, pages, list.length, function ( p ) {
			rowsPage = p;
			renderRows();
		} );
	}

	function pager( box, page, pages, total, go ) {
		clear( box );
		if ( pages < 2 ) {
			return;
		}
		var s = ( page - 1 ) * PER;
		box.appendChild( el( 'span', { className: 'tbtst-muted' }, ( s + 1 ) + '–' + Math.min( total, s + PER ) + ' of ' + total ) );
		var prev = el( 'button', { type: 'button', className: 'button', disabled: page === 1 }, '‹ Previous' );
		var next = el( 'button', { type: 'button', className: 'button', disabled: page === pages }, 'Next ›' );
		prev.addEventListener( 'click', function () {
			go( page - 1 );
		} );
		next.addEventListener( 'click', function () {
			go( page + 1 );
		} );
		box.appendChild( prev );
		box.appendChild( el( 'span', null, 'Page ' + page + ' of ' + pages ) );
		box.appendChild( next );
	}

	[ fSrc, fHid ].forEach( function ( x ) {
		x.addEventListener( 'change', function () {
			rowsPage = 1;
			renderRows();
		} );
	} );
	fQ.addEventListener( 'input', function () {
		rowsPage = 1;
		renderRows();
	} );

	function findRow( key ) {
		return D.rows.find( function ( r ) {
			return r.key === key;
		} );
	}

	$( '#tbtst-rows' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button[data-a]' );
		if ( ! b ) {
			return;
		}
		var r = findRow( b.getAttribute( 'data-key' ) );
		if ( ! r ) {
			return;
		}
		var act = b.getAttribute( 'data-a' );
		if ( act === 'edit' ) {
			rowForm( r );
		} else if ( act === 'hide' ) {
			b.disabled = true;
			api( 'POST', rowPath( r.key ) + '/hide', { hidden: ! r.hidden } ).then( function ( p ) {
				applyRow( p );
				toast( p.row.hidden ? 'Hidden from the VIP page' : 'Shown on the VIP page' );
			} ).catch( function ( err ) {
				b.disabled = false;
				toast( err.message, true );
			} );
		} else if ( act === 'reset' ) {
			b.disabled = true;
			api( 'POST', rowPath( r.key ) + '/reset' ).then( function ( p ) {
				applyRow( p );
				toast( 'Back to the automatic values' );
			} ).catch( function ( err ) {
				b.disabled = false;
				toast( err.message, true );
			} );
		} else if ( act === 'del' ) {
			confirmBox( 'Delete this row?', '“' + r.title + '” will be removed from the VIP table. This can\'t be undone.', 'Delete', function ( yes ) {
				api( 'DELETE', rowPath( r.key ) ).then( function () {
					D.rows = D.rows.filter( function ( x ) {
						return x.key !== r.key;
					} );
					closeModal();
					renderRows();
					toast( 'Row deleted' );
				} ).catch( function ( err ) {
					yes.disabled = false;
					toast( err.message, true );
				} );
			} );
		}
	} );

	/* ---------- Edit / Add row ---------- */

	function field( label, id, control, desc ) {
		return [
			el( 'div', { className: 'tbtst-row' }, [ el( 'label', { htmlFor: id }, label ), control ] ),
			desc ? el( 'p', { className: 'tbtst-desc' }, desc ) : null
		];
	}

	function rowForm( r ) {
		var auto = r && r.source !== 'manual';
		var url = el( 'input', { type: 'url', id: 'tbtst-wu', value: r ? r.url : '', placeholder: 'https://thebluetree.pl/…' } );
		var title = el( 'input', { type: 'text', id: 'tbtst-wt', value: r ? r.title : '', maxlength: 255 } );
		var cat = catSelect( r ? r.category : '', ! r, { id: 'tbtst-wc' } );
		var tags = el( 'input', { type: 'text', id: 'tbtst-wg', value: r ? r.tags.join( ', ' ) : '' } );
		var lvl = el( 'select', { id: 'tbtst-wl' }, [ el( 'option', { value: '' }, 'None' ) ].concat( D.levels.map( function ( l ) {
			return el( 'option', { value: l, selected: !! ( r && r.level === l ) }, l );
		} ) ) );
		var err = el( 'p', { className: 'tbtst-why', hidden: true, role: 'alert' } );
		var save = el( 'button', { type: 'button', className: 'button button-primary' }, r ? 'Save' : 'Add row' );

		var parts = [ el( 'h2', { id: 'tbtst-wh' }, r ? 'Edit row' : 'Add row' ) ];
		if ( auto ) {
			parts.push( el( 'div', { className: 'tbtst-row' }, [ el( 'span', null, 'Source' ), el( 'span', { className: 'tbtst-ro' }, ( D.labels[ r.source ] || r.source ) + ' · link is set automatically' ) ] ) );
		} else {
			parts = parts.concat( field( 'Link', 'tbtst-wu', url ) );
		}
		parts = parts.concat(
			field( 'Title', 'tbtst-wt', title ),
			field( 'Category', 'tbtst-wc', cat, r && ! isKnown( r.category ) && r.category ? '“' + r.category + '” is no longer a category. Pick a new one.' : null ),
			field( 'Tags', 'tbtst-wg', tags, 'Separate tags with commas.' + ( r && r.source === 'lesson' ? ' For lessons, the level comes from the CEF tag.' : '' ) ),
			field( 'Level', 'tbtst-wl', lvl )
		);
		if ( auto ) {
			parts.push( el( 'p', { className: 'tbtst-desc tbtst-desc-full' }, 'Your changes override the automatic values until you press Reset.' ) );
		}
		parts.push( err, el( 'div', { className: 'tbtst-mact' }, [ el( 'button', { type: 'button', className: 'button', 'data-close': true }, 'Cancel' ), save ] ) );

		var box = el( 'div', { className: 'tbtst-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'tbtst-wh' }, parts );

		function fail( msg ) {
			err.textContent = msg;
			err.hidden = false;
			save.disabled = false;
		}

		save.addEventListener( 'click', function () {
			var body = { title: title.value.trim(), category: cat.value, tags: tags.value, level: lvl.value };
			if ( ! auto ) {
				body.url = url.value.trim();
			}
			if ( ! body.title ) {
				return fail( 'Add a title.' );
			}
			if ( ! body.category ) {
				return fail( 'Pick a category.' );
			}
			if ( ! auto && ! body.url ) {
				return fail( 'Add a link.' );
			}
			save.disabled = true;
			api( r ? 'PATCH' : 'POST', r ? rowPath( r.key ) : 'rows', body ).then( function ( p ) {
				applyRow( p );
				closeModal();
				toast( r ? 'Row saved' : 'Row added' );
			} ).catch( function ( e ) {
				fail( e.message );
			} );
		} );
		box.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && e.target.tagName === 'INPUT' ) {
				e.preventDefault();
				save.click();
			}
		} );
		openModal( box, auto ? '#tbtst-wt' : '#tbtst-wu' );
	}

	$( '#tbtst-add' ).addEventListener( 'click', function () {
		rowForm( null );
	} );

	/* ---------- Refresh now ---------- */

	$( '#tbtst-refresh' ).addEventListener( 'click', function () {
		var b = this;
		var out = $( '#tbtst-refreshed' );
		b.disabled = true;
		out.textContent = 'Refreshing…';
		api( 'POST', 'refresh' ).then( function ( p ) {
			applyPayload( p );
			out.textContent = 'Refreshed · ' + plural( p.auto_count, 'automatic row' );
			if ( imp.scanned ) {
				scan();
			}
		} ).catch( function ( err ) {
			out.textContent = '';
			toast( err.message, true );
		} ).then( function () {
			b.disabled = false;
		} );
	} );

	/* ---------- Missing CEF tag ---------- */

	function renderCef() {
		var body = clear( $( '#tbtst-cef' ) );
		var n = $( '#tbtst-n-cef' );
		n.textContent = String( D.cef.length );
		n.hidden = ! D.cef.length;
		if ( ! D.cef.length ) {
			body.appendChild( el( 'tr', null, el( 'td', { colspan: 4 }, 'Every lesson has a CEF tag.' ) ) );
		}
		D.cef.forEach( function ( c ) {
			body.appendChild( el( 'tr', null, [
				el( 'td', { 'data-l': 'Lesson' }, el( 'span', { className: 'tbtst-rt' }, c.title ) ),
				el( 'td', { 'data-l': 'Category' }, c.category ),
				el( 'td', { 'data-l': 'Tags now' }, c.tags.join( ', ' ) ),
				el( 'td', null, c.edit ? el( 'a', { className: 'button', href: c.edit }, 'Edit post' ) : null )
			] ) );
		} );
		var odd = clear( $( '#tbtst-odd' ) );
		if ( ! D.odd.length ) {
			odd.appendChild( el( 'tr', null, el( 'td', { colspan: 2 }, 'None.' ) ) );
		}
		D.odd.forEach( function ( o ) {
			odd.appendChild( el( 'tr', null, [
				el( 'td', { 'data-l': 'Tag' }, o.edit ? el( 'a', { href: o.edit }, o.name ) : o.name ),
				el( 'td', { 'data-l': 'Posts' }, String( o.count ) )
			] ) );
		} );
	}

	/* ---------- Import from table 185 ---------- */

	var imp = { scanned: false, busy: false, data: null, open: {}, show: '' };
	var GROUPS = { multidecker: 'Multidecker links', file: 'Files', site: 'Links to this site', other: 'Other sites' };
	var SKIPPED = [
		[ 'empty', 'empty' ],
		[ 'rule', 'not imported (by rule)' ],
		[ 'draft', 'draft previews' ],
		[ 'covered', 'covered by a lesson or activity' ],
		[ 'dupe', 'duplicates' ],
		[ 'imported', 'already imported' ]
	];

	function scan() {
		var box = $( '#tbtst-import' );
		imp.busy = true;
		clear( box ).appendChild( el( 'p', { className: 'tbtst-muted' }, 'Scanning table 185…' ) );
		api( 'GET', 'import/scan' ).then( function ( d ) {
			imp.data = d;
			imp.scanned = true;
			renderImport();
		} ).catch( function ( err ) {
			clear( box ).appendChild( el( 'div', { className: 'tbtst-notice tbtst-error' }, err.message ) );
		} ).then( function () {
			imp.busy = false;
		} );
	}

	function allLeft() {
		var out = [];
		Object.keys( GROUPS ).forEach( function ( g ) {
			( imp.data.groups[ g ] || [] ).forEach( function ( x ) {
				out.push( x );
			} );
		} );
		return out;
	}

	function linkLine( x ) {
		return el( 'a', { className: 'tbtst-link', href: x.url, target: '_blank', rel: 'noopener' }, x.url );
	}

	function renderImport() {
		var d = imp.data;
		var box = clear( $( '#tbtst-import' ) );
		var n = $( '#tbtst-n-imp' );
		n.textContent = String( d.left );
		n.hidden = ! d.left;

		// Stats line. Each skipped count opens its rows below.
		var stats = el( 'div', { className: 'tbtst-stats' }, [ el( 'div', null, [ el( 'b', null, String( d.total ) ), 'rows in table 185' ] ) ] );
		SKIPPED.forEach( function ( s ) {
			var list = d.skipped[ s[ 0 ] ] || [];
			var b = el( 'button', { type: 'button', className: 'tbtst-stat', 'aria-expanded': String( imp.show === s[ 0 ] ), disabled: ! list.length }, [ el( 'b', null, String( list.length ) ), s[ 1 ] ] );
			b.addEventListener( 'click', function () {
				imp.show = imp.show === s[ 0 ] ? '' : s[ 0 ];
				renderImport();
			} );
			stats.appendChild( b );
		} );
		stats.appendChild( el( 'div', null, [ el( 'b', null, String( d.left ) ), 'left for you to check' ] ) );
		var notice = el( 'div', { className: 'tbtst-notice' }, stats );
		if ( imp.show ) {
			var shown = d.skipped[ imp.show ] || [];
			var t = el( 'table', { className: 'tbtst-wpt tbtst-skipped' }, [
				el( 'thead', null, el( 'tr', null, [ el( 'th', null, 'Title and link' ), imp.show === 'rule' ? el( 'th', null, 'Category in table 185' ) : null ] ) ),
				el( 'tbody', null, shown.map( function ( x ) {
					return el( 'tr', null, [
						el( 'td', null, [ el( 'b', null, x.title || '(no title)' ), el( 'br' ), x.url ? linkLine( x ) : el( 'span', { className: 'tbtst-muted' }, '(no link) · row ' + x.ref ) ] ),
						imp.show === 'rule' ? el( 'td', null, x.category || '' ) : null
					] );
				} ) )
			] );
			notice.appendChild( t );
		}
		box.appendChild( notice );

		// Undo + rescan.
		var tools = el( 'p', { className: 'tbtst-wpbar' } );
		var rescan = el( 'button', { type: 'button', className: 'button' }, 'Scan again' );
		rescan.addEventListener( 'click', scan );
		tools.appendChild( rescan );
		if ( D.last ) {
			var undo = el( 'button', { type: 'button', className: 'button' }, 'Undo last import' );
			undo.addEventListener( 'click', function () {
				confirmBox( 'Undo the last import?', 'This deletes the ' + plural( D.last.rows, 'row' ) + ' of the import on ' + batchDate( D.last.batch ) + '. Rows you have edited since are kept.', 'Undo import', function ( yes ) {
					api( 'POST', 'import/undo' ).then( function ( p ) {
						applyPayload( p );
						closeModal();
						toast( 'Removed ' + plural( p.deleted, 'row' ) + '.' + ( p.kept ? ' ' + plural( p.kept, 'row was', 'rows were' ) + ' kept because ' + ( p.kept === 1 ? 'it had' : 'they had' ) + ' been edited.' : '' ) );
						scan();
					} ).catch( function ( err ) {
						yes.disabled = false;
						toast( err.message, true );
					} );
				} );
			} );
			tools.appendChild( undo );
			tools.appendChild( el( 'span', { className: 'tbtst-muted' }, 'Last import: ' + batchDate( D.last.batch ) + ' · ' + plural( D.last.rows, 'row' ) ) );
		}
		box.appendChild( tools );

		if ( ! d.left ) {
			box.appendChild( el( 'p', null, 'Nothing left to check.' ) );
			return;
		}
		box.appendChild( el( 'p', { className: 'tbtst-muted' }, 'Tick the rows to keep as manual rows. Red categories don\'t exist on the site: pick a real one first.' ) );

		Object.keys( GROUPS ).forEach( function ( g ) {
			var items = d.groups[ g ] || [];
			if ( items.length ) {
				box.appendChild( groupSection( g, items ) );
			}
		} );

		var go = el( 'button', { type: 'button', className: 'button button-primary', id: 'tbtst-do-import' }, 'Import selected' );
		go.addEventListener( 'click', doImport );
		box.appendChild( el( 'p', null, go ) );
	}

	function batchDate( b ) {
		b = String( b || '' );
		return b.length === 14 ? b.slice( 0, 4 ) + '-' + b.slice( 4, 6 ) + '-' + b.slice( 6, 8 ) + ' ' + b.slice( 8, 10 ) + ':' + b.slice( 10, 12 ) + ' UTC' : b;
	}

	function groupSection( g, items ) {
		var open = imp.open[ g ] !== false;
		var ticked = items.filter( function ( x ) {
			return x.ticked;
		} ).length;
		// Select all leaves out links to pages that no longer exist: those
		// can only be ticked one by one.
		var pickable = items.filter( function ( x ) {
			return ! x.broken;
		} );
		var pickedN = pickable.filter( function ( x ) {
			return x.ticked;
		} ).length;
		var all = el( 'input', { type: 'checkbox', 'aria-label': 'Select all ' + GROUPS[ g ] + ( pickable.length < items.length ? ' (not the ones that no longer exist)' : '' ), disabled: ! pickable.length } );
		all.checked = pickable.length > 0 && pickedN === pickable.length;
		all.indeterminate = pickedN > 0 && pickedN < pickable.length;
		all.addEventListener( 'change', function () {
			pickable.forEach( function ( x ) {
				x.ticked = all.checked;
			} );
			if ( ! all.checked ) {
				items.forEach( function ( x ) {
					x.ticked = false;
				} );
			}
			renderImport();
		} );
		var toggle = el( 'button', { type: 'button', className: 'tbtst-gtoggle', 'aria-expanded': String( open ) }, [ ( open ? '▾ ' : '▸ ' ) + GROUPS[ g ], el( 'span', { className: 'tbtst-muted' }, ' · ' + ticked + ' of ' + items.length + ' ticked' ) ] );
		toggle.addEventListener( 'click', function () {
			imp.open[ g ] = ! open;
			renderImport();
		} );
		var setCat = catSelect( '', true, { 'aria-label': 'Category for the ticked ' + GROUPS[ g ] } );
		var apply = el( 'button', { type: 'button', className: 'button' }, 'Apply' );
		apply.addEventListener( 'click', function () {
			if ( ! setCat.value ) {
				toast( 'Pick a category to apply first', true );
				return;
			}
			items.forEach( function ( x ) {
				if ( x.ticked ) {
					x.category = setCat.value;
				}
			} );
			renderImport();
		} );

		var head = el( 'div', { className: 'tbtst-ghead' }, [
			all, toggle,
			el( 'span', { className: 'tbtst-gset' }, [ el( 'span', null, 'Set category for the ticked rows' ), setCat, apply ] )
		] );
		var section = el( 'div', { className: 'tbtst-group' }, head );
		if ( ! open ) {
			return section;
		}

		var body = el( 'tbody' );
		items.forEach( function ( x ) {
			var cb = el( 'input', { type: 'checkbox', 'aria-label': 'Keep ' + ( x.title || x.url ) } );
			cb.checked = !! x.ticked;
			cb.addEventListener( 'change', function () {
				x.ticked = cb.checked;
				renderImport();
			} );
			var sel = catSelect( x.category, true, { className: x.category ? '' : 'tbtst-badcat', 'aria-label': 'Category' } );
			sel.addEventListener( 'change', function () {
				x.category = sel.value;
				renderImport();
			} );
			var why = null;
			if ( ! x.category ) {
				why = el( 'span', { className: 'tbtst-why' }, x.raw_category ? '“' + x.raw_category + '” is not a category' : 'No category in table 185' );
			}
			body.appendChild( el( 'tr', { className: x.ticked ? '' : 'tbtst-unticked' }, [
				el( 'td', { className: 'tbtst-cb' }, cb ),
				el( 'td', { 'data-l': 'Title and link' }, [
					el( 'b', null, x.title || '(no title)' ), el( 'br' ), linkLine( x ),
					x.current_url ? el( 'div', { className: 'tbtst-now' }, [ 'Now: ', el( 'a', { href: x.current_url, target: '_blank', rel: 'noopener' }, x.current_url ) ] ) : null,
					x.reason ? el( 'div', { className: 'tbtst-reason' }, x.reason ) : null,
					x.broken ? el( 'div', { className: 'tbtst-why' }, 'The page this links to no longer exists.' + ( x.note ? ' ' + x.note : '' ) ) : null
				] ),
				el( 'td', { 'data-l': 'Category' }, [ sel, why ? el( 'br' ) : null, why ] ),
				el( 'td', { 'data-l': 'Level' }, x.level ),
				el( 'td', { 'data-l': 'Tags', className: 'tbtst-muted' }, x.tags.join( ', ' ) )
			] ) );
		} );
		section.appendChild( el( 'table', { className: 'tbtst-wpt' }, [
			el( 'thead', null, el( 'tr', null, [
				el( 'th', { className: 'tbtst-cb' }, el( 'span', { className: 'screen-reader-text' }, 'Keep' ) ),
				el( 'th', null, 'Title and link' ), el( 'th', null, 'Category' ), el( 'th', null, 'Level' ), el( 'th', null, 'Tags' )
			] ) ),
			body
		] ) );
		return section;
	}

	function doImport() {
		var b = $( '#tbtst-do-import' );
		var picks = allLeft().filter( function ( x ) {
			return x.ticked;
		} );
		if ( ! picks.length ) {
			toast( 'Tick at least one row to import', true );
			return;
		}
		var bad = picks.filter( function ( x ) {
			return ! x.category;
		} ).length;
		if ( bad ) {
			toast( 'Pick a category for ' + plural( bad, 'row' ) + ' first', true );
			return;
		}
		b.disabled = true;
		b.textContent = 'Importing…';
		api( 'POST', 'import', { rows: picks.map( function ( x ) {
			return { ref: x.ref, category: x.category };
		} ) } ).then( function ( p ) {
			applyPayload( p );
			toast( plural( p.imported, 'row' ) + ' imported as manual rows' );
			scan();
		} ).catch( function ( err ) {
			b.disabled = false;
			b.textContent = 'Import selected';
			toast( err.message, true );
		} );
	}

	/* ---------- Settings ---------- */

	function renderSettings() {
		var S = D.settings;
		var form = clear( $( '#tbtst-settings' ) );
		var kids = {};
		D.wpcats.forEach( function ( c ) {
			( kids[ c.parent ] = kids[ c.parent ] || [] ).push( c );
		} );
		var ids = {};
		D.wpcats.forEach( function ( c ) {
			ids[ c.id ] = true;
		} );

		function tree( name, chosen, parent, depth ) {
			var list = ( kids[ parent ] || [] ).slice();
			// A category whose parent isn't listed shows at the top level.
			if ( parent === 0 ) {
				D.wpcats.forEach( function ( c ) {
					if ( c.parent && ! ids[ c.parent ] ) {
						list.push( c );
					}
				} );
			}
			list.sort( function ( a, b ) {
				return fold( a.name ) < fold( b.name ) ? -1 : fold( a.name ) > fold( b.name ) ? 1 : 0;
			} );
			var ul = el( 'ul', { className: depth ? 'tbtst-tree-sub' : 'tbtst-tree' } );
			list.forEach( function ( c ) {
				var cb = el( 'input', { type: 'checkbox', name: name, value: String( c.id ) } );
				cb.checked = chosen.indexOf( c.id ) > -1;
				var li = el( 'li', null, el( 'label', null, [ cb, ' ' + c.name + ' ', el( 'span', { className: 'tbtst-muted' }, '(' + c.count + ')' ) ] ) );
				if ( kids[ c.id ] ) {
					li.appendChild( tree( name, chosen, c.id, depth + 1 ) );
				}
				ul.appendChild( li );
			} );
			return ul;
		}

		function area( name, value, rows ) {
			return el( 'textarea', { name: name, rows: rows || 4, className: 'large-text code', id: 'tbtst-s-' + name }, value );
		}

		if ( ! S.saved ) {
			form.appendChild( el( 'div', { className: 'tbtst-notice tbtst-warn' }, 'These are the defaults. Check them, then press Save settings.' ) );
		}
		var table = el( 'table', { className: 'form-table', role: 'presentation' }, el( 'tbody', null, [
			el( 'tr', null, [ el( 'th', { scope: 'row' }, 'Lesson categories' ), el( 'td', null, [
				el( 'p', { className: 'description' }, 'Published posts in any of these become lesson rows.' ),
				el( 'div', { className: 'tbtst-checks' }, tree( 'lesson_cats', S.lesson_cats, 0, 0 ) )
			] ) ] ),
			el( 'tr', null, [ el( 'th', { scope: 'row' }, 'Never list posts in' ), el( 'td', null, [
				el( 'p', { className: 'description' }, 'A post in any of these is left out, even if it is also in a lesson category (for example the free copies).' ),
				el( 'div', { className: 'tbtst-checks' }, tree( 'never_cats', S.never_cats, 0, 0 ) )
			] ) ] ),
			el( 'tr', null, [ el( 'th', { scope: 'row' }, el( 'label', { htmlFor: 'tbtst-s-extra_cats' }, 'Extra categories' ) ), el( 'td', null, [
				area( 'extra_cats', S.extra_cats ),
				el( 'p', { className: 'description' }, 'One per line. Listed under Other in the dropdowns and under Activities on the VIP page. Removing one that rows still use marks those rows Unknown category.' )
			] ) ] ),
			el( 'tr', null, [ el( 'th', { scope: 'row' }, el( 'label', { htmlFor: 'tbtst-s-md_hosts' }, 'Multidecker hosts' ) ), el( 'td', null, [
				area( 'md_hosts', S.md_hosts, 3 ),
				el( 'p', { className: 'description' }, 'One per line. Table 185 links to these hosts are grouped as Multidecker links.' )
			] ) ] ),
			el( 'tr', null, [ el( 'th', { scope: 'row' }, el( 'label', { htmlFor: 'tbtst-s-skip_cats' }, 'Never import table 185 categories' ) ), el( 'td', null, [
				area( 'skip_cats', S.skip_cats, 5 ),
				el( 'p', { className: 'description' }, 'One per line. A table 185 row is skipped when any of its categories is one of these (case, spaces and & vs “and” are ignored).' )
			] ) ] )
		] ) );
		form.appendChild( table );
		var save = el( 'button', { type: 'submit', className: 'button button-primary' }, 'Save settings' );
		form.appendChild( el( 'p', { className: 'submit' }, save ) );
	}

	$( '#tbtst-settings' ).addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var form = e.target;
		var btn = $( 'button[type="submit"]', form );
		function checked( name ) {
			return Array.prototype.map.call( form.querySelectorAll( 'input[name="' + name + '"]:checked' ), function ( i ) {
				return parseInt( i.value, 10 );
			} );
		}
		var body = {
			lesson_cats: checked( 'lesson_cats' ),
			never_cats: checked( 'never_cats' ),
			extra_cats: form.elements.extra_cats.value,
			md_hosts: form.elements.md_hosts.value,
			skip_cats: form.elements.skip_cats.value
		};
		btn.disabled = true;
		api( 'POST', 'settings', body ).then( function ( p ) {
			D.settings = p.settings;
			applyPayload( p );
			renderSettings();
			toast( 'Settings saved · ' + plural( p.auto_count, 'automatic row' ) );
			imp.scanned = false;
		} ).catch( function ( err ) {
			btn.disabled = false;
			toast( err.message, true );
		} );
	} );

	renderRows();
	renderCef();
	renderSettings();
} )();
