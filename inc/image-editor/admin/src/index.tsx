import ReactDOM from 'react-dom';
import React, { useRef, useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Editor, { convertImageData } from './editor';
import ImageEditor from './image-editor';

import { addFilter } from '@wordpress/hooks';
import { Fragment } from '@wordpress/element';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';

const addEditButtonToImageBlock = (BlockEdit) => {
	return (props) => {
		// Check if the current block is an Image block
		if (props.name !== 'core/image') {
			return <BlockEdit {...props} />;
		}

		async function onClickEditAttachment() {
			const id = props.attributes.id;
			const post = await showImageEditor( id );
			// Set the attributes to the new image
			props.setAttributes({url: post.media_details.sizes.full.source_url});
		}

		return (
			<>
				<BlockEdit {...props} />
				<BlockControls>
					<ToolbarButton
						label="Edit"
						icon="edit"
						onClick={onClickEditAttachment}
					/>
				</BlockControls>
			</>
		);
	};
};

// Add the filter
addFilter(
	'editor.BlockEdit',
	'my-plugin/add-edit-button-to-image-block',
	addEditButtonToImageBlock
);

export default function EditorLoader( props: { id: number, onSaved: ( post: any ) => void, onDismiss: () => void } ) {
	const [ post, setPost ] = useState(null);
	useEffect(() => {
		apiFetch( { path: `/wp/v2/media/${props.id}` } ).then( ( post ) => {
			setPost( post );
		});
	}, [props.id]);

	const [ _trigger, setTrigger ] = useState(0);
	let editor = useRef(new ImageEditor()).current;

	useEffect(() => {
		if ( post ) {
			editor.load(post.media_details.sizes.full.source_url);
			window.editor = editor;
			editor.onChangeImageData(() => {
				console.log( 'image data has changed' );
				setTrigger( new Date().getTime() );
			});
		}
	}, [post]);

	async function onSave() {
		const post = await apiFetch( { path: `/wp/v2/media/${props.id}`, method: 'POST', data: {
			image_blob: convertImageData(editor.getImageData(), 'image/png'),
		} } );
		setPost( post );
		props.onSaved( post );
	}

	if ( ! post ) {
		return <div>Loading post...</div>;
	}

	console.log(post);

	return (
		<div className="editor">
			{ editor && editor.getImageData() ?
				<>
					<header>
						<h3>Edit { post.title.rendered }</h3>
						<div className="actions">
							<button className="button button-primary" onClick={ onSave }>Save</button>
							<button className="dashicons dashicons-no-alt close" onClick={ props.onDismiss }></button>
						</div>
					</header>
					<Editor trigger={ _trigger } editor={editor} />
				</>
			:
				<div>Loading...</div>
			}
		</div>
	)
}

async function showImageEditor( id ) {
	return new Promise( ( resolve, reject ) => {
		const el = document.createElement( 'div' );
		el.id = 'image-editor-modal';
		document.body.appendChild( el );
		ReactDOM.render( <EditorLoader id={ id } onSaved={post => {
			el.remove();
			resolve(post);
		}} onDismiss={ () => el.remove() } />, el );
	});
}
window.showImageEditor = async function ( id ) {
	const el = document.createElement( 'div' );
	el.id = 'image-editor-modal';
	document.body.appendChild( el );
	ReactDOM.render( <EditorLoader id={ id } onSaved={post => {}} onDismiss={ () => el.remove() } />, el );
}
