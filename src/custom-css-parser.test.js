import { sha256 } from 'js-sha256';

import {
	COMPILED_VERSION,
	SELECTOR_TOKEN,
	compileCustomCSS,
	getCompiledCache,
	validateMarkup,
} from './custom-css-parser';

describe( 'validateMarkup', () => {
	it( 'rejects markup-looking CSS values', () => {
		expect( validateMarkup( '<style>.foo { color: red; }</style>' ) ).toBe( false );
		expect( validateMarkup( 'color: red;' ) ).toBe( true );
		expect( validateMarkup( null ) ).toBe( true );
	} );
} );

describe( 'compileCustomCSS', () => {
	it( 'compiles top-level declarations into the selector token scope', () => {
		expect( compileCustomCSS( 'color: red; padding: 10px;' ) ).toEqual( {
			css: `:root ${ SELECTOR_TOKEN } {color: red; padding: 10px\n}`,
			errors: [],
		} );
	} );

	it( 'compiles nested selectors using the current block token', () => {
		expect( compileCustomCSS( '&:hover { color: blue; }' ) ).toEqual( {
			css: `:root ${ SELECTOR_TOKEN }:hover { color: blue\n}`,
			errors: [],
		} );
	} );

	it( 'scopes each comma-separated nested selector', () => {
		expect( compileCustomCSS( '&:hover, &.is-active { color: blue; }' ) ).toEqual( {
			css: `:root ${ SELECTOR_TOKEN }:hover, :root ${ SELECTOR_TOKEN }.is-active { color: blue\n}`,
			errors: [],
		} );
	} );

	it( 'preserves supported wrapper at-rules', () => {
		const css = [
			'@media (min-width: 600px) { color: red; &.wide { padding: 20px; } }',
			'@supports (display: grid) { display: grid; }',
			'@container (min-width: 400px) { gap: 20px; }',
		].join( '\n' );

		expect( compileCustomCSS( css ) ).toEqual( {
			css: [
				`@media (min-width: 600px) {\n    :root ${ SELECTOR_TOKEN } { color: red\n    }\n    :root ${ SELECTOR_TOKEN }.wide { padding: 20px\n    }\n}`,
				`@supports (display: grid) {\n    :root ${ SELECTOR_TOKEN } { display: grid\n    }\n}`,
				`@container (min-width: 400px) {\n    :root ${ SELECTOR_TOKEN } { gap: 20px\n    }\n}`,
			].join( '\n' ),
			errors: [],
		} );
	} );

	it( 'ignores comments', () => {
		expect( compileCustomCSS( '/* hello */ color: red; &::before { /* x */ content: ""; }' ) ).toEqual( {
			css: [
				`:root ${ SELECTOR_TOKEN } { color: red\n}`,
				`:root ${ SELECTOR_TOKEN }::before { content: ""\n}`,
			].join( '\n' ),
			errors: [],
		} );
	} );

	it.each( [ '', '   ', null, undefined, 12, { css: 'color: red;' }, '<div>bad</div>' ] )(
		'returns empty CSS without parser errors for unusable value %#',
		( value ) => {
			expect( compileCustomCSS( value ) ).toEqual( {
				css: '',
				errors: [],
			} );
		}
	);

	it( 'rejects nested selectors that do not include ampersands', () => {
		expect( compileCustomCSS( '.child { color: red; }' ) ).toEqual( {
			css: '',
			errors: [ 'Nested selectors must use & to target the current block.' ],
		} );
	} );

	it( 'rejects empty nested selectors', () => {
		expect( compileCustomCSS( '{ color: red; }' ) ).toEqual( {
			css: '',
			errors: [ 'Nested selectors must not be empty.' ],
		} );
	} );

	it( 'rejects unsupported at-rules', () => {
		expect( compileCustomCSS( '@keyframes spin { from { opacity: 0; } to { opacity: 1; } }' ) ).toEqual( {
			css: '',
			errors: [ '@keyframes is not supported in Additional CSS.' ],
		} );
	} );

	it( 'rejects nested rules with non-declaration children', () => {
		expect( compileCustomCSS( '& .child { color: red; & .grandchild { color: blue; } }' ) ).toEqual( {
			css: '',
			errors: [ 'Nested rules may only contain CSS declarations.' ],
		} );
	} );
} );

describe( 'getCompiledCache', () => {
	it( 'returns a versioned hash and compiled CSS for valid raw CSS', () => {
		const rawCSS = 'color: red;';

		expect( getCompiledCache( rawCSS ) ).toEqual( {
			version: COMPILED_VERSION,
			sourceHash: sha256( rawCSS ),
			css: `:root ${ SELECTOR_TOKEN } {color: red\n}`,
		} );
	} );

	it( 'returns undefined for invalid raw CSS', () => {
		expect( getCompiledCache( '.child { color: red; }' ) ).toBeUndefined();
	} );
} );
