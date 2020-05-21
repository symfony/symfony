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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTask extends AbstractTask
{
    public function __construct(string $name, string $url, string $method = 'GET', array $clientOptions = [], array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'client_options' => $clientOptions,
            'url' => $url,
            'method' => $method,
        ]), array_merge($additionalOptions, [
            'client_options' => ['array'],
            'method' => ['string'],
            'url' => ['string'],
        ]));
    }
}
