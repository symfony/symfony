<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Request\ParamConverter;

use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\DoctrineBundle\Tests\TestCase;
use Symfony\Bundle\DoctrineBundle\Request\ParamConverter\DoctrineConverter;

class DoctrineConverterTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    protected function setUp()
    {
        parent::setUp();
        $this->em = $this->createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array($this->em->getClassMetadata(__NAMESPACE__.'\Article'));
        try {
            $schemaTool->dropSchema($classes);
        } catch(\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch(\Exception $e) {
        }
    }

    public function testSupports()
    {
        $converter = new DoctrineConverter($this->em);

        $this->assertTrue($converter->supports(new \ReflectionClass(__NAMESPACE__.'\Article')));
        $this->assertFalse($converter->supports(new \ReflectionClass('stdClass')));
    }

    public function testFindEntityByIdentifier()
    {
        $articles = $this->createArticleFixtures();
        $converter = new DoctrineConverter($this->em);
        $reflectionParam = new \ReflectionParameter(array(__NAMESPACE__.'\ArticleController', 'showAction'), 0);

        $request = $this->buildRequestWithAttributes(array('id' => $articles->get(2)->id));
        $converter->apply($request, $reflectionParam);

        $article = $request->attributes->get($reflectionParam->getName());
        $this->assertTrue($article instanceof Article);
        $this->assertEquals($articles->get(2), $article);
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testFindEntityByIdentifierWithInvalidId()
    {
        $articles = $this->createArticleFixtures();
        $converter = new DoctrineConverter($this->em);
        $reflectionParam = new \ReflectionParameter(array(__NAMESPACE__.'\ArticleController', 'showAction'), 0);

        $request = $this->buildRequestWithAttributes(array('id' => $articles->get(4)->id+1));
        $converter->apply($request, $reflectionParam);
    }

    public function testFindEntityByField()
    {
        $articles = $this->createArticleFixtures();
        $converter = new DoctrineConverter($this->em);
        $reflectionParam = new \ReflectionParameter(array(__NAMESPACE__.'\ArticleController', 'showAction'), 0);

        $request = $this->buildRequestWithAttributes(array('name' => $articles->get(4)->name));
        $converter->apply($request, $reflectionParam);

        $article = $request->attributes->get($reflectionParam->getName());
        $this->assertTrue($article instanceof Article);
        $this->assertEquals($articles->get(4), $article);
    }

    public function testFindEntityByFields()
    {
        $articles = $this->createArticleFixtures();
        $converter = new DoctrineConverter($this->em);
        $reflectionParam = new \ReflectionParameter(array(__NAMESPACE__.'\ArticleController', 'showAction'), 0);

        $request = $this->buildRequestWithAttributes(array(
            'name'      => $articles->get(2)->name,
            'author'    => $articles->get(2)->author,
        ));
        $converter->apply($request, $reflectionParam);

        $article = $request->attributes->get($reflectionParam->getName());
        $this->assertTrue($article instanceof Article);
        $this->assertEquals($articles->get(2), $article);
    }

    /**
     * @expectedException LogicException
     */
    public function testCannotFindEntityByFieldWithInvalidFieldName()
    {
        $articles = $this->createArticleFixtures();
        $converter = new DoctrineConverter($this->em);
        $reflectionParam = new \ReflectionParameter(array(__NAMESPACE__.'\ArticleController', 'showAction'), 0);

        $request = $this->buildRequestWithAttributes(array('title' => 'foo'));
        $converter->apply($request, $reflectionParam);
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testCannotFindEntityByFieldWithInvalidFieldValue()
    {
        $articles = $this->createArticleFixtures();
        $converter = new DoctrineConverter($this->em);
        $reflectionParam = new \ReflectionParameter(array(__NAMESPACE__.'\ArticleController', 'showAction'), 0);

        $request = $this->buildRequestWithAttributes(array('name' => 'foooo'));
        $converter->apply($request, $reflectionParam);
    }


    protected function createArticleFixtures()
    {
        $articles = new ArrayCollection();
        $articles->add(new Article('foo', 'toto'));
        $articles->add(new Article('bar', 'toto'));
        $articles->add(new Article('bar', 'titi'));
        $articles->add(new Article('foo', 'titi'));
        $articles->add(new Article('barfoo', 'tata'));

        foreach ($articles as $article) {
            $this->em->persist($article);
        }

        $this->em->flush();
        $this->em->clear();

        return $articles;
    }

    protected function buildRequestWithAttributes(array $attributes)
    {
        return new Request(null, null, $attributes);
    }
}

/**
 * @Entity
 */
class Article
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Column(type="string")
     */
    public $name;

    /**
     * @Column(type="string")
     */
    public $author;

    public function __construct($name, $author)
    {
        $this->name = $name;
        $this->author = $author;
    }
}

class ArticleController
{
    public function showAction(Article $article)
    {
    }
}
