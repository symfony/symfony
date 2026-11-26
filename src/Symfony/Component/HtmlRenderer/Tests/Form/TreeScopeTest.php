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
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Form\ControlState;
use Symfony\Component\HtmlRenderer\Form\Datalist;
use Symfony\Component\HtmlRenderer\Html\TreeScope;

class TreeScopeTest extends TestCase
{
    public function testDetachedLeafRootsDoNotOfferElementLookup()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p id="outside">Outside</p>');
        foreach ([$document->createTextNode('text'), $document->createComment('comment')] as $node) {
            self::assertSame($node, TreeScope::root($node));
            self::assertNull(TreeScope::getElementById($node, 'outside'));
        }
    }

    public function testExplicitAssociationsStayWithinTheNativeRoot()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><form id="shared"></form><form id="outer"></form><datalist id="choices"><option value="outside"></datalist>');
        $fragment = $document->createDocumentFragment();
        $fragment->append($form = $document->createElement('form'));
        $form->id = 'shared';
        $fragment->append($input = $document->createElement('input'));
        $input->setAttribute('form', 'shared');
        $this->assertSame($form, ControlState::formOwner($input));
        $input->setAttribute('form', 'outer');
        $this->assertNull(ControlState::formOwner($input));
        $input->setAttribute('list', 'choices');
        $this->assertNull(Datalist::completion($input, 'out'));
        $fragment->append($list = $document->createElement('datalist'));
        $list->id = 'choices';
        $list->append($option = $document->createElement('option'));
        $option->setAttribute('value', 'outward');
        $this->assertSame('ward', Datalist::completion($input, 'out'));
        $this->assertSame($fragment, TreeScope::root($input));
    }

    public function testLiteralIdsAndDuplicateObstructions()
    {
        $document = \Dom\HTMLDocument::createEmpty();
        $fragment = $document->createDocumentFragment();
        $fragment->append($obstruction = $document->createElement('span'), $form = $document->createElement('form'), $input = $document->createElement('input'));
        $id = 'f]"\\:😀';
        $obstruction->id = $form->id = $id;
        $input->setAttribute('form', $id);
        $this->assertSame($obstruction, TreeScope::getElementById($input, $id));
        $this->assertNull(ControlState::formOwner($input));
        $obstruction->remove();
        $this->assertSame($form, ControlState::formOwner($input));
        $form->append($input);
        $input->removeAttribute('form');
        $this->assertSame($form, ControlState::formOwner($input));
        $this->assertSame($form, TreeScope::root($input)->firstElementChild);
    }

    public function testProjectedValidityUsesNativeAssociationsAndVisualState()
    {
        $source = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><form id="owner"><input type="radio" name="choice" checked></form>');
        $fragment = $source->createDocumentFragment();
        $fragment->append($form = $source->createElement('form'));
        $form->id = 'owner';
        $form->append($radio = $source->createElement('input'));
        $radio->setAttribute('type', 'radio');
        $radio->setAttribute('name', 'choice');
        $radio->setAttribute('required', '');
        $visual = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>input:invalid{color:red}form:invalid{background-color:blue}</style><form id="owner"><input type="radio" name="choice" checked></form><form id="inner"><input type="radio" name="choice" required></form>');
        $outerForm = $visual->getElementById('owner');
        $innerForm = $visual->getElementById('inner');
        $innerRadio = $innerForm->querySelector('input');
        $identities = new \SplObjectStorage();
        $identities[$outerForm] = $source->getElementById('owner');
        $identities[$outerForm->querySelector('input')] = $source->querySelector('input');
        $identities[$innerForm] = $form;
        $identities[$innerRadio] = $radio;
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($visual, 400, 200, [], null, 0, null, null, $identities);
        $this->assertSame('red', $styles[$innerRadio]['color']);
        $this->assertSame('blue', $styles[$innerForm]['background-color']);
        $this->assertArrayNotHasKey('background-color', $styles[$outerForm]);
        $styles = $resolver->resolve($visual, 400, 200, [[$innerRadio, ['checked' => true]]], null, 0, null, null, $identities);
        $this->assertArrayNotHasKey('background-color', $styles[$innerForm]);
    }

    public function testRequiredRadiosDoNotBorrowCheckedStateAcrossRoots()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input type="radio" name="choice" checked>');
        $radios = [$document->querySelector('input')];
        foreach ([true, false] as $checked) {
            $fragment = $document->createDocumentFragment();
            $fragment->append($radio = $document->createElement('input'));
            $radio->setAttribute('type', 'radio');
            $radio->setAttribute('name', 'choice');
            $radio->setAttribute('required', '');
            if ($checked) {
                $radio->setAttribute('checked', '');
            }
            $radios[] = $radio;
        }
        $validity = ControlState::radioValidity($radios);
        $this->assertTrue($validity[$radios[0]]);
        $this->assertTrue($validity[$radios[1]]);
        $this->assertFalse($validity[$radios[2]]);
    }
}
