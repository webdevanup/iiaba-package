import { basename, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from 'node:path';
import { sync as globSync } from 'glob';
import DependencyExtractionWebpackPlugin from '@wordpress/dependency-extraction-webpack-plugin';

const __dirname = dirname( fileURLToPath( import.meta.url ) );

function moduleName( str ) {
	return str.replace( /\//g, '.' ).replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );
}

function wdgExternal( { request }, callback ) {
	const ns = /@wdg\//;

	if ( ns.test( request ) ) {
		return callback( null, moduleName( request.replace( ns, 'wdg.' ) ) );
	}

	return callback();
}

export default function (
	env,
	{
		mode = 'production',
		...args
	}
) {
	return globSync( './js/*.js' ).map( ( entry ) => {
		const { dir, name, ext } = parse( entry );

		return {
			entry: `./${entry}`,
			output: {
				filename: `./${entry}`,
				path: resolve( __dirname, 'dist' ),
				iife: true,
				library: {
					type: 'assign-properties',
					name: `wdg.${ moduleName( basename( entry, ext ) ) }`,
				},
			},
			mode,
			devtool: 'source-map',
			externals: [ wdgExternal ],
			plugins: [
				new DependencyExtractionWebpackPlugin( {
					outputFormat: 'json',
					combineAssets: false,
					outputFilename: `${dir}/${name}.json`,
				} ),
			],
			module: {
				rules: [
					{
						test: /\.(?:json)$/,
						type: 'json',
					},
					{
						test: /\.(?:svg)$/,
						type: 'asset/inline',
					},
					{
						test: /\.(?:js|mjs|cjs)$/,
						exclude: /node_modules/,
						use: {
							loader: 'babel-loader',
							options: {
								presets: [
									[
										'@babel/preset-env',
										{
											targets: 'defaults',
											modules: false,
										},
									],
									[
										'@babel/preset-react',
										{
											pragma: 'wp.element.createElement',
											pragmaFrag: 'wp.element.Fragment',
										}
									],
								],
							},
						},
					},
				],
			},
		}
	} );
}
