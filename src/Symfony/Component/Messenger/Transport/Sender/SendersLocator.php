<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\HandlersLocator;

/**
 * Maps a message to a list of senders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SendersLocator implements SendersLocatorInterface
{
    private $sendersMap;
    private $sendersLocator;
    private $useLegacyLookup = false;

    /**
     * @param string[][]         $sendersMap     An array, keyed by "type", set to an array of sender aliases
     * @param ContainerInterface $sendersLocator Locator of senders, keyed by sender alias
     */
    public function __construct(array $sendersMap, /* ContainerInterface */ $sendersLocator = null)
    {
        $this->sendersMap = $sendersMap;

        if (\is_array($sendersLocator) || null === $sendersLocator) {
            @trigger_error(sprintf('"%s::__construct()" requires a "%s" as 2nd argument. Not doing so is deprecated since Symfony 4.3 and will be required in 5.0.', __CLASS__, ContainerInterface::class), \E_USER_DEPRECATED);
            // "%s" requires a "%s" as 2nd argument. Not doing so is deprecated since Symfony 4.3 and will be required in 5.0.'
            $this->sendersLocator = new ServiceLocator([]);
            $this->useLegacyLookup = true;
        } else {
            $this->sendersLocator = $sendersLocator;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSenders(Envelope $envelope): iterable
    {
        $seen = [];

        foreach (HandlersLocator::listTypes($envelope) as $type) {
            // the old way of looking up senders
            if ($this->useLegacyLookup) {
                foreach ($this->sendersMap[$type] ?? [] as $alias => $sender) {
                    if (!\in_array($sender, $seen, true)) {
                        yield $alias => $seen[] = $sender;
                    }
                }

                continue;
            }

            foreach ($this->sendersMap[$type] ?? [] as $senderAlias) {
                if (!\in_array($senderAlias, $seen, true)) {
                    if (!$this->sendersLocator->has($senderAlias)) {
                        throw new RuntimeException(sprintf('Invalid senders configuration: sender "%s" is not in the senders locator.', $senderAlias));
                    }

                    $seen[] = $senderAlias;
                    $sender = $this->sendersLocator->get($senderAlias);
                    yield $senderAlias => $sender;
                }
            }
        }
    }
}
