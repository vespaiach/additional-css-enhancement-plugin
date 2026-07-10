import { __, sprintf } from '@wordpress/i18n';
import { sha256 } from 'js-sha256';
import postcss from 'postcss';
import safeParser from 'postcss-safe-parser';

export const COMPILED_VERSION = 1;
export const SELECTOR_TOKEN = '__ACSSE_CUSTOM_CSS_SELECTOR__';

const SUPPORTED_AT_RULES = [ 'media', 'supports', 'container' ];

export function validateMarkup( css ) {
	return ! ( typeof css === 'string' && /<\/?\w/.test( css ) );
}

function hasCSSValue( css ) {
	return typeof css === 'string' && css.trim().length > 0 && validateMarkup( css );
}

function appendCompileError( errors, message ) {
	if ( ! errors.includes( message ) ) {
		errors.push( message );
	}
}

function scopeSelector( selector, errors ) {
	const selectors = selector
		.split( ',' )
		.map( ( item ) => item.trim() )
		.filter( Boolean );

	if ( selectors.length === 0 ) {
		appendCompileError( errors, __( 'Nested selectors must not be empty.', 'acsse' ) );
		return '';
	}

	const scopedSelectors = selectors.map( ( item ) => {
		if ( ! item.includes( '&' ) ) {
			appendCompileError(
				errors,
				__( 'Nested selectors must use & to target the current block.', 'acsse' )
			);
			return '';
		}

		return item.replaceAll( '&', SELECTOR_TOKEN );
	} );

	if ( scopedSelectors.some( ( item ) => ! item ) ) {
		return '';
	}

	return scopedSelectors.map( ( item ) => `:root ${ item }` ).join( ', ' );
}

function flushDeclarations( declarations, output ) {
	if ( declarations.length === 0 ) {
		return;
	}

	const rule = postcss.rule( {
		selector: `:root ${ SELECTOR_TOKEN }`,
	} );

	declarations.forEach( ( declaration ) => {
		rule.append( declaration.clone() );
	} );

	output.append( rule );
	declarations.length = 0;
}

function compileRule( ruleNode, output, errors ) {
	const scopedSelector = scopeSelector( ruleNode.selector || '', errors );

	if ( ! scopedSelector ) {
		return;
	}

	const outputRule = postcss.rule( {
		selector: scopedSelector,
	} );

	ruleNode.each( ( child ) => {
		if ( child.type === 'decl' ) {
			outputRule.append( child.clone() );
			return;
		}

		if ( child.type !== 'comment' ) {
			appendCompileError(
				errors,
				__( 'Nested rules may only contain CSS declarations.', 'acsse' )
			);
		}
	} );

	if ( outputRule.nodes && outputRule.nodes.length > 0 ) {
		output.append( outputRule );
	}
}

function compileAtRule( atRuleNode, output, errors ) {
	const atRuleName = ( atRuleNode.name || '' ).toLowerCase();

	if ( ! SUPPORTED_AT_RULES.includes( atRuleName ) || ! atRuleNode.nodes ) {
		appendCompileError(
			errors,
			sprintf(
				__( '@%s is not supported in Additional CSS.', 'acsse' ),
				atRuleNode.name
			)
		);
		return;
	}

	const outputAtRule = postcss.atRule( {
		name: atRuleNode.name,
		params: atRuleNode.params,
	} );

	compileContainer( atRuleNode, outputAtRule, errors );

	if ( outputAtRule.nodes && outputAtRule.nodes.length > 0 ) {
		output.append( outputAtRule );
	}
}

function compileContainer( input, output, errors ) {
	const declarations = [];

	input.each( ( node ) => {
		if ( node.type === 'comment' ) {
			return;
		}

		if ( node.type === 'decl' ) {
			declarations.push( node );
			return;
		}

		flushDeclarations( declarations, output );

		if ( node.type === 'rule' ) {
			compileRule( node, output, errors );
			return;
		}

		if ( node.type === 'atrule' ) {
			compileAtRule( node, output, errors );
			return;
		}

		appendCompileError(
			errors,
			__( 'Only declarations, & selectors, and supported at-rules are allowed.', 'acsse' )
		);
	} );

	flushDeclarations( declarations, output );
}

export function compileCustomCSS( css ) {
	const errors = [];

	if ( ! hasCSSValue( css ) ) {
		return {
			css: '',
			errors,
		};
	}

	try {
		const parsed = safeParser( css );
		const output = postcss.root();

		compileContainer( parsed, output, errors );

		if ( errors.length > 0 ) {
			return {
				css: '',
				errors,
			};
		}

		return {
			css: output.toString(),
			errors,
		};
	} catch ( error ) {
		return {
			css: '',
			errors: [ error.message || __( 'The custom CSS could not be parsed.', 'acsse' ) ],
		};
	}
}

export function getCompiledCache( rawCSS ) {
	const compiled = compileCustomCSS( rawCSS );

	if ( ! compiled.css || compiled.errors.length > 0 ) {
		return undefined;
	}

	return {
		version: COMPILED_VERSION,
		sourceHash: sha256( rawCSS ),
		css: compiled.css,
	};
}
