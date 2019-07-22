<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\BrowserKit\AbstractBrowser;

final class BrowserHasCookie extends Constraint
{
    private $name;
    private $path;
    private $domain;

    public function __construct(string $name, string $path = '/', string $domain = null)
    {
        $this->name = $name;
        $this->path = $path;
        $this->domain = $domain;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        $str = sprintf('has cookie "%s"', $this->name);
        if ('/' !== $this->path) {
            $str .= sprintf(' with path "%s"', $this->path);
        }
        if ($this->domain) {
            $str .= sprintf(' for domain "%s"', $this->domain);
        }

        return $str;
    }

    /**
     * @param AbstractBrowser $browser
     *
     * {@inheritdoc}
     */
    protected function matches($browser): bool
    {
        return null !== $browser->getCookieJar()->get($this->name, $this->path, $this->domain);
    }

    /**
     * @param AbstractBrowser $browser
     *
     * {@inheritdoc}
     */
    protected function failureDescription($browser): string
    {
        return 'the Browser '.$this->toString();
    }
}
