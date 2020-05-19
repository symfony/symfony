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
    private $apiVersion;
    private $kind;
    private $metadata;
    private $spec;
    private $status;

    public function __construct(string $name, array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'type' => 'cron_job',
        ]), $additionalOptions);
    }

    public function toArray(): array
    {
        return [
            'apiVersion' => $this->apiVersion,
            'bind' => $this->kind,
            'metadata' => $this->metadata->toArray(),
            'status' => $this->status->toArray(),
        ];
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getMetadata(): ObjectMeta
    {
        return $this->metadata;
    }

    public function getSpec(): CronJobSpec
    {
        return $this->spec;
    }

    public function getStatus(): CronJobStatus
    {
        return $this->status;
    }
}
