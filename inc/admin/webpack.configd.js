// change webpack default config
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

if ( process.env.NODE_ENV !== 'production' ) {
	// defaultConfig.devServer.server = {
	// 	type: 'https',
	// 	options: {
	// 		cert: 'path to your certitificate (use forward slashes)/your-certificate.crt',
	// 		key: 'path to ceritificate key (use forward slashes)/your-key.key'
	// 	}
	// };
	// defaultConfig.devServer.allowedHosts = ['your-host-name(if changed)', 'localhost', '127.0.0.1'];

}

console.log( defaultConfig );


const { join } = require( 'path' );

/**
 * WordPress dependencies
 */
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const sharedConfig = {
	mode: 'development',
	target: 'browserslist:/Users/joe/altis/ai/content/plugins/ai/inc/admin/node_modules/@wordpress/scripts/config/.browserslistrc',
	output: {
		filename: '[name]/index.min.js',
		path: join( __dirname, '..', '..', 'build' ),
	},
};

// See https://github.com/pmmmwh/react-refresh-webpack-plugin/blob/main/docs/TROUBLESHOOTING.md#externalising-react.
module.exports = [
	defaultConfig,
	{
		...sharedConfig,
		name: 'react-refresh-entry',
		entry: {
			'react-refresh-entry':
				'@pmmmwh/react-refresh-webpack-plugin/client/ReactRefreshEntry.js',
		},
		plugins: [ new DependencyExtractionWebpackPlugin() ],
	},
	{
		...sharedConfig,
		name: 'react-refresh-runtime',
		entry: {
			'react-refresh-runtime': {
				import: 'react-refresh',
				library: {
					name: 'ReactRefreshRuntime',
					type: 'window',
				},
			},
		},
		plugins: [
			new DependencyExtractionWebpackPlugin( {
				useDefaults: false,
			} ),
		],
	},
];
