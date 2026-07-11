import { InspectorControls, store as blockEditorStore, useBlockEditingMode } from '@wordpress/block-editor';
import * as blockEditor from '@wordpress/block-editor';
import { addFilter } from '@wordpress/hooks';
import { BaseControl, Button, Modal, Notice, TextareaControl } from '@wordpress/components';
import * as components from '@wordpress/components';
import { Fragment, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';
import { getBlockType } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import {
	SELECTOR_TOKEN,
	compileCustomCSS,
	getCompiledCache,
	validateMarkup,
} from './custom-css-parser';
import supportedBlocks from '../supported-blocks.json';

const COMPILED_ATTRIBUTE = 'acsseCompiledCss';
const VStack =
	components.VStack ||
	components.__experimentalVStack ||
	( ( { children } ) => <div>{ children }</div> );
const useBlockStyleOverride = blockEditor.useStyleOverride || function () {};
const expandIcon = (
	<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
		<path d="M6 9V6h3V4.5H4.5V9H6Zm9-3h3v3h1.5V4.5H15V6Zm3 9v3h-3v1.5h4.5V15H18ZM9 18H6v-3H4.5v4.5H9V18Z" />
	</svg>
);

function supportsAdditionalCSSEnhancement( name ) {
	return supportedBlocks.includes( name );
}

function cleanEmptyObject( value ) {
	if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
		return value;
	}

	const output = Object.keys( value ).reduce( ( result, key ) => {
		const item = cleanEmptyObject( value[ key ] );

		if (
			item === undefined ||
			item === null ||
			( typeof item === 'object' &&
				! Array.isArray( item ) &&
				Object.keys( item ).length === 0 )
		) {
			return result;
		}

		result[ key ] = item;
		return result;
	}, {} );

	return Object.keys( output ).length ? output : undefined;
}

function getCustomCSS( attributes ) {
	return attributes.style && typeof attributes.style.css === 'string' ? attributes.style.css : '';
}

function setCustomCSS( props, value ) {
	const attributes = props.attributes || {};
	const style = { ...( attributes.style || {} ) };

	if ( value && value.trim() ) {
		style.css = value;
	} else {
		delete style.css;
	}

	props.setAttributes( {
		style: cleanEmptyObject( style ),
		[ COMPILED_ATTRIBUTE ]: getCompiledCache( value ),
	} );
}

function getEditorBlockSelector( clientId ) {
	return `[data-block="${ clientId }"]`;
}

function addAdditionalCSSEnhancementAttribute( settings, name ) {
	if ( ! supportsAdditionalCSSEnhancement( name ) ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			[ COMPILED_ATTRIBUTE ]: {
				type: 'object',
				default: {},
			},
		},
		supports: {
			...settings.supports,
			customCSS: false,
		},
	};
}

function AdditionalCSSEnhancementControl( props ) {
	const { attributes = {} } = props;
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const blockType = getBlockType( props.name );
	const blockTitle = blockType && blockType.title ? blockType.title : __( 'block', 'acsse' );
	const canEditCSS = useSelect(
		( select ) => select( blockEditorStore ).getSettings().canEditCSS,
		[]
	);
	const blockEditingMode = useBlockEditingMode ? useBlockEditingMode() : 'default';
	const value = getCustomCSS( attributes );
	const markupError = value && ! validateMarkup( value );
	const compiled = useMemo( () => compileCustomCSS( value ), [ value ] );
	const compileError = value && ! markupError && compiled.errors.length > 0;
	const fieldHelp = sprintf(
		__(
			'Add scoped CSS for the %s block. Use declarations, & selectors, and @media, @supports, or @container blocks.',
			'acsse'
		),
		blockTitle
	);
	const handleChange = ( nextValue ) => {
		setCustomCSS( props, nextValue );
	};
	const notices = (
		<Fragment>
			{ markupError && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'The custom CSS is invalid. Do not use <> markup.', 'acsse' ) }
				</Notice>
			) }
			{ compileError && (
				<Notice status="warning" isDismissible={ false }>
					{ compiled.errors.join( ' ' ) }
				</Notice>
			) }
		</Fragment>
	);

	if ( ! canEditCSS || blockEditingMode !== 'default' ) {
		return null;
	}

	return (
		<Fragment>
			<InspectorControls group="advanced">
				<VStack spacing={ 3 }>
					{ notices }
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'space-between',
						} }
					>
						<BaseControl.VisualLabel style={ { margin: 0 } }>
							{ __( 'ADDITIONAL CSS', 'acsse' ) }
						</BaseControl.VisualLabel>
						<Button
							icon={ expandIcon }
							label={ __( 'Open expanded Additional CSS editor', 'acsse' ) }
							onClick={ () => setIsModalOpen( true ) }
							showTooltip
							size="small"
							variant="tertiary"
						/>
					</div>
					<TextareaControl
						label={ __( 'Additional CSS', 'acsse' ) }
						hideLabelFromVision
						value={ value }
						onChange={ handleChange }
						spellCheck={ false }
						help={ fieldHelp }
					/>
				</VStack>
			</InspectorControls>
			{ isModalOpen && (
				<Modal
					title={ sprintf( __( 'Additional CSS: %s', 'acsse' ), blockTitle ) }
					onRequestClose={ () => setIsModalOpen( false ) }
					focusOnMount="firstContentElement"
					size="large"
				>
					<VStack spacing={ 3 }>
						{ notices }
						<TextareaControl
							label={ __( 'Additional CSS', 'acsse' ) }
							value={ value }
							onChange={ handleChange }
							rows={ 20 }
							spellCheck={ false }
							help={ fieldHelp }
						/>
					</VStack>
				</Modal>
			) }
		</Fragment>
	);
}

const withAdditionalCSSEnhancementControl = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( ! supportsAdditionalCSSEnhancement( props.name ) ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<AdditionalCSSEnhancementControl { ...props } />
			</Fragment>
		);
	},
	'withAdditionalCSSEnhancementControl'
);

const withAdditionalCSSEnhancementPreview = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		const isSupportedBlock = supportsAdditionalCSSEnhancement( props.name );
		const selector = isSupportedBlock ? getEditorBlockSelector( props.clientId ) : '';
		const rawCSS = getCustomCSS( props.attributes || {} );
		const css = useMemo( () => {
			if ( ! isSupportedBlock ) {
				return '';
			}

			const compiled = compileCustomCSS( rawCSS );
			if ( ! compiled.css || compiled.errors.length > 0 ) {
				return '';
			}

			return compiled.css.replaceAll( SELECTOR_TOKEN, selector );
		}, [ isSupportedBlock, rawCSS, selector ] );

		useBlockStyleOverride( {
			id: 'acsse-additional-css-enhancement-' + props.clientId,
			css,
		} );

		return <BlockListBlock { ...props } />;
	},
	'withAdditionalCSSEnhancementPreview'
);

addFilter(
	'blocks.registerBlockType',
	'acsse/additional-css-enhancement/attribute',
	addAdditionalCSSEnhancementAttribute
);

addFilter(
	'editor.BlockEdit',
	'acsse/additional-css-enhancement/control',
	withAdditionalCSSEnhancementControl
);

addFilter(
	'editor.BlockListBlock',
	'acsse/additional-css-enhancement/preview',
	withAdditionalCSSEnhancementPreview
);
