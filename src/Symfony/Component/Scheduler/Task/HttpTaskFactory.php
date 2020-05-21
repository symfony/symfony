<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskFactory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $options): TaskInterface
    {
        if (!\array_key_exists('name', $options)) {
            throw new InvalidArgumentException('The "name" is required.');
        }

        $name = $options['name'] ?? '';

        if (!\array_key_exists('url', $options)) {
            throw new InvalidArgumentException('The "url" option is required.');
        }

        $url = $options['url'];
        $clientOptions = $options['client_options'] ?? [];
        $method = $options['method'] ?? 'GET';

        unset($options['name'], $options['url'], $options['method'], $options['client_options']);

        return new HttpTask($name, $url, $method, $clientOptions, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $type): bool
    {
        return 'http' === $type;
    }
}
