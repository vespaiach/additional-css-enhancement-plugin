import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { basename, join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const rootDir = join( fileURLToPath( new URL( '.', import.meta.url ) ), '..' );
const pluginSlug = 'additional-css-enhancement';
const distDir = join( rootDir, 'dist' );
const stagingDir = join( distDir, pluginSlug );
const zipName = `${ pluginSlug }.zip`;

const productionPaths = [
	'additional-css-enhancement.php',
	'uninstall.php',
	'supported-blocks.json',
	'build',
];

rmSync( distDir, { recursive: true, force: true } );
mkdirSync( stagingDir, { recursive: true } );

for ( const relativePath of productionPaths ) {
	const sourcePath = join( rootDir, relativePath );

	if ( ! existsSync( sourcePath ) ) {
		throw new Error( `Missing production file: ${ relativePath }` );
	}

	cpSync( sourcePath, join( stagingDir, basename( relativePath ) ), {
		recursive: true,
		errorOnExist: false,
		force: true,
	});
}

const zipResult = spawnSync(
	'zip',
	[ '-r', '-X', zipName, pluginSlug ],
	{
		cwd: distDir,
		env: {
			...process.env,
			COPYFILE_DISABLE: '1',
		},
		stdio: 'inherit',
	}
);

if ( zipResult.error ) {
	throw zipResult.error;
}

if ( 0 !== zipResult.status ) {
	process.exit( zipResult.status ?? 1 );
}

rmSync( stagingDir, { recursive: true, force: true } );

console.log( `Created dist/${ zipName }` );
