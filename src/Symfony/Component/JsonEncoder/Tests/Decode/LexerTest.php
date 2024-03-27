<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\Lexer;
use Symfony\Component\JsonEncoder\Exception\InvalidStreamException;
use Symfony\Component\JsonEncoder\Stream\BufferedStream;

class LexerTest extends TestCase
{
    public function testTokens()
    {
        $this->assertTokens([['1', 0]], '1');
        $this->assertTokens([['false', 0]], 'false');
        $this->assertTokens([['null', 0]], 'null');
        $this->assertTokens([['"string"', 0]], '"string"');
        $this->assertTokens([['[', 0], [']', 1]], '[]');
        $this->assertTokens([['[', 0], ['10', 2], [',', 4], ['20', 6], [']', 9]], '[ 10, 20 ]');
        $this->assertTokens([['[', 0], ['1', 1], [',', 2], ['[', 4], ['2', 5], [']', 6], [']', 8]], '[1, [2] ]');
        $this->assertTokens([['{', 0], ['}', 1]], '{}');
        $this->assertTokens([['{', 0], ['"foo"', 1], [':', 6], ['{', 8], ['"bar"', 9], [':', 14], ['"baz"', 15], ['}', 20], ['}', 21]], '{"foo": {"bar":"baz"}}');
    }

    public function testTokensSubset()
    {
        $this->assertTokens([['false', 7]], '[1, 2, false]', 7, 5);
    }

    public function testTokenizeOverflowingBuffer()
    {
        $veryLongString = sprintf('"%s"', str_repeat('.', 20000));

        $this->assertTokens([[$veryLongString, 0]], $veryLongString);
    }

