<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class Alias
{
    private $id;
    private $public;
    private $deprecation = [];

    private static $defaultDeprecationTemplate = 'The "%alias_id%" service alias is deprecated. You should stop using it, as it will be removed in the future.';

    public function __construct(string $id, bool $public = false)
    {
        $this->id = $id;
        $this->public = $public;
    }

    /**
     * Checks if this DI Alias should be public or not.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets if this Alias is public.
     *
     * @return $this
     */
    public function setPublic(bool $boolean)
    {
        $this->public = $boolean;

        return $this;
    }

    /**
     * Sets if this Alias is private.
     *
     * @return $this
     *
     * @deprecated since Symfony 5.2, use setPublic() instead
     */
    public function setPrivate(bool $boolean)
    {
        trigger_deprecation('symfony/dependency-injection', '5.2', 'The "%s()" method is deprecated, use "setPublic()" instead.', __METHOD__);

        return $this->setPublic(!$boolean);
    }

    /**
     * Whether this alias is private.
     *
     * @return bool
     */
    public function isPrivate()
    {
        return !$this->public;
    }

    /**
     * Whether this alias is deprecated, that means it should not be referenced
     * anymore.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The deprecation message to use
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    public function setDeprecated(/* string $package, string $version, string $message */)
    {
        $args = \func_get_args();

        if (\func_num_args() < 3) {
            trigger_deprecation('symfony/dependency-injection', '5.1', 'The signature of method "%s()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.', __METHOD__);

            $status = $args[0] ?? true;

            if (!$status) {
                trigger_deprecation('symfony/dependency-injection', '5.1', 'Passing a null message to un-deprecate a node is deprecated.');
            }

            $message = (string) ($args[1] ?? null);
            $package = $version = '';
        } else {
            $status = true;
            $package = (string) $args[0];
            $version = (string) $args[1];
            $message = (string) $args[2];
        }

        if ('' !== $message) {
            if (preg_match('#[\r\n]|\*/#', $message)) {
                throw new InvalidArgumentException('Invalid characters found in deprecation template.');
            }

            if (false === strpos($message, '%alias_id%')) {
                throw new InvalidArgumentException('The deprecation template must contain the "%alias_id%" placeholder.');
            }
        }

        $this->deprecation = $status ? ['package' => $package, 'version' => $version, 'message' => $message ?: self::$defaultDeprecationTemplate] : [];

        return $this;
    }

    public function isDeprecated(): bool
    {
        return (bool) $this->deprecation;
    }

    /**
     * @deprecated since Symfony 5.1, use "getDeprecation()" instead.
     */
    public function getDeprecationMessage(string $id): string
    {
        trigger_deprecation('symfony/dependency-injection', '5.1', 'The "%s()" method is deprecated, use "getDeprecation()" instead.', __METHOD__);

        return $this->getDeprecation($id)['message'];
    }

    /**
     * @param string $id Service id relying on this definition
     */
    public function getDeprecation(string $id): array
    {
        return [
            'package' => $this->deprecation['package'],
            'version' => $this->deprecation['version'],
            'message' => str_replace('%alias_id%', $id, $this->deprecation['message']),
        ];
    }

    /**
     * Returns the Id of this alias.
     *
     * @return string The alias id
     */
    public function __toString()
    {
        return $this->id;
    }
}
