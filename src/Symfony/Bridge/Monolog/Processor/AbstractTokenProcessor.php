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
 * @internal since Symfony 6.1
 */
abstract class AbstractTokenProcessor
{
    use CompatibilityProcessor;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    abstract protected function getKey(): string;

    abstract protected function getToken(): ?TokenInterface;

    private function doInvoke(array|LogRecord $record): array|LogRecord
    {
        $record['extra'][$this->getKey()] = null;

        if (null !== $token = $this->getToken()) {
            $record['extra'][$this->getKey()] = [
                'authenticated' => (bool) $token->getUser(),
                'roles' => $token->getRoleNames(),
            ];

            // @deprecated since Symfony 5.3, change to $token->getUserIdentifier() in 7.0
            $record['extra'][$this->getKey()]['user_identifier'] = method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername();
        }

        return $record;
    }
}
