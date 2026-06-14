requirements

1. Plugin Structure

The plugin should be built as a modular system, not one large Elementor widget file.

advanced-form-builder/
  advanced-form-builder.php

  includes/
    Core/
      Plugin.php
      Loader.php
      Hooks.php
      Assets.php
      Capabilities.php
      Installer.php
      Uninstaller.php

    Elementor/
      Widgets/
        Form_Builder_Widget.php
        Sidebar_Filter_Widget.php
      Controls/
        Field_Controls.php
        Step_Controls.php
        Style_Controls.php
        Action_Controls.php
        Validation_Controls.php
        Condition_Controls.php
        Query_Controls.php

    Forms/
      Form_Renderer.php
      Form_Config_Normalizer.php
      Form_State_Manager.php
      Form_Submission_Handler.php
      Form_Validator.php
      Form_Sanitizer.php
      Form_Escaper.php
      Form_Condition_Engine.php
      Form_Routing_Engine.php
      Form_Action_Runner.php
      Form_Test_Runner.php

    Fields/
      Abstract_Field.php
      Text_Field.php
      Email_Field.php
      Password_Field.php
      Textarea_Field.php
      Select_Field.php
      Checkbox_Field.php
      Radio_Field.php
      Date_Field.php
      File_Field.php
      Multi_File_Field.php
      Hidden_Field.php
      Html_Field.php
      Range_Field.php
      Repeater_Field.php
      Grid_Break_Field.php

    Actions/
      Abstract_Action.php
      Login_Action.php
      Register_Action.php
      Lost_Password_Action.php
      Confirm_Password_Action.php
      Redirect_Action.php
      User_Meta_Action.php
      Post_Meta_Action.php
      ACF_Action.php
      Taxonomy_Action.php
      WooCommerce_Product_Action.php
      Email_Action.php
      Webhook_Action.php

    Uploads/
      Upload_Controller.php
      Upload_Validator.php
      Upload_Temporary_Store.php
      Upload_Attachment_Finalizer.php
      Upload_Cleanup.php

    Filters/
      Sidebar_Filter_Renderer.php
      Query_Builder.php
      Query_Result_Handler.php
      Pagination_Handler.php
      Elementor_Loop_Handler.php

    Database/
      Tables.php
      Submission_Repository.php
      Upload_Repository.php
      Test_Log_Repository.php
      Audit_Log_Repository.php

    REST/
      Routes.php
      Submit_Form_Route.php
      Refresh_Nonce_Route.php
      Upload_File_Route.php
      Live_Check_Route.php
      Filter_Query_Route.php
      Run_Test_Route.php

    Admin/
      Options_Page.php
      Submissions_Page.php
      Tests_Page.php
      Logs_Page.php

    Utilities/
      Array_Utils.php
      Date_Utils.php
      Security_Utils.php
      Validation_Utils.php
      Template_Utils.php
      Asset_Utils.php
      String_Utils.php
      User_Utils.php
      Post_Utils.php
      WooCommerce_Utils.php

  assets/
    frontend/
      css/
        form-default.css
        form-builder.css
        sidebar-filter.css
      js/
        FormEngine.js
        ConditionEngine.js
        ValidationEngine.js
        StepRouter.js
        UploadManager.js
        LiveCheckManager.js
        EventDispatcher.js
        SidebarFilter.js

    editor/
      css/
        elementor-editor.css
      js/
        elementor-form-builder.js

    vendor/
      select2/
      validation-library/

  templates/
    form.php
    step.php
    field-wrapper.php
    notices.php
    buttons.php
    fields/
      text.php
      email.php
      password.php
      textarea.php
      select.php
      checkbox.php
      radio.php
      date.php
      file.php
      multi-file.php
      hidden.php
      html.php
      range.php
      repeater.php
      grid-break.php
    filters/
      sidebar-filter.php
      filter-section.php
      filter-results.php
      pagination.php
2. Core Loading Architecture

The plugin should load only what is needed.

2.1 Main plugin bootstrap

