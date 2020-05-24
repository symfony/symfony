<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * InputBag is a container for user input values such as $_GET, $_POST, $_REQUEST, and $_COOKIE.
 *
 * @author Saif Eddin Gmati <saif.gmati@symfony.com>
 */
final class InputBag extends ParameterBag
{
    /**
     * Returns a string input value by name.
     *
     * @param string|null $default The default value if the input key does not exist
     *
     * @return string|null
     */
    public function get(string $key, $default = null)
    {
        if (null !== $default && !is_scalar($default) && !(\is_object($default) && method_exists($default, '__toString'))) {
            trigger_deprecation('symfony/http-foundation', '5.1', 'Passing a non-string value as 2nd argument to "%s()" is deprecated, pass a string or null instead.', __METHOD__);
        }

        $value = parent::get($key, $this);

        if (null !== $value && $this !== $value && !is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            trigger_deprecation('symfony/http-foundation', '5.1', 'Retrieving a non-string value from "%s()" is deprecated, and will throw a "%s" exception in Symfony 6.0, use "%s::all()" instead.', __METHOD__, BadRequestException::class, __CLASS__);
        }

        return $this === $value ? $default : $value;
    }

    /**
     * Returns the inputs.
     *
     * @param string|null $key The name of the input to return or null to get them all
     */
    public function all(string $key = null): array
    {
        if (null === $key) {
            return $this->parameters;
        }

        $value = $this->parameters[$key] ?? [];
        if (!\is_array($value)) {
            throw new BadRequestException(sprintf('Unexpected value for "%s" input, expecting "array", got "%s".', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Replaces the current input values by a new set.
     */
    public function replace(array $inputs = [])
    {
        $this->parameters = [];
        $this->add($inputs);
    }

    /**
     * Adds input values.
     */
    public function add(array $inputs = [])
    {
        foreach ($inputs as $input => $value) {
            $this->set($input, $value);
        }
    }

    /**
     * Sets an input by name.
     *
     * @param string|array $value
     */
    public function set(string $key, $value)
    {
        if (!is_scalar($value) && !\is_array($value) && !method_exists($value, '__toString')) {
            trigger_deprecation('symfony/http-foundation', '5.1', 'Passing "%s" as a 2nd Argument to "%s()" is deprecated, pass a string or an array instead.', get_debug_type($value), __METHOD__);
        }

        $this->parameters[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(string $key, $default = null, int $filter = FILTER_DEFAULT, $options = [])
    {
        $value = $this->has($key) ? $this->all()[$key] : $default;

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (!\is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        if (\is_array($value) && !(($options['flags'] ?? 0) & (FILTER_REQUIRE_ARRAY | FILTER_FORCE_ARRAY))) {
            trigger_deprecation('symfony/http-foundation', '5.1', 'Filtering an array value with "%s()" without passing the FILTER_REQUIRE_ARRAY or FILTER_FORCE_ARRAY flag is deprecated', __METHOD__);

            if (!isset($options['flags'])) {
                $options['flags'] = FILTER_REQUIRE_ARRAY;
            }
        }

        return filter_var($value, $filter, $options);
    }
}
