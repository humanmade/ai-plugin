import ImageEditor from './image-editor';
import React, { useRef, useState, useEffect } from 'react';
import ReactCrop, { type Crop } from 'react-image-crop'
import './editor.css';
import 'react-image-crop/dist/ReactCrop.css'
import { TransformWrapper, TransformComponent, ReactZoomPanPinchContentRef } from "react-zoom-pan-pinch";

interface Inpaint {
	mask: ImageData | undefined,
}

export default function Editor( { editor, trigger }: { editor: ImageEditor, trigger: number }) {
	const canvas = useRef<HTMLCanvasElement>(null);

	const [selectedRect, setSelectedRect] = useState<Crop>()

	const [mask, setMask] = useState<Inpaint | undefined>(undefined);
	const [inpaint, setInpaint] = useState<boolean>(false);
	const [replaceBackground, setReplaceBackground] = useState<boolean>(false);
	const [inpaintOptions, setInpaintOptions] = useState<ImageData[]>([]);
	const panner = useRef<ReactZoomPanPinchContentRef>(null);
	const [selectionMode, setSelectionMode] = useState<"pan"|"rectangle"|"mask">('pan')
	const [processing, setProcessing] = useState<boolean>(true);

	useEffect(() => {
		document.onkeydown = (e) => {
			if ( document.activeElement?.tagName === 'INPUT' ) {
				return;
			}
			console.log( e.key );
			if ( e.key === 'c' ) {
				setSelectionMode('rectangle');
				e.stopPropagation();
			} else if ( e.key === 'm' ) {
				setSelectionMode('mask');
				e.stopPropagation();
			} else if ( e.key === ' ' ) {
				setSelectionMode('pan');
				e.stopPropagation();
			} else if ( e.key === 'Escape' ) {
				setSelectedRect(undefined);
				setMask(undefined);
				setInpaint(false);
				setReplaceBackground(false);
				e.stopPropagation();
			} else if ( e.key === 'Backspace' ) {
				onSubmitCleanup();
				e.stopPropagation();
			} else if ( e.key === 'Enter' ) {
				onSubmitCrop();
				e.stopPropagation();
			}
		};
		editor.onChangeImageData(() => {
			if ( ! canvas.current ) {
				return;
			}
			canvas.current.width = editor.getImageData().width;
			canvas.current.height = editor.getImageData().height;
			canvas.current.getContext('2d')?.putImageData(editor.getImageData(), 0, 0);
			setProcessing(false);
		});
	}, [editor, onSubmitCleanup]);

	function toggleSelectRect() {
		setSelectedRect( selectedRect ? undefined : {
			x: 100,
			y: 100,
			width: 100,
			height: 100,
			unit: 'px',
		} );
	}

	async function onSubmitInpaint(e: React.FormEvent<HTMLFormElement>) {
		setProcessing(true);
		e.preventDefault();
		try {
			let inpaintOptions = await fetchInpaintOptions( editor.getImageData(), invertMask( mask!.mask! ), (e.target as HTMLFormElement).querySelector<HTMLInputElement>('input[type="text"]')!.value );
			setInpaintOptions( inpaintOptions );
			editor.setImageData( inpaintOptions[0] );
			setMask(undefined);
			setSelectionMode('pan');
		} catch (e) {
			alert( e.message );
		} finally {
			setProcessing(false);
		}
	}

	async function onSubmitCleanup() {
		console.log( mask );
		if ( ! mask?.mask ) {
			return;
		}
		setProcessing(true);

		try {
			let cleanupOptions = await fetchCleanupOptions( editor.getImageData(), invertMask( mask.mask ) );
			editor.setImageData( cleanupOptions[0] );
			setMask(undefined);
			setSelectionMode('pan');
		} catch (e) {
			alert( e.message );
		} finally {
			setProcessing(false);
		}
	}

	async function onSubmitRemoveBackground() {
		setProcessing(true);
		try {
			let newImage = await fetchRemoveBackground( editor.getImageData() );
			editor.setImageData( newImage );
		} catch ( e ) {
			alert( e.message );
		} finally {
			setProcessing(false);
		}
	}

	async function onSubmitUpscale() {
		setProcessing(true);
		try {
			let newImage = await fetchUpscale( editor.getImageData() );
			editor.setImageData( newImage );
		} catch ( e ) {
			alert( e.message );
		} finally {
			setProcessing(false);
		}
	}

	async function onSubmitCrop() {
		editor.crop(selectedRect!.x, selectedRect!.y, selectedRect!.width, selectedRect!.height);
		toggleSelectRect();
		setSelectionMode('pan');
	}

	async function onSubmitReplaceBackground(e: React.FormEvent<HTMLFormElement>) {
		setProcessing(true);
		e.preventDefault();
		try {
			let newImage = await fetchReplaceBackground( editor.getImageData(), (e.target as HTMLFormElement).querySelector<HTMLInputElement>('input[type="text"]')!.value );
			editor.setImageData( newImage );
		} catch ( e ) {
			alert( e.message );
		} finally {
			setProcessing(false);
		}
	}

	return (
		<>
			<div className="editor-actions">

				<div className="editor-actions-group">
					<button className={`${ selectionMode === 'pan' ? 'active' : '' }`} title="Pan" onClick={ () => setSelectionMode('pan') }><span className="dashicons dashicons-move"></span></button>
					<button className={`${ selectionMode === 'rectangle' ? 'active' : '' }`} title="Reactangle" onClick={ () => setSelectionMode('rectangle') }><span className="dashicons dashicons-grid-view"></span></button>
					<button className={`${ selectionMode === 'mask' ? 'active' : '' }`} title="Mask" onClick={ () => setSelectionMode('mask') }><span className="dashicons dashicons-admin-customizer"></span></button>
				</div>

				<button title="Flip Vertically" onClick={() => editor.flipX()}><span className="dashicons dashicons-image-flip-vertical"></span></button>
				<button title="Flip Horizontally" onClick={() => editor.flipY()}><span className="dashicons dashicons-image-flip-horizontal"></span></button>
				<button title="Rotate Left" onClick={() => editor.rotate(-90)}><span className="dashicons dashicons-image-rotate-left"></span></button>
				<button title="Rotate Right" onClick={() => editor.rotate(90)}><span className="dashicons dashicons-image-rotate-right"></span></button>

				<button title="Remove Background" onClick={ onSubmitRemoveBackground }><span className="dashicons dashicons-screenoptions"></span></button>
				<button title="Replace Background" onClick={ () => { setReplaceBackground(true) } }><span className="dashicons dashicons-format-image"></span></button>
				<button title="Upscale" onClick={ () => { onSubmitUpscale() } }><span className="dashicons dashicons-editor-expand"></span></button>

				<button title="Remove Object" disabled={ selectionMode !== 'mask' } onClick={ onSubmitCleanup }><span className="dashicons dashicons-no-alt"></span></button>
				<button title="Inpaint" disabled={ selectionMode !== 'mask' } onClick={() => { setInpaint(true); } }><span className="dashicons dashicons-admin-appearance"></span></button>

				<button title="Crop Selection" disabled={ selectionMode !== 'rectangle' } onClick={ onSubmitCrop }><span className="dashicons dashicons-image-crop"></span></button>

				<button title="Undo" disabled={ ! editor.canUndo() } onClick={ () => editor.undo() }><span className="dashicons dashicons-undo"></span></button>
				<button title="Redo" disabled={ ! editor.canRedo() } onClick={ () => editor.redo() }><span className="dashicons dashicons-redo"></span></button>

				{ inpaint &&
					<form className="inpaint-options" onSubmit={ onSubmitInpaint }>
						<strong>Inpainting</strong>
						<input type="text" placeholder="Describe what you want to add to the image" />
						<button type="submit" disabled={ ! mask } className="button button-primary">Inpaint</button>
					</form>
				}

				{ replaceBackground &&
					<form className="inpaint-options" onSubmit={ onSubmitReplaceBackground }>
						<strong>Replace Background</strong>
						<input type="text" placeholder="Describe what you want the background to be" />
						<button type="submit" className="button button-primary">Inpaint</button>
					</form>
				}
			</div>
			<div className="canvas-wrapper">
				{ processing &&
					<div className="processing">
						<div className="spin"></div>
					</div>
				}
					<TransformWrapper ref={ panner } panning={ { disabled: selectionMode !== 'pan' } } minScale={0.2}>
						<TransformComponent>
							<ReactCrop crop={ selectedRect } disabled={ selectionMode !== 'rectangle' } onChange={ setSelectedRect } style={{width:'100%', overflow: 'visible'}}>
								<div className="canvas" style={{ cursor: 'grab' }}>
										{ selectionMode === 'mask' &&
											<Mask
												panner={ panner.current! }
												width={ editor.getImageData().width }
												height={ editor.getImageData().height }
												onChange={ data => { setMask({mask: data}); console.log( 'set mask' ) }
											} />
										}
									<canvas ref={canvas}></canvas>
								</div>
							</ReactCrop>
						</TransformComponent>
					</TransformWrapper>
			</div>
		</>
	);
}

