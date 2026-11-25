<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Form;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Form\ConstraintValidator;
use Symfony\Component\HtmlRenderer\Form\ControlState;

final class ConstraintValidatorTest extends TestCase
{
    public function testDisabledFieldsetExemptsOnlyItsFirstLegend()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><fieldset disabled><legend><input id="allowed"></legend><input id="disabled"><legend><input id="also-disabled"></legend></fieldset>');
        self::assertFalse(ControlState::isDisabled($document->getElementById('allowed')));
        self::assertTrue(ControlState::isDisabled($document->getElementById('disabled')));
        self::assertTrue(ControlState::isDisabled($document->getElementById('also-disabled')));
    }

    public function testTextConstraintsUseDocumentAttributes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input type="email" required multiple>');
        $element = $document->querySelector('input');
        self::assertSame([false, null], ConstraintValidator::text($element, 'email', ''));
        self::assertSame([false, null], ConstraintValidator::text($element, 'email', 'invalid'));
        self::assertSame([true, null], ConstraintValidator::text($element, 'email', 'a@example.org,b@example.org'));
    }

    public function testDateAndNumberRangesPreserveValidationRules()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input min="2024-01-01" max="2024-12-31">');
        $element = $document->querySelector('input');
        self::assertSame([true, true], ConstraintValidator::text($element, 'date', '2024-02-29'));
        self::assertSame([false, null], ConstraintValidator::text($element, 'date', '2024-02-30'));
        self::assertSame([false, false], ConstraintValidator::text($element, 'date', '2025-01-01'));
    }
}
