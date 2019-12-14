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

use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class JobFactory implements TaskFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $options): TaskInterface
    {
        $name = $options['name'];
        unset($options['name']);

        $options = $this->normalizeOptions($options);

        return new Job(
            $name,
            $options['attempt_deadline'] ?? '',
            $options['retry_config'] ?? [],
            $options['pub_sub_target'] ?? [],
            $options['app_engine_http_target'] ?? [],
            $options['http_target'] ?? [],
            $options
        );
    }

    public function createFromArray(array $jobs): array
    {
        foreach ($jobs as $job) {
            $jobs[] = $this->create($job);
        }

        return $jobs;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $type): bool
    {
        return 'job' === $type ;
    }

    private function normalizeOptions(array $options): array
    {
        if (empty($options)) {
            return $options;
        }

        $newOptions = [];

        foreach ($options as $key => $option) {
            if ('timeZone' === $key) {
                $newOptions['timezone'] = new \DateTimeZone($option);
                continue;
            }

            if ('schedule' === $key) {
                $newOptions['expression'] = $option;
                continue;
            }

            $newOptions[strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($key)))] = $option;
        }

        return $newOptions;
    }
}
