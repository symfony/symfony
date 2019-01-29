<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Messenger\Exception;

use Symfony\Component\Messenger\Envelope;

class ChainedHandlerFailedException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var \Throwable[]
     */
    private $nested;

    /**
     * @var Envelope
     */
    private $envelope;

    public function __construct(Envelope $envelope, \Throwable ...$nested)
    {
        parent::__construct($this->constructMessage($nested));
        $this->envelope = $envelope;
        $this->nested = $nested;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    /**
     * @return \Throwable[]
     */
    public function getNestedExceptions(): array
    {
        return $this->nested;
    }

    /**
     * @param \Throwable[] $nested
     *
     * @return string
     */
    private function constructMessage(array $nested): string
    {
        return 1 === \count($nested) ?
            $nested[0]->getMessage() :
            sprintf('%d MessageHandler failed. First one failed with Message: %s', \count($nested), $nested[0]->getMessage());
    }
}
