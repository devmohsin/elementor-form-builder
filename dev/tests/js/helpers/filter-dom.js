/** DOM helpers for FilterEngine tests. */
function buildFilterWidget( opts = {} ) {
	const sections = opts.sections || [];
	const config = {
		postType: 'post',
		sections,
		resultsTarget: opts.resultsTarget || '',
	};

	const widget = document.createElement( 'div' );
	widget.setAttribute( 'data-clefa-filter-widget', '1' );
	widget.setAttribute( 'data-clefa-filter-config', JSON.stringify( config ) );

	const form = document.createElement( 'form' );
	form.setAttribute( 'data-clefa-filter-form', '1' );

	sections.forEach( ( sec ) => {
		const sectionEl = document.createElement( 'div' );
		sectionEl.setAttribute( 'data-clefa-filter-section', sec.section_id );
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.setAttribute( 'data-clefa-filter-input', sec.section_id );
		input.name = 'clefa_filter[' + sec.section_id + '][]';
		input.value = 'news';
		sectionEl.appendChild( input );
		form.appendChild( sectionEl );
	} );

	widget.appendChild( form );
	var mount = document.getElementById( 'clefa-test-mount' );
	if ( mount ) {
		mount.appendChild( widget );
	} else {
		document.body.appendChild( widget );
	}
	return widget;
}

module.exports = { buildFilterWidget };
