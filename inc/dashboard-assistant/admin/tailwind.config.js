/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./src/**/*.{html,js,ts,tsx}', '../../src/**/*.{js,ts,tsx}'],
	theme: {
		extend: {
			colors: {
				'gradient-blue': 'rgba(70, 103, 222, 0.66)',
				'gradient-pink': 'rgba(232, 121, 249, 0.66)',
			},
			animation: {
				loader: 'loader 0.6s infinite alternate',
			},
			keyframes: {
				loader: {
					to: {
						opacity: 0.1,
						transform: 'translate3d(0, -6px, 0)',
					},
				},
			},
		},
	},

	plugins: [],
	important: '.tailwind',
	corePlugins: {
		preflight: false,
	},
};
