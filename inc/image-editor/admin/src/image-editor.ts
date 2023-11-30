export default class ImageEditor {
	private imageData: ImageData;
	private originalImageData: ImageData;
	private onChangeCallbacks: (() => void)[] = [];
	private undoStack: ImageData[] = [];
	private redoStack: ImageData[] = [];

	constructor() {
		this.imageData = new ImageData(1, 1);
		this.originalImageData = new ImageData(1, 1);
	}

	async load(src: string): Promise<void> {
		const image = new Image();
		image.crossOrigin = 'Anonymous';
		return new Promise((resolve, reject) => {
			console.log('loading');
			image.onload = () => {
				const canvas = document.createElement('canvas');
				const context = canvas.getContext('2d');
				canvas.width = image.width;
				canvas.height = image.height;
				context?.drawImage(image, 0, 0);
				const imageData = context?.getImageData(0, 0, image.width, image.height)!
				this.setImageData(imageData, false);
				this.originalImageData = context?.getImageData(0, 0, image.width, image.height)!;
				console.log('loaded');
				resolve();
			};
			image.onerror = (e) => {
				console.log( e.type)
				reject(e);
			}
			image.oncancel = (e) => {
				reject(e);
			}
			image.src = src;
		} );
	}

	getImageData() : ImageData {
		return this.imageData;
	}

	async flipX() : Promise<void> {
		await this.addAction({ type: 'flipX' });
	}

	async flipY() : Promise<void> {
		await this.addAction({ type: 'flipY' });
	}

	async rotate(deg: number) : Promise<void> {
		this.addAction({ type: 'rotate', deg });
	}

	async crop(x: number, y: number, width: number, height: number) : Promise<void> {
		this.addAction({ type: 'crop', x, y, width, height });
	}

	async undo() : Promise<void> {
		if (this.undoStack.length === 0) {
			return;
		}
		const imageData = this.undoStack.pop()!;
		this.redoStack.push(this.imageData);
		this.setImageData(imageData, false);
	}

	async redo() : Promise<void> {
		if (this.redoStack.length === 0) {
			return;
		}
		const imageData = this.redoStack.pop()!;
		this.undoStack.push(this.imageData);
		this.setImageData(imageData, false);
	}

	canUndo() : boolean {
		return this.undoStack.length > 0;
	}

	canRedo() : boolean {
		return this.redoStack.length > 0;
	}

	/**
	 * Add a new action, and progressively apply it to the current image.
	 */
	async addAction(action: Action) : Promise<void> {
		this.setImageData(await applyActions(this.imageData, [action]));
	}


	/**
	 * Replace the current image, and clear the undo/redo stack.
	 */
	setImageData(imageData: ImageData, undoStack = true) : void {
		if ( undoStack ) {
			this.undoStack.push(cloneImageData(this.imageData));
		}

		this.imageData = imageData;
		this.callOnChangeCallbacks();
	}
	onChangeImageData(callback: () => void) : void {
		this.onChangeCallbacks.push(callback);
	}
	callOnChangeCallbacks() : void {
		this.onChangeCallbacks.forEach(callback => callback());
	}
}

interface FlipXAction {
	type: 'flipX';
}

interface FlipYAction {
	type: 'flipY';
}

interface RotateAction {
	type: 'rotate';
	deg: number;
}

interface CropAction {
	type: 'crop';
	x: number;
	y: number;
	width: number;
	height: number;
}

type Action = FlipXAction | FlipYAction | RotateAction | CropAction;

async function applyActions(imageData: ImageData, actions: Action[]) : Promise<ImageData> {
	for (const action of actions) {
		switch (action.type) {
			case 'flipX':
				imageData = await flipX(imageData, action);
				break;
			case 'flipY':
				imageData = await flipY(imageData, action);
				break;
			case 'rotate':
				imageData = await rotate(imageData, action);
				break;
			case 'crop':
				imageData = await crop(imageData, action);
				break;
		}
	}
	return imageData
}

async function flipX( imageData: ImageData, _action: FlipXAction) : Promise<ImageData> {
	const { width, height, data } = cloneImageData(imageData);
	for (let y = 0; y < height / 2; y++) {
		for (let x = 0; x < width; x++) {
			const offset = (y * width + x) * 4;
			const offset2 = ((height - y - 1) * width + x) * 4;
			for (let i = 0; i < 4; i++) {
				const tmp = data[offset + i];
				data[offset + i] = data[offset2 + i];
				data[offset2 + i] = tmp;
			}
		}
	}
	return new ImageData(data, width, height);
}

async function flipY( imageData: ImageData, _action: FlipYAction) : Promise<ImageData> {
	const { width, height, data } = cloneImageData(imageData);
	for (let y = 0; y < height; y++) {
		for (let x = 0; x < width / 2; x++) {
			const offset = (y * width + x) * 4;
			const offset2 = (y * width + (width - x - 1)) * 4;
			for (let i = 0; i < 4; i++) {
				const tmp = data[offset + i];
				data[offset + i] = data[offset2 + i];
				data[offset2 + i] = tmp;
			}
		}
	}
	return new ImageData(data, width, height);
}

async function rotate( imageData: ImageData, action: RotateAction) : Promise<ImageData> {
	const { width, height } = cloneImageData(imageData);
	const canvas = document.createElement('canvas');
	const context = canvas.getContext('2d');
	canvas.width = width;
	canvas.height = height;
	context?.putImageData(imageData, 0, 0);
	context?.translate(width / 2, height / 2);
	context?.rotate(action.deg * Math.PI / 180);
	context?.translate(-width / 2, -height / 2);
	context?.drawImage(canvas, 0, 0);
	return context?.getImageData(0, 0, width, height)!;
}

async function crop( imageData: ImageData, action: CropAction) : Promise<ImageData> {
	const { width, height } = cloneImageData(imageData);
	const canvas = document.createElement('canvas');
	const context = canvas.getContext('2d');
	canvas.width = width;
	canvas.height = height;
	context?.putImageData(imageData, 0, 0);
	return context?.getImageData(action.x, action.y, Math.min(width - action.x, action.width), Math.min(height - action.y, action.height))!;
}

function cloneImageData(imageData: ImageData) : ImageData {
	return new ImageData(
		new Uint8ClampedArray(imageData.data),
		imageData.width,
		imageData.height
	);
}
