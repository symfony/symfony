<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Twig\Extension;

/**
 * SyntaxExtension class
 *
 * @package DoctrineBundle
 * @subpackage Extension
 * @author William Durand <william.durand1@gmail.com>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 */
class SyntaxExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return array(
            'highlight_sql' => new \Twig_Function_Method($this, 'highlightQueryKeywords', array('is_safe' => array('html'))),
        );
    }

    public function getName()
    {
        return 'doctrine_syntax_extension';
    }

    public function highlightQueryKeywords($sql)
    {
        $sql = preg_replace('/\b(UPDATE|SET|SELECT|AS|LIMIT|ASC|COUNT|DESC|IN|LIKE|DISTINCT|DELETE|INSERT|INTO|VALUES|ON|AND|OR)\b/', '<span class="keyword_sql">\\1</span>', $sql);

        return preg_replace('/\b(FROM|WHERE|INNER JOIN|LEFT JOIN|RIGHT JOIN|ORDER BY|GROUP BY)\b/', '<br /><span class="keyword_sql">\\1</span>', $sql);
    }
}