The root plugin file should only define constants and load the main plugin class.

Core constants should include:

AFB_PLUGIN_FILE
AFB_PLUGIN_PATH
AFB_PLUGIN_URL
AFB_PLUGIN_VERSION
AFB_TEMPLATE_PATH
AFB_ASSET_URL
AFB_DB_VERSION
2.2 Loader class

The loader should register:

Elementor widgets.
REST routes.
Admin pages.
Database installer.
Frontend assets.
Editor assets.
Template paths.
Submission handlers.
Upload handlers.
Scheduled cleanup events.
2.3 Conditional asset loading

Assets must only load when a page contains:

The form widget.
The sidebar filter widget.
A shortcode version of the form.
A block version, if added later.

Do not globally load Select2, validation scripts, upload scripts, or filter scripts across the whole website.

3. Database Structure

The plugin should save all form submissions, uploads, test logs, and audit logs.

3.1 Submissions table
wp_afb_submissions
  id
  form_id
  form_instance_id
  elementor_page_id
  user_id
  status
  source_url
  ip_hash
  user_agent_hash
  submitted_data_json
  sanitized_data_json
  mapped_data_json
  action_results_json
  created_at
  updated_at
3.2 Uploads table
wp_afb_uploads
  id
  form_id
  form_instance_id
  field_id
  user_id
  temp_token
  attachment_id
  original_filename
  mime_type
  file_size
  upload_status
  is_committed
  created_at
  committed_at
  expires_at
3.3 Test logs table
wp_afb_test_logs
  id
  form_id
  test_group_id
  test_name
  expected_result_json
  actual_result_json
  status
  created_records_json
  cleanup_status
  created_at
3.4 Audit logs table
wp_afb_audit_logs
  id
  form_id
  event_type
  event_context_json
  user_id
  created_at
4. Elementor Form Builder Widget

The Elementor widget should be the main visual builder.

4.1 Widget-level settings

Each form widget needs global settings:

Form ID.
Form name.
Form type.
Form data attribute.
Submission endpoint.
Require logged-in user.
Allow guest submission.
Redirect URL.
Use default redirect from plugin options.
Enable AJAX submission.
Enable REST submission.
Enable draft saving.
Enable localStorage persistence.
Enable anti-spam.
Enable dynamic nonce refresh.
Enable file uploads.
Enable instant uploads.
Enable frontend events.
Enable console helper notices.
Enable submission database storage.
4.2 Step repeater

The first-level Elementor repeater should be Steps.

Each step should include:

Step ID.
Step name.
Step heading.
Step description.
Step button text.
Previous button text.
Next button text.
Submit button text.
Step visibility rules.
Step entry conditions.
Step exit conditions.
Step routing conditions.
Step validation scope.
Step notice before fields.
Step notice after fields.
Step layout options.
Step custom CSS class.
Step custom data attributes.
4.3 Field repeater inside each step

Inside each step, there should be an Add Item button.

Each item can be:

Input field.
Textarea field.
Email field.
Password field.
Confirm password field.
Select field.
Select2 field.
Checkbox group.
Radio group.
Date field.
File upload field.
Multi-file upload field.
Range slider.
Hidden field.
HTML field.
Notice field.
Repeater field.
Grid/column break field.
Section heading.
Button field.
Dynamic span replacement field.
5. Field Configuration Plan

Every field should have consistent base settings.

5.1 Common field settings

Each field should support:

Field ID.
Field name.
Field type.
Label.
Show label.
Placeholder.
Description.
Default value.
Required.
Readonly.
Disabled.
Hidden.
Custom CSS class.
Custom wrapper class.
Custom data attributes.
Custom validation message.
Field notice.
Notice position.
Left icon.
Right icon.
Uploaded icon.
Input mask.
Dynamic token support.
Conditional display.
Conditional value replacement.
Mapping destination.
Sanitization type.
Escaping context.
5.2 Field naming convention

Nested fields need predictable names.

Use a structured naming model like:

afb[form_id][step_id][field_id]
afb[form_id][step_id][repeater_id][row_index][field_id]

