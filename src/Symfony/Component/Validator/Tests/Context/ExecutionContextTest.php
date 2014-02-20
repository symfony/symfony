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
        if (version_compare(PHP_VERSION, '5.3.9', '<')) {
            $this->markTestSkipped('Not supported prior to PHP 5.3.9');
        }

        $this->validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $this->groupManager = $this->getMock('Symfony\Component\Validator\Group\GroupManagerInterface');
        $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $this->context = new ExecutionContext(
            $this->validator, self::ROOT, $this->groupManager, $this->translator, self::TRANSLATION_DOMAIN
        );
    }

    public function testGetGroup()
    {
        $this->groupManager->expects($this->once())
            ->method('getCurrentGroup')
            ->will($this->returnValue('Current Group'));

        $this->assertSame('Current Group', $this->context->getGroup());
    }
}
