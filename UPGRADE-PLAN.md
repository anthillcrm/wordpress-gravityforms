# Gravity Forms – Anthill Integration: Upgrade Plan

**Current plugin version:** 1.0.17
**Target:** Gravity Forms **3.1.1.2**
**Prepared:** 2026-09-11

---

## 0. Scope, assumptions and one open question

The plugin is a *hook-based* Gravity Forms integration (not a `GFAddOn`). It adds:

| File | Responsibility |
|---|---|
| `gravity-forms-anthill.php` | Bootstrap, `gform_loaded`, script enqueue |
| `anthill.class.php` | SOAP client for the Anthill CRM `api/v1.asmx` endpoint + UTM capture |
| `anthill-settings.php` | WP Settings page (credentials + config data browser) |
| `gravity-forms-anthill-form-settings.php` | **Form**-level settings (location / customer / contact types) |
| `gravity-forms-anthill-form.php` | **Field**-level settings, choice pre-population, prepopulate values |
| `gravity-forms-anthill-submit.php` | `gform_after_submission` → push to Anthill |
| `fields/class-gf-anthill-field-name.php` | Custom `anthill_name` field |
| `fields/class-gf-anthill-field-address.php` | **Dead code** – never `require`d |

**Confirmed:** "3.1.1.2" is the current **Gravity Forms** version. The plugin also talks to
the Anthill SOAP API pinned at `api/v1.asmx` (`anthill.class.php:3`) — if 3.1.1.2 is in fact the *Anthill*
API version, §6 becomes the main body of work instead of a side note. The outbound network in this
environment is firewalled, so the Gravity Forms changelog could not be fetched to confirm exact
removals in 3.1.x; items below are graded by how long they have been deprecated, and Phase 0 is a
one-hour verification pass against the real 3.1.1.2 build.

---

## 1. Blocking incompatibilities — the form settings UI will not render

### 1.1 `gform_form_settings` / `gform_pre_form_settings_save` (legacy since GF 2.5)

`gravity-forms-anthill-form-settings.php:5` returns **raw `<tr>`/`<th>`/`<td>` HTML strings** into
`$form_settings['Anthill']`. This is the pre-2.5 form-settings contract. GF 2.5 replaced it with
the Settings framework
(`Gravity_Forms\Gravity_Forms\Settings\Settings`) driven by
**`gform_form_settings_fields`**, which takes a *declarative array* of field definitions. The legacy
filter survived 2.5–2.9 only through a compatibility shim, and the changelog confirms it was
**removed outright in 3.0**. **Any client already on 3.0 is therefore running a broken plugin
today**: their Anthill form settings panel is simply gone.

**Action — rewrite as `gform_form_settings_fields`:**

```php
add_filter( 'gform_form_settings_fields', 'gf_anthill_form_settings_fields', 10, 2 );
function gf_anthill_form_settings_fields( $fields, $form ) {
    $fields['anthill'] = array(
        'title'  => esc_html__( 'Anthill', 'gf-anthill' ),
        'fields' => array(
            array(
                'name'    => 'anthill_location',
                'type'    => 'select',
                'label'   => esc_html__( 'Location', 'gf-anthill' ),
                'choices' => gf_anthill_location_choices(),
            ),
            array(
                'name'       => 'anthill_enquiry',
                'type'       => 'select',
                'label'      => esc_html__( 'Enquiry Type', 'gf-anthill' ),
                'choices'    => gf_anthill_type_choices( 'Enquiry' ),
                'dependency' => array(
                    'live'   => true,
                    'fields' => array( array( 'field' => 'anthill_contact_type', 'values' => array( 'Enquiry' ) ) ),
                ),
            ),
            // …customer, customer_contact, contact_type, issue, lead, sale, source, tracking
        ),
    );
    return $fields;
}
```

Knock-on changes:

- **`js/gf-anthill.js` can be deleted.** Its only job is show/hide of `.contact_type` rows and
  `#anthill_customer_contact` — the framework's `dependency` / `'live' => true` does this natively.
  Remove the enqueue at `gravity-forms-anthill.php:20-28` and the `gform_noconflict_scripts` filter
  with it.
