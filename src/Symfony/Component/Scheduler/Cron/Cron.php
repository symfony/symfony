<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Cron;

use Symfony\Component\Scheduler\Exception\LogicException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Cron implements CronInterface
{
    private const MASK = '* * * * * cd %s && php bin/console scheduler:run %s >> /dev/null 2>&1';

    private $name;
    private $expression;
    private $generationDate;
    private $options;

    public function __construct(string $name, array $options = [])
    {
        $this->name = $name;
        $this->options = $options;
        $this->generationDate = new \DateTimeImmutable();

        $this->generateExpression();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGenerationDate(): \DateTimeImmutable
    {
        return $this->generationDate;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    private function generateExpression(): void
    {
        if (!\array_key_exists('path', $this->options)) {
            throw new LogicException('The "path" option must be defined');
        }

        $this->expression = sprintf(self::MASK, $this->options['path'], $this->name);
    }
}
