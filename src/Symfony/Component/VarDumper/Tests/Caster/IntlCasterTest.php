<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension intl
 */
class IntlCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testMessageFormatter()
    {
        $var = new \MessageFormatter('en', 'Hello {name}');

        $expected = <<<EOTXT
MessageFormatter {
  locale: "en"
  pattern: "Hello {name}"
}
EOTXT;
        $this->assertDumpEquals($expected, $var);
    }

    public function testCastNumberFormatter()
    {
        $var = new \NumberFormatter('en', \NumberFormatter::DECIMAL);

        $expectedLocale = $var->getLocale();
        $expectedPattern = $var->getPattern();

        $expectedAttribute1 = $var->getAttribute(\NumberFormatter::PARSE_INT_ONLY);
        $expectedAttribute2 = $var->getAttribute(\NumberFormatter::GROUPING_USED);
        $expectedAttribute3 = $var->getAttribute(\NumberFormatter::DECIMAL_ALWAYS_SHOWN);
        $expectedAttribute4 = $var->getAttribute(\NumberFormatter::MAX_INTEGER_DIGITS);
        $expectedAttribute5 = $var->getAttribute(\NumberFormatter::MIN_INTEGER_DIGITS);
        $expectedAttribute6 = $var->getAttribute(\NumberFormatter::INTEGER_DIGITS);
        $expectedAttribute7 = $var->getAttribute(\NumberFormatter::MAX_FRACTION_DIGITS);
        $expectedAttribute8 = $var->getAttribute(\NumberFormatter::MIN_FRACTION_DIGITS);
        $expectedAttribute9 = $var->getAttribute(\NumberFormatter::FRACTION_DIGITS);
        $expectedAttribute10 = $var->getAttribute(\NumberFormatter::MULTIPLIER);
        $expectedAttribute11 = $var->getAttribute(\NumberFormatter::GROUPING_SIZE);
        $expectedAttribute12 = $var->getAttribute(\NumberFormatter::ROUNDING_MODE);
        $expectedAttribute13 = number_format($var->getAttribute(\NumberFormatter::ROUNDING_INCREMENT), 1);
        $expectedAttribute14 = $var->getAttribute(\NumberFormatter::FORMAT_WIDTH);
        $expectedAttribute15 = $var->getAttribute(\NumberFormatter::PADDING_POSITION);
        $expectedAttribute16 = $var->getAttribute(\NumberFormatter::SECONDARY_GROUPING_SIZE);
        $expectedAttribute17 = $var->getAttribute(\NumberFormatter::SIGNIFICANT_DIGITS_USED);
        $expectedAttribute18 = $var->getAttribute(\NumberFormatter::MIN_SIGNIFICANT_DIGITS);
        $expectedAttribute19 = $var->getAttribute(\NumberFormatter::MAX_SIGNIFICANT_DIGITS);
        $expectedAttribute20 = $var->getAttribute(\NumberFormatter::LENIENT_PARSE);

        $expectedTextAttribute1 = $var->getTextAttribute(\NumberFormatter::POSITIVE_PREFIX);
        $expectedTextAttribute2 = $var->getTextAttribute(\NumberFormatter::POSITIVE_SUFFIX);
        $expectedTextAttribute3 = $var->getTextAttribute(\NumberFormatter::NEGATIVE_PREFIX);
        $expectedTextAttribute4 = $var->getTextAttribute(\NumberFormatter::NEGATIVE_SUFFIX);
        $expectedTextAttribute5 = $var->getTextAttribute(\NumberFormatter::PADDING_CHARACTER);
        $expectedTextAttribute6 = $var->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
        $expectedTextAttribute7 = $var->getTextAttribute(\NumberFormatter::DEFAULT_RULESET) ? 'true' : 'false';
        $expectedTextAttribute8 = $var->getTextAttribute(\NumberFormatter::PUBLIC_RULESETS) ? 'true' : 'false';

        $expectedSymbol1 = $var->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $expectedSymbol2 = $var->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
        $expectedSymbol3 = $var->getSymbol(\NumberFormatter::PATTERN_SEPARATOR_SYMBOL);
        $expectedSymbol4 = $var->getSymbol(\NumberFormatter::PERCENT_SYMBOL);
        $expectedSymbol5 = $var->getSymbol(\NumberFormatter::ZERO_DIGIT_SYMBOL);
        $expectedSymbol6 = $var->getSymbol(\NumberFormatter::DIGIT_SYMBOL);
        $expectedSymbol7 = $var->getSymbol(\NumberFormatter::MINUS_SIGN_SYMBOL);
        $expectedSymbol8 = $var->getSymbol(\NumberFormatter::PLUS_SIGN_SYMBOL);
        $expectedSymbol9 = $var->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
        $expectedSymbol10 = $var->getSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL);
        $expectedSymbol11 = $var->getSymbol(\NumberFormatter::MONETARY_SEPARATOR_SYMBOL);
        $expectedSymbol12 = $var->getSymbol(\NumberFormatter::EXPONENTIAL_SYMBOL);
        $expectedSymbol13 = $var->getSymbol(\NumberFormatter::PERMILL_SYMBOL);
        $expectedSymbol14 = $var->getSymbol(\NumberFormatter::PAD_ESCAPE_SYMBOL);
        $expectedSymbol15 = $var->getSymbol(\NumberFormatter::INFINITY_SYMBOL);
        $expectedSymbol16 = $var->getSymbol(\NumberFormatter::NAN_SYMBOL);
        $expectedSymbol17 = $var->getSymbol(\NumberFormatter::SIGNIFICANT_DIGIT_SYMBOL);
        $expectedSymbol18 = $var->getSymbol(\NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL);

        $expected = <<<EOTXT
