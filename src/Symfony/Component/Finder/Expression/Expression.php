<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Expression;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @since v2.2.0
 */
class Expression implements ValueInterface
{
    const TYPE_REGEX = 1;
    const TYPE_GLOB  = 2;

    /**
     * @var ValueInterface
     */
    private $value;

    /**
     * @param string $expr
     *
     * @return Expression
     *
     * @since v2.2.0
     */
    public static function create($expr)
    {
        return new self($expr);
    }

    /**
     * @param string $expr
     *
     * @since v2.2.0
     */
    public function __construct($expr)
    {
        try {
            $this->value = Regex::create($expr);
        } catch (\InvalidArgumentException $e) {
            $this->value = new Glob($expr);
        }
    }

    /**
     * @return string
     *
     * @since v2.2.0
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function render()
    {
        return $this->value->render();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function renderPattern()
    {
        return $this->value->renderPattern();
    }

    /**
     * @return bool
     *
     * @since v2.2.0
     */
    public function isCaseSensitive()
    {
        return $this->value->isCaseSensitive();
    }

    /**
     * @return int
     *
     * @since v2.2.0
     */
    public function getType()
    {
        return $this->value->getType();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function prepend($expr)
    {
        $this->value->prepend($expr);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function append($expr)
    {
        $this->value->append($expr);

        return $this;
    }

    /**
     * @return bool
     *
     * @since v2.2.0
     */
    public function isRegex()
    {
        return self::TYPE_REGEX === $this->value->getType();
    }

    /**
     * @return bool
     *
     * @since v2.2.0
     */
    public function isGlob()
    {
        return self::TYPE_GLOB === $this->value->getType();
    }

    /**
     * @throws \LogicException
     *
     * @return Glob
     *
     * @since v2.3.0
     */
    public function getGlob()
    {
        if (self::TYPE_GLOB !== $this->value->getType()) {
            throw new \LogicException('Regex cant be transformed to glob.');
        }

        return $this->value;
    }

    /**
     * @return Regex
     *
     * @since v2.2.0
     */
    public function getRegex()
    {
        return self::TYPE_REGEX === $this->value->getType() ? $this->value : $this->value->toRegex();
    }
}
