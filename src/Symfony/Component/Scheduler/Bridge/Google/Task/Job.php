<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Task;

use Symfony\Component\Scheduler\Task\AbstractTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Job extends AbstractTask
{
    public function __construct(string $name, string $attemptDeadline = '', array $retryConfig = [], array $pubSubTarget = [], array $appEngineHttpTarget = [], array $httpTarget = [], array $defaultOptions = [])
    {
        parent::__construct($name, array_merge($this->getOptions(), [
            'retry_config' => $retryConfig,
            'attempt_deadline' => $attemptDeadline,
            'pub_sub_target' => $pubSubTarget,
            'app_engine_http_target' => $appEngineHttpTarget,
            'http_target' => $httpTarget,
            'user_update_time' => null,
            'schedule_time' => null,
            'last_attempt_time' => null,
            'type' => 'job',
        ], $defaultOptions), [
            'retry_config' => ['array'],
            'attempt_deadline' => ['string'],
            'pub_sub_target' => ['array', 'null'],
            'app_engine_http_target' => ['array'],
            'http_target' => ['array'],
            'user_update_time' => ['string', 'null'],
            'schedule_time' => ['string', 'null'],
            'last_attempt_time' => ['string', 'null'],
        ]);
    }

    public function toArray(): array
    {
        $defaultOptions = [
            'name' => $this->getName(),
            'schedule' => $this->getExpression(),
            'timeZone' => $this->get('timezone'),
        ];

        if (null !== $this->get('description')) {
            $defaultOptions['description'] = $this->get('description');
        }

        if (!empty($this->get('retry_config'))) {
            $defaultOptions['retryConfig'] = $this->get('retry_config');
        }

        if (!empty($this->get('attempt_deadline'))) {
            $defaultOptions['attemptDeadline'] = $this->get('attempt_deadline');
        }

        if (!empty($this->get('pub_sub_target'))) {
            $defaultOptions['pubSubTarget'] = $this->get('pub_sub_target');
        }

        if (!empty($this->get('app_engine_http_target'))) {
            $defaultOptions['appEngineHttpTarget'] = $this->get('app_engine_http_target');
        }

        if (!empty($this->get('http_target'))) {
            $defaultOptions['httpTarget'] = $this->get('http_target');
        }

        return $defaultOptions;
    }
}
