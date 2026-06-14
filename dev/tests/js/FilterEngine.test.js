/** FilterEngine — exhaustive mocked unit tests. */
const { describe, test, expect, beforeEach, afterEach } = require( '@jest/globals' );
const { buildFilterWidget } = require( './helpers/filter-dom.js' );

require( '../../../assets/frontend/js/FilterEngine.js' );

function mockFetchSuccess( html ) {
	global.fetch = jest.fn().mockResolvedValue( {
		ok: true,
		json: () => Promise.resolve( {
			success: true,
			html: html || '<div data-clefa-posts>results</div>',
			active_filters: [],
			url_params: 'clefa_filter[cat]=news',
		} ),
	} );
}

function buildRichWidget( opts ) {
	const widget = buildFilterWidget( opts );
	const config = JSON.parse( widget.getAttribute( 'data-clefa-filter-config' ) );
	config.restUrl = opts.restUrl || 'http://example.com/wp-json/clefa/v1/filter';
	config.nonce = opts.nonce || 'test-nonce';
	config.autoApply = opts.autoApply !== false;
	config.syncUrl = !! opts.syncUrl;
	config.widgetId = opts.widgetId || 'w1';
	widget.setAttribute( 'data-clefa-filter-config', JSON.stringify( config ) );
	return widget;
}

describe( 'FilterEngine config & DOM wiring', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		jest.useFakeTimers();
		mockFetchSuccess();
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.clearAllMocks();
	} );

	test( 'constructor parses config from data attribute', () => {
		const engine = new window.CLEFAFilterEngine( buildRichWidget( { sections: [ { section_id: 'cat', label: 'Cat' } ] } ) );
		expect( engine.config ).not.toBeNull();
		expect( engine.config.sections ).toHaveLength( 1 );
	} );

	test( '_parseConfig returns null when attribute missing', () => {
		const bare = document.createElement( 'div' );
		document.body.appendChild( bare );
		expect( new window.CLEFAFilterEngine( bare ).config ).toBeNull();
	} );

	test( '_parseConfig returns null on invalid JSON', () => {
		const w = document.createElement( 'div' );
		w.setAttribute( 'data-clefa-filter-config', '{bad json' );
		const form = document.createElement( 'form' );
		form.setAttribute( 'data-clefa-filter-form', '1' );
		w.appendChild( form );
		document.body.appendChild( w );
		expect( new window.CLEFAFilterEngine( w ).config ).toBeNull();
	} );

	test( 'constructor skips init when form or config missing', () => {
		const w = document.createElement( 'div' );
		w.setAttribute( 'data-clefa-filter-config', '{}' );
		document.body.appendChild( w );
		expect( () => new window.CLEFAFilterEngine( w ) ).not.toThrow();
	} );

	test( '_findResultsEl uses sibling with data-clefa-filter-results', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const results = document.createElement( 'div' );
		results.setAttribute( 'data-clefa-filter-results', '1' );
		widget.insertAdjacentElement( 'afterend', results );
		expect( new window.CLEFAFilterEngine( widget ).resultsEl ).toBe( results );
	} );

	test( '_findResultsEl resolves resultsTarget id', () => {
		const target = document.createElement( 'div' );
		target.id = 'filter-results';
		document.body.appendChild( target );
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ], resultsTarget: 'filter-results' } );
		expect( new window.CLEFAFilterEngine( widget ).resultsEl ).toBe( target );
	} );

	test( '_findResultsEl is null without target or sibling', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		expect( new window.CLEFAFilterEngine( widget ).resultsEl ).toBeNull();
	} );
} );

