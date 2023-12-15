const WordPressConfig = require( '@wordpress/scripts/config/webpack.config' );
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

/**
 * Extend the default WordPress/Scripts webpack to make entries and output more dynamic.
 * This checks the assts/js and assets/scss folder for any .js* and .scss files and compiles those to separate files
 *
 * Latest @Wordpress/Scripts webpack config: https://github.com/WordPress/gutenberg/blob/master/packages/scripts/config/webpack.config.js
 */
module.exports = {
    ...WordPressConfig,
    entry: entryObject( glob.sync( './assets/{css,js}/*.{css,js*}' ) ),
    output: {
        filename: '[name].js',
        path: path.resolve( process.cwd(), 'build' ),
        publicPath: '/content/plugins/ledyer-checkout-for-woocommerce/build/',
    },
    module: {
        ...WordPressConfig.module,
        rules: [
            ...WordPressConfig.module.rules,
            {
                test: /\.(png|jp(e*)g|gif)$/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: 'images/[name].[ext]',
                        },
                    },
                ],
            },
            {
                test: /\.(woff|woff2|eot|ttf|otf)$/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: 'fonts/[name].[ext]', // TODO: if multiple folders inside the fonts folder with the same filename inside the folders. this will override files with the latest compiled file
                        },
                    },
                ],
            },
        ],
    },
    plugins: [
        ...WordPressConfig.plugins.filter(
            ( plugin ) => plugin.constructor.name !== 'CleanWebpackPlugin'
        ),
    ],
};
