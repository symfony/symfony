<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Flow;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Flow\FormFlowCursor;

class FormFlowCursorTest extends TestCase
{
    private static array $steps = ['personal', 'professional', 'account'];

    public function testConstructorWithValidStep()
    {
        $cursor = new FormFlowCursor(self::$steps, 'personal');

        $this->assertSame(self::$steps, $cursor->getSteps());
        $this->assertSame('personal', $cursor->getCurrentStep());
    }

    public function testConstructorWithInvalidStep()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Step "invalid" does not exist. Available steps are: "personal", "professional", "account".');

        new FormFlowCursor(self::$steps, 'invalid');
    }

    public function testGetSteps()
    {
        $cursor = new FormFlowCursor(self::$steps, 'personal');

        $this->assertSame(self::$steps, $cursor->getSteps());
    }

    public function testGetTotalSteps()
    {
        $cursor = new FormFlowCursor(self::$steps, 'personal');

        $this->assertSame(3, $cursor->getTotalSteps());
    }

    public function testGetStepIndex()
    {
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertSame(0, $cursor->getStepIndex());

        $cursor = new FormFlowCursor(self::$steps, 'professional');
        $this->assertSame(1, $cursor->getStepIndex());

        $cursor = new FormFlowCursor(self::$steps, 'account');
        $this->assertSame(2, $cursor->getStepIndex());
    }

    public function testGetFirstStep()
    {
        $cursor = new FormFlowCursor(self::$steps, 'professional');

        $this->assertSame('personal', $cursor->getFirstStep());
    }

    public function testGetPrevStep()
    {
        // First step has no previous step
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertNull($cursor->getPreviousStep());

        // Middle step has previous step
        $cursor = new FormFlowCursor(self::$steps, 'professional');
        $this->assertSame('personal', $cursor->getPreviousStep());

        // Last step has previous step
        $cursor = new FormFlowCursor(self::$steps, 'account');
        $this->assertSame('professional', $cursor->getPreviousStep());
    }

    public function testGetCurrentStep()
    {
        $cursor = new FormFlowCursor(self::$steps, 'professional');

        $this->assertSame('professional', $cursor->getCurrentStep());
    }

    public function testWithCurrentStep()
    {
        $cursor = new FormFlowCursor(self::$steps, 'personal');

        $newCursor = $cursor->withCurrentStep('professional');

        // Original cursor should remain unchanged
        $this->assertSame('personal', $cursor->getCurrentStep());

        // New cursor should have the new current step
        $this->assertSame('professional', $newCursor->getCurrentStep());

        // Both cursors should have the same steps
        $this->assertSame(self::$steps, $cursor->getSteps());
        $this->assertSame(self::$steps, $newCursor->getSteps());
    }

    public function testGetNextStep()
    {
        // First step has next step
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertSame('professional', $cursor->getNextStep());

        // Middle step has next step
        $cursor = new FormFlowCursor(self::$steps, 'professional');
        $this->assertSame('account', $cursor->getNextStep());

        // Last step has no next step
        $cursor = new FormFlowCursor(self::$steps, 'account');
        $this->assertNull($cursor->getNextStep());
    }

    public function testGetLastStep()
    {
        $cursor = new FormFlowCursor(self::$steps, 'personal');

        $this->assertSame('account', $cursor->getLastStep());
    }

    public function testIsFirstStep()
    {
        // First step
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertTrue($cursor->isFirstStep());

        // Not first step
        $cursor = new FormFlowCursor(self::$steps, 'professional');
        $this->assertFalse($cursor->isFirstStep());
    }

    public function testIsLastStep()
    {
        // Not last step
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertFalse($cursor->isLastStep());

        // Last step
        $cursor = new FormFlowCursor(self::$steps, 'account');
        $this->assertTrue($cursor->isLastStep());
    }

    public function testCanMovePreviousStep()
    {
        // First position cannot move a previous step
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertFalse($cursor->canMoveBack());

        // Middle position can move a previous step
        $cursor = new FormFlowCursor(self::$steps, 'professional');
        $this->assertTrue($cursor->canMoveBack());

        // Last step can move a previous step
        $cursor = new FormFlowCursor(self::$steps, 'account');
        $this->assertTrue($cursor->canMoveBack());
    }

    public function testCanMoveNext()
    {
        // First position can move next step
        $cursor = new FormFlowCursor(self::$steps, 'personal');
        $this->assertTrue($cursor->canMoveNext());

        // Middle position can move next step
        $cursor = new FormFlowCursor(self::$steps, 'professional');
        $this->assertTrue($cursor->canMoveNext());

        // Last position cannot move the next step
        $cursor = new FormFlowCursor(self::$steps, 'account');
        $this->assertFalse($cursor->canMoveNext());
    }

    public function testCursorWithSingleStep()
    {
        $steps = ['single'];
        $cursor = new FormFlowCursor($steps, 'single');

        $this->assertSame('single', $cursor->getCurrentStep());
        $this->assertTrue($cursor->isFirstStep());
        $this->assertTrue($cursor->isLastStep());
        $this->assertSame('single', $cursor->getFirstStep());
        $this->assertNull($cursor->getPreviousStep());
        $this->assertNull($cursor->getNextStep());
        $this->assertSame('single', $cursor->getLastStep());
        $this->assertSame(['single'], $cursor->getSteps());
        $this->assertSame(0, $cursor->getStepIndex());
        $this->assertSame(1, $cursor->getTotalSteps());
        $this->assertFalse($cursor->canMoveBack());
        $this->assertFalse($cursor->canMoveNext());
    }
}
