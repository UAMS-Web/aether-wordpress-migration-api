<?php

/**
 * `OptionsPayload::classify()` — the one place that decides what is withheld.
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\MigrationApi\Content\OptionsPayload;

/*
 * The endpoint and `scripts/audit-option-denylist.php` both call this, which is
 * what makes the audit incapable of drifting from what ships (#38). Before that
 * the script kept its own transcription of the rules, and the transcription went
 * stale the moment #33 taught the endpoint to look inside serialized values —
 * so for a release the one tool for reviewing the denylist against production
 * reported on a denylist that was not shipped.
 *
 * These cases pin the verdicts rather than only the served/withheld split,
 * because the audit reports *which* rule fired and a reviewer acts on that: a
 * name match is a decision somebody made, a nested-key or shape match is a
 * guess about content nobody enumerated.
 */

it('serves an ordinary option', function (): void {
    expect(OptionsPayload::classify('blogname', 'UAMS Health'))->toBe(OptionsPayload::VERDICT_SERVED);
});

it('exempts an `_options_*` row whatever its name would otherwise match', function (): void {
    // D-007. The row holds an ACF field key, never a value — and `_options_fad_google_key`
    // ends in `key`, so the exemption has to outrank the pattern rather than
    // merely coexist with it.
    expect(OptionsPayload::classify('_options_fad_google_key', 'field_fad_google_key'))
        ->toBe(OptionsPayload::VERDICT_EXEMPT);
});

it('reports a name match as a name match, not as a shape match', function (): void {
    // The value here is innocuous; only the name is denied. Collapsing this into
    // the shape bucket would tell a reviewer a guess had fired where in fact a
    // reviewed decision had.
    expect(OptionsPayload::classify('vendor_api_key', 'x'))->toBe(OptionsPayload::VERDICT_NAME);
});

it('reports a top-level credential-shaped value as a shape match', function (): void {
    expect(OptionsPayload::classify('vendor_setting', str_repeat('a1b2c3d4', 8)))
        ->toBe(OptionsPayload::VERDICT_SHAPE);
});

it('reports a secret nested in a serialized value as a shape match', function (): void {
    // Named neither by the option name nor by any nested key — found only by
    // walking the decoded value.
    $stored = serialize(['integration' => ['handle' => 'SG.'.str_repeat('a1b2c3d4', 8)]]);

    expect(OptionsPayload::classify('vendor_settings', $stored))->toBe(OptionsPayload::VERDICT_SHAPE);
});

it('reports a denied key inside a value as a nested-key match', function (): void {
    // The value is 20 characters and matches none of the shape rules; only the
    // key `api_key` denies it. That is the distinction the audit prints.
    $stored = serialize(['integration' => ['api_key' => 'AKIAIOSFODNN7EXAMPLE']]);

    expect(OptionsPayload::classify('vendor_settings', $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY);
});

it('serves a settings array whose nested credential slot is empty or boolean', function (): void {
    // The two conditions that keep the nested-key rule from dropping whole
    // configurations: `wp_user_roles` maps a capability named
    // `…_manage_license` to boolean `true`, and a blank `license` field is not
    // a license.
    $boolean = serialize(['capabilities' => ['plugin_manage_license' => true]]);
    $empty = serialize(['scheduling_license' => '', 'rows_per_page' => 25]);

    expect(OptionsPayload::classify('vendor_roles', $boolean))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('vendor_settings', $empty))->toBe(OptionsPayload::VERDICT_SERVED);
});