- **Saving changes shape.** The framework posts settings as `_gform_setting_<name>` and writes them
  into the form meta under the `name` key automatically, so the whole
  `gform_form_settings_anthill_save()` body (`…form-settings.php:202-221`) plus all 10 `rgpost()`
  calls go away.
- **Meta keys change.** Today's keys are `_gf_anthill_location`, `_gf_anthill_customer`, … Settings
  framework names cannot start with `_`. Either name the settings `anthill_location` etc. **and ship a
  one-time migration** (see §5), or keep a read shim:

  ```php
  function gf_anthill_setting( $form, $key ) {
      return rgar( $form, 'anthill_' . $key, rgar( $form, '_gf_anthill_' . $key ) );
  }
  ```

  The shim is the lower-risk option for existing installs; route **every** read in
  `…-submit.php:11-24` and `…-form.php:6,33,56-60,209,216,223` through it.

### 1.2 `esc_attr()` used as a sanitiser on stored values

`…form-settings.php:8-23` wraps every `rgar()` in `esc_attr()` before comparing to option values, and
`anthill-settings.php:34-40` wraps `$_POST` in `esc_attr()` before `update_option()`. `esc_attr()` is an
*output* escaper — it HTML-encodes `&`, `<`, `"` into entities and **corrupts API keys** containing those
characters, permanently. Replace with `sanitize_text_field()` on input and `esc_attr()` only at the
point of echo.

---

## 2. High risk — field editor and field registration

### 2.1 `gform_field_advanced_settings` markup (`…-form.php:81-142`)

The hook still exists, but the 2.5+ editor renders settings inside the new sidebar panels and expects
specific wrapper classes. The current output is a bare `<li class="anthill_field">` with an inline
`onchange="SetFieldProperty(...)"`. `SetFieldProperty()` and the `gform_load_field_settings` JS event
are still part of the editor API, so the logic survives — but the markup needs the current
`<li class="anthill_field field_setting">` + `<label class="section_label">…<span class="gf_tooltip">`
structure to not look broken.

**Action:** re-emit using the current editor markup, move the inline handler to a delegated listener in
the `gform_editor_js` block, and verify the two `fieldSettings.select` / `fieldSettings.fileupload`
registrations (`…-form.php:150-151`) still target the right types. Note `fieldSettings.select` means the
Anthill mapping dropdown is **only available on Select fields** — every other mapped field type
(text, email, phone, name) silently has no UI. That looks like a long-standing bug worth fixing in the
same pass: append `.anthill_field` to all mappable types.

### 2.2 `GF_Field_Anthill_Name` (`fields/class-gf-anthill-field-name.php`)

Registers type `anthill_name` extending `GF_Field_Name`, overriding only the title. Problems:

- It inherits `GF_Field_Name::get_form_editor_button()`, so the editor shows **two identical "Name"
  buttons** in Advanced Fields.
- It defines none of the 2.5+ editor metadata: `get_form_editor_field_icon()`,
  `get_form_editor_field_description()`, `get_form_editor_inline_script_on_page_render()`.
- Nothing in the codebase consumes `anthill_name` — `…-submit.php:133-137` matches on the *mapping*
  name `name`, not the field type, so a stock `GF_Field_Name` works identically.

**Action:** delete it unless there is a live form depending on `type: anthill_name`. If it must stay,
add the icon/description/button overrides and a `gform_add_field_buttons` entry.

### 2.3 `fields/class-gf-anthill-field-address.php` — delete

1,300 lines forked from Gravity Forms ~2.4's `GF_Field_Address`. It is **never required** by
`init_anthill()`. It also declares `public $type = 'address'` (line 10) and ends with
`GF_Fields::register( new GF_Field_Address() );` (line 1300) — registering *core's* class, not the
subclass. If anyone ever wires it up it will clobber the built-in Address field with a version two
major releases stale. Remove it; address handling already works through the mapping path at
`…-submit.php:62-84`.

