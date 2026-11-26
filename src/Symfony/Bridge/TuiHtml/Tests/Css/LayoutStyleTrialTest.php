<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\LayoutStyleTrial;

class LayoutStyleTrialTest extends TestCase
{
    public function testOnlyTheChosenCandidateCommitsOnce()
    {
        $commits = [];
        $trial = new LayoutStyleTrial(['value' => 'initial'], static fn ($metrics) => ['value' => \count($metrics)], static function ($candidate) use (&$commits): void { $commits[] = $candidate['value']; });
        $first = $trial->resolve(new \SplObjectStorage());
        $metrics = new \SplObjectStorage();
        $metrics[new \stdClass()] = [];
        $final = $trial->resolve($metrics);
        self::assertSame([], $commits);
        self::assertSame('initial', $trial->getInitial()['value']);
        self::assertSame(0, $first['value']);
        $trial->commit($final);
        self::assertSame([1], $commits);
        $this->expectException(\LogicException::class);
        $trial->commit($final);
    }

    public function testCandidatesFromAnotherFrameCannotCommit()
    {
        $trial = new LayoutStyleTrial([], static fn () => [], static function (): void {});
        $foreign = new LayoutStyleTrial([], static fn () => [], static function (): void {});
        $this->expectException(\LogicException::class);
        $trial->commit($foreign->getInitial());
    }

    public function testDisposalFreesCapturedInputsEvenWithAnExternallyRetainedInitialCandidate()
    {
        $owner = new \stdClass();
        $owner->value = 'captured';
        $weak = \WeakReference::create($owner);
        $trial = new LayoutStyleTrial(['value' => 'initial'], static fn () => ['value' => $owner->value], static function ($candidate) use ($owner): void { $owner->value = $candidate['value']; });
        $initial = $trial->getInitial();
        unset($owner);
        self::assertNotNull($weak->get());
        $trial->dispose();
        self::assertNull($weak->get());
        self::assertSame('initial', $initial['value']);
        $this->expectException(\LogicException::class);
        $trial->resolve(new \SplObjectStorage());
    }
}
