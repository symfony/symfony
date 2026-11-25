<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class CustomPropertyAnimationTest extends TestCase
{
    public function testAnimatedCustomPropertiesResolveSameElementAliasesAndShorthandDependencies()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:green}@property --space{syntax:"<length>";inherits:false;initial-value:0px}@keyframes change{from{--tone:red;--space:0px}to{--tone:blue;--space:20px}}p{--alias:var(--tone);color:var(--alias);margin:var(--space);animation:change 1s linear both}p::before{content:"before";color:var(--tone)}</style><p><span>Child</span></p>');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $first = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $target = $document->querySelector('p');
        self::assertSame('red', $first[$target]['color']);
        $half = $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('#800080', $half[$target]['--alias']);
        self::assertSame('#800080', $half[$target]['color']);
        self::assertSame('10px', $half[$target]['margin-left']);
        self::assertSame('#800080', $half[$document->querySelector('span')]['color']);
        self::assertSame('#800080', $resolver->getPseudoStyle($target, 'before')['color']);
        self::assertArrayHasKey('color', $resolver->getCascadeRecord($target)['animated']);
        self::assertArrayHasKey('margin-left', $resolver->getCascadeRecord($target)['animated']);
    }

    public function testDirectAnimationAndImportantDeclarationsKeepTheirActualPrecedence()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:green}@keyframes change{from{--tone:red;color:white}to{--tone:blue;color:white}}p{color:var(--tone);animation:change 1s linear both}.important{color:var(--tone)!important}</style><p>Direct</p><p class="important">Important</p>');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $first = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('white', $first[$document->querySelector('p')]['color']);
        $half = $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('white', $half[$document->querySelector('p')]['color']);
        $important = $document->querySelector('.important');
        self::assertSame('#800080', $half[$important]['color']);
        self::assertTrue($resolver->getCascadeRecord($important)['declarations']['color']['important']);
    }

    public function testSelectedFallbacksAndLonghandWinnersRemainAuthoritative()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:green}@keyframes change{from{--tone:red;--space:0px}to{--tone:blue;--space:20px}}p{animation:change 1s linear both;margin:var(--space);margin-left:5px}.used{color:var(--missing,var(--tone))}.unused{--fixed:green;color:var(--fixed,var(--tone))}.logical{direction:rtl;margin:0;margin-inline:var(--space);margin-right:5px}</style><p class="used">Used</p><p class="unused">Unused</p><p class="logical">Logical</p>');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $first = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('red', $first[$document->querySelector('.used')]['color']);
        $half = $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('#800080', $half[$document->querySelector('.used')]['color']);
        self::assertSame('green', $half[$document->querySelector('.unused')]['color']);
        self::assertSame('10px', $half[$document->querySelector('.used')]['margin-top']);
        self::assertSame('5px', $half[$document->querySelector('.used')]['margin-left']);
        self::assertArrayNotHasKey('color', $resolver->getCascadeRecord($document->querySelector('.unused'))['animated']);
        self::assertSame('10px', $half[$document->querySelector('.logical')]['margin-left']);
        self::assertSame('5px', $half[$document->querySelector('.logical')]['margin-right']);
    }

    public function testRegisteredColorEndpointsUseTheElementSchemeAndRejectInvalidSyntax()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:green}@keyframes dark{from{--tone:light-dark(red,blue)}to{--tone:light-dark(white,black)}}@keyframes invalid{from{--tone:12px}to{--tone:blue}}p{color:var(--tone);animation:dark 1s linear both;color-scheme:dark}.invalid{animation-name:invalid}</style><p>Dark</p><p class="invalid">Invalid endpoint</p>');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $first = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('#0000ff', $first[$document->querySelector('p')]['color']);
        self::assertSame('green', $first[$document->querySelector('.invalid')]['color']);
        $half = $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('#000080', $half[$document->querySelector('p')]['color']);
        self::assertSame('#004080', $half[$document->querySelector('.invalid')]['color']);
    }

    public function testTransitionOfARegisteredCustomPropertyUpdatesItsDependentColor()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:green}p{--tone:red;color:var(--tone);transition:--tone 1s linear}</style><p>Transition</p>');
        $element = $document->querySelector('p');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $first = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('red', $first[$element]['color']);
        $element->setAttribute('style', '--tone:blue');
        $resolver->resolve($document, 400, 200, [], $timeline, 1);
        $half = $resolver->resolve($document, 400, 200, [], $timeline, 1.5);
        self::assertSame('#800080', $half[$element]['color']);
        self::assertSame('blue', $resolver->resolve($document, 400, 200, [], $timeline, 2)[$element]['color']);
    }

    public function testAnimatedValuesCannotBypassARegisteredAliasSyntax()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --alias{syntax:"<length>";inherits:false;initial-value:5px}@keyframes change{from{--n:0px}to{--n:red}}p{--n:0px;--alias:var(--n);width:var(--alias);animation:change 1s linear both}</style><p>Typed alias</p>');
        $element = $document->querySelector('p');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $first = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('0px', $first[$element]['width']);
        $later = $resolver->resolve($document, 400, 200, [], $timeline, .75);
        self::assertSame('5px', $later[$element]['--alias']);
        self::assertSame('5px', $later[$element]['width']);
        self::assertFalse($resolver->getCascadeRecord($element)['declarations']['--alias']['valid']);
    }
}
