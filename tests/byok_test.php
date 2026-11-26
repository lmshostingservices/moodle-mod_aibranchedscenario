<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aibranchedscenario;

use mod_aibranchedscenario\local\ai\byok;
use mod_aibranchedscenario\local\ai\generation_exception;
use mod_aibranchedscenario\local\ai\lmslabs_provider;
use mod_aibranchedscenario\local\ai\scenario_engine;
use mod_aibranchedscenario\local\schema;
use mod_aibranchedscenario\local\validator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/aibranchedscenario/tests/fixtures/byok_prose.php');

/**
 * Tests for writing scenarios, pictures and narration on the site's own AI.
 *
 * scenario_engine was compared with the LMS Labs service code on 300 randomised scenario runs and 80 wizard
 * calls (identical prompts, schemas, token budgets, repair calls, results and failure stages); these tests
 * cover the wiring into the plugin.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aibranchedscenario\local\ai\byok
 * @covers     \mod_aibranchedscenario\local\ai\scenario_engine
 * @covers     \mod_aibranchedscenario\local\ai\lmslabs_provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scenario_engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lmslabs_provider::class)]
final class byok_test extends \advanced_testcase {
    /** @var array Requests the mocked provider received. */
    protected $requests = [];

    /** @var array Answers the mocked provider gives, in order. Callables get the request body. */
    protected $answers = [];

    /** Wizard values for a scenario request. */
    const WIZARD = [
        'sourcecontent' => 'Every personal danger lock on the isolation board must be matched to a named worker before a '
            . 'shift handover is signed. Locks without an owner are escalated to the shift supervisor.',
        'title' => 'Handover at the primary crusher',
        'audience' => 'Shift supervisors',
        'participantrole' => 'Shift supervisor',
        'setting' => 'Mine processing plant',
        'language' => 'en-AU',
        'tone' => 'serious',
        'complexity' => 'intermediate',
    ];

    /**
     * Licence the site for Bring your own AI on a test key, with text on an Anthropic key.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config 1.1.0 or later is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        $this->requests = [];
        $this->answers = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body, $vendor, $raw = false) {
            $this->requests[] = ['url' => $url, 'body' => $body];
            $answer = array_shift($this->answers);
            if ($answer === null) {
                throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) ['vendor' => $vendor,
                    'detail' => 'Overloaded']);
            }
            return is_callable($answer) ? $answer($body) : $answer;
        };
    }

    /**
     * Clean up the provider mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        parent::tearDown();
    }

    /**
     * An Anthropic reply that calls the structured-output tool.
     *
     * @param callable $make fn(array $schema, array $body): array, the tool input.
     * @return callable
     */
    protected function tool(callable $make): callable {
        return function (array $body) use ($make) {
            $schema = json_decode(json_encode($body['tools'][0]['input_schema']), true);
            // Real calls get the raw reply back, so the answer is JSON text, as the provider sends it.
            return json_encode(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use',
                'name' => $body['tools'][0]['name'], 'input' => $make($schema, $body)]]]);
        };
    }

    public function test_routes_follow_the_licence_and_the_source_of_each_kind(): void {
        $this->assertTrue(byok::handles('/api/aibranchedscenario/generate'));
        $this->assertTrue(byok::handles('/api/aibranchedscenario/suggest'));
        $this->assertFalse(byok::handles('/api/aibranchedscenario/image'));
        $this->assertFalse(byok::handles('/api/aibranchedscenario/status'));
        $this->assertFalse(lmslabs_provider::bundle_mode());
        $this->assertTrue((new lmslabs_provider())->is_configured());
        $this->assertSame(20000, lmslabs_provider::standard_budget('populate'));
        set_config('src_text', 'credits', 'local_aiconfig');
        $this->assertFalse(byok::handles('/api/aibranchedscenario/generate'));
        $this->assertSame(0, lmslabs_provider::standard_budget('populate'));
    }

    public function test_scenario_is_written_on_own_ai_and_passes_the_plugin_validator(): void {
        $this->answers[] = $this->tool(function (array $schema) {
            return mod_aibranchedscenario_byok_prose($schema);
        });
        $provider = new lmslabs_provider();
        $result = $provider->generate_scenario(self::WIZARD);
        $definition = validator::validate($result['scenario']);
        // Five stages, two of them with a parallel decision on the other branch.
        $this->assertSame(schema::DECISIONS + 2, $definition['stats']['decisioncount']);
        $this->assertSame('byok', $result['meta']['provider']);
        $this->assertSame(0, $result['meta']['creditsused']);
        $this->assertGreaterThanOrEqual(2, $result['meta']['branchingdecisions']);

        $this->assertCount(1, $this->requests);
        $body = $this->requests[0]['body'];
        $this->assertSame('https://api.anthropic.com/v1/messages', $this->requests[0]['url']);
        $this->assertSame(scenario_engine::SYSTEM, $body['system']);
        $this->assertSame(16000, $body['max_tokens']);
        $this->assertSame(0.2, $body['temperature']);
        $this->assertSame('branched_scenario', $body['tools'][0]['name']);
        $this->assertSame(['type' => 'tool', 'name' => 'branched_scenario'], $body['tool_choice']);
        $prompt = $body['messages'][0]['content'][0]['text'] ?? $body['messages'][0]['content'];
        $this->assertStringContainsString('FULL CRAFT STANDARD', $prompt);
        $this->assertStringContainsString('Learner role: Shift supervisor', $prompt);
        $this->assertStringContainsString("SOURCE MATERIAL:\nEvery personal danger lock", $prompt);
    }

    public function test_verdict_wording_gets_one_repair_call(): void {
        $this->answers[] = $this->tool(function (array $schema) {
            $prose = mod_aibranchedscenario_byok_prose($schema);
            $prose['nodes']['n1']['content'] = 'Well done, the crew has arrived early at the crusher.';
            return $prose;
        });
        $this->answers[] = $this->tool(function (array $schema, array $body) {
            $this->assertSame(1, $schema['properties']['repairs']['minItems']);
            $this->assertStringContainsString(
                '["nodes",0,"content"] (VERDICT_WORDING)',
                $body['messages'][0]['content'][0]['text']
            );
            return ['repairs' => [['path' => ['nodes', 0, 'content'], 'value' =>
                'The crew has arrived early at the crusher.']]];
        });
        $result = (new lmslabs_provider())->generate_scenario(self::WIZARD);
        $this->assertCount(2, $this->requests);
        $this->assertSame('branched_scenario_prose_repair', $this->requests[1]['body']['tools'][0]['name']);
        $this->assertSame(4000, $this->requests[1]['body']['max_tokens']);
        $this->assertStringContainsString('The crew has arrived early', json_encode($result['scenario']));
    }

    public function test_a_refused_scenario_names_the_stage_and_charges_nothing(): void {
        $this->answers[] = $this->tool(function (array $schema) {
            $prose = mod_aibranchedscenario_byok_prose($schema);
            unset($prose['nodes']['n2']);
            return $prose;
        });
        try {
            (new lmslabs_provider())->generate_scenario(self::WIZARD);
            $this->fail('Expected a failure');
        } catch (generation_exception $e) {
            $this->assertSame('error:byokfailed', $e->errorcode);
            $this->assertStringContainsString('assembly: NODE_SLOTS_MISMATCH', $e->getMessage());
        }
        $this->assertContains('error:byokfailed', \mod_aibranchedscenario\local\generator::no_charge_errors());

        try {
            (new lmslabs_provider())->generate_scenario(self::WIZARD);
            $this->fail('Expected a failure');
        } catch (generation_exception $e) {
            $this->assertSame('error:byokfailed', $e->errorcode);
            $this->assertStringContainsString('Overloaded', $e->getMessage());
        }
    }

    public function test_wizard_fill_and_field_suggestion(): void {
        $this->answers[] = $this->tool(function () {
            return ['title' => 'Handover at the crusher', 'introduction' => 'You hand the plant over to the day crew.',
                'audience' => 'Shift supervisors', 'learningObjectives' => ['Match every lock to an owner'], 'instructions' =>
                    ''];
        });
        $fields = (new lmslabs_provider())->populate(['sourcecontent' => self::WIZARD['sourcecontent'], 'title' => 'Draft']);
        $this->assertSame('Handover at the crusher', $fields['title']);
        $prompt = $this->requests[0]['body']['messages'][0]['content'][0]['text'];
        $this->assertStringContainsString('Existing values: {"title":"Draft"}', $prompt);
        $this->assertSame(4000, $this->requests[0]['body']['max_tokens']);

        $this->answers[] = $this->tool(function () {
            return ['suggestion' => '  Two locks on CV-03 have no owner at handover.  '];
        });
        $out = (new lmslabs_provider())->suggest('centralproblem', ['sourcecontent' => self::WIZARD['sourcecontent']]);
        $this->assertSame('Two locks on CV-03 have no owner at handover.', $out['suggestion']);
        $this->assertSame(1000, $this->requests[1]['body']['max_tokens']);
        $this->assertStringContainsString(
            'context.whatThisFieldNeeds',
            $this->requests[1]['body']['messages'][0]['content'][0]['text']
        );
    }

    public function test_pictures_and_narration_on_own_keys(): void {
        set_config('src_image', 'ownkey', 'local_aiconfig');
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'openai', 'local_aiconfig');
        set_config('key_openai', 'sk-test', 'local_aiconfig');
        $image = imagecreatetruecolor(1536, 1024);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        $this->answers[] = ['data' => [['b64_json' => base64_encode($png)]]];
        $this->answers[] = str_repeat("\xff\xfb\x90\x64", 200);
        $provider = new lmslabs_provider();
        $out = $provider->generate_image(str_repeat('A crusher control room at dawn. ', 4), 'photo', 'Handover');
        $this->assertSame('image/jpeg', $out['mimetype']);
        $this->assertSame([1600, 900], array_slice(getimagesizefromstring($out['data']), 0, 2));
        $this->assertSame('1536x1024', $this->requests[0]['body']['size']);
        $clip = $provider->generate_speech('Two locks are still on the board.', 'Aoede', 'en-AU');
        $this->assertSame('audio/mpeg', $clip['mimetype']);
        $this->assertSame('nova', $this->requests[1]['body']['voice']);
        $this->assertSame(0, $provider->get_last_meta()['creditsused']);
    }

    public function test_work_on_own_ai_is_not_priced_in_credits(): void {
        $units = schema::price_for_units(13, 8, 40);
        $this->assertSame(0, $units['nodecost']);
        $this->assertSame(16, $units['imagecost']);
        set_config('src_image', 'ownkey', 'local_aiconfig');
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('key_openai', 'sk-test', 'local_aiconfig');
        set_config('vendor_voice', 'openai', 'local_aiconfig');
        $this->assertSame(0, schema::price_for_units(13, 8, 40)['total']);
        $status = (new lmslabs_provider())->get_status();
        $this->assertTrue($status['connected']);
        $this->assertTrue($status['byok']);
    }
}
