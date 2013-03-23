<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Factory;

use Symfony\Component\HttpFoundation\Factory\RequestFactoryInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class RequestFactory implements RequestFactoryInterface
{
    const REQUEST_CLASS = 'Symfony\Component\HttpFoundation\Request';

    /**
     * @var string
     */
    private $class;

    /**
     * Constructor.
     *
     * @param string $class
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($class = self::REQUEST_CLASS)
    {
        if ($class !== self::REQUEST_CLASS && !is_subclass_of($class, self::REQUEST_CLASS)) {
            throw new \InvalidArgumentException(sprintf('Request class "%s" must extend "%s" class.', $class, self::REQUEST_CLASS));
        }

        $this->class = $class;
    }

    /**
     * {@inheritdoc}
     */
    public function create($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        $callable = $this->class.'::create';

        return call_user_func($callable, $uri, $method, $parameters, $cookies, $files, $server, $content);
    }
}