it('withholds WP All Import settings carrying a cron key', function (): void {
    // #320. `cron_job_key` is the secret a scheduled-import URL carries, stored
    // inside `PMXI_Plugin_Options`. Ten characters is too short for the shape
    // rule and the option name matches nothing, so only a nested key can see it.
    $stored = serialize(['cron_job_key' => 'Xq7-Lm2-Rt', 'large_import' => 'No']);

    expect(OptionsPayload::classify('PMXI_Plugin_Options', $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY);
});

it('serves WP All Import settings whose cron key was never generated', function (): void {
    $stored = serialize(['cron_job_key' => '', 'large_import' => 'No']);

    expect(OptionsPayload::classify('PMXI_Plugin_Options', $stored))->toBe(OptionsPayload::VERDICT_SERVED);
});

it('serves a queue whose entries carry a transient name under `transient_key`', function (): void {
    // Why #320's pattern is `cron_job_key` and not `%\_key`: `image_sizer_queue`
    // holds a transient name per entry under `transient_key`, and a general
    // suffix pattern would withhold the whole queue for it.
    $stored = serialize([['transient_key' => 'image_sizer_'.md5('attachment-1')]]);

    expect(OptionsPayload::classify('image_sizer_queue', $stored))->toBe(OptionsPayload::VERDICT_SERVED);
});

/**
 * A Formidable license-check cache, `frm_<kind>_l<md5>`, as stored: a
 * serialized array whose `value` element is the vendor's response as a JSON
 * string.
 *
 * @param  array<string, mixed>  $response
 */
function formidableLicenseCache(array $response): string
{
    return serialize([
        'timeout' => 1792900000,
        'value' => json_encode($response, JSON_THROW_ON_ERROR),
        'version' => '6.16',
    ]);
}

it('withholds a license inside a JSON string nested in a serialized option', function (): void {
    // #457. The license sits in the JSON string under `error.license`, so a
    // walk that decodes only the top-level value stops at `value` and serves
    // it, while `frmpro-credentials` holds the same license and is withheld
    // by name.
    $stored = formidableLicenseCache(['error' => [
        'message' => 'Your license has expired.',
        'license' => 'q27fd-41b8c-amber-h3k59',
    ]]);

    expect(OptionsPayload::classify('frm_form_templates_l'.md5('probe'), $stored))
        ->toBe(OptionsPayload::VERDICT_NESTED_KEY);
});

it('serves an option whose nested JSON string carries only benign keys', function (): void {
    $stored = formidableLicenseCache(['templates' => [
        '1' => ['name' => 'Contact form', 'categories' => ['Business']],
    ]]);

    expect(OptionsPayload::classify('frm_form_templates_l'.md5('probe'), $stored))
        ->toBe(OptionsPayload::VERDICT_SERVED);
});

it('applies the depth bound through a nested JSON string', function (): void {
    // The decode is one more level of the same walk, so the bound that stops a
    // self-referential serialized array still fails closed when the nesting
    // continues inside a JSON string.
    $deep = ['leaf' => 'x'];

    for ($level = 0; $level < 20; $level++) {
        $deep = ['a' => $deep];
    }

    expect(OptionsPayload::classify('vendor_cache', formidableLicenseCache($deep)))
        ->toBe(OptionsPayload::VERDICT_SHAPE);
});

/**
 * `gforms_rcwdupload_verifypurchase` as the Gravity Forms File Upload add-on
 * stores it, with the purchase code given.
 */
function rcwdUploadVerifyPurchase(string $purchaseCode): string
{
    return serialize([
        'username' => 'shopper001',
        'purchase_code' => $purchaseCode,
        'api_key' => '',
        'item_id' => '8041636',
        'purchased' => true,
        'site_url' => 'https://communications.uams.edu',
        'home_url' => 'https://communications.uams.edu',
    ]);
}

it('withholds the File Upload add-on settings carrying a purchase code', function (): void {
    // #455. A marketplace purchase code is a UUID: its hyphens keep it out of
    // the shape rule and the option name matches nothing, so only a nested key
    // can see it.
    $stored = rcwdUploadVerifyPurchase('3f2a9c1e-7b4d-4e8a-9c21-5d6e7f8a9b0c');

    expect(OptionsPayload::classify('gforms_rcwdupload_verifypurchase', $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY);
});

it('serves the File Upload add-on settings whose purchase code is empty', function (): void {
    $stored = rcwdUploadVerifyPurchase('');

    expect(OptionsPayload::classify('gforms_rcwdupload_verifypurchase', $stored))->toBe(OptionsPayload::VERDICT_SERVED);
});

it('serves a settings array carrying only `purchased`', function (): void {
    // Why #455's pattern is `purchase_code` and not `%purchase%`: the boolean
    // beside the code would match the broader pattern, and on the next plugin
    // `purchased` may be a string.
    $stored = serialize(['purchased' => 'yes', 'item_id' => '8041636']);

    expect(OptionsPayload::classify('vendor_settings', $stored))->toBe(OptionsPayload::VERDICT_SERVED);
});

/*
 * #104. Four rows the shipped denylist served. Each synthetic value is short and
 * punctuated, so no shape rule sees it; each test first proves that, so a pass
 * means the new rule withheld the row and not a rule that was already there.
 */

it('withholds FlyingPress license settings by each of the three license keys', function (string $key): void {
    $stored = serialize([$key => 'fp-7Q2-x9', 'sc_license_status' => 'valid']);
    $unnamed = serialize(['vendor_field' => 'fp-7Q2-x9', 'sc_license_status' => 'valid']);

    expect(OptionsPayload::classify('flyingpress_license_options', $unnamed))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('flyingpress_license_options', $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY);
})->with(['sc_license_key', 'sc_license_id', 'sc_activation_id']);

it('withholds the 301 Redirects Pro and LearnDash license settings while a key is filled in', function (string $option, string $key, array $rest): void {
    // #117. The synthetic value is short and punctuated so no shape rule sees
    // it, and the unnamed control proves a pass comes from the new rule.
    $stored = serialize([$key => 'lk-7Q2-x9'] + $rest);
    $unnamed = serialize(['vendor_field' => 'lk-7Q2-x9'] + $rest);
    $empty = serialize([$key => ''] + $rest);

    expect(OptionsPayload::classify($option, $unnamed))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify($option, $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY)
        ->and(OptionsPayload::classify($option, $empty))->toBe(OptionsPayload::VERDICT_SERVED);
})->with([
    '301 Redirects Pro license_key' => ['wf_licensing_wf301', 'license_key', ['error' => '', 'valid_until' => '', 'name' => '', 'access_key' => '']],
    '301 Redirects Pro access_key' => ['wf_licensing_wf301', 'access_key', ['license_key' => '', 'error' => '', 'valid_until' => '', 'name' => '']],
    'LearnDash license_key' => ['learndash_setup_wizard', 'license_key', ['scene' => 'step-2', 'courses_amount' => '1-5']],
]);

it('withholds a Tweets Widget token stored under its widget instance suffix', function (): void {
    // The name ends in the instance id, so the end-anchored `%token` misses it.
    expect(OptionsPayload::classify('vendor_widget-2', 'tw-41.x'))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('cfTwitterToken_tweets-widget-2', 'tw-41.x'))->toBe(OptionsPayload::VERDICT_NAME)
        ->and(OptionsPayload::classify('cfTwitterToken_tweets-widget-17', 'tw-41.x'))->toBe(OptionsPayload::VERDICT_NAME);
});

it('withholds AutomatorWP settings carrying an ActiveCampaign webhook', function (): void {
    $webhook = 'https://example.invalid/hook/a-1';
    $unnamed = serialize(['automatorwp_vendor_url' => $webhook]);
    $stored = serialize(['automatorwp_activecampaign_webhook' => $webhook, 'automatorwp_activecampaign_webhook_enabled' => 'on']);

    expect(OptionsPayload::classify('automatorwp_settings', $unnamed))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('automatorwp_settings', $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY);
});

it('withholds Edit Flow calendar options carrying the feed secret', function (): void {
    $unnamed = serialize(['ics_feed_name' => 'ef-9x-2Q', 'number_of_weeks' => 6]);
    $stored = serialize(['ics_secret_key' => 'ef-9x-2Q', 'number_of_weeks' => 6]);

    expect(OptionsPayload::classify('edit_flow_calendar_options', $unnamed))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('edit_flow_calendar_options', $stored))->toBe(OptionsPayload::VERDICT_NESTED_KEY);
});

