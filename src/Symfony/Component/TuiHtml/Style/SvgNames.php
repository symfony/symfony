<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/** @internal */
final class SvgNames
{
    public static function normalize(\Dom\Element $svg): void
    {
        static $needed;
        $needed ??= null === \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 1 1"></svg>', \LIBXML_NOERROR)->querySelector('svg')->getAttribute('viewBox');
        if (!$needed || !$svg->ownerDocument instanceof \Dom\HTMLDocument) {
            return;
        }
        // The SVG names the HTML parser adjusts, which older versions left in lower case.
        static $tags = ['altglyph' => 'altGlyph', 'altglyphdef' => 'altGlyphDef', 'altglyphitem' => 'altGlyphItem', 'animatecolor' => 'animateColor', 'animatemotion' => 'animateMotion', 'animatetransform' => 'animateTransform', 'clippath' => 'clipPath', 'feblend' => 'feBlend', 'fecolormatrix' => 'feColorMatrix', 'fecomponenttransfer' => 'feComponentTransfer', 'fecomposite' => 'feComposite', 'feconvolvematrix' => 'feConvolveMatrix', 'fediffuselighting' => 'feDiffuseLighting', 'fedisplacementmap' => 'feDisplacementMap', 'fedistantlight' => 'feDistantLight', 'fedropshadow' => 'feDropShadow', 'feflood' => 'feFlood', 'fefunca' => 'feFuncA', 'fefuncb' => 'feFuncB', 'fefuncg' => 'feFuncG', 'fefuncr' => 'feFuncR', 'fegaussianblur' => 'feGaussianBlur', 'feimage' => 'feImage', 'femerge' => 'feMerge', 'femergenode' => 'feMergeNode', 'femorphology' => 'feMorphology', 'feoffset' => 'feOffset', 'fepointlight' => 'fePointLight', 'fespecularlighting' => 'feSpecularLighting', 'fespotlight' => 'feSpotLight', 'fetile' => 'feTile', 'feturbulence' => 'feTurbulence', 'foreignobject' => 'foreignObject', 'glyphref' => 'glyphRef', 'lineargradient' => 'linearGradient', 'radialgradient' => 'radialGradient', 'textpath' => 'textPath'];
        static $attributes = ['viewbox' => 'viewBox', 'preserveaspectratio' => 'preserveAspectRatio', 'gradientunits' => 'gradientUnits', 'gradienttransform' => 'gradientTransform', 'clippathunits' => 'clipPathUnits', 'filterunits' => 'filterUnits', 'primitiveunits' => 'primitiveUnits', 'stddeviation' => 'stdDeviation', 'markerheight' => 'markerHeight', 'markerwidth' => 'markerWidth', 'markerunits' => 'markerUnits', 'maskcontentunits' => 'maskContentUnits', 'maskunits' => 'maskUnits', 'refx' => 'refX', 'refy' => 'refY', 'patternunits' => 'patternUnits', 'patterncontentunits' => 'patternContentUnits', 'patterntransform' => 'patternTransform'];
        foreach ([$svg, ...iterator_to_array($svg->getElementsByTagName('*'), false)] as $element) {
            if ('http://www.w3.org/2000/svg' !== $element->namespaceURI) {
                continue;
            }
            if (isset($tags[$element->localName])) {
                $renamed = $element->ownerDocument->createElementNS($element->namespaceURI, $tags[$element->localName]);
                foreach ($element->attributes as $attribute) {
                    $renamed->setAttributeNS($attribute->namespaceURI, $attribute->name, $attribute->value);
                }
                while (null !== $element->firstChild) {
                    $renamed->appendChild($element->firstChild);
                }
                $element->replaceWith($renamed);
                $element = $renamed;
            }
            foreach (iterator_to_array($element->attributes, false) as $attribute) {
                $name = $attribute->name;
                if (isset($attributes[$name])) {
                    $element->removeAttribute($name);
                    $element->setAttribute($attributes[$name], $attribute->value);
                }
            }
        }
    }
}