function Mask( props: { width: number, height: number, panner: ReactZoomPanPinchContentRef, onChange: ( data: ImageData ) => void } ) {
	const canvas = useRef<HTMLCanvasElement>(null);
	const [isDrawing, setIsDrawing] = useState(false);
	const previousPoint = useRef<{ x: number, y: number } | undefined>(undefined);

	useEffect(() => {
		if (!canvas.current) return;

		const ctx = canvas.current.getContext('2d');
		if (!ctx) return;

		function handleMouseDown(event: MouseEvent) {
			const rect = canvas.current!.getBoundingClientRect();
			const x = (event.clientX - rect.left) / props.panner.instance!.transformState.scale;
			const y = (event.clientY - rect.top) / props.panner.instance!.transformState.scale;

			previousPoint.current = { x, y };
			setIsDrawing(true);
			drawPixel(event);
		}

		function handleMouseMove(event: MouseEvent) {
			console.log( isDrawing );
			if (!isDrawing) {
				return;
			}
			console.log( 'moved');
			drawPixel(event);
		}

		function handleMouseUp() {
			setIsDrawing(false);
			previousPoint.current = undefined;
			props.onChange( ctx!.getImageData(0, 0, props.width, props.height) );
		}

		function drawPixel(event: MouseEvent) {
			const rect = canvas.current!.getBoundingClientRect();
			const x = (event.clientX - rect.left) / props.panner.instance!.transformState.scale;
			const y = (event.clientY - rect.top) / props.panner.instance!.transformState.scale;

			// Draw from the previous mouse position to the current position
			ctx!.beginPath();
			ctx!.moveTo(previousPoint.current?.x ?? x, previousPoint.current?.y ?? y);
			ctx!.strokeStyle = 'black';
			ctx!.lineWidth = 30 / props.panner.instance!.transformState.scale;
			ctx!.lineTo(x, y);
			ctx!.stroke();
			ctx!.lineCap = 'round';
			previousPoint.current = { x, y };
		}

		canvas.current.addEventListener('mousedown', handleMouseDown);
		canvas.current.addEventListener('mousemove', handleMouseMove);
		canvas.current.addEventListener('mouseup', handleMouseUp);

		return () => {
			canvas.current?.removeEventListener('mousedown', handleMouseDown);
			canvas.current?.removeEventListener('mousemove', handleMouseMove);
			canvas.current?.removeEventListener('mouseup', handleMouseUp);
		};
	}, [isDrawing]);

	return (
		<canvas className="Mask" ref={canvas} width={ props.width } height={ props.height } style={{ cursor: 'crosshair' }}></canvas>
	)
}

