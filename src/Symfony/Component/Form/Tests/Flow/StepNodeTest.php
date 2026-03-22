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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Flow\StepFlowBuilder;
use Symfony\Component\Form\Flow\StepFlowNode;

class StepNodeTest extends TestCase
{
    /**
     * @return list<StepFlowNode>
     */
    private static function createNodes(array $steps): array
    {
        $configs = [];
        foreach (self::buildBuilders($steps) as $name => $builder) {
            $configs[$name] = $builder->getStepConfig();
        }

        return StepFlowNode::fromConfig($configs);
    }

    /**
     * @return array<string, StepFlowBuilder>
     */
    private static function buildBuilders(array $steps): array
    {
        $builders = [];
        foreach ($steps as $key => $value) {
            if (\is_int($key)) {
                $builders[$value] = new StepFlowBuilder($value);
            } else {
                $builder = new StepFlowBuilder($key);
                foreach (self::buildBuilders($value) as $child) {
                    $builder->addStep($child);
                }
                $builders[$key] = $builder;
            }
        }

        return $builders;
    }

    public function testFlatSteps()
    {
        $roots = self::createNodes(['personal', 'professional', 'account']);

        $this->assertCount(3, $roots);
        $this->assertSame('personal', $roots[0]->getName());
        $this->assertSame('professional', $roots[1]->getName());
        $this->assertSame('account', $roots[2]->getName());

        foreach ($roots as $root) {
            $this->assertNull($root->getParent());
            $this->assertEmpty($root->getChildren());
        }
    }

    public function testNestedSteps()
    {
        $roots = self::createNodes([
            'intro',
            'personal' => ['name', 'contact'],
            'summary',
        ]);

        $this->assertCount(3, $roots);

        $this->assertSame('intro', $roots[0]->getName());
        $this->assertEmpty($roots[0]->getChildren());

        $this->assertSame('personal', $roots[1]->getName());
        $this->assertCount(2, $roots[1]->getChildren());
        $this->assertSame('name', $roots[1]->getChildren()[0]->getName());
        $this->assertSame('contact', $roots[1]->getChildren()[1]->getName());

        $this->assertSame($roots[1], $roots[1]->getChildren()[0]->getParent());
        $this->assertSame($roots[1], $roots[1]->getChildren()[1]->getParent());

        $this->assertSame('summary', $roots[2]->getName());
        $this->assertEmpty($roots[2]->getChildren());
    }

    public function testDeepNesting()
    {
        $roots = self::createNodes([
            'work' => [
                'company',
                'position' => ['title', 'department'],
            ],
        ]);

        $this->assertCount(1, $roots);
        $work = $roots[0];
        $this->assertSame('work', $work->getName());
        $this->assertCount(2, $work->getChildren());

        $company = $work->getChildren()[0];
        $this->assertSame('company', $company->getName());
        $this->assertEmpty($company->getChildren());

        $position = $work->getChildren()[1];
        $this->assertSame('position', $position->getName());
        $this->assertCount(2, $position->getChildren());
        $this->assertSame('title', $position->getChildren()[0]->getName());
        $this->assertSame('department', $position->getChildren()[1]->getName());

        $this->assertSame($work, $company->getParent());
        $this->assertSame($work, $position->getParent());
        $this->assertSame($position, $position->getChildren()[0]->getParent());
        $this->assertSame($position, $position->getChildren()[1]->getParent());
    }

    public function testParentLinksAcrossMultipleLevels()
    {
        $roots = self::createNodes([
            'root' => [
                'l1' => [
                    'l2' => ['l3a', 'l3b'],
                ],
            ],
        ]);

        $root = $roots[0];
        $l1 = $root->getChildren()[0];
        $l2 = $l1->getChildren()[0];
        $l3a = $l2->getChildren()[0];
        $l3b = $l2->getChildren()[1];

        $this->assertNull($root->getParent());
        $this->assertSame($root, $l1->getParent());
        $this->assertSame($l1, $l2->getParent());
        $this->assertSame($l2, $l3a->getParent());
        $this->assertSame($l2, $l3b->getParent());

        $this->assertSame('l2', $l3a->getParent()->getName());
        $this->assertSame('l1', $l3a->getParent()->getParent()->getName());
        $this->assertSame('root', $l3a->getParent()->getParent()->getParent()->getName());
        $this->assertNull($l3a->getParent()->getParent()->getParent()->getParent());
    }