### 2.4 Injected hidden fields with hardcoded IDs 1000/1001 (`…-form.php:167-186`)

`gform_anthill_pre_render_cookies` appends two `GF_Field_Hidden` objects with fixed IDs on
**`gform_pre_render` only**. The sibling function is correctly registered on all four filters
(`…-form.php:188-191`); this one is not — so at validation and submission time the fields do not exist,
which is why `…-submit.php:44-50` has to read `$_POST['input_1000']` directly instead of the entry.

**Actions:**
- Register `gform_anthill_pre_render_cookies` on `gform_pre_validation`, `gform_pre_submission_filter`
  and `gform_admin_pre_render` too, then read from `$entry` rather than `$_POST`.
- Guard against ID collision — a form with 1,000+ fields is unlikely, but re-adding on every render
  means the fields are appended repeatedly if the filter runs twice. Check
  `GFFormsModel::get_field( $form, 1000 )` before appending.
- ~~Sign the hidden input~~ — **corrected**: signing it would achieve nothing. The value originates
  from the `?customerid=` query string (`anthill.class.php`, `anthill_capture_source`), so it is
  caller-supplied *by design*, presumably to support Anthill's outbound links. An attacker would
  simply use the URL rather than the hidden input. The exposure is real — a submission can edit any
  customer whose id is known — but closing it means changing the contract with Anthill's link
  generation, which is a product decision, not a refactor. See §13.

---

## 3. PHP 8.x correctness (GF 3.x will require PHP 8)

| Issue | Location | Fix |
|---|---|---|
| `property_exists()` on `false` → **TypeError** (`GetCustomerType()` returns `false` via `GetById()`) | `anthill.class.php:75, 99, 140` | `is_object( $type ) && property_exists(...)` |
| Undefined dynamic property reads on `GF_Field` → warning on every field, every submission | `…-submit.php:53, 123, 223`; `…-form.php:251` | `rgobj( $field, 'anthillField' )` |
| Dynamic property *writes* (`$field->anthillField`) deprecated in PHP 8.2 | field meta set by `SetFieldProperty` | GF adds `#[AllowDynamicProperties]` to `GF_Field`; confirm still present in 3.1.1.2 |
| `foreach` over possibly-`false` `$fielddetails->choice` | `…-form.php:199-246` | null-guard each `case` |
| `$obj == new stdClass()` object comparison | `anthill.class.php:471` | `empty( (array) $obj )` |
| `$field->inputs` truthiness on `null` | `…-submit.php:165` | `! empty( $field->inputs )` |

---

## 4. Security and hygiene (fix in the same pass)

1. **Reflected XSS** — `anthill_utm_source()` (`anthill.class.php:562-568`) echoes `$_COOKIE` unescaped
   via a shortcode, and `anthill_capture_source()` (`anthill.class.php:548-558`) writes unsanitised
   `$_GET` straight into that cookie. Sanitise on write, `esc_html()` on read.
2. **No CSRF protection** on the settings form — `anthill_settings()` processes `$_POST` with no
   `check_admin_referer()` and no `current_user_can()` re-check (`anthill-settings.php:31`). Add
   `wp_nonce_field()` + `check_admin_referer()`.
3. **API key in plaintext options**, rendered into a `type="text"` input (`anthill-settings.php:126`).
   Use `type="password"`, and mask on redisplay.
4. **`setcookie()` legacy signature** (`anthill.class.php:552`) — no `SameSite`/`Secure`. Modern
   browsers increasingly drop these. Switch to the PHP 7.3+ array form.
5. **No SOAP timeout** (`anthill.class.php:11`) — a slow Anthill endpoint hangs the WP admin
   indefinitely. Pass `connection_timeout` + a `stream_context` with a socket timeout, and
   `'exceptions' => true`.
6. **8 blocking SOAP calls per admin page load** (`anthill-settings.php:136-145`) and ~8 more per form
   settings render (`…form-settings.php:28-36`). Cache each `Get*Types()` in a transient (5–15 min)
   with a "Refresh from Anthill" button.
