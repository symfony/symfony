<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Monolog\LogRecord;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The base class for security token processors.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Igor Timoshenko <igor.timoshenko@i.ua>
 *
 * @internal
 */
abstract class AbstractTokenProcessor
{
    use CompatibilityProcessor;

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
    ) {
    }

    abstract protected function getKey(): string;

    abstract protected function getToken(): ?TokenInterface;

    private function doInvoke(array|LogRecord $record): array|LogRecord
    {
        $record['extra'][$this->getKey()] = null;

        if (null !== $token = $this->getToken()) {
            $record['extra'][$this->getKey()] = [
                'authenticated' => method_exists($token, 'isAuthenticated') ? $token->isAuthenticated(false) : (bool) $token->getUser(),
                'roles' => $token->getRoleNames(),
            ];

            $record['extra'][$this->getKey()]['user_identifier'] = $token->getUserIdentifier();
        }

        return $record;
    }
}
