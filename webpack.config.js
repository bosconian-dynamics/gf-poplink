const path = require( 'path' );

const config = require( '@wordpress/scripts/config/webpack.config' );

const isProduction = process.env.NODE_ENV === 'production';

config.entry = {
  'form-admin': path.resolve( process.cwd(), 'src', 'form-admin.js' ),
  'frontend': path.resolve( process.cwd(), 'src', 'frontend.js' ),
};

config.output = {
  filename: '[name].js',
  path: path.resolve( process.cwd(), 'build' ),
};

config.resolve = {
  ...config.resolve,
  roots: [
    path.resolve( process.cwd(), 'src' ),
  ],
  alias: {
    ...config.resolve.alias,
  }
};

module.exports = config;