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
final class RateLimiterFactory implements RateLimiterFactoryInterface
{
    private array $config;

    public function __construct(
        array $config,
        private StorageInterface $storage,
        private ?LockFactory $lockFactory = null,
    ) {
        $options = new OptionsResolver();
        self::configureOptions($options);

        $this->config = $options->resolve($config);
    }

    public function create(?string $key = null): LimiterInterface
    {
        $id = $this->config['id'].'-'.$key;
        $lock = $this->lockFactory?->createLock($id);

        return match ($this->config['policy']) {
            'token_bucket' => new TokenBucketLimiter($id, $this->config['limit'], $this->config['rate'], $this->storage, $lock),
            'fixed_window' => new FixedWindowLimiter($id, $this->config['limit'], $this->config['interval'], $this->storage, $lock),
            'sliding_window' => new SlidingWindowLimiter($id, $this->config['limit'], $this->config['interval'], $this->storage, $lock),
            'no_limit' => new NoLimiter(),
            default => throw new \LogicException(\sprintf('Limiter policy "%s" does not exists, it must be either "token_bucket", "sliding_window", "fixed_window" or "no_limit".', $this->config['policy'])),
        };
    }

    private static function configureOptions(OptionsResolver $options): void
    {
        $intervalNormalizer = static function (Options $options, string $interval): \DateInterval {
            // Create DateTimeImmutable from unix timesatmp, so the default timezone is ignored and we don't need to
            // deal with quirks happening when modifying dates using a timezone with DST.
            $now = \DateTimeImmutable::createFromFormat('U', time());

            try {
                $nowPlusInterval = @$now->modify('+'.$interval);
            } catch (\DateMalformedStringException $e) {
                throw new \LogicException(\sprintf('Cannot parse interval "%s", please use a valid unit as described on https://php.net/datetime.formats#datetime.formats.relative', $interval), 0, $e);
            }

            if (!$nowPlusInterval) {
                throw new \LogicException(\sprintf('Cannot parse interval "%s", please use a valid unit as described on https://php.net/datetime.formats#datetime.formats.relative', $interval));
            }

            return $now->diff($nowPlusInterval);
        };

        $options
            ->define('id')->required()
            ->define('policy')
                ->required()
                ->allowedValues('token_bucket', 'fixed_window', 'sliding_window', 'no_limit')

            ->define('limit')->allowedTypes('int')
            ->define('interval')->allowedTypes('string')->normalize($intervalNormalizer)
            ->define('rate')
                ->options(function (OptionsResolver $rate) use ($intervalNormalizer) {
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
