import {
	Button,
	ExternalLink,
	PanelRow,
	TextareaControl,
} from '@wordpress/components';
// @ts-ignore
import { compose } from '@wordpress/compose';
// @ts-ignore
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useEffect, useState } from '@wordpress/element';
// @ts-ignore
import { PostExcerptCheck } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

import { withSelect, withDispatch } from '@wordpress/data';

import Icon from './icon';
import useAiSummary from './useAiSummary';

interface BodyProps {
	excerpt: string,
	onUpdateExcerpt( excerpt: string ): void,
}

function _ExcerptPanelBody( { excerpt, onUpdateExcerpt }: BodyProps ) {
	const [ suggestion, setSuggestion ] = useState<string | null>( null );
	const { loading, submitPrompt } = useAiSummary();

	const onGenerate = async () => {
		try {
			const result = await submitPrompt( 'Write a teaser for this post with a minimum of 50 characters and a maximum of 250 characters.' );

			// ChatGPT loves adding quotations, so strip them off.
			const content = result.replace( /^"|"$/g, '' );
			setSuggestion( content );
			console.log( result, content );
		}
		catch ( e ) {
			console.log( e );
		}
	};

	const onAccept = () => {
		if ( ! suggestion ) {
			return;
		}

		onUpdateExcerpt( suggestion );
		setSuggestion( null );
	};

	return (
		<div className="editor-post-excerpt tailwind">
			{ suggestion ? (
				<>
					<TextareaControl
						// @ts-ignore
						__nextHasNoMarginBottom
						disabled
						label="Suggested excerpt"
						className="editor-post-excerpt__textarea"
						rows={ 10 }
						value={ suggestion }
					/>
					<PanelRow className="my-2 space-between">
						<Button
							icon="yes"
							isBusy={ loading }
							// @ts-ignore
							variant="primary"
							onClick={ onAccept }
						>
							Accept
						</Button>
						<Button
							icon={ Icon }
							isBusy={ loading }
							// @ts-ignore
							variant="secondary"
							onClick={ onGenerate }
						>
							Try again
						</Button>
					</PanelRow>
				</>
			) : (
				<>
					<TextareaControl
						// @ts-ignore
						__nextHasNoMarginBottom
						label={ __( 'Write an excerpt (optional)' ) }
						className="editor-post-excerpt__textarea"
						rows={ 10 }
						value={ excerpt }
						onChange={ ( value ) => onUpdateExcerpt( value ) }
					/>
					<PanelRow className="tailwind my-2">
						<Button
							icon={ Icon }
							isBusy={ loading }
							// @ts-ignore
							variant="secondary"
							onClick={ onGenerate }
						>
							Write it for me
						</Button>
					</PanelRow>
				</>
			) }
			<ExternalLink
				href={ __(
					'https://wordpress.org/documentation/article/page-post-settings-sidebar/#excerpt'
				) }
			>
				{ __( 'Learn more about excerpts' ) }
			</ExternalLink>
		</div>
	);
}

const ExcerptPanelBody = compose( [
	withSelect( ( select ) => {
		return {
			excerpt: select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ),
		};
	} ),
	withDispatch( ( dispatch ) => ( {
		onUpdateExcerpt( excerpt: string ) {
			dispatch( 'core/editor' ).editPost( { excerpt } );
		},
	} ) ),
] )( _ExcerptPanelBody );

function BuiltinExcerptHider() {
	useEffect( () => {
		// Find and hide the existing excerpt panel.
		const panels = document.querySelectorAll( '.components-panel__body' );
		panels.forEach( panel => {
			// Find using the panel's title.
			const title = panel.querySelector( '.components-panel__body-toggle' )?.textContent;
			if ( title !== __( 'Excerpt' ) ) {
				return;
			}

			// Exclude our own.
			if ( panel.classList.contains( 'altis-accelerate-excerpt' ) ) {
				console.log( 'nope' );
				return;
			}

			// Found the panel. Hide it.
			( panel as HTMLElement ).style.display = 'none';
		} );
	}, [] );

	return null;
}

interface PanelProps {
	isEnabled: boolean,
	isOpened: boolean,
}

function ExcerptPanel( props: PanelProps ) {
	if ( ! props.isEnabled ) {
		return null;
	}

	return (
		<PostExcerptCheck>
			<PluginDocumentSettingPanel
				name="post-excerpt"
				title="Excerpt"
				className="altis-accelerate-excerpt"
			>
				<>
					<BuiltinExcerptHider />
					<ExcerptPanelBody

					/>
				</>
			</PluginDocumentSettingPanel>
		</PostExcerptCheck>
	);
}

export default compose( [
	withSelect( ( select ) => {
		return {
			isEnabled:
				select( 'core/edit-post' ).isEditorPanelEnabled( 'post-excerpt' ),
			isOpened: select( 'core/edit-post' ).isEditorPanelOpened( 'post-excerpt' ),
		};
	} ),
	withDispatch( ( dispatch ) => ( {
		onTogglePanel() {
			return dispatch( 'core/edit-post' ).toggleEditorPanelOpened(
				'post-excerpt'
			);
		},
	} ) ),
] )( ExcerptPanel );
