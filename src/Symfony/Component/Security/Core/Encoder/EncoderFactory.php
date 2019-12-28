<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\Security\Core\Exception\LogicException;

/**
 * A generic encoder factory implementation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EncoderFactory implements EncoderFactoryInterface
{
    private $encoders;

    public function __construct(array $encoders)
    {
        $this->encoders = $encoders;
    }

    /**
     * {@inheritdoc}
     */
    public function getEncoder($user)
    {
        $encoderKey = null;

        if ($user instanceof EncoderAwareInterface && (null !== $encoderName = $user->getEncoderName())) {
            if (!\array_key_exists($encoderName, $this->encoders)) {
                throw new \RuntimeException(sprintf('The encoder "%s" was not configured.', $encoderName));
            }

            $encoderKey = $encoderName;
        } else {
            foreach ($this->encoders as $class => $encoder) {
                if ((\is_object($user) && $user instanceof $class) || (!\is_object($user) && (is_subclass_of($user, $class) || $user == $class))) {
                    $encoderKey = $class;
                    break;
                }
            }
        }

        if (null === $encoderKey) {
            throw new \RuntimeException(sprintf('No encoder has been configured for account "%s".', \is_object($user) ? \get_class($user) : $user));
        }

        if (!$this->encoders[$encoderKey] instanceof PasswordEncoderInterface) {
            $this->encoders[$encoderKey] = $this->createEncoder($this->encoders[$encoderKey]);
        }

        return $this->encoders[$encoderKey];
    }

    /**
     * Creates the actual encoder instance.
     *
     * @throws \InvalidArgumentException
     */
    private function createEncoder(array $config, bool $isExtra = false): PasswordEncoderInterface
    {
        if (isset($config['algorithm'])) {
            $rawConfig = $config;
            $config = $this->getEncoderConfigFromAlgorithm($config);
        }
        if (!isset($config['class'])) {
            throw new \InvalidArgumentException(sprintf('"class" must be set in %s.', json_encode($config)));
        }
        if (!isset($config['arguments'])) {
            throw new \InvalidArgumentException(sprintf('"arguments" must be set in %s.', json_encode($config)));
        }

        $encoder = new $config['class'](...$config['arguments']);

        if ($isExtra || !\in_array($config['class'], [NativePasswordEncoder::class, SodiumPasswordEncoder::class], true)) {
            return $encoder;
        }

        if ($rawConfig ?? null) {
            $extraEncoders = array_map(function (string $algo) use ($rawConfig): PasswordEncoderInterface {
                $rawConfig['algorithm'] = $algo;

                return $this->createEncoder($rawConfig);
            }, ['pbkdf2', $rawConfig['hash_algorithm'] ?? 'sha512']);
        } else {
            $extraEncoders = [new Pbkdf2PasswordEncoder(), new MessageDigestPasswordEncoder()];
        }

        return new MigratingPasswordEncoder($encoder, ...$extraEncoders);
    }

    private function getEncoderConfigFromAlgorithm(array $config): array
    {
        if ('auto' === $config['algorithm']) {
            $encoderChain = [];
            // "plaintext" is not listed as any leaked hashes could then be used to authenticate directly
            foreach ([SodiumPasswordEncoder::isSupported() ? 'sodium' : 'native', 'pbkdf2', $config['hash_algorithm']] as $algo) {
                $config['algorithm'] = $algo;
                $encoderChain[] = $this->createEncoder($config, true);
            }

            return [
                'class' => MigratingPasswordEncoder::class,
                'arguments' => $encoderChain,
            ];
        }

        if ($fromEncoders = ($config['migrate_from'] ?? false)) {
            unset($config['migrate_from']);
            $encoderChain = [$this->createEncoder($config, true)];

            foreach ($fromEncoders as $name) {
                if ($encoder = $this->encoders[$name] ?? false) {
                    $encoder = $encoder instanceof PasswordEncoderInterface ? $encoder : $this->createEncoder($encoder, true);
                } else {
                    $encoder = $this->createEncoder(['algorithm' => $name], true);
                }

                $encoderChain[] = $encoder;
            }

            return [
                'class' => MigratingPasswordEncoder::class,
                'arguments' => $encoderChain,
            ];
        }

        switch ($config['algorithm']) {
            case 'plaintext':
                return [
                    'class' => PlaintextPasswordEncoder::class,
                    'arguments' => [$config['ignore_case']],
                ];

            case 'pbkdf2':
                return [
                    'class' => Pbkdf2PasswordEncoder::class,
                    'arguments' => [
                        $config['hash_algorithm'] ?? 'sha512',
                        $config['encode_as_base64'] ?? true,
                        $config['iterations'] ?? 1000,
                        $config['key_length'] ?? 40,
                    ],
                ];

            case 'bcrypt':
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = PASSWORD_BCRYPT;

                return $this->getEncoderConfigFromAlgorithm($config);

            case 'native':
                return [
                    'class' => NativePasswordEncoder::class,
                    'arguments' => [
                        $config['time_cost'] ?? null,
                        (($config['memory_cost'] ?? 0) << 10) ?: null,
                        $config['cost'] ?? null,
                    ] + (isset($config['native_algorithm']) ? [3 => $config['native_algorithm']] : []),
                ];

            case 'sodium':
                return [
                    'class' => SodiumPasswordEncoder::class,
                    'arguments' => [
                        $config['time_cost'] ?? null,
                        (($config['memory_cost'] ?? 0) << 10) ?: null,
                    ],
                ];

            case 'argon2i':
                if (SodiumPasswordEncoder::isSupported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                    $config['algorithm'] = 'sodium';
                } elseif (\defined('PASSWORD_ARGON2I')) {
                    $config['algorithm'] = 'native';
                    $config['native_algorithm'] = PASSWORD_ARGON2I;
                } else {
                    throw new LogicException(sprintf('Algorithm "argon2i" is not available. Either use %s"auto" or upgrade to PHP 7.2+ instead.', \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') ? '"argon2id", ' : ''));
                }

                return $this->getEncoderConfigFromAlgorithm($config);

            case 'argon2id':
                if (($hasSodium = SodiumPasswordEncoder::isSupported()) && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                    $config['algorithm'] = 'sodium';
                } elseif (\defined('PASSWORD_ARGON2ID')) {
                    $config['algorithm'] = 'native';
                    $config['native_algorithm'] = PASSWORD_ARGON2ID;
                } else {
                    throw new LogicException(sprintf('Algorithm "argon2id" is not available. Either use %s"auto", upgrade to PHP 7.3+ or use libsodium 1.0.15+ instead.', \defined('PASSWORD_ARGON2I') || $hasSodium ? '"argon2i", ' : ''));
                }

                return $this->getEncoderConfigFromAlgorithm($config);
        }

        return [
            'class' => MessageDigestPasswordEncoder::class,
            'arguments' => [
                $config['algorithm'],
                $config['encode_as_base64'] ?? true,
                $config['iterations'] ?? 5000,
            ],
        ];
    }
}
