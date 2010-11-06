<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer;

use Symfony\Bundle\DoctrineBundle\Form\ValueTransformer\CollectionToStringTransformer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Tools\SchemaTool;

class CollectionToStringTransformerTest extends \Symfony\Bundle\DoctrineBundle\Tests\TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    public function setUp()
    {
        parent::setUp();
        $this->em = $this->createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array($this->em->getClassMetadata('Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag'));
        try {
            $schemaTool->dropSchema($classes);
        } catch(\Exception $e) {

        }
        try {
            $schemaTool->createSchema($classes);
        } catch(\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function testNoEntityManagerThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new CollectionToStringTransformer(array(
            'class_name' => 'Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag',
            'field_name' => 'name',
        ));
    }

    public function testNoClassNameThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new CollectionToStringTransformer(array(
            'field_name' => 'name',
            'em' => $this->em,
        ));
    }

    public function testNoFieldNameThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new CollectionToStringTransformer(array(
            'class_name' => 'Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag',
            'em' => $this->em,
        ));
    }

    public function createTransformer()
    {
        $transformer = new CollectionToStringTransformer(array(
            'class_name' => 'Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag',
            'field_name' => 'name',
            'em' => $this->em,
            'create_instance_callback' => function($tagName) {
                return new Tag($tagName);
            }
        ));
        return $transformer;
    }

    public function testTransformEmptyCollection()
    {
        $transformer = $this->createTransformer();
        $ret = $transformer->transform(new ArrayCollection());

        $this->assertEquals("", $ret);
    }

    /**
     * @depends testTransformEmptyCollection
     */
    public function testTransformCollection()
    {
        $transformer = $this->createTransformer();

        $tags = new ArrayCollection();
        $tags->add(new Tag("foo"));
        $tags->add(new Tag("bar"));
        
        $this->assertEquals("foo,bar", $transformer->transform($tags));
    }

    public function createTagCollection()
    {
        $tags = new ArrayCollection();
        $tags->add(new Tag("foo"));
        $tags->add(new Tag("bar"));

        return $tags;
    }

    /**
     * @depends testTransformEmptyCollection
     */
    public function testReverseTransformEmptyString()
    {
        $transformer = $this->createTransformer();

        $col = new ArrayCollection();

        $newCol = $transformer->reverseTransform("", $col);
        $this->assertSame($col, $newCol, "A collection is an expenive object that is re-used by the transformer!");
        $this->assertEquals(0, count($newCol));
    }

    /**
     * @depends testReverseTransformEmptyString
     */
    public function testReverseTransformEmptyStringEmptiesCollection()
    {
        $transformer = $this->createTransformer();

        $col = $this->createTagCollection();

        $newCol = $transformer->reverseTransform("", $col);
        $this->assertSame($col, $newCol, "A collection is an expenive object that is re-used by the transformer!");
        $this->assertEquals(0, count($newCol));
    }

    /**
     * @depends testTransformEmptyCollection
     */
    public function testReverseTransformUnchanged()
    {
        $transformer = $this->createTransformer();

        $tags = $this->createTagCollection();

        $tags = $transformer->reverseTransform("foo,bar", $tags);

        $this->assertEquals(2, count($tags));
    }

    /**
     * @depends testTransformEmptyCollection
     */
    public function testReverseTransformNewKnownEntity()
    {
        $transformer = $this->createTransformer();

        $newTag = new Tag("baz");
        $this->em->persist($newTag);
        $this->em->flush();

        $tags = $this->createTagCollection();
        $tags = $transformer->reverseTransform("foo, bar, baz", $tags);

        $this->assertEquals(3, count($tags));
        $this->assertTrue($tags->contains($newTag));
    }

    /**
     * @depends testReverseTransformNewKnownEntity
     */
    public function testReverseTransformNewUnknownEntity()
    {
        $transformer = $this->createTransformer();

        $tags = $this->createTagCollection();
        $tags = $transformer->reverseTransform("foo, bar, baz", $tags);

        $this->assertEquals(3, count($tags));
        $this->em->flush();

        $this->assertSame($this->em, $transformer->getOption('em'));

        $this->assertEquals(1, count($this->em->getRepository('Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag')->findAll()));
    }

    /**
     * @depends testReverseTransformNewUnknownEntity
     */
    public function testReverseTransformRemoveEntity()
    {
        $transformer = $this->createTransformer();

        $tags = $this->createTagCollection();
        $tags = $transformer->reverseTransform("foo", $tags);

        $this->assertEquals(1, count($tags));
    }

}

/** @Entity */
class Tag
{
    /** @Id @GeneratedValue @Column(type="integer") */
    public $id;

    /** @Column(type="string") */
    public $name;

    public function __construct($name) {
        $this->name = $name;
    }
}