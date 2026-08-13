<?php

namespace Tests\Unit\Agent;

use App\Agent\Harness\CanonicalJson;
use App\Agent\Harness\CanonicalJsonException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Locks the byte-level output of the encoder that every handover signature and
 * hash-chain link is computed over.
 *
 * Extends PHPUnit's TestCase rather than the project's Laravel one on purpose:
 * the harness has to run from git hooks and CI before the framework is
 * bootstrapped, so a container dependency creeping in here should break a test.
 */
class CanonicalJsonTest extends TestCase
{
    protected const FIXTURES = __DIR__.'/../../Fixtures/agent/canonical';

    /**
     * Builds a \uXXXX escape without writing one literally, so the expectation
     * cannot be silently rewritten by an editor or tool that interprets it.
     */
    protected function u(string $hex): string
    {
        return chr(92).'u'.$hex;
    }

    protected function backslash(): string
    {
        return chr(92);
    }

    public function test_the_golden_file_is_reproduced_byte_for_byte(): void
    {
        // Decoded as objects, not associative arrays, so the fixture's empty
        // object stays distinguishable from its empty array.
        $input = CanonicalJson::decode(file_get_contents(self::FIXTURES.'/input.json'), assoc: false);
        $expected = rtrim(file_get_contents(self::FIXTURES.'/expected.canonical.json'), "\n");

        $this->assertSame($expected, CanonicalJson::encode($input));
    }

    public function test_encoding_is_byte_identical_across_a_thousand_runs(): void
    {
        $input = CanonicalJson::decode(file_get_contents(self::FIXTURES.'/input.json'), assoc: false);

        $seen = [];

        for ($i = 0; $i < 1000; $i++) {
            $seen[CanonicalJson::encode($input)] = true;
        }

        $this->assertCount(1, $seen, 'Encoder output varied between runs, so no signature over it can be trusted.');
    }

    public function test_key_order_in_the_input_does_not_change_the_output(): void
    {
        $one = ['zulu' => 1, 'alpha' => 2, 'mike' => ['z' => 1, 'a' => 2]];
        $two = ['mike' => ['a' => 2, 'z' => 1], 'alpha' => 2, 'zulu' => 1];

        $this->assertSame(CanonicalJson::encode($one), CanonicalJson::encode($two));
        $this->assertSame('{"alpha":2,"mike":{"a":2,"z":1},"zulu":1}', CanonicalJson::encode($one));
    }

    public function test_numeric_keys_sort_lexically_not_numerically(): void
    {
        // PHP narrows these to integers, so without the string cast in
        // writeMap() they would sort 1, 2, 10 while every other JSON
        // implementation sorts 1, 10, 2.
        $this->assertSame(
            '{"1":"c","10":"a","2":"b"}',
            CanonicalJson::encode(['10' => 'a', '2' => 'b', '1' => 'c'])
        );
    }

    public function test_a_list_stays_an_array_but_a_gapped_one_becomes_an_object(): void
    {
        $this->assertSame('[1,2,3]', CanonicalJson::encode([1, 2, 3]));
        $this->assertSame('{"0":"a","2":"b"}', CanonicalJson::encode([0 => 'a', 2 => 'b']));
        $this->assertSame('{"0":"b","1":"a"}', CanonicalJson::encode([1 => 'a', 0 => 'b']));
    }

    public function test_null_is_preserved_rather_than_dropped(): void
    {
        $this->assertSame('{"a":null,"b":1}', CanonicalJson::encode(['a' => null, 'b' => 1]));
        $this->assertNotSame(
            CanonicalJson::encode(['b' => 1]),
            CanonicalJson::encode(['a' => null, 'b' => 1]),
            'An absent key and a null key must hash differently.'
        );
    }

    public function test_empty_containers_keep_their_shape(): void
    {
        $this->assertSame('[]', CanonicalJson::encode([]));
        $this->assertSame('{}', CanonicalJson::encode(new stdClass));
    }

    public function test_control_characters_use_the_locked_escape_forms(): void
    {
        $bs = $this->backslash();

        $this->assertSame(
            '"'.$bs.'b'.$bs.'t'.$bs.'n'.$bs.'f'.$bs.'r"',
            CanonicalJson::encode(chr(8).chr(9).chr(10).chr(12).chr(13))
        );

        // Controls without a short form use lowercase four-digit hex.
        $this->assertSame('"'.$this->u('0000').'"', CanonicalJson::encode(chr(0)));
        $this->assertSame('"'.$this->u('001f').'"', CanonicalJson::encode(chr(31)));
        $this->assertSame('"'.$this->u('000b').'"', CanonicalJson::encode(chr(11)));
    }

