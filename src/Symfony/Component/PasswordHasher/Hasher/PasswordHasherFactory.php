<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Hasher;

use Symfony\Component\PasswordHasher\Exception\LogicException;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * A generic hasher factory implementation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class PasswordHasherFactory implements PasswordHasherFactoryInterface
{
    /**
     * @param array<string, PasswordHasherInterface|array> $passwordHashers
     */
    public function __construct(
        private array $passwordHashers,
    ) {
    }

    public function getPasswordHasher(string|PasswordAuthenticatedUserInterface|PasswordHasherAwareInterface $user): PasswordHasherInterface
    {
        $hasherKey = null;

        if ($user instanceof PasswordHasherAwareInterface && null !== $hasherName = $user->getPasswordHasherName()) {
            if (!\array_key_exists($hasherName, $this->passwordHashers)) {
                throw new \RuntimeException(\sprintf('The password hasher "%s" was not configured.', $hasherName));
            }

            $hasherKey = $hasherName;
        } else {
            foreach ($this->passwordHashers as $class => $hasher) {
                if (!class_exists($class) && !interface_exists($class, false)) {
                    throw new LogicException(sprintf('Invalid password hashers\' configuration: class or interface "%s" not found.', $class));
                }
                if ((\is_object($user) && $user instanceof $class) || (!\is_object($user) && (is_subclass_of($user, $class) || $user == $class))) {
                    $hasherKey = $class;
                    break;
                }
            }
        }

        if (null === $hasherKey) {
            throw new \RuntimeException(\sprintf('No password hasher has been configured for account "%s".', \is_object($user) ? get_debug_type($user) : $user));
        }

        if (!$this->passwordHashers[$hasherKey] instanceof PasswordHasherInterface) {
            $this->passwordHashers[$hasherKey] = $this->createHasher($this->passwordHashers[$hasherKey]);
        }

        return $this->passwordHashers[$hasherKey];
    }

    /**
     * Creates the actual hasher instance.
     *
     * @throws \InvalidArgumentException
     */
    private function createHasher(array $config, bool $isExtra = false): PasswordHasherInterface
    {
        if (isset($config['instance'])) {
            if (!isset($config['migrate_from'])) {
                return $config['instance'];
            }

            $config = $this->getMigratingPasswordConfig($config);
        }

        if (isset($config['algorithm'])) {
            $rawConfig = $config;
            $config = $this->getHasherConfigFromAlgorithm($config);
        }
        if (!isset($config['class'])) {
            throw new \InvalidArgumentException('"class" must be set in '.json_encode($config));
        }
        if (!isset($config['arguments'])) {
            throw new \InvalidArgumentException('"arguments" must be set in '.json_encode($config));
        }

        $hasher = new $config['class'](...$config['arguments']);

        if ($isExtra || !\in_array($config['class'], [NativePasswordHasher::class, SodiumPasswordHasher::class], true)) {
            return $hasher;
        }

        if ($rawConfig ?? null) {
            $extrapasswordHashers = array_map(function (string $algo) use ($rawConfig): PasswordHasherInterface {
                $rawConfig['algorithm'] = $algo;

                return $this->createHasher($rawConfig);
            }, ['pbkdf2', $rawConfig['hash_algorithm'] ?? 'sha512']);
        } else {
            $extrapasswordHashers = [new Pbkdf2PasswordHasher(), new MessageDigestPasswordHasher()];
        }

        return new MigratingPasswordHasher($hasher, ...$extrapasswordHashers);
    }

    private function getHasherConfigFromAlgorithm(array $config): array
    {
        if ('auto' === $config['algorithm']) {
            // "plaintext" is not listed as any leaked hashes could then be used to authenticate directly
            if (SodiumPasswordHasher::isSupported()) {
                $algorithms = ['native', 'sodium', 'pbkdf2'];
            } else {
                $algorithms = ['native', 'pbkdf2'];
            }

            if ($config['hash_algorithm'] ?? '') {
                $algorithms[] = $config['hash_algorithm'];
            }

            $hasherChain = [];
            foreach ($algorithms as $algorithm) {
                $config['algorithm'] = $algorithm;
                $hasherChain[] = $this->createHasher($config, true);
            }

            return [
                'class' => MigratingPasswordHasher::class,
                'arguments' => $hasherChain,
            ];
        }

        if ($config['migrate_from'] ?? false) {
            return $this->getMigratingPasswordConfig($config);
        }

        switch ($config['algorithm']) {
            case 'plaintext':
                return [
                    'class' => PlaintextPasswordHasher::class,
                    'arguments' => [$config['ignore_case'] ?? false],
                ];

            case 'pbkdf2':
                return [
                    'class' => Pbkdf2PasswordHasher::class,
                    'arguments' => [
                        $config['hash_algorithm'] ?? 'sha512',
                        $config['encode_as_base64'] ?? true,
                        $config['iterations'] ?? 1000,
                        $config['key_length'] ?? 40,
                    ],
                ];

            case 'bcrypt':
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_BCRYPT;

                return $this->getHasherConfigFromAlgorithm($config);

            case 'native':
                return [
                    'class' => NativePasswordHasher::class,
                    'arguments' => [
                        $config['time_cost'] ?? null,
                        (($config['memory_cost'] ?? 0) << 10) ?: null,
                        $config['cost'] ?? null,
                    ] + (isset($config['native_algorithm']) ? [3 => $config['native_algorithm']] : []),
                ];

            case 'sodium':
                return [
                    'class' => SodiumPasswordHasher::class,
                    'arguments' => [
                        $config['time_cost'] ?? null,
                        (($config['memory_cost'] ?? 0) << 10) ?: null,
                    ],
                ];

            case 'argon2i':
                if (SodiumPasswordHasher::isSupported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                    $config['algorithm'] = 'sodium';
                } elseif (\defined('PASSWORD_ARGON2I')) {
                    $config['algorithm'] = 'native';
                    $config['native_algorithm'] = \PASSWORD_ARGON2I;
                } else {
                    throw new LogicException(\sprintf('Algorithm "argon2i" is not available. Use "%s" instead.', \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') ? 'argon2id" or "auto' : 'auto'));
                }

                return $this->getHasherConfigFromAlgorithm($config);

            case 'argon2id':
                if (($hasSodium = SodiumPasswordHasher::isSupported()) && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                    $config['algorithm'] = 'sodium';
                } elseif (\defined('PASSWORD_ARGON2ID')) {
                    $config['algorithm'] = 'native';
                    $config['native_algorithm'] = \PASSWORD_ARGON2ID;
                } else {
                    throw new LogicException(\sprintf('Algorithm "argon2id" is not available; use "%s" or libsodium 1.0.15+ instead.', \defined('PASSWORD_ARGON2I') || $hasSodium ? 'argon2i", "auto' : 'auto'));
                }

                return $this->getHasherConfigFromAlgorithm($config);
        }

        return [
            'class' => MessageDigestPasswordHasher::class,
            'arguments' => [
                $config['algorithm'],
                $config['encode_as_base64'] ?? true,
                $config['iterations'] ?? 5000,
            ],
        ];
    }

    private function getMigratingPasswordConfig(array $config): array
    {
        $frompasswordHashers = $config['migrate_from'];
        unset($config['migrate_from']);
        $hasherChain = [$this->createHasher($config, true)];

        foreach ($frompasswordHashers as $name) {
            if ($hasher = $this->passwordHashers[$name] ?? false) {
                $hasher = $hasher instanceof PasswordHasherInterface ? $hasher : $this->createHasher($hasher, true);
            } else {
                $hasher = $this->createHasher(['algorithm' => $name], true);
            }

            $hasherChain[] = $hasher;
        }

        return [
            'class' => MigratingPasswordHasher::class,
            'arguments' => $hasherChain,
        ];
    }
}