For example:

afb[registration_form][personal_details][first_name]
afb[registration_form][work_history][jobs][0][company_name]
afb[registration_form][work_history][jobs][1][company_name]

This prevents messy PHP parsing and makes validation easier.

6. Repeater Field Plan

The repeater field should allow nested rows of fields.

6.1 Repeater settings

Each repeater should support:

Repeater ID.
Repeater label.
Minimum rows.
Maximum rows.
Add row button text.
Remove row button text.
Clone row support.
Sortable row support.
Row heading template.
Required rows.
Validation per row.
Conditional display per row.
Conditional field display inside each row.
Nested file uploads.
Nested date validations.
Nested checkbox/radio/select logic.
6.2 Repeater validation

Repeater validation must support:

Minimum row count.
Maximum row count.
Required fields inside each row.
Unique value inside repeater.
Date validation inside repeater.
Conditional required fields.
Required if another field has a value.
Required if checkbox is selected.
Required if row exists.
7. Grid / Column Break Field

The builder needs a structural item for layouts.

7.1 Purpose

The grid/column break field should not submit data. It should control layout only.

It should allow:

Two-column layout.
Three-column layout.
Four-column layout.
Custom CSS grid.
Responsive stacking.
Field grouping.
Wrapper start/end logic.
Repeater row layout.
Step layout.
Conditional layout sections.
7.2 Example use
Step: Personal Details
  Grid Start: 2 columns
    First Name
    Last Name
  Grid End

  Grid Start: 3 columns
    Date of Birth
    Gender
    Country
  Grid End
8. Template System

Every visible field should render through a template.

8.1 Plugin templates

Default templates live in:

advanced-form-builder/templates/
8.2 Child theme overrides

Child theme overrides should use:

your-child-theme/advanced-form-builder/
  form.php
  step.php
  fields/text.php
  fields/email.php
  fields/textarea.php
  fields/select.php
  fields/file.php
8.3 Template lookup order

The plugin should check in this order:

Child theme override.
Parent theme override.
Plugin template.
Fallback internal renderer.
8.4 Template variables

Each template should receive:

Form config.
Step config.
Field config.
Current value.
Validation state.
Error messages.
CSS classes.
Attributes.
Conditional rules.
Upload tokens.
Field mapping config.
9. Style System

The styling model should have two modes.

9.1 Default inherited mode

This should be the default.

In this mode:

The plugin outputs minimal CSS.
Theme styles are inherited.
Inputs follow the active theme.
Buttons follow the active theme.
Typography follows the active theme.
Elementor global styles still apply.
9.2 Custom style mode

If the user disables inherited styling, expose full style controls for:

Input border.
Input border radius.
Input shadow.
Input inner padding.
Input margin.
Input background.
Input text color.
Placeholder color.
Label typography.
Label spacing.
Description typography.
Error message typography.
Success message typography.
Notice box style.
Button style.
Step wrapper style.
Repeater row style.
Checkbox/radio style.
Range slider style.
Upload field style.
Icons inside inputs.
10. Frontend JavaScript Engine

The frontend should be modular and event-driven.

10.1 Core JS files
FormEngine.js
ConditionEngine.js
ValidationEngine.js
StepRouter.js
UploadManager.js
LiveCheckManager.js
EventDispatcher.js
SidebarFilter.js
10.2 Main responsibilities

The frontend engine should handle:

Step navigation.
Conditional field visibility.
Conditional HTML visibility.
Conditional span replacement.
Field validation.
Repeater row state.
Instant uploads.
Multi-file uploads.
Live API checks.
Step routing.
Dynamic next-button blocking.
Dynamic nonce refresh.
Draft persistence.
Custom browser events.
Console helper logs.
11. Conditional Logic Engine

Conditional logic should be shared across PHP and JavaScript.

11.1 Rule structure

Every condition should use a predictable rule object:

source_field
operator
compare_value
target_type
target_id
action
logic_group
11.2 Supported operators

Support:

