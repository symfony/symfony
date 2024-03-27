<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\UserSession;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class UserSessionHandler extends AbstractSessionHandler
{
    public function __construct(
        private readonly UserSessionRepositoryInterface $userSessionRepository,
        private readonly ClockInterface $clock,
        private readonly int $ttl = 1800,
    ) {
    }

    public function close(): bool
    {
        $this->userSessionRepository->removeExpired();

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $now = $this->clock->now()
            ->getTimestamp();
        $expiry = $now + $this->ttl;
        $userSession = $this->userSessionRepository->findOneById($id);
        if (null !== $userSession) {
            $userSession->setLifetime($expiry);
            $this->userSessionRepository->save($userSession);
        }

        return true;
    }

    protected function doRead(#[\SensitiveParameter] string $userSessionId): string
    {
        $userSession = $this->userSessionRepository->findOneById($userSessionId);
        if (null === $userSession) {
            return '';
        }

        $data = $userSession->getData();

        return \is_resource($data) ? stream_get_contents($data) : $data;
    }

    protected function doWrite(#[\SensitiveParameter] string $userSessionId, string $data): bool
    {
        $now = $this->clock->now()
            ->getTimestamp();
        $userSession = $this->userSessionRepository->findOneById($userSessionId);
        if (null === $userSession) {
            $maxLifetime = $now + $this->ttl;
            $userSession = $this->userSessionRepository->create(
                $userSessionId,
                $data,
                $maxLifetime,
                $this->clock->now()->getTimestamp()
            );
        } else {
            $userSession->setData($data);
        }
        $this->userSessionRepository->save($userSession);

        return true;
    }

    protected function doDestroy(#[\SensitiveParameter] string $userSessionId): bool
    {
        $userSession = $this->userSessionRepository->findOneById($userSessionId);
        if (null !== $userSession) {
            $this->userSessionRepository->remove($userSession);
        }

        return true;
    }
}