describe( 'FilterEngine._collectState', () => {
	let widget;
	let engine;

	beforeEach( () => {
		document.body.innerHTML = '';
		jest.useFakeTimers();
		mockFetchSuccess();
		widget = buildRichWidget( {
			sections: [
				{ section_id: 'cat', label: 'Category' },
				{ section_id: 'type', label: 'Type' },
				{ section_id: 'q', label: 'Search' },
			],
		} );
		const form = widget.querySelector( '[data-clefa-filter-form]' );
		const radio = document.createElement( 'input' );
		radio.type = 'radio';
		radio.name = 'type';
		radio.value = 'video';
		radio.setAttribute( 'data-clefa-filter-input', 'type' );
		form.appendChild( radio );
		const select = document.createElement( 'select' );
		select.setAttribute( 'data-clefa-filter-input', 'sort' );
		select.innerHTML = '<option value="">Any</option><option value="date">Date</option>';
		form.appendChild( select );
		const search = document.createElement( 'input' );
		search.type = 'search';
		search.setAttribute( 'data-clefa-filter-input', 'q' );
		form.appendChild( search );
		engine = new window.CLEFAFilterEngine( widget );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'returns checked checkbox values keyed by section', () => {
		const checkbox = widget.querySelector( 'input[type="checkbox"]' );
		checkbox.checked = true;
		checkbox.value = 'news';
		expect( engine._collectState().cat ).toContain( 'news' );
	} );

	test( 'ignores unchecked checkboxes', () => {
		const checkbox = widget.querySelector( 'input[type="checkbox"]' );
		checkbox.checked = false;
		expect( engine._collectState().cat ).toBeUndefined();
	} );

	test( 'collects checked radio value', () => {
		const radio = widget.querySelector( 'input[type="radio"]' );
		radio.checked = true;
		expect( engine._collectState().type ).toBe( 'video' );
	} );

	test( 'collects select value when set', () => {
		const select = widget.querySelector( 'select' );
		select.value = 'date';
		expect( engine._collectState().sort ).toBe( 'date' );
	} );

	test( 'ignores empty select value', () => {
		expect( engine._collectState().sort ).toBeUndefined();
	} );

	test( 'collects trimmed search text', () => {
		const search = widget.querySelector( 'input[type="search"]' );
		search.value = '  hello  ';
		expect( engine._collectState().q ).toBe( 'hello' );
	} );

	test( 'ignores whitespace-only search', () => {
		const search = widget.querySelector( 'input[type="search"]' );
		search.value = '   ';
		expect( engine._collectState().q ).toBeUndefined();
	} );

	test( 'collects dual range when not at defaults', () => {
		const wrap = document.createElement( 'div' );
		wrap.setAttribute( 'data-clefa-range-dual', '1' );
		wrap.setAttribute( 'data-clefa-filter-input', 'price' );
		wrap.setAttribute( 'data-min', '0' );
		wrap.setAttribute( 'data-max', '100' );
		const hiddenMin = document.createElement( 'input' );
		hiddenMin.setAttribute( 'data-clefa-range-hidden', 'min' );
		hiddenMin.value = '10';
		const hiddenMax = document.createElement( 'input' );
		hiddenMax.setAttribute( 'data-clefa-range-hidden', 'max' );
		hiddenMax.value = '90';
		wrap.appendChild( hiddenMin );
		wrap.appendChild( hiddenMax );
		engine.formEl.appendChild( wrap );
		expect( engine._collectState().price ).toEqual( { min: 10, max: 90 } );
	} );
} );

describe( 'FilterEngine requests & debounce', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		jest.useFakeTimers();
		mockFetchSuccess();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( '_scheduleRequest debounces fetch by 400ms', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const engine = new window.CLEFAFilterEngine( widget );
		engine._scheduleRequest();
		expect( fetch ).not.toHaveBeenCalled();
		jest.advanceTimersByTime( 400 );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( '_sendRequest POSTs filter state JSON', async () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const engine = new window.CLEFAFilterEngine( widget );
		const cb = widget.querySelector( 'input[type="checkbox"]' );
		cb.checked = true;
		cb.value = 'news';
		engine._sendRequest();
		await Promise.resolve();
		expect( fetch ).toHaveBeenCalled();
		const opts = fetch.mock.calls[ 0 ][ 1 ];
		expect( opts.method ).toBe( 'POST' );
		const body = JSON.parse( opts.body );
		expect( body.filter.cat ).toContain( 'news' );
		expect( body.page ).toBe( 1 );
	} );

	test( 'change on filter input triggers debounced request', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		new window.CLEFAFilterEngine( widget );
		const cb = widget.querySelector( 'input[type="checkbox"]' );
		cb.checked = true;
		cb.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		jest.advanceTimersByTime( 400 );
		expect( fetch ).toHaveBeenCalled();
	} );

	test( 'apply button sends request when autoApply disabled', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ], autoApply: false } );
		const btn = document.createElement( 'button' );
		btn.setAttribute( 'data-clefa-filter-apply', '1' );
		widget.appendChild( btn );
		new window.CLEFAFilterEngine( widget );
		btn.click();
		expect( fetch ).toHaveBeenCalled();
	} );

	test( '_resetFilters clears form and sends request', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const engine = new window.CLEFAFilterEngine( widget );
		const cb = widget.querySelector( 'input[type="checkbox"]' );
		cb.checked = true;
		const resetBtn = document.createElement( 'button' );
		resetBtn.setAttribute( 'data-clefa-filter-reset', '1' );
		widget.appendChild( resetBtn );
		resetBtn.click();
		expect( cb.checked ).toBe( false );
		expect( fetch ).toHaveBeenCalled();
	} );

	test( '_setLoading toggles loading element visibility', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const loading = document.createElement( 'div' );
		loading.setAttribute( 'data-clefa-filter-loading', '1' );
		widget.appendChild( loading );
		const engine = new window.CLEFAFilterEngine( widget );
		engine._setLoading( true );
		expect( loading.style.display ).not.toBe( 'none' );
		engine._setLoading( false );
		expect( loading.style.display ).toBe( 'none' );
	} );
} );

