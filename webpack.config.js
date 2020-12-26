const path = require( 'path' );

const config = require( '@wordpress/scripts/config/webpack.config' );

config.entry = {
	'form-admin': path.resolve( process.cwd(), 'src', 'form-admin.js' ),
	frontend: path.resolve( process.cwd(), 'src', 'frontend.js' ),
	prepop: path.resolve( process.cwd(), 'src', 'prepop.js' ),
};

config.output = {
	filename: '[name].js',
	path: path.resolve( process.cwd(), 'build' ),
};

config.resolve = {
	...config.resolve,
	roots: [ path.resolve( process.cwd(), 'src' ) ],
	alias: {
		...config.resolve.alias,
	},
};

module.exports = config;