function invertMask(imageData: ImageData) : ImageData {
	const data = imageData.data; // the pixel data

	// Modify each pixel
	for (let i = 0; i < data.length; i += 4) {
		// data[i] = red, data[i + 1] = green, data[i + 2] = blue, data[i + 3] = alpha
		if (data[i + 3] === 0) { // If the pixel is transparent
			// Make the pixel black
			data[i] = 0;     // Red
			data[i + 1] = 0; // Green
			data[i + 2] = 0; // Blue
			data[i + 3] = 255; // Make it opaque
		} else {
			// Make the pixel white
			data[i] = 255;     // Red
			data[i + 1] = 255; // Green
			data[i + 2] = 255; // Blue
			// Alpha remains the same
		}
	}

	const newImageData = new ImageData(data, imageData.width, imageData.height);
	return newImageData;
}

interface ErrorResponse {
	message: string,
	code: string,
}

interface ImageResponse {
	image: string,
}


async function fetchInpaintOptions( imageData: ImageData, maskData: ImageData, prompt: string ) : Promise<ImageData[]> {
	let response = await fetch( '/wp-json/ai/v1/image-editor/inpaint', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			image: convertImageData(imageData),
			mask: convertImageData(maskData),
			prompt: prompt,
			samples: 4,
		}),
	});

	let json: ErrorResponse | ImageResponse = await response.json();

	if ( 'message' in json ) {
		throw new Error( json.message );
	}
	let base64String = json.image[0];

	const editor = new ImageEditor()
	await editor.load('data:image/png;base64,' + base64String);

	return [editor.getImageData()];
}

