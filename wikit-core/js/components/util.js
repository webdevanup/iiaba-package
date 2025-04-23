export function titleCase( string ) {
	if ( string ) {
		return string
			.split( /[-_\s]/g )
			.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ).toLowerCase() )
			.join( ' ' );
	}

	return '';
}