describe( 'FilterEngine UI interactions', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		jest.useFakeTimers();
		mockFetchSuccess();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( '_openDrawer and _closeDrawer toggle mobile state', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const openBtn = document.createElement( 'button' );
		openBtn.setAttribute( 'data-clefa-filter-mobile-open', '1' );
		widget.appendChild( openBtn );
		const engine = new window.CLEFAFilterEngine( widget );
		engine._openDrawer();
		expect( widget.hasAttribute( 'data-clefa-mobile-open' ) ).toBe( true );
		engine._closeDrawer();
		expect( widget.hasAttribute( 'data-clefa-mobile-open' ) ).toBe( false );
	} );

	test( 'collapsible section toggle expands and collapses body', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const section = document.createElement( 'div' );
		section.className = 'clefa-filter-section-collapsible';
		const toggle = document.createElement( 'button' );
		toggle.className = 'clefa-filter-section-toggle';
		toggle.setAttribute( 'aria-expanded', 'true' );
		const body = document.createElement( 'div' );
		body.className = 'clefa-filter-section-body';
		section.appendChild( toggle );
		section.appendChild( body );
		widget.appendChild( section );
		new window.CLEFAFilterEngine( widget );
		toggle.click();
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( body.style.display ).toBe( 'none' );
	} );

	test( '_updateResults replaces posts area HTML', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const results = document.createElement( 'div' );
		results.setAttribute( 'data-clefa-filter-results', '1' );
		results.innerHTML = '<div data-clefa-posts>old</div>';
		widget.insertAdjacentElement( 'afterend', results );
		const engine = new window.CLEFAFilterEngine( widget );
		engine._updateResults( { posts_html: '<div data-clefa-posts>new</div>' }, false );
		expect( results.querySelector( '[data-clefa-posts]' ).textContent ).toBe( 'new' );
	} );

	test( '_updateChips renders active filter chips', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const bar = document.createElement( 'div' );
		bar.setAttribute( 'data-clefa-chips-bar', '1' );
		const wrap = document.createElement( 'div' );
		wrap.setAttribute( 'data-clefa-chips', '1' );
		bar.appendChild( wrap );
		widget.appendChild( bar );
		const engine = new window.CLEFAFilterEngine( widget );
		engine._updateChips( [ { section_id: 'cat', label: 'News', value_label: 'news' } ] );
		expect( wrap.querySelector( '[data-clefa-chip-remove="cat"]' ) ).toBeTruthy();
	} );

	test( 'pagination click updates page and sends request', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const results = document.createElement( 'div' );
		results.setAttribute( 'data-clefa-filter-results', '1' );
		results.innerHTML = '<button data-clefa-page="3">3</button>';
		widget.insertAdjacentElement( 'afterend', results );
		const engine = new window.CLEFAFilterEngine( widget );
		results.querySelector( '[data-clefa-page]' ).click();
		expect( engine._page ).toBe( 3 );
		expect( fetch ).toHaveBeenCalled();
	} );

	test( '_clearSection unchecks section inputs', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const engine = new window.CLEFAFilterEngine( widget );
		const cb = widget.querySelector( 'input[type="checkbox"]' );
		cb.checked = true;
		engine._clearSection( 'cat' );
		expect( cb.checked ).toBe( false );
	} );

	test( 'dual range slider clamps min below max on input', () => {
		const widget = buildRichWidget( { sections: [ { section_id: 'cat' } ] } );
		const form = widget.querySelector( '[data-clefa-filter-form]' );
		const wrap = document.createElement( 'div' );
		wrap.setAttribute( 'data-clefa-range-dual', '1' );
		wrap.setAttribute( 'data-min', '0' );
		wrap.setAttribute( 'data-max', '100' );
		wrap.innerHTML = [
			'<input data-clefa-range-dual-input="min" type="range" value="50">',
			'<input data-clefa-range-dual-input="max" type="range" value="40">',
			'<div data-clefa-range-dual-fill></div>',
			'<span data-clefa-range-dual-value="min"></span>',
			'<span data-clefa-range-dual-value="max"></span>',
			'<input data-clefa-range-hidden="min" value="50">',
			'<input data-clefa-range-hidden="max" value="40">',
		].join( '' );
		form.appendChild( wrap );
		new window.CLEFAFilterEngine( widget );
		const inputMin = wrap.querySelector( '[data-clefa-range-dual-input="min"]' );
		inputMin.value = '60';
		inputMin.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		expect( parseFloat( inputMin.value ) ).toBeLessThanOrEqual( 40 );
	} );
} );
