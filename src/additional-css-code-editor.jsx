import { css } from '@codemirror/lang-css';
import CodeMirror, { EditorView } from '@uiw/react-codemirror';
import { BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const codeMirrorExtensions = [
	css(),
	EditorView.contentAttributes.of( {
		'aria-label': __( 'Additional CSS code editor', 'acsse' ),
	} ),
];
const codeMirrorBasicSetup = {
	lineNumbers: true,
	foldGutter: true,
	indentOnInput: true,
	bracketMatching: true,
	closeBrackets: true,
	autocompletion: true,
	searchKeymap: true,
};

export default function AdditionalCSSCodeEditor( { help, value, onChange } ) {
	return (
		<BaseControl label={ __( 'Additional CSS', 'acsse' ) } help={ help }>
			<div
				style={ {
					border: '1px solid #949494',
					borderRadius: '2px',
					overflow: 'hidden',
				} }
			>
				<CodeMirror
					autoFocus
					basicSetup={ codeMirrorBasicSetup }
					extensions={ codeMirrorExtensions }
					height="50vh"
					minHeight="320px"
					maxHeight="640px"
					indentWithTab
					value={ value }
					onChange={ onChange }
				/>
			</div>
		</BaseControl>
	);
}
