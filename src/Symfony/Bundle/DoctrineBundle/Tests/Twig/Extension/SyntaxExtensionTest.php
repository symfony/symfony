<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Twig\Extension;

use Symfony\Bundle\DoctrineBundle\Twig\Extension\SyntaxExtension;

class SyntaxExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected $extension;

    public function setUp()
    {
        $this->extension = new SyntaxExtension();
    }

    public function tearDown()
    {
        $this->extension = null;
    }

    public function testHighlightQueryKeywords()
    {
        $query = 'SELECT DISTINCT a.id, a.title, a.created_at, COUNT(c.id) AS nb_comments FROM article AS a LEFT JOIN comment AS c ON c.article_id = a.id WHERE a.id IN(1,2,3) GROUP BY a.id ORDER BY a.created_at DESC LIMIT 3';

        $expected = '<span class="keyword_sql">SELECT</span> <span class="keyword_sql">DISTINCT</span> a.id, a.title, a.created_at, <span class="keyword_sql">COUNT</span>(c.id) <span class="keyword_sql">AS</span> nb_comments <br /><span class="keyword_sql">FROM</span> article <span class="keyword_sql">AS</span> a <br /><span class="keyword_sql">LEFT JOIN</span> comment <span class="keyword_sql">AS</span> c <span class="keyword_sql">ON</span> c.article_id = a.id <br /><span class="keyword_sql">WHERE</span> a.id <span class="keyword_sql">IN</span>(1,2,3) <br /><span class="keyword_sql">GROUP BY</span> a.id <br /><span class="keyword_sql">ORDER BY</span> a.created_at <span class="keyword_sql">DESC</span> <span class="keyword_sql">LIMIT</span> 3';

        $this->assertEquals($expected, $this->extension->highlightQueryKeywords($query));
    }
}