Equals.
Not equals.
Contains.
Does not contain.
Starts with.
Ends with.
Greater than.
Less than.
Greater than or equal.
Less than or equal.
Is empty.
Is not empty.
Is checked.
Is not checked.
Date after.
Date before.
Date equals.
Age over.
Age under.
File uploaded.
API check passed.
API check failed.
11.3 Conditional targets

Conditions should target:

Field.
Field wrapper.
Step.
Button.
HTML section.
Notice.
Repeater row.
Grid section.
Span replacement.
Submit action.
Redirect URL.
User role action.
Query filter section.
12. Step Routing Engine

Step routing should allow different paths depending on user answers.

12.1 Routing examples

Example:

Step 1:
  If user_type = creator → go to Step 2
  If user_type = customer → go to Step 3
  If user_type = agency → go to Step 4
12.2 Step blocking

A user should not proceed if:

Required field is missing.
Validation fails.
Live API check fails.
Upload is still processing.
Username is taken.
Required checkbox is not selected.
Conditional field is visible and invalid.
Step-level rule returns false.
Hidden blocker token still exists.
Anti-spam check fails.
12.3 Data-block-next-step approach

For live validation, use an internal blocker system.

Example:

data-afb-block-next-step="username_check_failed"

If the username API returns available, remove the blocker.

If the username is taken, keep the blocker and show the validation message.

13. Validation Library

The plugin needs both client-side and server-side validation.

13.1 Validation categories

Support:

Required.
Email.
URL.
Number.
Integer.
Minimum number.
Maximum number.
Minimum length.
Maximum length.
Exact length.
Regex.
Date required.
Date after today.
Date before today.
Date after selected date.
Date before selected date.
Age over 18.
Age under custom value.
Password strength.
Password confirmation.
File required.
File type allowed.
File size max.
File count max.
Checkbox minimum selected.
Checkbox maximum selected.
Radio required.
Select required.
Select2 required.
Unique value.
Value must not equal X.
Value must equal X.
API check must pass.
Hidden token must be valid.
Repeater minimum rows.
Repeater maximum rows.
Repeater inner field validation.
13.2 Per-field messages

Every validation rule should allow a custom message.

Example:

Field: Date of Birth
Rule: Age over 18
Message: You must be over 18 to continue.
13.3 Final submission validation

On submit, the server must validate everything again.

This includes:

Hidden fields.
Conditional fields.
Fields not visible due to routing.
Repeater fields.
File upload tokens.
Live API check results.
Required step rules.
User login requirement.
Nonce validity.
Honeypot result.
14. Sanitization and Escaping

Sanitization must happen before saving or action execution.

Escaping must happen only when outputting data.

14.1 Sanitization map

Each field type should have a sanitization strategy:

text          → sanitize_text_field
textarea      → sanitize_textarea_field
email         → sanitize_email
url           → esc_url_raw
number        → absint / floatval
html          → wp_kses_post or restricted allowed HTML
hidden        → sanitize_text_field with token verification
checkbox      → sanitize array values
radio         → sanitize selected value
select        → sanitize selected value
file          → validate attachment/upload token
date          → validate and normalize date
range         → numeric sanitization
14.2 Escaping map

Each output context should escape correctly:

HTML text      → esc_html
Attribute      → esc_attr
URL            → esc_url
Textarea       → esc_textarea
Allowed HTML   → wp_kses_post
JSON           → wp_json_encode
15. Form Submission Handler

The form submission handler should be separate from the Elementor widget.

15.1 Submission flow

The flow should be:

Receive request.
Refresh/verify nonce.
Verify form instance.
Verify login requirement.
Verify honeypot.
Verify time-to-submit.
Load form config from saved Elementor data.
Normalize submitted data.
Validate submitted data.
Validate files.
Sanitize data.
Save submission to database.
Run mapped updates.
Run selected actions.
Finalize uploads.
Log result.
Return JSON response.
Redirect if configured.
15.2 Submission response

Return structured JSON:

