import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { Icon } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState, useRef, useEffect } from '@wordpress/element';

import useAiSuperpowers from './useAiSuperpowers';
import Loading from './loading';

function InputButton( props ) {
	return (
		<button
			className="block px-2 cursor-pointer text-[#4667DE] opacity-80 hover:opacity-100 text-xs self-stretch bg-transparent border-0 border-l border-pink-300"
			type="button"
			onClick={ props.onClick }
		>
			{ props.children }
		</button>
	);
}

export default function Edit( props ) {
	const innerBlocks = useSelect( select => {
		return select('core/block-editor').getBlocksByClientId(props.clientId)?.[0]?.innerBlocks;
	}, [] );

	const { replaceBlock, removeBlock } = useDispatch('core/block-editor');
	const [prompt, setPrompt] = useState('');
	const inputRef = useRef(null);

	const {
		cancelLoading,
		error,
		loading,
		submitPrompt
	} = useAiSuperpowers( props.clientId );

	const attributes = props.attributes;

	useEffect( () => {
		if ( attributes._prompt ) {
			// Prefilled prompt from a transform. Run it, and remove it.
			onSubmitPrompt( attributes._prompt );
			props.setAttributes( {
				...attributes,
				_prompt: null,
			} );
		}
	}, [ attributes ] );

	const onSubmitPrompt = async ( prompt ) => {
		await submitPrompt( prompt );
		inputRef.current?.focus();
	};

	const onAccept = () => {
		replaceBlock( props.clientId, innerBlocks );
	};
	// const onReject = () => {
	// 	// ...
	// };

	async function onSubmit(e) {
		e.preventDefault();

		if (prompt === '' && innerBlocks?.length > 0) {
			return onAccept();
		}

		await onSubmitPrompt( prompt );
		setPrompt('');
	}

	const blockProps = useBlockProps( {} );

	function onPrompKeyDown(e) {
		if (e.code === 'Backspace' && e.target.value === '' && !e.nativeEvent.altKey) {
			removeBlock(props.clientId);
		}
		if ( e.key === 'Escape' && loading ) {
			e.preventDefault();
			e.stopPropagation();
			cancelLoading();
		} else if ( e.key === 'Escape' && innerBlocks.length > 0 ) {
			return onAccept();
		}
	}

	useEffect(() => {
		inputRef.current?.focus();
	}, []);

	const hasCompletion = innerBlocks?.length > 0 && prompt === '';

	return (
		<div { ...blockProps } className={ `wp-block wp-block-ai-insert ${innerBlocks?.length && 'has-blocks'}` }>
			<InnerBlocks renderAppender={ false } />
			<div className="tailwind">
				{ error && <p className="error bg-red-200 border-red-800 rounded text-red-900 text-sm p-2">{ error }</p> }
				<div className="bg-gradient-to-r from-gradient-blue to-gradient-pink rounded p-[2px]">
					<form
						className="bg-[#F0F3FD] text-sm flex items-center rounded-sm"
						onSubmit={ (e) => onSubmit(e) }
					>
						<input
							ref={ inputRef }
							autoFocus
							className={ `border-none py-2 px-4 bg-transparent flex-1 focus:shadow-none placeholder:text-[#4667DE] text-[#4667DE] ${ loading && 'animate-pulse' }` }
							placeholder={ hasCompletion > 0 ? 'Press return to insert' : 'Enter prompt...' }
							type="text"
							value={ prompt }
							onChange={ (e) => setPrompt(e.target.value) }
							onKeyDown={ (e) => onPrompKeyDown(e) }
						/>

						{ hasCompletion && (
							<>
								<InputButton
									onClick={ onAccept }
								>
									<Icon
										icon="yes-alt"
									/>
								</InputButton>
								{ /* <InputButton
									onClick={ onReject }
								>
									<Icon
										icon="dismiss"
									/>
								</InputButton> */ }
							</>
						) }

						{ loading && (
							<Loading onClick={ cancelLoading } />
						) }
					</form>
				</div>
			</div>
		</div>
	);
}
