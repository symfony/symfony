<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\ResettableInterface;

/**
 * Helper trait for implementing ProcessableInterface.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @internal
 */
trait ProcessableHandlerTrait
{
    /**
     * @var callable[]
     */
    protected $processors = [];

    /**
     * {@inheritdoc}
     *
     * @suppress PhanTypeMismatchReturn
     */
    public function pushProcessor($callback): HandlerInterface
    {
        array_unshift($this->processors, $callback);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function popProcessor(): callable
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    /**
     * Processes a record.
     */
    protected function processRecord(array $record): array
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        return $record;
    }

    protected function resetProcessors()
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof ResettableInterface) {
                $processor->reset();
            }
        }
    }
}