success
message
errors
redirect_url
submission_id
action_results
event_payload
16. Default Form Actions

The plugin should ship with default actions.

16.1 Login action

Settings:

Username/email field.
Password field.
Remember me.
Redirect URL.
Error message.
Success message.
Require email verification option.
16.2 Register action

Settings:

Email field.
Username field.
Password field.
Confirm password field.
First name field.
Last name field.
Default role.
Custom role on success.
User meta mapping.
Auto-login after registration.
Redirect URL.
Email notification.
16.3 Lost password action

Settings:

Email/username field.
Success message.
Error message.
Redirect URL.
Custom email template option.
16.4 Confirm password flow

Settings:

Password field.
Confirm password field.
Password strength rules.
Matching validation message.
Minimum length.
Required characters.
16.5 Redirect action

Settings:

Use global default redirect.
Override redirect per form.
Conditional redirect.
Redirect with query params.
Redirect with submission ID.
Redirect after AJAX response.
17. Data Mapping System

The form should auto-link values to different WordPress data targets.

17.1 Supported mapping targets

Support:

User meta.
ACF user fields.
Post meta.
ACF post fields.
Post title.
Post content.
Post excerpt.
Featured image.
Taxonomy terms.
WooCommerce product title.
WooCommerce product description.
WooCommerce product price.
WooCommerce product sale price.
WooCommerce product categories.
WooCommerce product tags.
WooCommerce product gallery.
WooCommerce product attributes.
WooCommerce product stock.
WooCommerce downloadable files.
Custom action hook.
17.2 Mapping settings per field

Each field should allow:

No mapping.
User meta key.
ACF field key/name.
Post meta key.
Post field.
Taxonomy.
Product field.
Custom callback key.
Create if missing.
Update existing.
Append value.
Replace value.
18. File Upload System

Uploads should happen immediately, not only on submit.

18.1 Instant upload flow

When a file is selected:

Validate file type in JS.
Validate file size in JS.
Upload via REST endpoint.
Verify nonce.
Store as temporary upload.
Return temporary upload token.
Store token in hidden field.
Show upload success state.
Show remove file option.
On final submit, attach upload to submission.
On failed/abandoned form, cleanup later.
18.2 Multi-file upload

Support:

Minimum files.
Maximum files.
Drag and drop.
Multiple file tokens.
Per-file progress.
Per-file validation.
Remove single file.
Replace single file.
Reorder files.
Map files to gallery/product/media field.
18.3 Upload security

Validate:

Allowed MIME type.
File extension.
File size.
User permission.
Form permission.
Nonce.
Temporary token.
Upload ownership.
Expiry.
Final submission binding.
19. Live API Checks With Spinner

Some fields need async checks before the user can continue.

19.1 Example use cases
Username availability.
Email already exists.
Coupon code check.
Product SKU check.
Invite code check.
License key check.
Membership status check.
Custom API check.
19.2 Field-level spinner

Each field should support:

Spinner enabled.
Spinner label.
Checking message.
Success message.
Failure message.
API endpoint.
Request method.
Debounce time.
Required success before next step.
Required success before submit.

Example:

Checking username...
Username available.
Username already taken.
20. Hidden Fields and Dynamic Tokens

Hidden fields should support dynamic values.

20.1 Supported dynamic tokens

Examples:

{current_user_id}
{current_user_email}
{current_user_role}
{current_page_id}
{current_post_id}
{current_url}
{referrer_url}
{timestamp}
{submission_id}
{random_token}
{query_param:key}
{post_meta:key}
{user_meta:key}
20.2 Hidden field security

Hidden fields must not be blindly trusted.

For sensitive hidden values:

Generate server-side token.
Sign token with hash.
Verify token on submit.
Reject tampered values.
Never trust role, price, product ID, or permission values from the browser.
21. HTML Fields and Dynamic Display

HTML fields should be powerful but controlled.

21.1 Static HTML sections

HTML sections can display:

Heading.
Text.
Notice.
Card.
Warning.
Success message.
Rich HTML.
Dynamic placeholder values.
21.2 Conditional HTML display