7. **jQuery UI CSS from the Google CDN** (`anthill-settings.php:9`) — external dependency, and the
   version is derived from WP's `jquery-ui-core` handle which may not exist on that CDN. Bundle it or
   use core WP admin tabs.

---

## 5. File attachments (`…-submit.php:220-225`, `anthill.class.php:400-403`)

`$entry[$field->id]` for a File Upload field is a **URL**, not a path — so `file_get_contents()` does an
HTTP round trip back to the site, which fails for any protected/offloaded upload directory and doubles
the transfer. Multi-file upload fields store a **JSON array**, which this code would base64 wholesale.

**Action:** decode multi-file JSON, map each URL through `GFFormsModel::get_physical_file_path()`, skip
empty values, and cap size before base64-encoding into the SOAP envelope.

---

## 6. Anthill SOAP endpoint

`define('ANTHILL_WSDL','api/v1.asmx?wsdl')` is hardcoded. If the Anthill API is also moving, make this
a filterable constant and version the client:

```php
define( 'ANTHILL_WSDL', apply_filters( 'anthill_wsdl_path', 'api/v1.asmx?wsdl' ) );
```

Confirm with Anthill support (`support@anthill.co.uk`, per the bundled instructions doc) whether `v1`
remains current before shipping.

---

## 7. Strategic option: convert to a `GFFeedAddOn`

Everything above patches a hook-based plugin into 3.x shape. The alternative is a rewrite onto
**`GFFeedAddOn`**, which is the supported extension contract and would give, for free: the settings
framework (§1.1), per-form *feeds* with conditional logic, built-in `GFAddOn` logging (replacing the
four ad-hoc `GFCommon::log_debug()` calls), async feed processing (so a slow SOAP call no longer blocks
the submission response), and automatic minimum-GF-version enforcement via `$_min_gravityforms_version`.

Rough sizing: the patch path in §1–§5 is ~3–5 days; the feed add-on rewrite is ~8–12 days but removes
most of the recurring upgrade tax. **Recommendation: patch now (Phases 0–4), schedule the add-on
rewrite separately** — §1.1 is the bulk of the patch work and translates almost directly into the
add-on's `feed_settings_fields()`.

---

## 8. Phased execution

| Phase | Work | Output |
|---|---|---|
| **0 — Verify** (0.5d) | Stand up WP + GF 3.1.1.2 + this plugin. Enable `WP_DEBUG`, GF logging. Record exactly what fatals/renders blank. Confirm which of §1/§2 are removals vs. deprecations. | Confirmed defect list |
| **1 — Guardrails** ✅ *done, branch `phase-2-form-settings-framework`* | Bump header to 2.0.0; add `Requires at least`, `Requires PHP: 8.1`; runtime `version_compare( GFForms::$version, '3.1.1.2', '<' )` guard with an admin notice instead of a fatal; add `GF_ANTHILL_VERSION` constant and a text domain. | Safe to install |
| **2 — Settings migration** ✅ *done, branch `phase-2-form-settings-framework`* | §1.1 + §1.2. Rewrite form settings onto `gform_form_settings_fields`, delete `js/gf-anthill.js`, add the meta-key read shim + one-time migration. | Form settings render on 3.1.1.2 |
| **3 — Editor + fields** ✅ *done* | §2.1–2.4. Update field-settings markup, widen `fieldSettings` beyond `select`, delete the dead address class, fix the hidden-field registration. | Field mapping works |
| **4 — Runtime hardening** (1–1.5d) | §3, §4, §5. PHP 8 guards, escaping, nonce, SOAP timeouts, transient caching, file-upload paths. | Clean debug log |
| **5 — Regression** (0.5d) | Test matrix below. | Sign-off |

---

## 9. Regression checklist

- [ ] Settings page saves credentials; Ping succeeds; all 8 config tabs populate.
- [ ] Form settings → Anthill tab: every dropdown populates, dependency show/hide works without
      `gf-anthill.js`, values persist across save/reload.
