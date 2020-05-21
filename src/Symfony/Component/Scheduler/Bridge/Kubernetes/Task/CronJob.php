<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Task;

use Symfony\Component\Scheduler\Task\AbstractTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronJob extends AbstractTask
{
    public function __construct(string $name, string $apiVersion, array $spec = [], array $jobStatus = [], array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'api_version' => $apiVersion,
            'job_status' => $jobStatus,
            'spec' => $spec,
        ]), array_merge($additionalOptions, [
            'api_version' => ['string'],
            'spec' => ['array'],
            'job_status' => ['array'],
        ]));
    }
}
