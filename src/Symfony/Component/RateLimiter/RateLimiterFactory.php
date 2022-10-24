<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Policy\Rate;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class RateLimiterFactory
{
    private array $config;
    private StorageInterface $storage;
    private ?LockFactory $lockFactory;

    public function __construct(array $config, StorageInterface $storage, LockFactory $lockFactory = null)
    {
        $this->storage = $storage;
        $this->lockFactory = $lockFactory;

        $options = new OptionsResolver();
        self::configureOptions($options);

        $this->config = $options->resolve($config);
    }

    public function create(string $key = null): LimiterInterface
    {
        $id = $this->config['id'].'-'.$key;
        $lock = $this->lockFactory?->createLock($id);

        return match ($this->config['policy']) {
            'token_bucket' => new TokenBucketLimiter($id, $this->config['limit'], $this->config['rate'], $this->storage, $lock),
            'fixed_window' => new FixedWindowLimiter($id, $this->config['limit'], $this->config['interval'], $this->storage, $lock),
            'sliding_window' => new SlidingWindowLimiter($id, $this->config['limit'], $this->config['interval'], $this->storage, $lock),
            'no_limit' => new NoLimiter(),
            default => throw new \LogicException(sprintf('Limiter policy "%s" does not exists, it must be either "token_bucket", "sliding_window", "fixed_window" or "no_limit".', $this->config['policy'])),
        };
    }

    protected static function configureOptions(OptionsResolver $options): void
    {
        $intervalNormalizer = static function (Options $options, string $interval): \DateInterval {
            try {
                return (new \DateTimeImmutable())->diff(new \DateTimeImmutable('+'.$interval));
            } catch (\Exception $e) {
                if (!preg_match('/Failed to parse time string \(\+([^)]+)\)/', $e->getMessage(), $m)) {
                    throw $e;
                }

                throw new \LogicException(sprintf('Cannot parse interval "%s", please use a valid unit as described on https://www.php.net/datetime.formats.relative.', $m[1]));
            }
        };

        $options
            ->define('id')->required()
            ->define('policy')
                ->required()
                ->allowedValues('token_bucket', 'fixed_window', 'sliding_window', 'no_limit')

            ->define('limit')->allowedTypes('int')
            ->define('interval')->allowedTypes('string')->normalize($intervalNormalizer)
            ->define('rate')
                ->default(function (OptionsResolver $rate) use ($intervalNormalizer) {
                    $rate
                        ->define('amount')->allowedTypes('int')->default(1)
                        ->define('interval')->allowedTypes('string')->normalize($intervalNormalizer)
                    ;
                })
                ->normalize(function (Options $options, $value) {
                    if (!isset($value['interval'])) {
                        return null;
                    }

                    return new Rate($value['interval'], $value['amount']);
                })
        ;
    }
}