Example:

If first_name equals Linden:
  Show HTML section: Welcome Linden
21.3 Span replacement

Support replacing text inside HTML.

Example:

Welcome, {first_name}

If the user types Linden, the HTML updates live:

Welcome, Linden
22. Notices System

The form needs notices at multiple levels.

22.1 Notice locations

Support notices:

Before form.
After form.
Before step.
After step.
Before field.
After field.
Below input.
Above submit button.
On success.
On error.
On API check.
On upload progress.
22.2 Notice types

Support:

Info.
Success.
Warning.
Error.
Custom.
23. Anti-Spam and Nonce Plan

This is important for cached sites.

23.1 Anti-spam features

Add:

Conditional honeypot.
Time-to-submit check.
Optional minimum interaction count.
Optional hidden timestamp.
Optional dynamic token.
Optional block repeated submission.
Optional IP/user hash throttling.
Optional user-agent hash.
Optional per-form rate limit.
Optional logged-in-only mode.
23.2 Dynamic nonce refresh

Before final submit:

JS requests a fresh nonce.
Server returns a nonce for that form instance.
JS injects nonce into the request.
Submit request continues.
Server verifies nonce.
If nonce fails, return retryable error.
JS can refresh once and retry.

This avoids cached pages breaking submissions.

24. JavaScript Events

The plugin should dispatch useful events for developers.

24.1 Event names

Use clean event names:

afb:form:init
afb:form:ready
afb:step:before-change
afb:step:changed
afb:field:changed
afb:condition:matched
afb:condition:unmatched
afb:validation:passed
afb:validation:failed
afb:upload:started
afb:upload:progress
afb:upload:success
afb:upload:failed
afb:live-check:started
afb:live-check:success
afb:live-check:failed
afb:submit:started
afb:submit:success
afb:submit:failed
afb:redirect:before
24.2 Console helper

When enabled, log helpful messages like:

Event fired: afb:step:changed
Use this event to run custom logic after a user changes step.
25. REST API Routes

Use REST routes for cleaner frontend actions.

25.1 Required routes
POST /afb/v1/submit
POST /afb/v1/refresh-nonce
POST /afb/v1/upload
POST /afb/v1/remove-upload
POST /afb/v1/live-check
POST /afb/v1/filter-query
POST /afb/v1/run-test
25.2 REST security

Every route should check:

Nonce.
Form instance ID.
Form permission.
User permission if logged-in required.
Rate limit where needed.
Expected payload shape.
Allowed fields only.
Server-side config rules.
26. Options Page

The plugin needs a settings page.

26.1 Global settings

Options should include:

Default redirect URL.
Default success message.
Default error message.
Default login redirect.
Default register redirect.
Default lost password redirect.
Upload max size.
Upload allowed types.
Temporary upload expiry.
Enable cleanup schedule.
Enable debug console events.
Enable submission database storage.
Enable anti-spam by default.
Enable nonce refresh by default.
Default form style mode.
Default Select2 setting.
Default validation behavior.
Default test cleanup behavior.
27. Admin Submission Viewer

You need an admin area to view submissions.

27.1 Submission list

Columns:

ID.
Form name.
Page.
User.
Status.
Source URL.
Created date.
Actions.
27.2 Submission detail

Show:

Raw submitted data.
Sanitized data.
Uploaded files.
Action results.
Validation result.
User info.
Page info.
Audit log.
Resend action option.
Delete submission option.
28. Form Test Runner

The plugin should allow testing forms.

28.1 Test purpose

The test runner should simulate submissions and verify expected results.

Examples:

Required field fails.
Email validation fails.
Date must be after today.
User must be over 18.
Username taken blocks next step.
Username available allows next step.
Role is set after registration.
User meta is saved.
ACF field is updated.
Post meta is updated.
Product field is updated.
Upload attaches correctly.
Redirect URL is correct.
Conditional step routing works.
28.2 Test logs

Each test should save:

Test name.
Expected result.
Actual result.
Pass/fail.
Created user IDs.
Created post IDs.
Created attachment IDs.
Created submission IDs.
Cleanup result.
28.3 Cleanup

Test cleanup should delete:

Test users.
Test posts.
Test attachments.
Test submissions.
Test uploads.
Test options/transients.
29. Sidebar Filter Builder

Part 2 should be treated as a second major widget.

29.1 Sidebar filter purpose

The same form engine can power filters.

Instead of submitting a form, fields update a query and return results.

29.2 Filter widget settings

The sidebar filter widget should support:

Query target.
Post type.
Product support.
Taxonomy filters.
Meta filters.
ACF filters.
Price filters.
Date filters.
Search keyword.
Sort order.
Results container.
Elementor Loop integration.
AJAX results.
Pagination.
Load more.
Reset filters.
Active filter chips.
URL query sync.
Browser history update.
Mobile drawer mode.
29.3 Filter sections

Each section should support:

Section heading.
Collapsible section.
Default open/closed.
Checkbox filters.
Radio filters.
Select filters.
Range sliders.
Date range.
Search input.
Conditional display.
29.4 Query builder

The query builder should support:

post_type.
post_status.
s.
tax_query.
meta_query.
Date query.
Author query.
WooCommerce product visibility.
Product categories.
Product tags.
Price range.
Stock status.
Featured products.
Sale products.
Pagination.
Sorting.
Offset.
Per-page limit.
29.5 Elementor Loop support

The filter should be able to update an Elementor loop/results container by:

Target container selector.
Query ID.
AJAX response HTML.
Pagination response.
Result count.
Empty result template.
Loading state.
Scroll-to-results option.
30. Range Slider Plan

Range sliders should work in both forms and filters.

30.1 Form usage

Use cases:

Budget amount.
Age range.
Quantity.
Rating.
Distance.
Custom score.
30.2 Filter usage

Use cases:

Product price.
Distance.
Rating.
Date range.
Numeric meta query.
30.3 Settings

Support:

Minimum value.
Maximum value.
Step.
Single value.
Min/max pair.
Prefix.
Suffix.
Display live value.
Hidden input sync.
Validation.
31. Select2 Plan

Select fields should optionally support Select2.

31.1 Select2 settings

Support:

Enable Select2.
Single select.
Multi select.
Search.
Placeholder.
AJAX source.
Static options.
Taxonomy options.
User options.
Post options.
Product options.
Minimum input length.
Maximum selected items.
32. Button System

Buttons should be configurable per step.

32.1 Button types

Support:

Next.
Previous.
Submit.
Save draft.
Add repeater row.
Remove repeater row.
Upload.
Reset filters.
Apply filters.
Load more.
32.2 Button conditions

Buttons can be:

Always visible.
Hidden until valid.
Disabled until valid.
Hidden based on condition.
Disabled based on API result.
Replaced based on step.
Custom text per step.
33. Logged-In Requirement

Forms should optionally require logged-in users.

33.1 Logged-in settings

Support:

Require logged-in user.
Allow logged-out users.
Show login message.
Show login form.
Redirect to login.
Return to current page after login.
Restrict by role.
Restrict by capability.
34. Role Assignment on Completion

Registration or onboarding forms should be able to set roles.

34.1 Role settings

Support:

Assign role on success.
Replace existing role.
Add additional role.
Conditional role assignment.
Role after onboarding.
Role before onboarding.
Role after approval.
Store onboarding status in user meta.

Example:

When onboarding form succeeds:
  Set user role to creator_pending
  Set user_meta onboarding_status = complete
35. Security Rules
35.1 Never trust frontend config alone

The server must reload the saved form/widget config and validate against that.

35.2 Prevent tampering

Check:

Unknown fields.
Removed fields.
Hidden fields.
Disabled fields.
Conditional fields.
File tokens.
User role values.
Product price values.
Post IDs.
Redirect URLs.
35.3 Permissions

Check:

