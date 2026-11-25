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
use Symfony\Component\HtmlRenderer\Form\ControlState;

class ControlStateTest extends TestCase
{
    public function testLeafNodesHaveNoDescendantRadioGroups(): void
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input type="radio" required>');
        foreach ([$document->createTextNode('text'), $document->createComment('comment'), $document->doctype] as $node) {
            self::assertCount(0, ControlState::radioValidity($node));
        }
        self::assertFalse(ControlState::radioValidity($document)[$document->querySelector('input')]);
        $fragment = $document->createDocumentFragment();
        $radio = $document->createElement('input');
        $radio->setAttribute('type', 'radio');
        $radio->setAttribute('required', '');
        $fragment->append($radio);
        self::assertFalse(ControlState::radioValidity($fragment)[$radio]);
    }
}
