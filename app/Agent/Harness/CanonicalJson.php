<?php

namespace App\Agent\Harness;

use stdClass;

/**
 * Byte-stable JSON serialisation for anything the harness hashes or signs.
 *
 * @internal FROZEN CONTRACT. Every handover signature and every link in the
 * handover hash chain was produced by this encoder. Changing its output — even
 * to make it prettier — invalidates all of them retroactively, and there is no
 * migration path because old signatures cannot be recomputed. The golden-file
 * test in tests/Unit/Agent/CanonicalJsonTest.php exists to make such a change
 * fail loudly. If it fails, the encoder is wrong, not the fixture.
 *
 * The rules, in full:
 *
 *   - Object keys are sorted ascending by raw byte value. UTF-8 preserves code
 *     point order under byte comparison, so this is code point order too.
 *   - No whitespace anywhere outside string values.
 *   - Strings pass through as raw UTF-8. Only ", backslash and C0 controls are
 *     escaped; forward slashes and non-ASCII are never escaped. Controls use
 *     the short forms \b \t \n \f \r where they exist, otherwise lowercase
 *     \u00xx. Invalid UTF-8 is rejected rather than substituted.
 *   - Integers serialise as plain decimal.
 *   - Floats are REJECTED. Their text form depends on the serialize_precision
 *     ini setting and on the language re-encoding them, which is exactly how
 *     two correct implementations end up with two different hashes. Store a
 *     fixed-point value as a string, or an exact count as an integer.
 *   - null is preserved. It is never dropped and never coerced, because an
 *     absent key and a null key are different documents.
 *
 * Two representational notes:
 *
 *   - A PHP array is emitted as a JSON array only when array_is_list() holds.
 *     An integer-keyed array with gaps or out-of-order keys is a map, and is
 *     emitted as an object with numeric string keys. This mirrors json_encode.
 *     It does mean array_filter() on a list changes the encoding, so re-index
 *     with array_values() before handing a filtered list to the encoder.
 *   - An empty PHP array encodes as [] and an empty stdClass as {}. When a
 *     document is round-tripped through json_decode($raw, true) that
 *     distinction is already lost, so signing an assoc-decoded document and an
 *     object-decoded one could in principle disagree. It cannot arise in
 *     practice: every object in .agent/schema/*.json declares "required", so an
 *     empty object never validates and never reaches a signature.
 */
class CanonicalJson
{
    /**
     * Deep enough for any harness document, shallow enough that a cyclic
     * structure fails fast instead of exhausting the stack.
     */
    public const MAX_DEPTH = 64;

    public static function encode(mixed $value): string
    {
        return self::write($value, 0);
    }

    /**
     * BLAKE2b-256 over the canonical bytes, prefixed so a hash is never
     * mistaken for a plain hex string in a diff. This is the hash used for
     * prev_handover_hash in the handover chain.
     */
    public static function hash(mixed $value): string
    {
        return 'blake2b:'.bin2hex(sodium_crypto_generichash(self::encode($value), '', 32));
    }

    /**
     * SHA-256 over the canonical bytes, for the state_sha256 and fingerprint
     * fields where interoperability with ordinary shell tooling matters more
     * than speed.
     */
    public static function sha256(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }

    /**
     * The exact bytes a handover signature covers.
     *
     * The signature key is removed outright rather than nulled: a null would
     * still be serialised, so signing and verifying would disagree about
     * whether the key was there. Only the top level is stripped — a nested
     * "signature" key is ordinary data and stays.
     *
     * @param  array<string, mixed>  $envelope
     */
    public static function encodeForSigning(array $envelope): string
    {
        unset($envelope['signature']);

        return self::encode($envelope);
    }

    /**
     * Strict decode, so callers do not each reinvent the flag set. Associative
     * by default because that is what the rest of the harness works with.
     */
    public static function decode(string $json, bool $assoc = true): mixed
    {
        try {
            return json_decode($json, $assoc, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CanonicalJsonException('Input is not valid JSON: '.$e->getMessage(), 0, $e);
        }
    }

    protected static function write(mixed $value, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new CanonicalJsonException(
                'Structure is deeper than '.self::MAX_DEPTH.' levels, or contains a cycle.'
            );
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            throw new CanonicalJsonException(sprintf(
                'Refusing to encode the float %s. Floats have no byte-stable text form; '
                .'cast it to a string to keep the exact digits, or to an int if it is a count.',
                var_export($value, true)
            ));
        }

        if (is_string($value)) {
            return self::writeString($value);
        }

        if (is_array($value)) {
            return array_is_list($value)
                ? self::writeList($value, $depth)
                : self::writeMap($value, $depth);
        }

        if ($value instanceof stdClass) {
            return self::writeMap(get_object_vars($value), $depth);
        }

        throw new CanonicalJsonException(sprintf(
            'Cannot encode a value of type %s. Convert it to a scalar, array or stdClass first — '
            .'the encoder deliberately does not call JsonSerializable, so that what is signed is '
            .'exactly what was inspected.',
            get_debug_type($value)
        ));
    }

    /**
     * @param  array<int, mixed>  $list
     */
    protected static function writeList(array $list, int $depth): string
    {
        $parts = [];

        foreach ($list as $item) {
            $parts[] = self::write($item, $depth + 1);
        }

        return '['.implode(',', $parts).']';
    }

    /**
     * @param  array<array-key, mixed>  $map
     */
    protected static function writeMap(array $map, int $depth): string
    {
        // PHP silently narrows numeric string keys to int, so compare as
        // strings — otherwise "10" and "2" would sort numerically here and
        // lexically in every other JSON implementation.
        $keys = array_map(strval(...), array_keys($map));
        usort($keys, strcmp(...));

        $parts = [];

        foreach ($keys as $key) {
            $parts[] = self::writeString($key).':'.self::write($map[$key], $depth + 1);
        }

        return '{'.implode(',', $parts).'}';
    }

    protected static function writeString(string $string): string
    {
        // preg with /u validates the subject as UTF-8 without needing mbstring,
        // which keeps this class usable from a bare CI container.
        if ($string !== '' && preg_match('//u', $string) !== 1) {
            throw new CanonicalJsonException(
                'String is not valid UTF-8. It is rejected rather than repaired, because silently '
                .'substituting replacement characters would change the bytes being signed.'
            );
        }

        $out = '"';
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];
            $byte = ord($char);

            // Every byte needing an escape is ASCII, and no UTF-8 continuation
            // byte is below 0x80, so a byte-wise walk is safe for multibyte
            // text and passes it through untouched.
            if ($byte >= 0x20 && $char !== '"' && $char !== '\\') {
                $out .= $char;

                continue;
            }

            $out .= match ($char) {
                '"' => '\"',
                '\\' => '\\\\',
                "\x08" => '\b',
                "\x09" => '\t',
                "\x0a" => '\n',
                "\x0c" => '\f',
                "\x0d" => '\r',
                default => sprintf('\u%04x', $byte),
            };
        }

        return $out.'"';
    }
}