    public function testSiblingLinks()
    {
        $roots = self::createNodes(['a', 'b', 'c']);

        $this->assertNull($roots[0]->getPreviousSibling());
        $this->assertSame($roots[1], $roots[0]->getNextSibling());

        $this->assertSame($roots[0], $roots[1]->getPreviousSibling());
        $this->assertSame($roots[2], $roots[1]->getNextSibling());

        $this->assertSame($roots[1], $roots[2]->getPreviousSibling());
        $this->assertNull($roots[2]->getNextSibling());
    }

    public function testSiblingLinksForChildren()
    {
        $roots = self::createNodes([
            'personal' => ['name', 'email', 'phone'],
        ]);
        $children = $roots[0]->getChildren();

        $this->assertNull($children[0]->getPreviousSibling());
        $this->assertSame($children[1], $children[0]->getNextSibling());

        $this->assertSame($children[0], $children[1]->getPreviousSibling());
        $this->assertSame($children[2], $children[1]->getNextSibling());

        $this->assertSame($children[1], $children[2]->getPreviousSibling());
        $this->assertNull($children[2]->getNextSibling());
    }

    public function testNextInTraversalDFSOrder()
    {
        $roots = self::createNodes([
            'intro',
            'personal' => ['name', 'contact'],
            'summary',
        ]);

        $names = [];
        $node = $roots[0];
        while (null !== $node) {
            $names[] = $node->getName();
            $node = $node->getNextInTraversal();
        }

        $this->assertSame(['intro', 'personal', 'name', 'contact', 'summary'], $names);
    }

    public function testPreviousInTraversalOrder()
    {
        $roots = self::createNodes([
            'intro',
            'personal' => ['name', 'contact'],
            'summary',
        ]);

        $last = $roots[\count($roots) - 1];
        $this->assertSame('summary', $last->getName());

        $names = [];
        $node = $last;
        while (null !== $node) {
            $names[] = $node->getName();
            $node = $node->getPreviousInTraversal();
        }

        $this->assertSame(['summary', 'contact', 'name', 'personal', 'intro'], $names);
    }

    public function testNextInTraversalWithDeepNesting()
    {
        $roots = self::createNodes([
            'intro',
            'work' => [
                'company',
                'position' => ['title', 'department'],
            ],
            'summary',
        ]);

        $names = [];
        $node = $roots[0];
        while (null !== $node) {
            $names[] = $node->getName();
            $node = $node->getNextInTraversal();
        }

        $this->assertSame(['intro', 'work', 'company', 'position', 'title', 'department', 'summary'], $names);
    }

    public function testNextInTraversalFromLastNodeReturnsNull()
    {
        $roots = self::createNodes(['a', 'b']);

        $this->assertNull($roots[1]->getNextInTraversal());
    }

    public function testPreviousInTraversalFromFirstNodeReturnsNull()
    {
        $roots = self::createNodes(['a', 'b']);

        $this->assertNull($roots[0]->getPreviousInTraversal());
    }

    public function testIsSkippedWithNullSkip()
    {
        $roots = self::createNodes(['step']);

        $this->assertNull($roots[0]->getSkip());
        $this->assertFalse($roots[0]->isGroupOrSkipped('data'));
    }

