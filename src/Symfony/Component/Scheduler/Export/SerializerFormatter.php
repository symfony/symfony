<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Export;

use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SerializerFormatter implements FormatterInterface
{
    private $serializer;

    /**
     * @var string
     */
    private $format;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function format(TaskInterface $task): string
    {
        return $this->serializer->serialize($task, $this->format);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $format): bool
    {
        if ($this->serializer->supportsEncoding($format)) {
            $this->format = $format;

            return true;
        }

        return false;
    }
}
