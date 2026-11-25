/**
 * TBT Search Table: filters, sorting and paging on the VIP page.
 *
 * Rows come from the data-rows attribute: [title, category, tags[], level,
 * url, is_tool]. Every value is inserted as text, never as HTML.
 */
( function () {
	'use strict';

	var LEVELS = [ 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' ];
	var ACCENTS = /[̀-ͯ]/g;

	// Same as TBTST_Format::fold(): lower-case, no accents, ł → l, spaces collapsed.
	function fold( s ) {
		s = String( s == null ? '' : s ).toLowerCase().replace( /ł/g, 'l' );
		if ( s.normalize ) {
			s = s.normalize( 'NFD' ).replace( ACCENTS, '' );
		}
		return s.replace( /[\s ]+/g, ' ' ).trim();
	}

	function el( tag, cls, text ) {
		var e = document.createElement( tag );
		if ( cls ) {
			e.className = cls;
		}
		if ( text != null ) {
			e.textContent = text;
		}
		return e;
	}

	function sameSite( url ) {
		try {
			var u = new URL( url, location.href );
			return u.host.replace( /^www\./, '' ) === location.host.replace( /^www\./, '' );
		} catch ( e ) {
			return true;
		}
	}

	function init( root ) {
		var raw;
		try {
			raw = JSON.parse( root.getAttribute( 'data-rows' ) || '[]' );
		} catch ( e ) {
			raw = [];
		}
		var rows = raw.map( function ( r ) {
			var tags = Array.isArray( r[ 2 ] ) ? r[ 2 ].map( String ) : [];
			return {
				title: String( r[ 0 ] || '' ),
				cat: String( r[ 1 ] || '' ),
				tags: tags,
				level: String( r[ 3 ] || '' ),
				url: String( r[ 4 ] || '' ),
				tool: !! r[ 5 ],
				fTitle: fold( r[ 0 ] ),
				fCat: fold( r[ 1 ] ),
				fTags: tags.map( fold ),
				fAll: fold( [ r[ 0 ], r[ 1 ], tags.join( ' ' ), r[ 3 ] ].join( ' ' ) )
			};
		} );

		var f = {};
		[ 'title', 'cat', 'tags', 'level', 'per', 'q' ].forEach( function ( k ) {
			f[ k ] = root.querySelector( '[data-f="' + k + '"]' );
		} );
		var tbody = root.querySelector( 'tbody' );
		var range = root.querySelector( '[data-r="range"]' );
		var pages = root.querySelector( '[data-r="pages"]' );
		var active = root.querySelector( '[data-r="active"]' );
		var heads = Array.prototype.slice.call( root.querySelectorAll( 'th' ) );
		var labels = heads.map( function ( th ) {
			return th.textContent.replace( /[↕↑↓]/g, '' ).trim();
		} );
		var st = { sort: 'title', dir: 1, page: 1 };

		// Category filter: only categories with a visible row, in two groups.
		( function fillCats() {
			var lessons = {}, acts = {};
			rows.forEach( function ( r ) {
				if ( r.cat ) {
					( r.tool ? acts : lessons )[ r.cat ] = true;
				}
			} );
			[ [ 'Lessons', lessons ], [ 'Activities', acts ] ].forEach( function ( g ) {
				var names = Object.keys( g[ 1 ] ).sort( function ( a, b ) {
					return fold( a ) < fold( b ) ? -1 : fold( a ) > fold( b ) ? 1 : 0;
				} );
				if ( ! names.length ) {
					return;
				}
				var og = document.createElement( 'optgroup' );
				og.label = g[ 0 ];
				names.forEach( function ( n ) {
					og.appendChild( el( 'option', null, n ) );
				} );
				f.cat.appendChild( og );
			} );
		} )();

		function lvlRank( l ) {
			var i = LEVELS.indexOf( l );
			return i < 0 ? 99 : i;
		}

		function list() {
			var t = fold( f.title.value ), c = f.cat.value, g = fold( f.tags.value ), l = f.level.value, q = fold( f.q.value );
			var out = rows.filter( function ( r ) {
				return ( ! t || r.fTitle.indexOf( t ) > -1 ) &&
					( ! c || r.cat === c ) &&
					( ! g || r.fTags.some( function ( x ) { return x.indexOf( g ) > -1; } ) ) &&
					( ! l || r.level === l ) &&
					( ! q || r.fAll.indexOf( q ) > -1 );
			} );
			out.sort( function ( a, b ) {
				var A, B;
				if ( st.sort === 'level' ) {
					A = lvlRank( a.level );
					B = lvlRank( b.level );
				} else if ( st.sort === 'cat' ) {
					A = a.fCat;
					B = b.fCat;
				} else {
					A = a.fTitle;
					B = b.fTitle;
				}
				var c1 = ( A < B ? -1 : A > B ? 1 : 0 ) * st.dir;
				if ( c1 ) {
					return c1;
				}
				return a.fTitle < b.fTitle ? -1 : a.fTitle > b.fTitle ? 1 : 0;
			} );
			return out;
		}

		function td( i, cls ) {
			var c = el( 'td', cls );
			c.setAttribute( 'data-l', labels[ i ] || '' );
			return c;
		}

		function renderRows( slice ) {
			tbody.textContent = '';
			if ( ! slice.length ) {
				var tr0 = el( 'tr', 'tbtst-empty' );
				var c0 = el( 'td', null, 'No lessons or activities match. Try fewer filters.' );
				c0.colSpan = 5;
				tr0.appendChild( c0 );
				tbody.appendChild( tr0 );
				return;
			}
			slice.forEach( function ( r ) {
				var tr = el( 'tr' );
				var c1 = td( 0, 'tbtst-t' );
				c1.textContent = r.title;
				var c2 = td( 1, r.tool ? 'tbtst-cat-tool' : '' );
				c2.textContent = r.cat;
				var c3 = td( 2 );
				r.tags.forEach( function ( x ) {
					var b = el( 'button', 'tbtst-tag', x );
					b.type = 'button';
					b.setAttribute( 'data-tag', x );
					c3.appendChild( b );
				} );
				var c4 = td( 3 );
				if ( r.level ) {
					c4.appendChild( el( 'span', 'tbtst-lvl', r.level ) );
				}
				var c5 = td( 4 );
				if ( r.url ) {
					var a = el( 'a', 'tbtst-open', 'Open' );
					a.href = r.url;
					if ( ! sameSite( r.url ) ) {
						a.target = '_blank';
						a.rel = 'noopener';
					}
					a.setAttribute( 'aria-label', 'Open ' + r.title );
					c5.appendChild( a );
				}
				[ c1, c2, c3, c4, c5 ].forEach( function ( c ) {
					tr.appendChild( c );
				} );
				tbody.appendChild( tr );
			} );
		}

		function pageButton( label, p, opts ) {
			var b = el( 'button', 'tbtst-pg', label );
			b.type = 'button';
			b.setAttribute( 'data-p', String( p ) );
			if ( opts.disabled ) {
				b.disabled = true;
			}
			if ( opts.current ) {
				b.setAttribute( 'aria-current', 'page' );
			}
			if ( opts.aria ) {
				b.setAttribute( 'aria-label', opts.aria );
			}
			return b;
		}

		function renderPager( total, per ) {
			var n = Math.max( 1, Math.ceil( total / per ) );
			pages.textContent = '';
			pages.appendChild( pageButton( '‹', st.page - 1, { disabled: st.page === 1, aria: 'Previous page' } ) );
			var last = 0;
			for ( var i = 1; i <= n; i++ ) {
				if ( i === 1 || i === n || Math.abs( i - st.page ) <= 1 ) {
					if ( last && i - last > 1 ) {
						pages.appendChild( el( 'span', 'tbtst-gap', '…' ) );
					}
					pages.appendChild( pageButton( String( i ), i, { current: i === st.page } ) );
					last = i;
				}
			}
			pages.appendChild( pageButton( '›', st.page + 1, { disabled: st.page === n, aria: 'Next page' } ) );
		}

		function renderActive() {
			var act = [ [ 'title', 'Title' ], [ 'cat', 'Category' ], [ 'tags', 'Tags' ], [ 'level', 'Level' ], [ 'q', 'Search' ] ].filter( function ( a ) {
				return f[ a[ 0 ] ].value.trim();
			} );
			active.textContent = '';
			active.hidden = ! act.length;
			if ( ! act.length ) {
				return;
			}
			active.appendChild( document.createTextNode( 'Filters: ' ) );
			act.forEach( function ( a ) {
				var b = el( 'button', 'tbtst-chip', a[ 1 ] + ': ' + f[ a[ 0 ] ].value.trim() + ' ✕' );
				b.type = 'button';
				b.setAttribute( 'data-clr', a[ 0 ] );
				b.setAttribute( 'aria-label', 'Clear the ' + a[ 1 ] + ' filter' );
				active.appendChild( b );
			} );
			if ( act.length > 1 ) {
				var all = el( 'button', 'tbtst-chip', 'Clear all' );
				all.type = 'button';
				all.setAttribute( 'data-clr', 'all' );
				active.appendChild( all );
			}
		}

		function render() {
			var all = list();
			var per = parseInt( f.per.value, 10 ) || 10;
			var n = Math.max( 1, Math.ceil( all.length / per ) );
			st.page = Math.min( Math.max( 1, st.page ), n );
			var s = ( st.page - 1 ) * per;
			var slice = all.slice( s, s + per );
			renderRows( slice );
			range.textContent = all.length ? 'Showing ' + ( s + 1 ) + ' to ' + ( s + slice.length ) + ' of ' + all.length + ' entries' : 'Showing 0 entries';
			renderPager( all.length, per );
			heads.forEach( function ( th ) {
				var k = th.getAttribute( 'data-k' );
				if ( ! k ) {
					return;
				}
				var on = k === st.sort;
				th.setAttribute( 'aria-sort', on ? ( st.dir > 0 ? 'ascending' : 'descending' ) : 'none' );
				th.querySelector( '.tbtst-arr' ).textContent = on ? ( st.dir > 0 ? '↑' : '↓' ) : '↕';
			} );
			renderActive();
		}

		function reset() {
			st.page = 1;
			render();
		}

		[ 'title', 'tags', 'q' ].forEach( function ( k ) {
			f[ k ].addEventListener( 'input', reset );
		} );
		[ 'cat', 'level', 'per' ].forEach( function ( k ) {
			f[ k ].addEventListener( 'change', reset );
		} );
		root.querySelector( 'thead' ).addEventListener( 'click', function ( e ) {
			var th = e.target.closest( 'th[data-k]' );
			if ( ! th ) {
				return;
			}
			var k = th.getAttribute( 'data-k' );
			st.dir = st.sort === k ? -st.dir : 1;
			st.sort = k;
			render();
		} );
		tbody.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.tbtst-tag' );
			if ( b ) {
				f.tags.value = b.getAttribute( 'data-tag' );
				reset();
			}
		} );
		pages.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.tbtst-pg' );
			if ( b && ! b.disabled ) {
				st.page = parseInt( b.getAttribute( 'data-p' ), 10 ) || 1;
				render();
				var top = root.getBoundingClientRect().top;
				if ( top < 0 ) {
					root.scrollIntoView( { block: 'start' } );
				}
			}
		} );
		active.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-clr]' );
			if ( ! b ) {
				return;
			}
			var k = b.getAttribute( 'data-clr' );
			( k === 'all' ? [ 'title', 'cat', 'tags', 'level', 'q' ] : [ k ] ).forEach( function ( x ) {
				f[ x ].value = '';
			} );
			reset();
		} );

		render();
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.tbtst[data-rows]' ), init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