    public function test_quotes_and_backslashes_are_escaped_but_slashes_are_not(): void
    {
        $bs = $this->backslash();

        $this->assertSame('"'.$bs.'""', CanonicalJson::encode('"'));
        $this->assertSame('"'.$bs.$bs.'"', CanonicalJson::encode($bs));
        $this->assertSame('"a/b"', CanonicalJson::encode('a/b'));
    }

    public function test_non_ascii_passes_through_as_raw_utf8(): void
    {
        $this->assertSame('"中文 🔐 é"', CanonicalJson::encode('中文 🔐 é'));
        $this->assertSame(chr(0x7F), CanonicalJson::decode(CanonicalJson::encode(chr(0x7F))));
    }

    public function test_invalid_utf8_is_rejected_rather_than_repaired(): void
    {
        $this->expectException(CanonicalJsonException::class);
        $this->expectExceptionMessageMatches('/not valid UTF-8/');

        CanonicalJson::encode(chr(0xC3).chr(0x28));
    }

    public function test_a_float_is_rejected_with_an_actionable_message(): void
    {
        try {
            CanonicalJson::encode(['amount' => 12.30]);
            $this->fail('Expected a float to be rejected.');
        } catch (CanonicalJsonException $e) {
            $this->assertStringContainsString('byte-stable', $e->getMessage());
            $this->assertStringContainsString('cast it to a string', $e->getMessage());
        }
    }

    public function test_an_unsupported_object_type_is_rejected(): void
    {
        $this->expectException(CanonicalJsonException::class);

        CanonicalJson::encode(['at' => new DateTimeImmutable('2026-08-12')]);
    }

    public function test_the_depth_limit_is_enforced(): void
    {
        $deep = 'leaf';

        for ($i = 0; $i < CanonicalJson::MAX_DEPTH + 5; $i++) {
            $deep = ['down' => $deep];
        }

        $this->expectException(CanonicalJsonException::class);
        $this->expectExceptionMessageMatches('/deeper than|cycle/');

        CanonicalJson::encode($deep);
    }

    public function test_encode_for_signing_removes_only_the_top_level_signature(): void
    {
        $envelope = [
            'handover_id' => 'h1',
            'payload' => ['signature' => 'this one is ordinary data'],
            'signature' => ['alg' => 'Ed25519', 'value' => 'base64:zzz'],
        ];

        $signed = CanonicalJson::encodeForSigning($envelope);

        $this->assertSame(
            '{"handover_id":"h1","payload":{"signature":"this one is ordinary data"}}',
            $signed
        );

        // A signature set to null must not survive either, or signing and
        // verifying would disagree about whether the key was present.
        $this->assertSame(
            $signed,
            CanonicalJson::encodeForSigning(['handover_id' => 'h1', 'payload' => $envelope['payload'], 'signature' => null])
        );
    }

    public function test_hash_is_prefixed_and_sensitive_to_nested_change(): void
    {
        $base = ['a' => ['b' => ['c' => 1]]];
        $changed = ['a' => ['b' => ['c' => 2]]];

        $hash = CanonicalJson::hash($base);

        $this->assertMatchesRegularExpression('/^blake2b:[0-9a-f]{64}$/', $hash);
        $this->assertSame($hash, CanonicalJson::hash(['a' => ['b' => ['c' => 1]]]));
        $this->assertNotSame($hash, CanonicalJson::hash($changed));
    }

    public function test_sha256_is_taken_over_the_canonical_bytes(): void
    {
        $value = ['b' => 2, 'a' => 1];

        $this->assertSame(
            hash('sha256', '{"a":1,"b":2}'),
            CanonicalJson::sha256($value)
        );
    }

    public function test_object_and_associative_input_agree(): void
    {
        $raw = '{"b":[1,2],"a":{"nested":true},"c":null}';

        $this->assertSame(
            CanonicalJson::encode(CanonicalJson::decode($raw, assoc: true)),
            CanonicalJson::encode(CanonicalJson::decode($raw, assoc: false))
        );
    }

    public function test_decode_rejects_invalid_json(): void
    {
        $this->expectException(CanonicalJsonException::class);

        CanonicalJson::decode('{"unterminated": ');
    }
}
