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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Flow\FormFlowCursor;
use Symfony\Component\Form\Flow\StepFlowBuilder;
use Symfony\Component\Form\Flow\StepFlowConfigInterface;

class FormFlowCursorTest extends TestCase
{
    private const array STEPS = ['personal', 'professional', 'account'];

    /**
     * @param list<string> $names
     *
     * @return array<string, StepFlowConfigInterface>
     */
    private static function createSteps(array $names = self::STEPS): array
    {
        $configs = [];
        foreach ($names as $name) {
            $configs[$name] = new StepFlowBuilder($name)->getStepConfig();
        }

        return $configs;
    }

    private static function createNestedSteps(): array
    {
        $personal = new StepFlowBuilder('personal')
            ->addStep('name')
            ->addStep('contact');

        return [
            'intro' => new StepFlowBuilder('intro')->getStepConfig(),
            'personal' => $personal->getStepConfig(),
            'summary' => new StepFlowBuilder('summary')->getStepConfig(),
        ];
    }

    public function testConstructorWithValidStep()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'personal');

        $this->assertSame(self::STEPS, $cursor->getSteps());
        $this->assertSame('personal', $cursor->getCurrentStep());
    }

    public function testConstructorWithInvalidStep()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Step "invalid" does not exist. Available steps are: "personal", "professional", "account".');

        new FormFlowCursor(self::createSteps(), 'invalid');
    }

    public function testConstructorWithDeprecatedStringList()
    {
        $cursor = new FormFlowCursor(self::STEPS, 'personal');

        $this->assertSame(self::STEPS, $cursor->getSteps());
    }

    public function testGetSteps()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'personal');

        $this->assertSame(self::STEPS, $cursor->getSteps());
    }

    public function testGetTotalSteps()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'personal');

        $this->assertSame(3, $cursor->getTotalSteps());
    }

    public function testGetStepIndex()
    {
        $steps = self::createSteps();

        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertSame(0, $cursor->getStepIndex());

        $cursor = new FormFlowCursor($steps, 'professional');
        $this->assertSame(1, $cursor->getStepIndex());

        $cursor = new FormFlowCursor($steps, 'account');
        $this->assertSame(2, $cursor->getStepIndex());
    }

    public function testGetFirstStep()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'professional');

        $this->assertSame('personal', $cursor->getFirstStep());
    }

    public function testGetPrevStep()
    {
        $steps = self::createSteps();

        // First step has no previous step
        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertNull($cursor->getPreviousStep());

        // Middle step has previous step
        $cursor = new FormFlowCursor($steps, 'professional');
        $this->assertSame('personal', $cursor->getPreviousStep());

        // Last step has previous step
        $cursor = new FormFlowCursor($steps, 'account');
        $this->assertSame('professional', $cursor->getPreviousStep());
    }

    public function testGetCurrentStep()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'professional');

        $this->assertSame('professional', $cursor->getCurrentStep());
    }

    public function testWithCurrentStep()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'personal');

        $newCursor = $cursor->withCurrentStep('professional');

        // Original cursor should remain unchanged
        $this->assertSame('personal', $cursor->getCurrentStep());

        // New cursor should have the new current step
        $this->assertSame('professional', $newCursor->getCurrentStep());

        // Both cursors should have the same steps
        $this->assertSame(self::STEPS, $cursor->getSteps());
        $this->assertSame(self::STEPS, $newCursor->getSteps());
    }

    public function testGetNextStep()
    {
        $steps = self::createSteps();

        // First step has next step
        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertSame('professional', $cursor->getNextStep());

        // Middle step has next step
        $cursor = new FormFlowCursor($steps, 'professional');
        $this->assertSame('account', $cursor->getNextStep());

        // Last step has no next step
        $cursor = new FormFlowCursor($steps, 'account');
        $this->assertNull($cursor->getNextStep());
    }

    public function testGetLastStep()
    {
        $cursor = new FormFlowCursor(self::createSteps(), 'personal');

        $this->assertSame('account', $cursor->getLastStep());
    }

    public function testIsFirstStep()
    {
        $steps = self::createSteps();

        // First step
        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertTrue($cursor->isFirstStep());

        // Not first step
        $cursor = new FormFlowCursor($steps, 'professional');
        $this->assertFalse($cursor->isFirstStep());
    }

    public function testIsLastStep()
    {
        $steps = self::createSteps();

        // Not last step
        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertFalse($cursor->isLastStep());

        // Last step
        $cursor = new FormFlowCursor($steps, 'account');
        $this->assertTrue($cursor->isLastStep());
    }

    public function testCanMovePreviousStep()
    {
        $steps = self::createSteps();

        // First position cannot move a previous step
        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertFalse($cursor->canMoveBack());

        // Middle position can move a previous step
        $cursor = new FormFlowCursor($steps, 'professional');
        $this->assertTrue($cursor->canMoveBack());

        // Last step can move a previous step
        $cursor = new FormFlowCursor($steps, 'account');
        $this->assertTrue($cursor->canMoveBack());
    }

    public function testCanMoveNext()
    {
        $steps = self::createSteps();

        // First position can move next step
        $cursor = new FormFlowCursor($steps, 'personal');
        $this->assertTrue($cursor->canMoveNext());

        // Middle position can move next step
        $cursor = new FormFlowCursor($steps, 'professional');
        $this->assertTrue($cursor->canMoveNext());

        // Last position cannot move the next step
        $cursor = new FormFlowCursor($steps, 'account');
        $this->assertFalse($cursor->canMoveNext());
    }

    public function testCursorWithSingleStep()
    {
        $steps = ['single'];
        $cursor = new FormFlowCursor(self::createSteps($steps), 'single');

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

    public function testNestedStepsAreFlattened()
    {
        $cursor = new FormFlowCursor(self::createNestedSteps(), 'intro');

        $this->assertSame(['intro', 'personal', 'name', 'contact', 'summary'], $cursor->getSteps());
        $this->assertSame(5, $cursor->getTotalSteps());
    }

    public function testNavigationWithNestedSteps()
    {
        $nestedSteps = self::createNestedSteps();

        // Forward: intro → personal → name → contact → summary
        // Backward: summary → contact → name → personal → intro

        $cursor = new FormFlowCursor($nestedSteps, 'intro');
        $this->assertTrue($cursor->isFirstStep());
        $this->assertNull($cursor->getPreviousStep());
        $this->assertSame('personal', $cursor->getNextStep());

        $cursor = new FormFlowCursor($nestedSteps, 'personal');
        $this->assertSame('intro', $cursor->getPreviousStep());
        $this->assertSame('name', $cursor->getNextStep());
        $this->assertSame(1, $cursor->getStepIndex());

        $cursor = new FormFlowCursor($nestedSteps, 'name');
        $this->assertSame('personal', $cursor->getPreviousStep());
        $this->assertSame('contact', $cursor->getNextStep());
        $this->assertSame(2, $cursor->getStepIndex());

        $cursor = new FormFlowCursor($nestedSteps, 'contact');
        $this->assertSame('name', $cursor->getPreviousStep());
        $this->assertSame('summary', $cursor->getNextStep());
        $this->assertSame(3, $cursor->getStepIndex());

        $cursor = new FormFlowCursor($nestedSteps, 'summary');
        $this->assertTrue($cursor->isLastStep());
        $this->assertSame('contact', $cursor->getPreviousStep());
        $this->assertNull($cursor->getNextStep());
        $this->assertSame(4, $cursor->getStepIndex());
    }

    public function testNestedStepsWithMultipleForests()
    {
        $personal = new StepFlowBuilder('personal')
            ->addStep('name')
            ->addStep('email');
        $work = new StepFlowBuilder('work')
            ->addStep('company')
            ->addStep('role');

        $cursor = new FormFlowCursor([
            'intro' => new StepFlowBuilder('intro')->getStepConfig(),
            'personal' => $personal->getStepConfig(),
            'middle' => new StepFlowBuilder('middle')->getStepConfig(),
            'work' => $work->getStepConfig(),
            'summary' => new StepFlowBuilder('summary')->getStepConfig(),
        ], 'intro');

        $this->assertSame(['intro', 'personal', 'name', 'email', 'middle', 'work', 'company', 'role', 'summary'], $cursor->getSteps());
        $this->assertSame(9, $cursor->getTotalSteps());
        $this->assertSame('intro', $cursor->getFirstStep());
        $this->assertSame('summary', $cursor->getLastStep());
    }

    public function testInvalidStepInNestedStructure()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Step "invalid" does not exist. Available steps are: "intro", "personal", "name", "contact", "summary".');

        new FormFlowCursor(self::createNestedSteps(), 'invalid');
    }

    public function testStringKeyNestedStepsWithDepth()
    {
        $position = new StepFlowBuilder('position')
            ->addStep('title')
            ->addStep('department');
        $work = new StepFlowBuilder('work')
            ->addStep('company')
            ->addStep($position);
        $personal = new StepFlowBuilder('personal')
            ->addStep('name')
            ->addStep('contact');

        $cursor = new FormFlowCursor([
            'intro' => new StepFlowBuilder('intro')->getStepConfig(),
            'personal' => $personal->getStepConfig(),
            'work' => $work->getStepConfig(),
            'summary' => new StepFlowBuilder('summary')->getStepConfig(),
        ], 'intro');

        $this->assertSame([
            'intro',
            'personal', 'name', 'contact',
            'work', 'company', 'position', 'title', 'department',
            'summary',
        ], $cursor->getSteps());
    }

    public function testGetCurrentNode()
    {
        $cursor = new FormFlowCursor(self::createNestedSteps(), 'name');

        $this->assertSame('name', $cursor->getCurrentStepNode()->getName());
    }

    public function testGetNode()
    {
        $cursor = new FormFlowCursor(self::createNestedSteps(), 'intro');

        $this->assertSame('contact', $cursor->getStepNode('contact')->getName());
    }

    public function testGetNodeThrowsForInvalidName()
    {
        $cursor = new FormFlowCursor(self::createNestedSteps(), 'intro');

        $this->expectException(InvalidArgumentException::class);
        $cursor->getStepNode('invalid');
    }

    public function testGetRoots()
    {
        $cursor = new FormFlowCursor(self::createNestedSteps(), 'intro');

        $this->assertCount(3, $cursor->getRootStepNodes());
        $this->assertSame('intro', $cursor->getRootStepNodes()[0]->getName());
    }

    public function testGetParentStep()
    {
        $steps = self::createNestedSteps();

        $this->assertNull(new FormFlowCursor($steps, 'intro')->getParentStep());
        $this->assertNull(new FormFlowCursor($steps, 'personal')->getParentStep());
        $this->assertSame('personal', new FormFlowCursor($steps, 'name')->getParentStep());
        $this->assertSame('personal', new FormFlowCursor($steps, 'contact')->getParentStep());
    }

    public function testGetChildSteps()
    {
        $steps = self::createNestedSteps();

        $this->assertSame([], new FormFlowCursor($steps, 'intro')->getChildSteps());
        $this->assertSame(['name', 'contact'], new FormFlowCursor($steps, 'personal')->getChildSteps());
        $this->assertSame([], new FormFlowCursor($steps, 'name')->getChildSteps());
    }

    public function testWithCurrentStepSharesForest()
    {
        $cursor = new FormFlowCursor(self::createNestedSteps(), 'intro');
        $newCursor = $cursor->withCurrentStep('summary');

        $this->assertSame($cursor->getRootStepNodes(), $newCursor->getRootStepNodes());
        $this->assertSame($cursor->getStepNode('intro'), $newCursor->getStepNode('intro'));
    }

    public function testIsFirstStepWithGroupParent()
    {
        $steps = self::createGroupSteps();

        $this->assertTrue((new FormFlowCursor($steps, 'a1'))->isFirstStep());
        $this->assertFalse((new FormFlowCursor($steps, 'a2'))->isFirstStep());
        $this->assertFalse((new FormFlowCursor($steps, 'b'))->isFirstStep());
        $this->assertFalse((new FormFlowCursor($steps, 'c1'))->isFirstStep());
    }

    public function testIsLastStepWithGroupParent()
    {
        $steps = self::createGroupSteps();

        $this->assertTrue((new FormFlowCursor($steps, 'c1'))->isLastStep());
        $this->assertFalse((new FormFlowCursor($steps, 'b'))->isLastStep());
        $this->assertFalse((new FormFlowCursor($steps, 'a2'))->isLastStep());
        $this->assertFalse((new FormFlowCursor($steps, 'a1'))->isLastStep());
    }

    public function testCanMoveBackWithGroupParent()
    {
        $steps = self::createGroupSteps();

        $this->assertFalse((new FormFlowCursor($steps, 'a1'))->canMoveBack());
        $this->assertTrue((new FormFlowCursor($steps, 'a2'))->canMoveBack());
        $this->assertTrue((new FormFlowCursor($steps, 'b'))->canMoveBack());
        $this->assertTrue((new FormFlowCursor($steps, 'c1'))->canMoveBack());
    }

    public function testCanMoveNextWithGroupParent()
    {
        $steps = self::createGroupSteps();

        $this->assertFalse((new FormFlowCursor($steps, 'c1'))->canMoveNext());
        $this->assertTrue((new FormFlowCursor($steps, 'b'))->canMoveNext());
        $this->assertTrue((new FormFlowCursor($steps, 'a2'))->canMoveNext());
        $this->assertTrue((new FormFlowCursor($steps, 'a1'))->canMoveNext());
    }

    public function testGetFirstStepWithGroupRoot()
    {
        $steps = self::createGroupSteps();

        $this->assertSame('a1', (new FormFlowCursor($steps, 'b'))->getFirstStep());
    }

    public function testGetLastStepWithGroupTail()
    {
        $steps = self::createGroupSteps();

        $this->assertSame('c1', (new FormFlowCursor($steps, 'b'))->getLastStep());
    }

    /**
     * Creates steps: a(group) -> [a1, a2], b, c(group) -> [c1].
     *
     * @return array<string, StepFlowConfigInterface>
     */
    private static function createGroupSteps(): array
    {
        $a = (new StepFlowBuilder('a'))
            ->setGroup(true)
            ->addStep('a1')
            ->addStep('a2');
        $c = (new StepFlowBuilder('c'))
            ->setGroup(true)
            ->addStep('c1');

        return [
            'a' => $a->getStepConfig(),
            'b' => (new StepFlowBuilder('b'))->getStepConfig(),
            'c' => $c->getStepConfig(),
        ];
    }
}
