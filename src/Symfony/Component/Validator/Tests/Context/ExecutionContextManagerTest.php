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

use Symfony\Component\Validator\Context\ExecutionContextManager;

/**
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ExecutionContextManagerTest extends \PHPUnit_Framework_TestCase
{
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
     * @var ExecutionContextManager
     */
    private $contextManager;

    protected function setUp()
    {
        $this->validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $this->groupManager = $this->getMock('Symfony\Component\Validator\Group\GroupManagerInterface');
        $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $this->contextManager = new ExecutionContextManager(
            $this->groupManager,
            $this->translator,
            self::TRANSLATION_DOMAIN
        );
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     */
    public function testInitializeMustBeCalledBeforeStartContext()
    {
        $this->contextManager->startContext('root');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     */
    public function testCannotStopContextIfNoneWasStarted()
    {
        $this->contextManager->stopContext();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     */
    public function testCannotEnterNodeWithoutActiveContext()
    {
        $node = $this->getMockBuilder('Symfony\Component\Validator\Node\Node')
            ->disableOriginalConstructor()
            ->getMock();

        $this->contextManager->enterNode($node);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     */
    public function testCannotLeaveNodeWithoutActiveContext()
    {
        $node = $this->getMockBuilder('Symfony\Component\Validator\Node\Node')
            ->disableOriginalConstructor()
            ->getMock();

        $this->contextManager->leaveNode($node);
    }
}
