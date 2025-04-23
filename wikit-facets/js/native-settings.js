document.addEventListener('DOMContentLoaded', () => {
	const { apiFetch } = wp;

	const progress  = document.querySelector('progress');
	const fieldset  = document.querySelector('fieldset');
	const rebuild   = document.querySelector('button[data-action="rebuild"]');
	const wipe      = document.querySelector('button[data-action="wipe"]');
	const create    = document.querySelector('button[data-action="create"]');
	const indexSize = document.querySelector('.index-size');

	function setIndexSize( size ) {
		while ( indexSize.firstChild ) {
			indexSize.removeChild( indexSize.firstChild );
		}

		indexSize.appendChild( document.createTextNode( size ) );
	}

	function onRebuild() {
		progress.removeAttribute('hidden');
		fieldset.disabled = true;
		rebuild.classList.add('is-busy');

		apiFetch({
			path: 'wdg/v1/facets-index',
			method: 'PUT'
		}).then((response) => {
			if ( response.complete ) {
				progress.hidden = true;
				progress.value  = 0;

				fieldset.removeAttribute('disabled');
				rebuild.classList.remove('is-busy');
			} else {
				onRebuild();

				if ( response.page && response.max_page && response.max_page > 0 ) {
					progress.value = response.page / response.max_page;
				}
			}

			if ( indexSize && response.total ) {
				setIndexSize( response.total );
			}
		}).catch((fault) => {
			console.error(fault);
		});
	}

	function onWipe() {
		fieldset.disabled = true;

		apiFetch({
			path: 'wdg/v1/facets-index',
			method: 'DELETE'
		}).then((response) => {
			if ( response.success ) {
				setIndexSize(0);
				create.style.display = '';
				rebuild.style.display = 'none';
				wipe.style.display = 'none';
				// onCreate();
			}
		}).catch((fault) => {
			console.error(fault);
		}).finally(() => {
			fieldset.removeAttribute('disabled');
		});
	}

	function onCreate() {
		fieldset.disabled = true;

		apiFetch({
			path: 'wdg/v1/facets-index',
			method: 'POST'
		}).then((response) => {
			console.log(response);

			if ( response.success ) {
				rebuild.style.display = '';
				wipe.style.display = '';
				create.style.display = 'none';
				onRebuild();
			}
		}).catch((fault) => {
			console.error(fault);
		}).finally(() => {
			fieldset.removeAttribute('disabled');
		});
	}

	if ( rebuild ) {
		rebuild.addEventListener( 'click', onRebuild );
		rebuild.removeAttribute( 'disabled' );
	}

	if ( wipe ) {
		wipe.addEventListener( 'click', onWipe );
		wipe.removeAttribute( 'disabled' );
	}

	if ( create ) {
		create.addEventListener( 'click', onCreate );
		create.removeAttribute( 'disabled' );
	}
});