async function fetchCleanupOptions( imageData: ImageData, maskData: ImageData ) : Promise<ImageData[]> {
	let response = await fetch( '/wp-json/ai/v1/image-editor/cleanup', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			image: convertImageData(imageData),
			mask: convertImageData(maskData),
		}),
	});

	let json: ErrorResponse | ImageResponse = await response.json();

	if ( 'message' in json ) {
		throw new Error( json.message );
	}
	let base64String = json.image;

	const newImageData = new ImageData(imageData.width, imageData.height);

	const editor = new ImageEditor();

	await editor.load('data:image/png;base64,' + base64String);
	newImageData.data.set(editor.getImageData().data);
	return [newImageData];
}

async function fetchRemoveBackground( imageData: ImageData ) : Promise<ImageData> {
	let response = await fetch( '/wp-json/ai/v1/image-editor/remove-background', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			image: convertImageData(imageData),
		}),
	});

	let json: ErrorResponse | ImageResponse = await response.json();

	if ( 'message' in json ) {
		throw new Error( json.message );
	}
	let base64String = json.image;

	const newImageData = new ImageData(imageData.width, imageData.height);

	const editor = new ImageEditor()
	await editor.load('data:image/png;base64,' + base64String);
	newImageData.data.set(editor.getImageData().data);
	return newImageData;
}

async function fetchUpscale( imageData: ImageData ) : Promise<ImageData> {
	let response = await fetch( '/wp-json/ai/v1/image-editor/upscale', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			image: convertImageData(imageData),
		}),
	});

	let json: ErrorResponse | ImageResponse = await response.json();

	if ( 'message' in json ) {
		throw new Error( json.message );
	}
	let base64String = json.image;

	const newImageData = new ImageData(imageData.width * 2, imageData.height * 2);

	const editor = new ImageEditor()
	await editor.load('data:image/png;base64,' + base64String);
	newImageData.data.set(editor.getImageData().data);
	return newImageData;
}


async function fetchReplaceBackground( imageData: ImageData, prompt: string ) : Promise<ImageData> {
	let response = await fetch( '/wp-json/ai/v1/image-editor/replace-background', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			image: convertImageData(imageData),
			prompt,
		}),
	});

	let json: ErrorResponse | ImageResponse = await response.json();

	if ( 'message' in json ) {
		throw new Error( json.message );
	}

	let base64String = json.image;

	const newImageData = new ImageData(imageData.width, imageData.height);

	const editor = new ImageEditor()
	await editor.load('data:image/png;base64,' + base64String);
	newImageData.data.set(editor.getImageData().data);
	return newImageData;
}

export function convertImageData(imageData:ImageData) : string {
	// Create a canvas element
	const canvas = document.createElement('canvas');
	canvas.width = imageData.width;
	canvas.height = imageData.height;

	// Draw the image data onto the canvas
	const ctx = canvas.getContext('2d');
	ctx!.putImageData(imageData, 0, 0);

	// Convert the canvas to a data URL (base64)
	const dataURL = canvas.toDataURL('image/png');

	// Extract and return the base64 encoded string
	// The dataURL looks like "data:image/jpg;base64,iVBORw0K...",
	// and we need to extract the part after "base64,"
	return dataURL.split(',')[1];
}
