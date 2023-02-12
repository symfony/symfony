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

    /**
     * @param string $message
     * @param string $class
     * @param string $method
     */
    public function addNoticeFromObject($message, $class, $method)
    {
        $this->deprecationNotice($message)->addObjectOccurrence($class, $method);
        $this->addNotice();
    }

    /**
     * @param string $message
     */
    public function addNoticeFromProceduralCode($message)
    {
        $this->deprecationNotice($message)->addProceduralOccurrence();
        $this->addNotice();
    }

    public function addNotice()
    {
        ++$this->count;
    }

    /**
     * @param string $message
     */
    private function deprecationNotice($message): DeprecationNotice
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