NumberFormatter {
  locale: "$expectedLocale"
  pattern: "$expectedPattern"
  attributes: {
    PARSE_INT_ONLY: $expectedAttribute1
    GROUPING_USED: $expectedAttribute2
    DECIMAL_ALWAYS_SHOWN: $expectedAttribute3
    MAX_INTEGER_DIGITS: $expectedAttribute4
    MIN_INTEGER_DIGITS: $expectedAttribute5
    INTEGER_DIGITS: $expectedAttribute6
    MAX_FRACTION_DIGITS: $expectedAttribute7
    MIN_FRACTION_DIGITS: $expectedAttribute8
    FRACTION_DIGITS: $expectedAttribute9
    MULTIPLIER: $expectedAttribute10
    GROUPING_SIZE: $expectedAttribute11
    ROUNDING_MODE: $expectedAttribute12
    ROUNDING_INCREMENT: $expectedAttribute13
    FORMAT_WIDTH: $expectedAttribute14
    PADDING_POSITION: $expectedAttribute15
    SECONDARY_GROUPING_SIZE: $expectedAttribute16
    SIGNIFICANT_DIGITS_USED: $expectedAttribute17
    MIN_SIGNIFICANT_DIGITS: $expectedAttribute18
    MAX_SIGNIFICANT_DIGITS: $expectedAttribute19
    LENIENT_PARSE: $expectedAttribute20
  }
  text_attributes: {
    POSITIVE_PREFIX: "$expectedTextAttribute1"
    POSITIVE_SUFFIX: "$expectedTextAttribute2"
    NEGATIVE_PREFIX: "$expectedTextAttribute3"
    NEGATIVE_SUFFIX: "$expectedTextAttribute4"
    PADDING_CHARACTER: "$expectedTextAttribute5"
    CURRENCY_CODE: "$expectedTextAttribute6"
    DEFAULT_RULESET: $expectedTextAttribute7
    PUBLIC_RULESETS: $expectedTextAttribute8
  }
  symbols: {
    DECIMAL_SEPARATOR_SYMBOL: "$expectedSymbol1"
    GROUPING_SEPARATOR_SYMBOL: "$expectedSymbol2"
    PATTERN_SEPARATOR_SYMBOL: "$expectedSymbol3"
    PERCENT_SYMBOL: "$expectedSymbol4"
    ZERO_DIGIT_SYMBOL: "$expectedSymbol5"
    DIGIT_SYMBOL: "$expectedSymbol6"
    MINUS_SIGN_SYMBOL: "$expectedSymbol7"
    PLUS_SIGN_SYMBOL: "$expectedSymbol8"
    CURRENCY_SYMBOL: "$expectedSymbol9"
    INTL_CURRENCY_SYMBOL: "$expectedSymbol10"
    MONETARY_SEPARATOR_SYMBOL: "$expectedSymbol11"
    EXPONENTIAL_SYMBOL: "$expectedSymbol12"
    PERMILL_SYMBOL: "$expectedSymbol13"
    PAD_ESCAPE_SYMBOL: "$expectedSymbol14"
    INFINITY_SYMBOL: "$expectedSymbol15"
    NAN_SYMBOL: "$expectedSymbol16"
    SIGNIFICANT_DIGIT_SYMBOL: "$expectedSymbol17"
    MONETARY_GROUPING_SEPARATOR_SYMBOL: "$expectedSymbol18"
  }
}
EOTXT;
        $this->assertDumpEquals($expected, $var);
    }
}
