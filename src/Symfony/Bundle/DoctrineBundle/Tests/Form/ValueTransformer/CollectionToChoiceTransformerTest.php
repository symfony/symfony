<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\DoctrineBundle\Form\ValueTransformer\CollectionToChoiceTransformer;

class CollectionToChoiceTransformerTest extends \Symfony\Bundle\DoctrineBundle\Tests\TestCase
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
        }
    }

    public function testCreateWithoutEntityManagerThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new CollectionToChoiceTransformer(array("className" => "Tag"));
    }

    public function testCreateWithoutClassNameThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\MissingOptionsException');
        $transformer = new CollectionToChoiceTransformer(array("em" => $this->em));
    }

    public function createTransformer()
    {
        return new CollectionToChoiceTransformer(array(
            "em" => $this->em,
            "className" => 'Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag'
        ));
    }

    public function testTransformEmpty()
    {
        $transformer = $this->createTransformer();
        $ids = $transformer->transform(new ArrayCollection());

        $this->assertEquals(array(), $ids);
    }

    public function createTagCollection()
    {
        $tags = new ArrayCollection();
        $tags->add(new Tag("foo"));
        $tags->add(new Tag("bar"));

        foreach ($tags AS $tag) {
            $this->em->persist($tag);
        }
        $this->em->flush();
        $this->em->clear();

        return $tags;
    }

    public function testTransform()
    {
        $transformer = $this->createTransformer();
        $ids = $transformer->transform($this->createTagCollection());

        $this->assertEquals(array(1, 2), $ids);
    }

    public function testReverseTransformEmpty()
    {
        $transformer = $this->createTransformer();

        $col = new ArrayCollection();

        $newCol = $transformer->reverseTransform(array(), $col);
        $this->assertSame($col, $newCol, "Collection is an expensive object, it should be re-used.");

        $this->assertEquals(0, count($newCol));
    }

    public function testReverseTransformEmptyClearsCollection()
    {
        $transformer = $this->createTransformer();

        $newCol = $transformer->reverseTransform(array(), $this->createTagCollection());
        $this->assertEquals(0, count($newCol));
    }

    public function testReverseTransformFetchFromEntityManager()
    {
        $transformer = $this->createTransformer();

        $col = new ArrayCollection();
        $tags = $this->createTagCollection();

        $newCol = $transformer->reverseTransform(array(1, 2), $col);
        $this->assertEquals(2, count($newCol));
    }

    public function testReverseTransformRemoveMissingFromCollection()
    {
        $transformer = $this->createTransformer();
        $tags = $this->createTagCollection();

        $newCol = $transformer->reverseTransform(array(1), $tags);
        $this->assertEquals(1, count($newCol));
        $this->assertFalse($newCol->contains($this->em->find('Symfony\Bundle\DoctrineBundle\Tests\Form\ValueTransformer\Tag', 2)));
    }
}