- [ ] **Existing** forms saved under 1.0.17 still submit correctly (meta-key shim).
- [ ] Field editor: Anthill Field dropdown appears on Select **and** text/email/phone/name; File Type
      dropdown appears on File Upload; selections persist.
- [ ] Choice pre-population from Anthill on Select and Location fields.
- [ ] Prepopulate via `?customerid=` / `?contactid=` fills customer and contact fields.
- [ ] Submission creates Customer + Customer Contact + Enquiry/Issue/Lead/Sale; re-submission with a
      known `customerid` **edits** rather than duplicates.
- [ ] Single- and multi-file uploads attach to the contact.
- [ ] UTM cookies captured and mapped to the configured custom field names.
- [ ] `WP_DEBUG` log clean through all of the above.
- [ ] Anthill unreachable → form still submits, error logged, no white screen.

---

## 10. Phase 2 — as built

Branch: `phase-2-form-settings-framework`.

- `gform_form_settings` / `gform_pre_form_settings_save` replaced by **`gform_form_settings_fields`**
  returning two sections, `anthill` and `anthill_tracking`. All raw table markup and all 10 `rgpost()`
  calls are gone.
- `js/gf-anthill.js` **deleted**, along with its enqueue and the `gform_noconflict_scripts` filter.
  Show/hide is now the framework's `'dependency' => array( 'live' => true, … )`. The
  Customer Contact field depends on the set of real customer-type ids rather than "non-zero", since
  dependencies match on an explicit value list.
- Meta keys moved `_gf_anthill_<key>` → `anthill_<key>`, with **two** compatibility mechanisms:
  1. `gf_anthill_form_setting( $form, $key, $default )` — every read in the plugin now goes through it.
     It uses `array_key_exists`, **not `rgar()`**: `rgar()` returns its default whenever the stored
     value is empty, so an explicit "None" (`0`) on the new key would silently fall back to the stale
     legacy value.
  2. `gf_anthill_migrate_form_settings()` on `admin_init`, guarded by the
     `gf_anthill_settings_migrated` option — copies legacy keys onto new keys across all forms, once.
     Legacy keys are left in place so a downgrade to 1.x still finds its settings.
  Each settings field also seeds `default_value` from the legacy key, so the UI is correct even before
  the migration runs.
- `gf_anthill_lookup()` memoises each `Anthill::Get*Types()` call per request **and catches**. Previously
  `Anthill::GetLocations()` had no internal try/catch, so an unreachable Anthill installation took the
  form settings page down; it now degrades to a "None"-only list and logs.
- `init_anthill()` loads the settings file first, since it defines the shared shim.

### Still to verify against a live GF 3.1.1.2

- [ ] `'dependency' => array( 'live' => true, 'fields' => …)` is still the current shape, and
      `default_value` is still honoured when a setting has no stored value.
- [ ] The Settings framework writes settings into form meta keyed by `name` with no prefix of its own.
- [ ] `GFAPI::get_forms( null, false )` still returns active **and** inactive forms.

### Not in this phase

Version bump and the minimum-GF-version guard are Phase 1; the legacy `gform_form_settings` path has
been removed outright, so this branch requires GF 2.5+ and should not ship before that guard lands.

---

## 11. Phase 1 — as built

Pulled forward and committed on the same branch, because Phase 2 removed the legacy
`gform_form_settings` path outright and the plugin therefore must not load against an older
Gravity Forms.

- Version **2.0.0**, `GF_ANTHILL_VERSION` / `GF_ANTHILL_MIN_GF_VERSION` / `GF_ANTHILL_MIN_PHP_VERSION`
  / `GF_ANTHILL_FILE` / `GF_ANTHILL_PATH` constants, `defined( 'ABSPATH' ) || exit`, and
  `Text Domain: gravity-forms-anthill` loaded on `init`. The settings stylesheet is versioned from
  `GF_ANTHILL_VERSION` rather than a hardcoded `1.0.0`, so it cache-busts on release.