    public function testGroupNodeIsSkipped()
    {
        $stepA = new StepFlowBuilder('stepA')
            ->setGroup(true)
            ->addStep('stepA1')
            ->addStep('stepA2');
        $roots = StepFlowNode::fromConfig(['stepA' => $stepA->getStepConfig()]);

        $this->assertTrue($roots[0]->isGroup());
        $this->assertTrue($roots[0]->isGroupOrSkipped(null));

        $this->assertFalse($roots[0]->getChildren()[0]->isGroupOrSkipped(null));
        $this->assertFalse($roots[0]->getChildren()[1]->isGroupOrSkipped(null));
    }

    public function testSkipPropagatesToChildren()
    {
        $stepB = new StepFlowBuilder('stepB')
            ->setSkip(static fn () => true)
            ->addStep('stepB1')
            ->addStep('stepB2');
        $roots = StepFlowNode::fromConfig(['stepB' => $stepB->getStepConfig()]);

        $this->assertTrue($roots[0]->isGroupOrSkipped(null));
        $this->assertTrue($roots[0]->getChildren()[0]->isGroupOrSkipped(null));
        $this->assertTrue($roots[0]->getChildren()[1]->isGroupOrSkipped(null));
    }

    public function testSkipPropagatesAcrossMultipleLevels()
    {
        $stepA = new StepFlowBuilder('stepA')
            ->setSkip(static fn () => true)
            ->addStep(
                new StepFlowBuilder('stepA1')
                    ->addStep('stepA11')
            );
        $roots = StepFlowNode::fromConfig(['stepA' => $stepA->getStepConfig()]);

        $this->assertTrue($roots[0]->isGroupOrSkipped(null));
        $stepA1 = $roots[0]->getChildren()[0];
        $this->assertTrue($stepA1->isGroupOrSkipped(null));
        $this->assertTrue($stepA1->getChildren()[0]->isGroupOrSkipped(null));
    }

    public function testSkipDoesNotPropagateWhenParentNotSkipped()
    {
        $stepA = new StepFlowBuilder('stepA')
            ->addStep(
                new StepFlowBuilder('stepA1')
                    ->setSkip(static fn () => true)
                    ->addStep('stepA11')
            )
            ->addStep('stepA2');
        $roots = StepFlowNode::fromConfig(['stepA' => $stepA->getStepConfig()]);

        $this->assertFalse($roots[0]->isGroupOrSkipped(null));

        $stepA1 = $roots[0]->getChildren()[0];
        $this->assertTrue($stepA1->isGroupOrSkipped(null));
        $this->assertTrue($stepA1->getChildren()[0]->isGroupOrSkipped(null));

        $this->assertFalse($roots[0]->getChildren()[1]->isGroupOrSkipped(null));
    }

    public function testGroupWithSkipOnChildrenAreSkipped()
    {
        $stepA = new StepFlowBuilder('stepA')
            ->setGroup(true)
            ->setSkip(static fn () => true)
            ->addStep('stepA1')
            ->addStep('stepA2');

        $roots = StepFlowNode::fromConfig(['stepA' => $stepA->getStepConfig()]);

        $this->assertTrue($roots[0]->isGroupOrSkipped(null));
        $this->assertTrue($roots[0]->getChildren()[0]->isGroupOrSkipped(null));
        $this->assertTrue($roots[0]->getChildren()[1]->isGroupOrSkipped(null));
    }

    public function testGroupWithNoChildrenThrows()
    {
        $stepA = new StepFlowBuilder('stepA')
            ->setGroup(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Step "stepA" is marked as group but has no child steps.');

        StepFlowNode::fromConfig(['stepA' => $stepA->getStepConfig()]);
    }

    public function testMultipleForests()
    {
        $roots = self::createNodes([
            'intro',
            'personal' => ['name', 'email'],
            'middle',
            'work' => ['company', 'role'],
            'summary',
        ]);

        $names = [];
        $node = $roots[0];
        while (null !== $node) {
            $names[] = $node->getName();
            $node = $node->getNextInTraversal();
        }

        $this->assertSame(['intro', 'personal', 'name', 'email', 'middle', 'work', 'company', 'role', 'summary'], $names);
    }
}
