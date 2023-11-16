import uniqueId from 'lodash/uniqueId';
import { useState } from '@wordpress/element';

export default function useId() {
	return useState( () => uniqueId() )[0];
}
