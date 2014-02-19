<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Context;

use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Node\GenericNode;

/**
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    const ROOT = '__ROOT__';

    const TRANSLATION_DOMAIN = '__TRANSLATION_DOMAIN__';

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $validator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $groupManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $translator;

    /**
     * @var ExecutionContext
     */
    private $context;

    protected function setUp()
    {
        $this->validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $this->groupManager = $this->getMock('Symfony\Component\Validator\Group\GroupManagerInterface');
        $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $this->context = new ExecutionContext(
            self::ROOT,
            $this->validator,
            $this->groupManager,
            $this->translator,
            self::TRANSLATION_DOMAIN
        );
    }

    public function testPushAndPop()
    {
        $metadata = $this->getMock('Symfony\Component\Validator\Mapping\MetadataInterface');
        $node = new GenericNode('value', $metadata, '', array(), array());

        $this->context->pushNode($node);

        $this->assertSame('value', $this->context->getValue());
        // the other methods are covered in AbstractValidatorTest

        $this->assertSame($node, $this->context->popNode());

        $this->assertNull($this->context->getValue());
    }

    public function testPushTwiceAndPop()
    {
        $metadata1 = $this->getMock('Symfony\Component\Validator\Mapping\MetadataInterface');
        $node1 = new GenericNode('value', $metadata1, '', array(), array());
        $metadata2 = $this->getMock('Symfony\Component\Validator\Mapping\MetadataInterface');
        $node2 = new GenericNode('other value', $metadata2, '', array(), array());

        $this->context->pushNode($node1);
        $this->context->pushNode($node2);

        $this->assertSame($node2, $this->context->popNode());

        $this->assertSame('value', $this->context->getValue());
    }

    public function testPopWithoutPush()
    {
        $this->assertNull($this->context->popNode());
    }

    public function testGetGroup()
    {
        $this->groupManager->expects($this->once())
            ->method('getCurrentGroup')
            ->will($this->returnValue('Current Group'));

        $this->assertSame('Current Group', $this->context->getGroup());
    }
}
