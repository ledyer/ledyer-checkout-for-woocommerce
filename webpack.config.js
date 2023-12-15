const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const stylesHandler = MiniCssExtractPlugin.loader;

const path = require( 'path' );
const glob = require( 'glob' );

/**
 * Help make webpack entries to the correct format with name: path
 * Modify name to exclude path and file extension
 *
 * @param {Object} paths
 */
const entryObject = ( paths ) => {
    const entries = {};

    paths.forEach( function ( filePath ) {
        let fileName = filePath.split( '/' ).slice( -1 )[ 0 ];
        fileName = fileName.replace( /\.[^/.]+$/, '' );

        if ( ! fileName.startsWith( '_' ) ) {
            entries[ fileName ] = filePath;
        }
    } );

    return entries;
};

module.exports = {
    ...defaultConfig,
    entry: entryObject( glob.sync( './assets/{css,js}/*.{css,js*}' ) ),
    output: {
        filename: '[name].js',
        path: path.resolve( process.cwd(), 'build' ),
        publicPath: '/content/plugins/ledyer-checkout-for-woocommerce/build/',
    },
    module: {
        ...defaultConfig.module,
        rules: [
            ...defaultConfig.module.rules,
            {
                test: /\.css$/i,
                use: [stylesHandler,'css-loader'],
            },
        ],
    },
    plugins: [
        new MiniCssExtractPlugin(),
        ...defaultConfig.plugins.filter(
            ( plugin ) => plugin.constructor.name !== 'CleanWebpackPlugin'
        ),
    ],
};