it('serves the #104 settings where the named slot is empty or boolean, and harmless neighbors', function (): void {
    // The new nested rules are exact names, so a similarly named flag or label
    // beside them, or the slot itself left blank or boolean, withholds nothing.
    $flyingpress = serialize(['sc_license_key' => '', 'sc_license_status' => 'inactive', 'sc_license_expires' => 'never']);
    $automatorwp = serialize(['automatorwp_activecampaign_webhook' => false, 'automatorwp_activecampaign_webhook_enabled' => 'on']);
    $editFlow = serialize(['ics_secret_key' => '', 'ics_subscription' => 'on']);

    expect(OptionsPayload::classify('flyingpress_license_options', $flyingpress))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('automatorwp_settings', $automatorwp))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('edit_flow_calendar_options', $editFlow))->toBe(OptionsPayload::VERDICT_SERVED)
        ->and(OptionsPayload::classify('cfTwitterTokenCount', '3'))->toBe(OptionsPayload::VERDICT_SERVED);
});

it('agrees with what the endpoint actually serves', function (): void {
    // The claim the sharing rests on: `classify()` is not a parallel opinion
    // about the filter, it *is* the filter. Seed one row per verdict, dispatch
    // the real request, and check the payload matches the verdict for each.
    $fixtures = [
        'classifier_probe_plain' => 'kept',
        'classifier_probe_named_key' => 'withheld',
        'classifier_probe_nested' => ['integration' => ['api_key' => 'AKIAIOSFODNN7EXAMPLE']],
        '_options_classifier_probe' => 'field_classifier_probe',
        'classifier_probe_nested_json' => ['value' => '{"error":{"license":"q27fd-41b8c-amber-h3k59"}}'],
    ];

    foreach ($fixtures as $name => $value) {
        update_option($name, $value);
    }

    // Dispatched here rather than through `optionsPayload()`: that helper lives
    // in another test file, so relying on it would make this test depend on
    // which file Pest loaded first and on that file's `beforeEach` for auth.
    actAsSuperAdmin();

    $response = rest_do_request(new WP_REST_Request('GET', '/uamswp-migration/v1/options'));
    $options = payload($response, 'options');

    assert(\is_array($options));

    foreach ($fixtures as $name => $value) {
        // `maybe_serialize()` is what `update_option()` ran on the way in, so
        // this is the row as stored — which is what `classify()` takes, and
        // what `OptionsPayload::rows()` reads straight off the table.
        $stored = maybe_serialize($value);

        // Every fixture is a string or an array, so the stored row is a string;
        // the 6.8 stubs type `maybe_serialize()` as `mixed` (D-106).
        assert(\is_string($stored));

        $verdict = OptionsPayload::classify($name, $stored);
        $served = \in_array($verdict, [OptionsPayload::VERDICT_SERVED, OptionsPayload::VERDICT_EXEMPT], true);

        expect(\array_key_exists($name, $options))->toBe(
            $served,
            "{$name} classified as {$verdict} but the payload disagreed"
        );
    }
});