    /**
     * Ensures that the lexer is compliant with RFC 8259.
     *
     * @dataProvider jsonDataProvider
     */
    public function testValidJson(string $name, string $json, bool $valid)
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);

        try {
            iterator_to_array((new Lexer())->getTokens($resource, 0, null));
            fclose($resource);

            if (!$valid) {
                $this->fail(sprintf('"%s" should not be parseable.', $name));
            }

            $this->addToAssertionCount(1);
        } catch (InvalidStreamException) {
            fclose($resource);

            if ($valid) {
                $this->fail(sprintf('"%s" should be parseable.', $name));
            }

            $this->addToAssertionCount(1);
        }
    }

    /**
     * Pulled from https://github.com/nst/JSONTestSuite.
     *
     * @return iterable<array{0: string, 1: string, 2: bool}>
     */
    public static function jsonDataProvider(): iterable
    {
        yield ['array_1_true_without_comma', '[1 true]', false];
        yield ['array_a_invalid_utf8', '[aå]', false];
        yield ['array_colon_instead_of_comma', '["": 1]', false];
        yield ['array_comma_after_close', '[""],', false];
        yield ['array_comma_and_number', '[,1]', false];
        yield ['array_double_comma', '[1,,2]', false];
        yield ['array_double_extra_comma', '["x",,]', false];
        yield ['array_extra_close', '["x"]]', false];
        yield ['array_extra_comma', '["",]', false];
        yield ['array_incomplete', '["x"', false];
        yield ['array_incomplete_invalid_value', '[x', false];
        yield ['array_inner_array_no_comma', '[3[4]]', false];
        yield ['array_invalid_utf8', '[ÿ]', false];
        yield ['array_items_separated_by_semicolon', '[1:2]', false];
        yield ['array_just_comma', '[,]', false];
        yield ['array_just_minus', '[-]', false];
        yield ['array_missing_value', '[   , ""]', false];
        yield ['array_newlines_unclosed', <<<JSON
["a",
4
,1,
JSON, false];
        yield ['array_number_and_comma', '[1,]', false];
        yield ['array_number_and_several_commas', '[1,,]', false];
        yield ['array_spaces_vertical_tab_formfeed', '["
a"\f]', false];
        yield ['array_star_inside', '[*]', false];
        yield ['array_unclosed', '[""', false];
        yield ['array_unclosed_trailing_comma', '[1,', false];
        yield ['array_unclosed_with_new_lines', <<<JSON
[1,
1
,1
JSON, false];
        yield ['array_unclosed_with_object_inside', '[{}', false];
        yield ['incomplete_false', '[fals]', false];
        yield ['incomplete_null', '[nul]', false];
        yield ['incomplete_true', '[tru]', false];
        yield ['multidigit_number_then_00', '123\\u0000', false];
        yield ['number_++', '[++1234]', false];
        yield ['number_+1', '[+1]', false];
        yield ['number_+Inf', '[+Inf]', false];
        yield ['number_-01', '[-01]', false];
        yield ['number_-1.0.', '[-1.0.]', false];
        yield ['number_-2.', '[-2.]', false];
        yield ['number_-NaN', '[-NaN]', false];
        yield ['number_.-1', '[.-1]', false];
        yield ['number_.2e-3', '[.2e-3]', false];
        yield ['number_0.1.2', '[0.1.2]', false];
        yield ['number_0.3e+', '[0.3e+]', false];
        yield ['number_0.3e', '[0.3e]', false];
        yield ['number_0.e1', '[0.e1]', false];
        yield ['number_0_capital_E+', '[0E+]', false];
        yield ['number_0_capital_E', '[0E]', false];
        yield ['number_0e+', '[0e+]', false];
        yield ['number_0e', '[0e]', false];
        yield ['number_1.0e+', '[1.0e+]', false];
        yield ['number_1.0e-', '[1.0e-]', false];
        yield ['number_1.0e', '[1.0e]', false];
        yield ['number_1_000', '[1 000.0]', false];
        yield ['number_1eE2', '[1eE2]', false];
        yield ['number_2.e+3', '[2.e+3]', false];
        yield ['number_2.e-3', '[2.e-3]', false];
        yield ['number_2.e3', '[2.e3]', false];
        yield ['number_9.e+', '[9.e+]', false];
        yield ['number_expression', '[1+2]', false];
        yield ['number_hex_1_digit', '[0x1]', false];
        yield ['number_hex_2_digits', '[0x42]', false];
        yield ['number_Inf', '[Inf]', false];
        yield ['number_infinity', '[Infinity]', false];
        yield ['number_invalid+-', '[0e+-1]', false];
        yield ['number_invalid-negative-real', '[-123.123foo]', false];
        yield ['number_invalid-utf-8-in-bigger-int', '[123å]', false];
        yield ['number_invalid-utf-8-in-exponent', '[1e1å]', false];
        yield ['number_invalid-utf-8-in-int', '[0å]', false];
        yield ['number_minus_infinity', '[-Infinity]', false];
        yield ['number_minus_sign_with_trailing_garbage', '[-foo]', false];
        yield ['number_minus_space_1', '[- 1]', false];
        yield ['number_NaN', '[NaN]', false];
        yield ['number_neg_int_starting_with_zero', '[-012]', false];
        yield ['number_neg_real_without_int_part', '[-.123]', false];
        yield ['number_neg_with_garbage_at_end', '[-1x]', false];
        yield ['number_real_garbage_after_e', '[1ea]', false];
        yield ['number_real_with_invalid_utf8_after_e', '[1eå]', false];
        yield ['number_real_without_fractional_part', '[1.]', false];
        yield ['number_starting_with_dot', '[.123]', false];
        yield ['number_U+FF11_fullwidth_digit_one', '[１]', false];
        yield ['number_with_alpha', '[1.2a-3]', false];
        yield ['number_with_alpha_char', '[1.8011670033376514H-308]', false];
        yield ['number_with_leading_zero', '[012]', false];
        yield ['object_bad_value', '["x", truth]', false];
        yield ['object_bracket_key', '{[: "x"}', false];
        yield ['object_comma_instead_of_colon', '{"x", null}', false];
        yield ['object_double_colon', '{"x"::"b"}', false];
        yield ['object_emoji', '{🇨🇭}', false];
        yield ['object_garbage_at_end', '{"a":"a" 123}', false];
        yield ['object_key_with_single_quotes', '{key: \'value\'}', false];
        yield ['object_lone_continuation_byte_in_key_and_trailing_comma', '{"¹":"0",}', false];
        yield ['object_missing_colon', '{"a" b}', false];
        yield ['object_missing_key', '{:"b"}', false];
        yield ['object_missing_semicolon', '{"a" "b"}', false];
        yield ['object_missing_value', '{"a":', false];
        yield ['object_no-colon', '{"a"', false];
        yield ['object_non_string_key', '{1:1}', false];
        yield ['object_non_string_key_but_huge_number_instead', '{9999E9999:1}', false];
        yield ['object_repeated_null_null', '{null:null,null:null}', false];
        yield ['object_several_trailing_commas', '{"id":0,,,,,}', false];
        yield ['object_single_quote', '{\'a\':0}', false];
        yield ['object_trailing_comma', '{"id":0,}', false];
        yield ['object_trailing_comment', '{"a":"b"}/**/', false];
        yield ['object_trailing_comment_open', '{"a":"b"}/**//', false];
        yield ['object_trailing_comment_slash_open', '{"a":"b"}//', false];
        yield ['object_trailing_comment_slash_open_incomplete', '{"a":"b"}/', false];
        yield ['object_two_commas_in_a_row', '{"a":"b",,"c":"d"}', false];
        yield ['object_unquoted_key', '{a: "b"}', false];
        yield ['object_unterminated-value', '{"a":"a', false];
        yield ['object_with_single_string', '{ "foo" : "bar", "a" }', false];
        yield ['object_with_trailing_garbage', '{"a":"b"}#', false];
        yield ['single_space', ' ', false];
        yield ['string_1_surrogate_then_escape', '["\\uD800\\"]', false];
        yield ['string_1_surrogate_then_escape_u', '["\\uD800\\u"]', false];
        yield ['string_1_surrogate_then_escape_u1', '["\\uD800\\u1"]', false];
        yield ['string_1_surrogate_then_escape_u1x', '["\\uD800\\u1x"]', false];
        yield ['string_accentuated_char_no_quotes', '[é]', false];
        yield ['string_backslash_00', '["\\\\u0000]', false];
        yield ['string_escape_x', '["\\x00"]', false];
        yield ['string_escaped_backslash_bad', '["\\\\\\"]', false];
        yield ['string_escaped_ctrl_char_tab', '["\\	"]', false];
        yield ['string_escaped_emoji', '["\\🌀"]', false];
        yield ['string_incomplete_escape', '["\\"]', false];
        yield ['string_incomplete_escaped_character', '["\\u00A"]', false];
        yield ['string_incomplete_surrogate', '["\\uD834\\uDd"]', false];
        yield ['string_incomplete_surrogate_escape_invalid', '["\\uD800\\uD800\\x"]', false];
        yield ['string_invalid-utf-8-in-escape', '["\\uå"]', false];
        yield ['string_invalid_backslash_esc', '["\\a"]', false];
        yield ['string_invalid_unicode_escape', '["\\uqqqq"]', false];
        yield ['string_invalid_utf8_after_escape', '["\å"]', false];
        yield ['string_leading_uescaped_thinspace', '[\u0020"asd"]', false];
        yield ['string_no_quotes_with_bad_escape', '[\n]', false];
        yield ['string_single_doublequote', '"', false];
        yield ['string_single_quote', '[\'single quote\']', false];
        yield ['string_single_string_no_double_quotes', 'abc', false];
        yield ['string_start_escape_unclosed', '["\\', false];
        yield ['string_unescaped_newline', <<<JSON
["new
line"]
JSON, false];
        yield ['string_unescaped_tab', '["	"]', false];
        yield ['string_unicode_CapitalU', '"\\UA66D"', false];
        yield ['string_with_trailing_garbage', '""x', false];
        yield ['structure_angle_bracket_.', '<.>', false];
        yield ['structure_angle_bracket_null', '[<null>]', false];
        yield ['structure_array_trailing_garbage', '[1]x', false];
        yield ['structure_array_with_extra_array_close', '[1]]', false];
        yield ['structure_array_with_unclosed_string', '["asd]', false];
        yield ['structure_ascii-unicode-identifier', 'aå', false];
        yield ['structure_capitalized_True', '[True]', false];
        yield ['structure_close_unopened_array', '1]', false];
        yield ['structure_comma_instead_of_closing_brace', '{"x": true,', false];
        yield ['structure_double_array', '[][]', false];
        yield ['structure_end_array', ']', false];
        yield ['structure_incomplete_UTF8_BOM', 'ï»{}', false];
        yield ['structure_lone-invalid-utf-8', 'å', false];
        yield ['structure_lone-open-bracket', '[', false];
        yield ['structure_no_data', '', false];
        yield ['structure_null-byte-outside-string', '[\\u0000]', false];
        yield ['structure_number_with_trailing_garbage', '2@', false];
        yield ['structure_object_followed_by_closing_object', '{}}', false];
        yield ['structure_object_unclosed_no_value', '{"":', false];
        yield ['structure_object_with_comment', '{"a":/*comment*/"b"}', false];
        yield ['structure_object_with_trailing_garbage', '{"a": true} "x"', false];
        yield ['structure_open_array_apostrophe', '[\'', false];
        yield ['structure_open_array_comma', '[,', false];
        yield ['structure_open_array_object', '[{', false];
        yield ['structure_open_array_open_object', '[{"":[{"":', false];
        yield ['structure_open_array_open_string', '["a', false];
        yield ['structure_open_array_string', '["a"', false];
        yield ['structure_open_object', '{', false];
        yield ['structure_open_object_close_array', '{]', false];
        yield ['structure_open_object_comma', '{,', false];
        yield ['structure_open_object_open_array', '{[', false];
        yield ['structure_open_object_open_string', '{"a', false];
        yield ['structure_open_object_string_with_apostrophes', '{\'a\'', false];
        yield ['structure_open_open', '["\\{["\\{["\\{["\\{', false];
        yield ['structure_single_eacute', 'é', false];
        yield ['structure_single_star', '*', false];
        yield ['structure_trailing_#', '{"a":"b"}#{}', false];
        yield ['structure_U+2060_word_joined', '[\\u2060]', false];
        yield ['structure_uescaped_LF_before_string', '[\\u000A""]', false];
        yield ['structure_unclosed_array', '[1', false];
        yield ['structure_unclosed_array_partial_null', '[ false, nul', false];
        yield ['structure_unclosed_array_unfinished_false', '[ true, fals', false];
        yield ['structure_unclosed_array_unfinished_true', '[ false, tru', false];
        yield ['structure_unclosed_object', '{"asd":"asd"', false];
        yield ['structure_whitespace_formfeed', '[\\u000c]', false];

        yield ['array_arraysWithSpaces', '[[]   ]', true];
        yield ['array_empty-string', '[""]', true];
        yield ['array_empty', '[]', true];
        yield ['array_ending_with_newline', '["a"]', true];
        yield ['array_false', '[false]', true];
        yield ['array_heterogeneous', '[null, 1, "1", {}]', true];
        yield ['array_null', '[null]', true];
        yield ['array_with_1_and_newline', <<<JSON
[1
]
JSON, true];
        yield ['array_with_leading_space', '[1]', true];
        yield ['array_with_several_null', '[1,null,null,null,2]', true];
        yield ['array_with_trailing_space', '[2] ', true];
        yield ['number', '[123e65]', true];
        yield ['number_0e+1', '[0e+1]', true];
        yield ['number_0e1', '[0e1]', true];
        yield ['number_after_space', '[ 4]', true];
        yield ['number_double_close_to_zero', '[-0.000000000000000000000000000000000000000000000000000000000000000000000000000001]', true];
        yield ['number_int_with_exp', '[20e1]', true];
        yield ['number_negative_int', '[-123]', true];
        yield ['number_negative_one', '[-1]', true];
        yield ['number_negative_zero', '[-0]', true];
        yield ['number_real_capital_e', '[1E22]', true];
        yield ['number_real_capital_e_neg_exp', '[1E-2]', true];
        yield ['number_real_capital_e_pos_exp', '[1E+2]', true];
        yield ['number_real_exponent', '[123e45]', true];
        yield ['number_real_fraction_exponent', '[123.456e78]', true];
        yield ['number_real_neg_exp', '[1e-2]', true];
        yield ['number_real_pos_exponent', '[1e+2]', true];
        yield ['number_simple_int', '[123]', true];
        yield ['number_simple_real', '[123.456789]', true];
        yield ['object', '{"asd":"sdf", "dfg":"fgh"}', true];
        yield ['object_basic', '{"asd":"sdf"}', true];
        yield ['object_empty', '{}', true];
        yield ['object_empty_key', '{"":0}', true];
        yield ['object_escaped_null_in_key', '{"foo\\u0000bar": 42}', true];
        yield ['object_extreme_numbers', '{ "min": -1.0e+28, "max": 1.0e+28 }', true];
        yield ['object_long_strings', '{"x":[{"id": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"}], "id": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"}', true];
        yield ['object_simple', '{"a":[]}', true];
        yield ['object_string_unicode', '{"title":"\\u041f\\u043e\\u043b\\u0442\\u043e\\u0440\\u0430 \\u0417\\u0435\\u043c\\u043b\\u0435\\u043a\\u043e\\u043f\\u0430" }', true];
        yield ['object_with_newlines', <<<JSON
{
"a": "b"
}
JSON, true];
        yield ['string_1_2_3_bytes_UTF-8_sequences', '["\\u0060\\u012a\\u12AB"]', true];
        yield ['string_accepted_surrogate_pair', '["\\uD801\\udc37"]', true];
        yield ['string_accepted_surrogate_pairs', '["\\ud83d\\ude39\\ud83d\\udc8d"]', true];
        yield ['string_allowed_escapes', '["\\"\\\\\\/\\b\\f\\n\\r\t"]', true];
        yield ['string_backslash_and_u_escaped_zero', '["\\\\u0000"]', true];
        yield ['string_backslash_doublequotes', '["\\""]', true];
        yield ['string_comments', '["a/*b*/c/*d//e"]', true];
        yield ['string_double_escape_a', '["\\\\a"]', true];
        yield ['string_double_escape_n', '["\\\\n"]', true];
        yield ['string_escaped_control_character', '["\\u0012"]', true];
        yield ['string_escaped_noncharacter', '["\\uFFFF"]', true];
        yield ['string_in_array', '["asd"]', true];
        yield ['string_in_array_with_leading_space', '[ "asd"]', true];
        yield ['string_last_surrogates_1_and_2', '["\uDBFF\uDFFF"]', true];
        yield ['string_nbsp_uescaped', '["new\u00A0line"]', true];
        yield ['string_nonCharacterInUTF-8_U+10FFFF', '["\\u10fff"]', true];
        yield ['string_nonCharacterInUTF-8_U+FFFF', '["\\uffff"]', true];
        yield ['string_null_escape', '["\\u0000"]', true];
        yield ['string_one-byte-utf-8', '["\\u002c"]', true];
        yield ['string_pi', '["π"]', true];
        yield ['string_reservedCharacterInUTF-8_U+1BFFF', '["\\u1bfff𛿿"]', true];
        yield ['string_simple_ascii', '["asd "]', true];
        yield ['string_space', '" "', true];
        yield ['string_surrogates_U+1D11E_MUSICAL_SYMBOL_G_CLEF', '["\\uD834\\uDd1e"]', true];
        yield ['string_three-byte-utf-8', '["\\u0821"]', true];
        yield ['string_two-byte-utf-8', '["\\u0123"]', true];
        yield ['string_u+2028_line_sep', '["\\u2028"]', true];
        yield ['string_u+2029_par_sep', '["\\u2029"]', true];
        yield ['string_uEscape', '["\\u0061\\u30af\\u30EA\\u30b9"]', true];
        yield ['string_uescaped_newline', '["new\\u000Aline"]', true];
        yield ['string_unescaped_char_delete', '[""]', true];
        yield ['string_unicode', '["\\uA66D"]', true];
        yield ['string_unicode_2', '["⍂㈴⍂"]', true];
        yield ['string_unicode_escaped_double_quote', '["\\u0022"]', true];
        yield ['string_unicode_U+10FFFE_nonchar', '["\\uDBFF\\uDFFE"]', true];
        yield ['string_unicode_U+1FFFE_nonchar', '["\\uD83F\\uDFFE"]', true];
        yield ['string_unicode_U+200B_ZERO_WIDTH_SPACE', '["\\u200B"]', true];
        yield ['string_unicode_U+2064_invisible_plus', '["\\u2064"]', true];
        yield ['string_unicode_U+FDD0_nonchar', '["\\uFDD0"]', true];
        yield ['string_unicode_U+FFFE_nonchar', '["\\uFFFE"]', true];
        yield ['string_unicodeEscapedBackslash', '["\\u005C"]', true];
        yield ['string_utf8', '["€𝄞"]', true];
        yield ['string_with_del_character', '["aa"]', true];
        yield ['structure_lonely_false', 'false', true];
        yield ['structure_lonely_int', '69004', true];
        yield ['structure_lonely_negative_real', '-0.1', true];
        yield ['structure_lonely_null', 'null', true];
        yield ['structure_lonely_string', '"asd"', true];
        yield ['structure_lonely_true', 'true', true];
        yield ['structure_string_empty', '""', true];
        yield ['structure_trailing_newline', <<<JSON
["a"]

JSON, true];
        yield ['structure_true_in_array', '[true]', true];
        yield ['structure_whitespace_array', '[] ', true];

        // Contrary to what https://datatracker.ietf.org/doc/html/rfc8259 says,
        // duplicate keys must result in error, see https://github.com/golang/go/discussions/63397.
        // Therefore "object_duplicated_key" and "object_duplicated_key_and_value" are considered
        // as invalid.
        yield ['object_duplicated_key', '{"a":"b","a":"c"}', false];
        yield ['object_duplicated_key_and_value', '{"a":"b","a":"b"}', false];
    }

    private function assertTokens(array $tokens, string $content, int $offset = 0, ?int $length = null): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $this->assertSame($tokens, iterator_to_array((new Lexer())->getTokens($resource, $offset, $length)));

        $stream = new BufferedStream();
        $stream->write($content);
        $stream->rewind();

        $this->assertSame($tokens, iterator_to_array((new Lexer())->getTokens($stream, $offset, $length)));
    }
}
