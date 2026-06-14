/**
 * Every public method on every CLEFA JS module must exist and be callable.
 * One test per method — no skipped modules.
 */
const path = require( 'path' );

require( path.resolve( __dirname, '../../../assets/frontend/js/TransitionEngine.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/EventDispatcher.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/ValidationEngine.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/ConditionEngine.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/StepRouter.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/FormEngine.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/FilterEngine.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/LiveCheckManager.js' ) );
require( path.resolve( __dirname, '../../../assets/frontend/js/UploadManager.js' ) );
require( path.resolve( __dirname, '../../../assets/admin/js/admin-builder.js' ) );

function methodTests( moduleName, obj, methods ) {
	describe( moduleName + ' methods', () => {
		methods.forEach( ( name ) => {
			test( name + ' is a function', () => {
				expect( typeof obj[ name ] ).toBe( 'function' );
			} );
		} );
	} );
}

methodTests( 'TransitionEngine', window.CLEFA.TransitionEngine, [
	'isEnabled', 'setFieldVisible', 'setStepActive', 'whenSettled',
] );

methodTests( 'EventDispatcher', window.CLEFAEventDispatcher, [
	'dispatch', 'setDebug',
] );

methodTests( 'ConditionEngine (static compare)', window.CLEFA.ConditionEngine, [
	'compare',
] );

methodTests( 'ConditionEngine.prototype', window.CLEFA.ConditionEngine.prototype, [
	'_buildFieldMap', 'init', 'evaluateAll', '_collectData', '_collectRepeaterRowData',
	'_evaluate', '_applyResult', '_applyResultToWrapper', '_saveOriginal', '_getOriginal',
] );

methodTests( 'ValidationRegistry', window.CLEFA.ValidationRegistry, [
	'register', 'check', '_registerCoreRules',
] );

methodTests( 'ValidationEngine.prototype', window.CLEFA.ValidationEngine.prototype, [
	'_buildFieldDefs', 'init', '_updateStepButtonStates', 'validateStep', 'validateAll',
	'_validateField', '_findRuleDef', '_showError', '_clearError', '_isEmpty',
	'_getFieldValue', '_collectAllData', 'showServerErrors',
] );

methodTests( 'StepRouter.prototype', window.CLEFA.StepRouter.prototype, [
	'_buildStepIndex', 'init', 'goNext', 'goPrev', '_bindButtonModes', '_syncButtonMode',
	'_syncButtonModeForStep', '_stepRequiredFilled', '_showStep', '_updateProgress',
	'_findStepIndexById', '_collectData', 'isLastStep', 'getCurrentIndex',
] );

methodTests( 'FormEngine.prototype', window.CLEFA.FormEngine.prototype, [
	'_parseConfig', '_init', '_formHasConditions', '_bindSubmit', '_handleSubmit',
	'_handleResponse', '_saveDraft', '_restoreDraft', '_clearDraft',
] );

methodTests( 'FilterEngine.prototype', window.CLEFAFilterEngine.prototype, [
	'_parseConfig', '_findResultsEl', '_initDualRanges', '_initSingleRanges', '_initCollapsibles',
	'_bindInputs', '_bindActions', '_openDrawer', '_closeDrawer', '_bindPagination',
	'_restoreFromUrl', '_bindPopState', '_syncUrl', '_collectState', '_resetFilters',
	'_clearSection', '_scheduleRequest', '_sendRequest', '_updateResults', '_updateChips', '_setLoading',
] );

methodTests( 'LiveCheckManager.prototype', window.CLEFA.LiveCheckManager.prototype, [
	'init', '_bindInput', '_runCheck', '_setStatus', '_clearStatus', '_i18n',
] );

methodTests( 'UploadManager.FileUpload.prototype', window.CLEFA.UploadManager.FileUpload.prototype, [
	'_bindEvents', '_processFiles', '_uploadFile', '_createListItem', '_removeUpload',
	'_syncHiddenInputs', '_showItemError', '_markItemError', '_esc',
] );

methodTests( 'UploadManager', window.CLEFA.UploadManager, [
	'initAll',
] );

methodTests( 'CLEFA.BuilderTest (admin-builder.js)', window.CLEFA.BuilderTest, [
	'uid', 'makeStep', 'makeField', 'esc', 'setNestedValue', 'isDefinitionAvailable',
	'getDisabledReason', 'getFieldLabel', 'getFieldIcon', 'getActionDef', 'markDirty',
	'markClean', 'getState', 'render', 'findField', 'addFieldToStep', 'deleteField',
	'addStep', 'deleteStep',
] );

describe( 'CLEFA global init helpers', () => {
	test( 'CLEFA.initForms is a function', () => {
		expect( typeof window.CLEFA.initForms ).toBe( 'function' );
	} );

	test( 'CLEFAInitFilters is a function', () => {
		expect( typeof window.CLEFAInitFilters ).toBe( 'function' );
	} );

	test( 'UploadManager.FileUpload is a constructor', () => {
		expect( typeof window.CLEFA.UploadManager.FileUpload ).toBe( 'function' );
	} );
} );
