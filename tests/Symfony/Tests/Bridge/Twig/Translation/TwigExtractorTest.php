<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Twig\Translation;

use Symfony\Bridge\Twig\Node\TransNode;
use Symfony\Bridge\Twig\Translation\TwigExtractor;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Tests\Bridge\Twig\TestCase;

class TwigExtractorTest extends TestCase
{
    public function testFilterExtraction()
    {
        // 1.Arrange
        // a node using trans filter : {{ 'new key' | trans({}, 'domain') }}
        $transNode = new \Twig_Node_Expression_Filter(
            new \Twig_Node_Expression_Constant('first key', 0),
            new \Twig_Node_Expression_Constant('trans', 0),
            new \Twig_Node(array(
                1 => new \Twig_Node_Expression_Constant('domain', 0)
            )), array(), 0);
        // a trans block : {% trans from 'domain' %}second key{% endtrans %}
        $transBlock = new TransNode(
            new \Twig_Node(array(), array('data' => 'second key')),
            new \Twig_Node(array(), array('value' => 'domain'))
        );
        // mock the twig environment
        $twig = $this->getMock('Twig_Environment');
        $twig->expects($this->once())
             ->method('tokenize')
             ->will($this->returnValue(new \Twig_TokenStream(array())))
        ;
        $twig->expects($this->once())
             ->method('parse')
             ->will($this->returnValue(
                 new \Twig_Node(array(
                     new \Twig_Node_Text('stub text', 0),
                     new \Twig_Node_Print($transNode,0),
                     $transBlock,
                 ))
             ))
        ;
        // prepare extractor and catalogue
        $extractor = new TwigExtractor($twig);
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');
        
        // 2.Act
        $extractor->extract(__DIR__.'/../Fixtures/Resources/views/', $catalogue);
        
        // 3.Assert
        $this->assertTrue($catalogue->has('first key', 'domain'), '->extract() should find at leat "first key" message in the domain "domain"');
        $this->assertTrue($catalogue->has('second key', 'domain'), '->extract() should find at leat "second key" message in the domain "domain"');
        $this->assertEquals(2, count($catalogue->all('domain')), '->extract() should find 2 translations in the domain "domain"');
        $this->assertEquals('prefixfirst key', $catalogue->get('first key', 'domain'), '->extract() should apply "prefix" as prefix');
    }
}