User can submit this form.
User can edit target post if updating post meta.
User can update target user if mapping user meta.
User can create product if product action is enabled.
User has required role/capability.
36. Performance Plan
36.1 Frontend performance
Load assets only when widget exists.
Load Select2 only when a Select2 field exists.
Load upload manager only when upload fields exist.
Load range slider only when range field exists.
Load filter scripts only when filter widget exists.
Avoid global DOM scanning.
Initialize only inside the form wrapper.
Use event delegation.
Debounce live checks.
Debounce filter AJAX requests.
36.2 Server performance
Cache parsed form config.
Cache field validation schema.
Cache query filter config.
Avoid loading Elementor data repeatedly.
Store normalized config where possible.
Use transients for temporary live-check states.
Clean expired uploads on schedule.
37. Developer Hooks

Expose PHP hooks.

37.1 Filters

Examples:

afb_form_config
afb_field_config
afb_validation_rules
afb_sanitized_submission_data
afb_submission_actions
afb_redirect_url
afb_upload_allowed_mimes
afb_filter_query_args
37.2 Actions

Examples:

afb_before_form_render
afb_after_form_render
afb_before_validation
afb_after_validation
afb_before_submission_save
afb_after_submission_save
afb_before_action_run
afb_after_action_run
afb_upload_completed
afb_test_completed
38. Build Phases
Phase 1: Core foundation

Build:

Plugin bootstrap.
Loader.
Asset manager.
Template loader.
Database installer.
Options page.
REST route structure.
Elementor widget registration.
Phase 2: Basic form builder

Build:

Elementor form widget.
Step repeater.
Field repeater.
Basic fields.
Template rendering.
Submit button.
Basic AJAX submit.
Submission saving.
Phase 3: Validation and sanitization

Build:

Validation library.
Server-side validator.
Client-side validator.
Sanitization map.
Escaping map.
Custom validation messages.
Final submit validation.
Phase 4: Conditional logic and routing

Build:

Conditional field display.
Conditional HTML display.
Conditional buttons.
Step routing.
Step blockers.
Repeater conditions.
Server-side condition verification.
Phase 5: Upload system

Build:

Instant upload endpoint.
File validation.
Temporary upload storage.
Multi-file upload.
Upload progress.
Upload finalization.
Upload cleanup.
Phase 6: Default actions

Build:

Login.
Register.
Lost password.
Confirm password.
Redirect.
User meta mapping.
Post meta mapping.
ACF mapping.
Taxonomy mapping.
WooCommerce product mapping.
Phase 7: Styling and templates

Build:

Default inherited mode.
Custom style controls.
Field templates.
Child theme overrides.
Notice templates.
Button templates.
Repeater templates.
Phase 8: Tests and logs

Build:

Test runner.
Expected vs actual result logging.
Created record tracking.
Cleanup system.
Admin test viewer.
Audit logs.
Phase 9: Sidebar filter widget

Build:

Sidebar filter Elementor widget.
Filter sections.
Query builder.
AJAX filtering.
Pagination.
Elementor loop integration.
Active filter chips.
Reset filters.
URL sync.
Phase 10: Polish and hardening

Build:

Anti-spam.
Dynamic nonce refresh.
Rate limits.
Debug console events.
Developer hooks.
Performance cleanup.
Security review.
Documentation.
39. Final Recommended Architecture Decision

The plugin should be treated as two connected systems:

System 1: Form Builder Engine

Responsible for:

Forms.
Steps.
Fields.
Repeaters.
Conditions.
Routing.
Validation.
Uploads.
Submissions.
Actions.
Data mapping.
Testing.
System 2: Filter Builder Engine

Responsible for:

Sidebar filters.
Query building.
Elementor Loop results.
Pagination.
Filter state.
AJAX refresh.
URL query syncing.

Both systems can share:

Field renderer.
Template system.
Asset loader.
Conditional logic engine.
Validation engine.
Range slider.
Select2.
Checkbox/radio/select controls.
REST route foundation.
JS event dispatcher.

This gives you one reusable engine, but avoids mixing form submissions with query filtering logic.