- Requirements are split in two, which is the substantive design decision here:
  - **`gf_anthill_environment_failures()`** — PHP version and the SOAP extension. Checked at
    activation; failing *blocks* activation, because neither can be fixed by the site owner from
    inside WordPress.
  - **`gf_anthill_requirement_failures()`** — the above plus Gravity Forms presence and version.
    Checked at load; failing means `init_anthill()` registers **nothing** and an `admin_notices`
    error explains why. Gravity Forms is deliberately *not* an activation blocker: installing this
    add-on before Gravity Forms is a reasonable order to work in, and a notice is recoverable where a
    refused activation is just confusing.
- An **unreadable** Gravity Forms version is treated as acceptable rather than blocking. If a future
  release moves `GFForms::$version`, the failure mode is "runs anyway" rather than "every site running
  this add-on goes dark".
- `init_anthill()` keeps its name so any existing `remove_action( 'gform_loaded', 'init_anthill' )`
  still works, and its `require_once` calls now use `GF_ANTHILL_PATH` instead of relying on the
  include-path fallback.
- Activation failure now calls `deactivate_plugins()` + `wp_die()` with a readable list. The previous
  `echo` + `trigger_error( …, E_USER_ERROR )` produced an "unexpected output" warning followed by a
  bare fatal.

### Verified so far

Against PHP 8.5 with SOAP, and stubs for WordPress and the small Gravity Forms surface touched at
load time:

- Requirements met → `init_anthill()` loads all six files, registers all 13 Gravity Forms hooks, and
  registers `GF_Field_Anthill_Name`. The legacy `gform_form_settings`, `gform_pre_form_settings_save`
  and `gform_noconflict_scripts` hooks are confirmed **absent**, and `gform_form_settings_fields`
  present.
- Requirements unmet → `init_anthill()` loads nothing, across six scenarios: Gravity Forms current,
  newer, absent, version-unreadable, one major behind (2.9.14) and one **patch** behind (3.1.1.1).
  The patch case matters: Gravity Forms uses four-segment versions, which a naive string comparison
  gets wrong.

Still unverified without a live Gravity Forms build: everything in the Phase 2 list above, plus the
`gform_field_advanced_settings` markup contract, which Phase 3 addresses.

### Requirement values to reconcile in Phase 0

`Requires PHP: 8.1` and `Requires at least: 6.5` are floors chosen for this plugin, **not** read off
Gravity Forms 3.1.1.2's own requirements, which could not be fetched here. Both are enforced by
WordPress at activation, so confirm them against the real release before shipping — setting either
too high locks out legitimate sites.

### Known, deliberately out of scope

`init_anthill`, `anthill_settings`, `anthill_sources` and friends are unprefixed global function
names with real collision potential. Renaming them is a breaking change for anything hooking them,
so it belongs with the Phase 7 add-on rewrite rather than a guardrails pass.

---

## 12. Supported Gravity Forms range

The minimum was initially set to the target, 3.1.1.2. That was wrong, and in the most damaging
direction: it would have refused to load on exactly the 3.0 sites whose 1.x install is already
broken, leaving them no route forward except a Gravity Forms upgrade they may not be ready for.

The floor is now the oldest release supporting the APIs 2.0.0 actually uses —
`gform_form_settings_fields` (2.5), plus `GFAPI::get_forms()`, `GFAPI::update_form()` and
`GFCommon::log_debug()`, all far older.

| Gravity Forms | 2.0.0 loads | Settings panel |
|---|---|---|
| 2.4 and earlier | no, notice shown | n/a — no Settings framework to build on |
| 2.5 – 2.9 | yes | works; 1.x also still worked here |
| **3.0** | **yes** | **works — 1.x is broken here** |
| 3.1.1.2 (target) | yes | works |
| 3.2+, or unreadable version | yes | works |

Verified across all ten of those cases: the guard's verdict, whether the plugin loads at all, and
which settings hook it ends up registering.

`Requires PHP` and `Requires at least` dropped to 7.4 and 6.0 on the same reasoning. Both are
enforced by WordPress at activation, 2.0.0 uses no syntax newer than PHP 7.0, and Gravity Forms
applies its own stricter requirements on top — so a floor above what the code needs can only lock
people out.

