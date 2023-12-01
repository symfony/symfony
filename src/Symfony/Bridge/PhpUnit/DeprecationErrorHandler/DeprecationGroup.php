<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

/**
 * @internal
 */
final class DeprecationGroup
{
    private $count = 0;

    /**
     * @var DeprecationNotice[] keys are messages
     */
    private $deprecationNotices = [];

    public function addNoticeFromObject(string $message, string $class, string $method): void
    {
        $this->deprecationNotice($message)->addObjectOccurrence($class, $method);
        $this->addNotice();
    }

    public function addNoticeFromProceduralCode(string $message): void
    {
        $this->deprecationNotice($message)->addProceduralOccurrence();
        $this->addNotice();
    }

    public function addNotice()
    {
        ++$this->count;
    }

    private function deprecationNotice(string $message): DeprecationNotice
    {
        return $this->deprecationNotices[$message] ?? $this->deprecationNotices[$message] = new DeprecationNotice();
    }

    public function count(): int
    {
        return $this->count;
    }

    public function notices(): array
    {
        return $this->deprecationNotices;
    }
}