### Scope of the 3.0 breakage — resolved

`gform_field_advanced_settings` was checked against current Gravity Forms documentation: **still
available, not deprecated**. So 3.0 did not take the field-mapping UI with it, and the damage on a
3.0 site is confined to the **form settings panel**:

| On a 3.0 site, running 1.0.17 | State |
|---|---|
| Form settings → Anthill tab | **gone** (`gform_form_settings` removed) |
| Field mapping UI | works |
| Submissions pushed to Anthill | works, using whatever settings were last saved |

That is a narrower failure than assumed, but not a benign one. The mapping UI still writes field
mappings while the form-level settings behind them — customer type, contact type, location — can no
longer be seen or changed. New forms cannot be configured at all, and existing ones are frozen on
their last-saved configuration. Phase 3 is therefore cleanup, as originally scoped; Phase 2 remains
the outage fix.
---

## 13. Phase 3 — as built

### Field editor (§2.1)

- Both settings now carry the **`field_setting`** class. Gravity Forms hides every `.field_setting`
  before showing the ones listed in `fieldSettings` for the selected type; without it these controls
  were never hidden, so they lingered on field types that do not support them.
- `fieldSettings.select` → **18 field types** (`gf_anthill_mappable_field_types()`, filterable via
  `gf_anthill_mappable_field_types`). Text, email, phone, name and address fields could never be
  mapped from the UI before, despite the submission handler reading a mapping from them.
- **Stale-mapping bug fixed.** `gform_load_field_settings` only ever *set* the controls when the
  field had a value, so selecting a mapped field and then an unmapped one left the previous field's
  mapping on screen, ready to be saved onto the wrong field. Both controls now reset to `''`.
- Anthill-supplied labels are escaped (`esc_html`/`esc_attr`); they were printed raw.
- `.bind()` → `.on()`, tooltips registered via `gform_tooltips`, labels given `for` attributes.

### Fields (§2.2, §2.3)

- `fields/class-gf-anthill-field-address.php` **deleted** — 1,300 lines never loaded, ending in
  `GF_Fields::register( new GF_Field_Address() )`, i.e. re-registering *core's* class.
- `GF_Field_Anthill_Name` **kept registered but withdrawn from the editor**, rather than deleted as
  originally proposed. Deleting a registered type that live forms may still use would leave Gravity
  Forms unable to reconstruct those fields. `get_form_editor_button()` now returns an empty array,
  so the duplicate "Name" button is gone and no new `anthill_name` fields can be created, while
  existing ones keep working. **Action for the client:** audit forms for `anthill_name` fields; once
  none remain, the class and its registration can go.

### Hidden id fields (§2.4)

- Injection registered on `gform_pre_validation` and `gform_pre_submission_filter` as well as
  `gform_pre_render`, so the fields exist when the entry is built. Deliberately **not** on
  `gform_admin_pre_render`, which would inject them into the form editor.
- Guarded against re-appending; the filters run more than once per request.
- Ids moved to `GF_ANTHILL_CUSTOMER_ID_FIELD` / `GF_ANTHILL_CONTACT_ID_FIELD` constants.
- The submission handler reads the ids from `$entry` (with a `$_POST` fallback), so they are also
  visible in the Gravity Forms entry detail for audit.
- Three unguarded dynamic property reads in the submission handler were guarded. This is Phase 4
  work pulled forward, because putting two more fields into the submission form would otherwise have
  widened an existing undefined-property warning.

### Still to verify against a live build

- [x] `gform_field_advanced_settings` still exists and is not deprecated — confirmed against current
      Gravity Forms documentation.
- [ ] It still fires at `$position === -1` specifically (the docs' own examples use numeric positions
      such as 25 or 50, so this is the part worth a second look).
- [ ] The `<li class="... field_setting">` + `section_label` markup is still what the sidebar expects.
- [ ] `get_form_editor_button()` returning `array()` suppresses the button rather than rendering an
      empty one.
- [ ] `SetFieldProperty()` and the `gform_load_field_settings` event are unchanged.