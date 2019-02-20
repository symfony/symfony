<?php


namespace Symfony\Component\Serializer\Tests;


use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BuiltInDenormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

class SerializerEndToEndTest extends TestCase
{
    /** @var SerializerInterface */
    private $serializer;

    private function assertYamlStringEqualsYamlString($expected, $actual)
    {
        $expectedParsed = Yaml::parse($expected);
        $actualParsed = Yaml::parse($actual);

        $this->assertEquals($expectedParsed, $actualParsed, 'Failed to assert that ' .$actual.' is equal to '.$expected);
    }

    private function assertCsvStringEqualsCsvString($expected, $actual)
    {
        $expectedParsed = str_getcsv($expected);
        $actualParsed = str_getcsv($actual);

        $this->assertEquals($expectedParsed, $actualParsed, 'Failed to assert that ' .$actual.' is equal to '.$expected);
    }

    public function setUp()
    {
        $classMetadata = new ClassMetadataFactory(new LoaderChain([new AnnotationLoader(new AnnotationReader())]));
        $this->serializer = new Serializer([
            new JsonSerializableNormalizer(),
            new DateTimeNormalizer(),
            new ConstraintViolationListNormalizer(),
            new DateIntervalNormalizer(),
            new DataUriNormalizer(),
            new BuiltInDenormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                $classMetadata,
                new MetadataAwareNameConverter($classMetadata),
                new PropertyAccessor(false, false, new ArrayAdapter(0, false)),
                new PropertyInfoExtractor([
                    new SerializerExtractor($classMetadata),
                    new ReflectionExtractor(),
                ], [
                    new PhpDocExtractor(),
                    new ReflectionExtractor(),
                ], [
                    new PhpDocExtractor()
                ], [
                    new ReflectionExtractor()
                ], [
                    new ReflectionExtractor()
                ]),
                new ClassDiscriminatorFromClassMetadata($classMetadata)
            ),
        ], [
            new XmlEncoder(),
            new JsonEncoder(),
            new YamlEncoder(),
            new CsvEncoder()
        ]);
    }

    /**
     * @dataProvider data
     */
    public function testXml($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$xml) {
            $this->markTestSkipped(sprintf('Xml doesnt support this (%s) data type', $type));
        }
        $data = $this->serializer->serialize($object, 'xml', $context);
        $this->assertXmlStringEqualsXmlString($xml, $data);
    }

    /**
     * @dataProvider data
     */
    public function testDeserializeXml($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$xml) {
            $this->markTestSkipped(sprintf('Xml doesnt support this (%s) data type', $type));
        }
        $deserialized = $this->serializer->deserialize($xml, $type, 'xml', $context);
        $this->assertEquals($object, $deserialized);
    }

    /**
     * @dataProvider data
     */
    public function testJson($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$json) {
            $this->markTestSkipped(sprintf('Json doesnt support this (%s) data type', $type));
        }
        $data = $this->serializer->serialize($object, 'json', $context);
        $this->assertJsonStringEqualsJsonString($json, $data);
    }

    /**
     * @dataProvider data
     */
    public function testDeserializeJson($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$json) {
            $this->markTestSkipped(sprintf('Json doesnt support this (%s) data type', $type));
        }
        $deserialized = $this->serializer->deserialize($json, $type, 'json', $context);
        $this->assertEquals($object, $deserialized);
    }

    /**
     * @dataProvider data
     */
    public function testYaml($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$yaml) {
            $this->markTestSkipped(sprintf('Yaml doesnt support this (%s) data type', $type));
        }
        $data = $this->serializer->serialize($object, 'yaml', $context);
        $this->assertYamlStringEqualsYamlString($yaml, $data);
    }

    /**
     * @dataProvider data
     */
    public function testDeserializeYaml($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$yaml) {
            $this->markTestSkipped(sprintf('Yaml doesnt support this (%s) data type', $type));
        }
        $deserialized = $this->serializer->deserialize($yaml, $type, 'yaml', $context);
        $this->assertEquals($object, $deserialized);
    }

    /**
     * @dataProvider data
     */
    public function testCsv($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$csv) {
            $this->markTestSkipped(sprintf('Csv doesnt support this (%s) data type', $type));
        }
        $data = $this->serializer->serialize($object, 'csv', array_merge([
            'as_collection' => true,
        ], $context));
        $this->assertCsvStringEqualsCsvString($csv, $data);
    }

    /**
     * @dataProvider data
     */
    public function testDeserializeCsv($object, $type, $context, $xml, $json, $yaml, $csv)
    {
        if (!$csv) {
            $this->markTestSkipped(sprintf('Csv doesnt support this (%s) data type', $type));
        }
        $deserialized = $this->serializer->deserialize($csv, $type, 'csv', array_merge([
            'as_collection' => true,
        ], $context));
        $this->assertEquals($object, $deserialized);
    }

    public function data() {
        return [
            'simple string' => [
                'hello',
                'string',
                [CsvEncoder::AS_COLLECTION_KEY => false],
                <<<XML
<?xml version="1.0"?>
<response>hello</response>

XML
                , <<<JSON
"hello"
JSON
                , <<<YAML
hello
YAML
                , null
            ],
            'string array' => [
                ['hello', 'world'],
                'string[]',
                [],
                <<<XML
<?xml version="1.0"?>
<response>
  <item key="0">hello</item>
  <item key="1">world</item>
</response>

XML
                , <<<JSON
["hello", "world"]
JSON
                , <<<YAML
- hello
- world
YAML
                , null
            ],
            'baz' => [
                new Baz('baz'),
                Baz::class,
                [CsvEncoder::AS_COLLECTION_KEY => false],
                <<<XML
<?xml version="1.0"?>
<response>
  <name>baz</name>
</response>

XML
                , <<<JSON
{
  "name":"baz"
}
JSON
                , <<<YAML
name: baz
YAML
                , <<<CSV
name
baz

CSV
            ],
            'baz array' => [
                [new Baz('baz')],
                Baz::class.'[]',
                [],
                <<<XML
<?xml version="1.0"?>
<response>
  <item key="0">
    <name>baz</name>
  </item>
</response>

XML
                , <<<JSON
[
  {
    "name":"baz"
  }
]
JSON
                , <<<YAML
- name: baz
YAML
                , <<<CSV
name
baz

CSV
            ],
            'string keys' => [
                ['first' => new Baz('baz'), 'second' => new Baz('baz2')],
                Baz::class.'[]',
                ['key_types' => [new Type(Type::BUILTIN_TYPE_STRING)]],
                null, <<<JSON
{
  "first": {
    "name": "baz"
  },
  "second": {
    "name": "baz2"
  }
}
JSON
                , <<<YAML
first:
  name: baz
second:
  name: baz2

YAML
                , null
            ],
            'string keys in object' => [
                new Quz(['a' => 'a', 'b' => 'b']),
                Quz::class,
                [CsvEncoder::AS_COLLECTION_KEY => false],
                null
                , <<<JSON
{
  "data": {
    "a": "a",
    "b": "b"
  }
}
JSON
                , <<<YAML
data:
  a: a
  b: b

YAML
                , <<<CSV
data.a,data.b
a,b

CSV
            ],
            'qaz' => $this->qazData()
        ];
    }

    private function qazData(): array
    {
        $qaz = new Qaz();
        $qaz->setQazString('foo');
        $qaz->setQazStringEmpty('');
        $qaz->setQazInt(123);
        $qaz->setQazFloat(3.14);
        $qaz->setQazBool(true);
        $qaz->setQazBoolFalse(false);
        $qaz->setQazDate(\DateTime::createFromFormat(DATE_ISO8601, '2019-01-17T12:05:06+02:00'));
        $qaz->setQazDateImmut(\DateTimeImmutable::createFromFormat(DATE_ISO8601, '2019-02-17T12:05:06+02:00'));
        $qaz->setQazInterval(new \DateInterval('P1D'));
        $qaz->setQazData(new \SplFileObject('data:,Hello%2C%20World!'));
        $qaz->setQazBaz(new Baz('baz1'));
        $qaz->setQazArray([1, 2.1, true]);
        $qaz->setQazStringArray(['foo', 'bar']);
        $qaz->setQazIntArray([1, 2, 3]);
        $qaz->setQazFloatArray([1.1, 2.2, 3.3]);
        $qaz->setQazBoolArray([true, false, true]);
        $qaz->setQazDateArray([\DateTime::createFromFormat(DATE_ISO8601, '2019-03-17T12:05:06+02:00'), \DateTime::createFromFormat(DATE_ISO8601, '2020-04-17T12:05:06+02:00')]);
        $qaz->setQazBazArray([new Baz('baz2'), new Baz('baz3')]);
        $qaz->setQazBazArrayOneItem([new Baz('baz4')]);
        $qaz->setQazArrayArray([[15, 16, 1.7], [19, 2.0, 2.1]]);
        $qaz->setQazStringArrayArray([['foo2', 'bar2'], ['baz2', 'qaz2']]);
        $qaz->setQazIntArrayArray([[4, 5, 6], [7, 8, 9]]);
        $qaz->setQazFloatArrayArray([[4.4, 5.5, 6.6], [7.7, 8.8, 9.9]]);
        $qaz->setQazBoolArrayArray([[true, true, false], [false, false, true]]);
        $qaz->setQazDateArrayArray([[\DateTime::createFromFormat(DATE_ISO8601, '2019-05-17T12:05:06+02:00')], [\DateTime::createFromFormat(DATE_ISO8601, '2020-06-17T12:05:06+02:00')]]);
        $qaz->setQazBazArrayArray([[new Baz('baz5'), new Baz('baz6')], [new Baz('baz7'), new Baz('baz8')]]);
        $qaz->setQazBazArrayArrayOneItem([[new Baz('baz9')]]);

        return [
            $qaz,
            Qaz::class,
            [CsvEncoder::AS_COLLECTION_KEY => false],
            <<<XML
<?xml version="1.0"?>
<response>
  <qazstring>foo</qazstring>
  <qazstringempty></qazstringempty>
  <qazint>123</qazint>
  <qazfloat>3.14</qazfloat>
  <qazbool>1</qazbool>
  <qazboolfalse>0</qazboolfalse>
  <qazdate>2019-01-17t12:05:06+02:00</qazdate>
  <qazdateimmut>2019-02-17t12:05:06+02:00</qazdateimmut>
  <qazinterval>P0Y0M1DT0H0M0S</qazinterval>
  <qazdata>data:application/octet-stream;base64,sgvsbg8sifdvcmxkiq==</qazdata>
  <qazbaz>
    <name>baz1</name>
  </qazbaz>
  <qazarray>1</qazarray>
  <qazarray>2.1</qazarray>
  <qazarray>1</qazarray>
  <qazstringarray>foo</qazstringarray>
  <qazstringarray>bar</qazstringarray>
  <qazintarray>1</qazintarray>
  <qazintarray>2</qazintarray>
  <qazintarray>3</qazintarray>
  <qazfloatarray>1.1</qazfloatarray>
  <qazfloatarray>2.2</qazfloatarray>
  <qazfloatarray>3.3</qazfloatarray>
  <qazboolarray>1</qazboolarray>
  <qazboolarray>0</qazboolarray>
  <qazboolarray>1</qazboolarray>
  <qazdatearray>2019-03-17t12:05:06+02:00</qazdatearray>
  <qazdatearray>2020-04-17t12:05:06+02:00</qazdatearray>
  <qazbazarray>
    <name>baz2</name>
  </qazbazarray>
  <qazbazarray>
    <name>baz3</name>
  </qazbazarray>
  <qazbazarrayoneitem>
    <name>baz4</name>
  </qazbazarrayoneitem>
  <qazarrayarray>
    <item key="0">15</item>
    <item key="1">16</item>
    <item key="2">1.7</item>
  </qazarrayarray>
  <qazarrayarray>
    <item key="0">19</item>
    <item key="1">2</item>
    <item key="2">2.1</item>
  </qazarrayarray>
  <qazstringarrayarray>
    <item key="0">foo2</item>
    <item key="1">bar2</item>
  </qazstringarrayarray>
  <qazstringarrayarray>
    <item key="0">baz2</item>
    <item key="1">qaz2</item>
  </qazstringarrayarray>
  <qazintarrayarray>
    <item key="0">4</item>
    <item key="1">5</item>
    <item key="2">6</item>
  </qazintarrayarray>
  <qazintarrayarray>
    <item key="0">7</item>
    <item key="1">8</item>
    <item key="2">9</item>
  </qazintarrayarray>
  <qazfloatarrayarray>
    <item key="0">4.4</item>
    <item key="1">5.5</item>
    <item key="2">6.6</item>
  </qazfloatarrayarray>
  <qazfloatarrayarray>
    <item key="0">7.7</item>
    <item key="1">8.8</item>
    <item key="2">9.9</item>
  </qazfloatarrayarray>
  <qazboolarrayarray>
    <item key="0">1</item>
    <item key="1">1</item>
    <item key="2">0</item>
  </qazboolarrayarray>
  <qazboolarrayarray>
    <item key="0">0</item>
    <item key="1">0</item>
    <item key="2">1</item>
  </qazboolarrayarray>
  <qazdatearrayarray>
    <item key="0">2019-05-17t12:05:06+02:00</item>
  </qazdatearrayarray>
  <qazdatearrayarray>
    <item key="0">2020-06-17t12:05:06+02:00</item>
  </qazdatearrayarray>
  <qazbazarrayarray>
    <item key="0">
      <name>baz5</name>
    </item>
    <item key="1">
      <name>baz6</name>
    </item>
  </qazbazarrayarray>
  <qazbazarrayarray>
    <item key="0">
      <name>baz7</name>
    </item>
    <item key="1">
      <name>baz8</name>
    </item>
  </qazbazarrayarray>
  <qazbazarrayarrayoneitem>
    <item key="0">
      <name>baz9</name>
    </item>
  </qazbazarrayarrayoneitem>
  <qazstringoption/>
  <qazintoption/>
  <qazdateoption/>
  <qazbazoption/>
</response>

XML
            , <<<JSON
{
  "qazString": "foo",
  "qazStringEmpty": "",
  "qazInt": 123,
  "qazFloat": 3.14,
  "qazBool": true,
  "qazBoolFalse": false,
  "qazDate": "2019-01-17T12:05:06+02:00",
  "qazDateImmut": "2019-02-17T12:05:06+02:00",
  "qazInterval": "P0Y0M1DT0H0M0S",
  "qazData": "data:application/octet-stream;base64,SGVsbG8sIFdvcmxkIQ==",
  "qazBaz": {
    "name": "baz1"
  },
  "qazArray": [
    1,
    2.1,
    true
  ],
  "qazStringArray": [
    "foo",
    "bar"
  ],
  "qazIntArray": [
    1,
    2,
    3
  ],
  "qazFloatArray": [
    1.1,
    2.2,
    3.3
  ],
  "qazBoolArray": [
    true,
    false,
    true
  ],
  "qazDateArray": [
    "2019-03-17T12:05:06+02:00",
    "2020-04-17T12:05:06+02:00"
  ],
  "qazBazArray": [
    {
      "name": "baz2"
    },
    {
      "name": "baz3"
    }
  ],
  "qazBazArrayOneItem": [
    {
      "name": "baz4"
    }
  ],
  "qazArrayArray": [
    [
      15,
      16,
      1.7
    ],
    [
      19,
      2,
      2.1
    ]
  ],
  "qazStringArrayArray": [
    [
      "foo2",
      "bar2"
    ],
    [
      "baz2",
      "qaz2"
    ]
  ],
  "qazIntArrayArray": [
    [
      4,
      5,
      6
    ],
    [
      7,
      8,
      9
    ]
  ],
  "qazFloatArrayArray": [
    [
      4.4,
      5.5,
      6.6
    ],
    [
      7.7,
      8.8,
      9.9
    ]
  ],
  "qazBoolArrayArray": [
    [
      true,
      true,
      false
    ],
    [
      false,
      false,
      true
    ]
  ],
  "qazDateArrayArray": [
    [
      "2019-05-17T12:05:06+02:00"
    ],
    [
      "2020-06-17T12:05:06+02:00"
    ]
  ],
  "qazBazArrayArray": [
    [
      {
        "name": "baz5"
      },
      {
        "name": "baz6"
      }
    ],
    [
      {
        "name": "baz7"
      },
      {
        "name": "baz8"
      }
    ]
  ],
  "qazBazArrayArrayOneItem": [
    [
      {
        "name": "baz9"
      }
    ]
  ],
  "qazStringOption": null,
  "qazIntOption": null,
  "qazDateOption": null,
  "qazBazOption": null
}

JSON
            , <<<YAML
qazString: foo
qazStringEmpty: ''
qazInt: 123
qazFloat: 3.14
qazBool: true
qazBoolFalse: false
qazDate: '2019-01-17T12:05:06+02:00'
qazDateImmut: '2019-02-17T12:05:06+02:00'
qazInterval: P0Y0M1DT0H0M0S
qazData: 'data:application/octet-stream;base64,SGVsbG8sIFdvcmxkIQ=='
qazBaz:
  name: baz1
qazArray:
  - 1
  - 2.1
  - true
qazStringArray:
  - foo
  - bar
qazIntArray:
  - 1
  - 2
  - 3
qazFloatArray:
  - 1.1
  - 2.2
  - 3.3
qazBoolArray:
  - true
  - false
  - true
qazDateArray:
  - '2019-03-17T12:05:06+02:00'
  - '2020-04-17T12:05:06+02:00'
qazBazArray:
  -
    name: baz2
  -
    name: baz3
qazBazArrayOneItem:
  -
    name: baz4
qazArrayArray:
  -
    - 15
    - 16
    - 1.7
  -
    - 19
    - 2
    - 2.1
qazStringArrayArray:
  -
    - foo2
    - bar2
  -
    - baz2
    - qaz2
qazIntArrayArray:
  -
    - 4
    - 5
    - 6
  -
    - 7
    - 8
    - 9
qazFloatArrayArray:
  -
    - 4.4
    - 5.5
    - 6.6
  -
    - 7.7
    - 8.8
    - 9.9
qazBoolArrayArray:
  -
    - true
    - true
    - false
  -
    - false
    - false
    - true
qazDateArrayArray:
  -
    - '2019-05-17T12:05:06+02:00'
  -
    - '2020-06-17T12:05:06+02:00'
qazBazArrayArray:
  -
    -
      name: baz5
    -
      name: baz6
  -
    -
      name: baz7
    -
      name: baz8
qazBazArrayArrayOneItem:
  -
    -
      name: baz9
qazStringOption: null
qazIntOption: null
qazDateOption: null
qazBazOption: null

YAML

            , <<<CSV
qazString,qazStringEmpty,qazInt,qazFloat,qazBool,qazBoolFalse,qazDate,qazDateImmut,qazInterval,qazData,qazBaz.name,qazArray.0,qazArray.1,qazArray.2,qazStringArray.0,qazStringArray.1,qazIntArray.0,qazIntArray.1,qazIntArray.2,qazFloatArray.0,qazFloatArray.1,qazFloatArray.2,qazBoolArray.0,qazBoolArray.1,qazBoolArray.2,qazDateArray.0,qazDateArray.1,qazBazArray.0.name,qazBazArray.1.name,qazBazArrayOneItem.0.name,qazArrayArray.0.0,qazArrayArray.0.1,qazArrayArray.0.2,qazArrayArray.1.0,qazArrayArray.1.1,qazArrayArray.1.2,qazStringArrayArray.0.0,qazStringArrayArray.0.1,qazStringArrayArray.1.0,qazStringArrayArray.1.1,qazIntArrayArray.0.0,qazIntArrayArray.0.1,qazIntArrayArray.0.2,qazIntArrayArray.1.0,qazIntArrayArray.1.1,qazIntArrayArray.1.2,qazFloatArrayArray.0.0,qazFloatArrayArray.0.1,qazFloatArrayArray.0.2,qazFloatArrayArray.1.0,qazFloatArrayArray.1.1,qazFloatArrayArray.1.2,qazBoolArrayArray.0.0,qazBoolArrayArray.0.1,qazBoolArrayArray.0.2,qazBoolArrayArray.1.0,qazBoolArrayArray.1.1,qazBoolArrayArray.1.2,qazDateArrayArray.0.0,qazDateArrayArray.1.0,qazBazArrayArray.0.0.name,qazBazArrayArray.0.1.name,qazBazArrayArray.1.0.name,qazBazArrayArray.1.1.name,qazBazArrayArrayOneItem.0.0.name,qazStringOption,qazIntOption,qazDateOption,qazBazOption
foo,,123,3.14,1,0,2019-01-17T12:05:06+02:00,2019-02-17T12:05:06+02:00,P0Y0M1DT0H0M0S,"data:application/octet-stream;base64,SGVsbG8sIFdvcmxkIQ==",baz1,1,2.1,1,foo,bar,1,2,3,1.1,2.2,3.3,1,0,1,2019-03-17T12:05:06+02:00,2020-04-17T12:05:06+02:00,baz2,baz3,baz4,15,16,1.7,19,2,2.1,foo2,bar2,baz2,qaz2,4,5,6,7,8,9,4.4,5.5,6.6,7.7,8.8,9.9,1,1,0,0,0,1,2019-05-17T12:05:06+02:00,2020-06-17T12:05:06+02:00,baz5,baz6,baz7,baz8,baz9,,,,

CSV
        ];
    }
}

class Baz
{
    /**
     * @var string
     */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}

class Qaz
{
    /**
     * @var string
     */
    private $qazString;

    /**
     * @var string
     */
    private $qazStringEmpty;

    /**
     * @var int
     */
    private $qazInt;

    /**
     * @var float
     */
    private $qazFloat;

    /**
     * @var bool
     */
    private $qazBool;

    /**
     * @var bool
     */
    private $qazBoolFalse;

    /**
     * @var \DateTime
     */
    private $qazDate;

    /**
     * @var \DateTimeImmutable
     */
    private $qazDateImmut;

    /**
     * @var \DateInterval
     */
    private $qazInterval;

    /**
     * @var \SplFileInfo
     */
    private $qazData;

    /**
     * @var Baz
     */
    private $qazBaz;

    /**
     * @var array
     */
    private $qazArray;

    /**
     * @var string[]
     */
    private $qazStringArray;

    /**
     * @var int[]
     */
    private $qazIntArray;

    /**
     * @var float[]
     */
    private $qazFloatArray;

    /**
     * @var bool[]
     */
    private $qazBoolArray;

    /**
     * @var \DateTime[]
     */
    private $qazDateArray;

    /**
     * @var Baz[]
     */
    private $qazBazArray;

    /**
     * @var Baz[]
     */
    private $qazBazArrayOneItem;

    /**
     * @var array
     */
    private $qazArrayArray;

    /**
     * @var string[][]
     */
    private $qazStringArrayArray;

    /**
     * @var int[][]
     */
    private $qazIntArrayArray;

    /**
     * @var float[][]
     */
    private $qazFloatArrayArray;

    /**
     * @var bool[][]
     */
    private $qazBoolArrayArray;

    /**
     * @var \DateTime[][]
     */
    private $qazDateArrayArray;

    /**
     * @var Baz[][]
     */
    private $qazBazArrayArray;

    /**
     * @var Baz[][]
     */
    private $qazBazArrayArrayOneItem;

    /**
     * @var ?string
     */
    private $qazStringOption;

    /**
     * @var ?int
     */
    private $qazIntOption;

    /**
     * @var ?\DateTime
     */
    private $qazDateOption;

    /**
     * @var ?Baz
     */
    private $qazBazOption;

    public function getQazString(): string
    {
        return $this->qazString;
    }

    public function setQazString(string $qazString): void
    {
        $this->qazString = $qazString;
    }

    public function getQazStringEmpty(): string
    {
        return $this->qazStringEmpty;
    }

    public function setQazStringEmpty(string $qazStringEmpty): void
    {
        $this->qazStringEmpty = $qazStringEmpty;
    }

    public function getQazInt(): int
    {
        return $this->qazInt;
    }

    public function setQazInt(int $qazInt): void
    {
        $this->qazInt = $qazInt;
    }

    public function getQazFloat(): float
    {
        return $this->qazFloat;
    }

    public function setQazFloat(float $qazFloat): void
    {
        $this->qazFloat = $qazFloat;
    }

    public function isQazBool(): bool
    {
        return $this->qazBool;
    }

    public function setQazBool(bool $qazBool): void
    {
        $this->qazBool = $qazBool;
    }

    public function isQazBoolFalse(): bool
    {
        return $this->qazBoolFalse;
    }

    public function setQazBoolFalse(bool $qazBoolFalse): void
    {
        $this->qazBoolFalse = $qazBoolFalse;
    }

    public function getQazDate(): \DateTime
    {
        return $this->qazDate;
    }

    public function setQazDate(\DateTime $qazDate): void
    {
        $this->qazDate = $qazDate;
    }

    public function getQazDateImmut(): \DateTimeImmutable
    {
        return $this->qazDateImmut;
    }

    public function setQazDateImmut(\DateTimeImmutable $qazDateImmut): void
    {
        $this->qazDateImmut = $qazDateImmut;
    }

    public function getQazInterval(): \DateInterval
    {
        return $this->qazInterval;
    }

    public function setQazInterval(\DateInterval $qazInterval): void
    {
        $this->qazInterval = $qazInterval;
    }

    public function getQazData(): \SplFileInfo
    {
        return $this->qazData;
    }

    public function setQazData(\SplFileInfo $qazData): void
    {
        $this->qazData = $qazData;
    }

    public function getQazBaz(): Baz
    {
        return $this->qazBaz;
    }

    public function setQazBaz(Baz $qazBaz): void
    {
        $this->qazBaz = $qazBaz;
    }

    public function getQazArray(): array
    {
        return $this->qazArray;
    }

    public function setQazArray(array $qazArray): void
    {
        $this->qazArray = $qazArray;
    }

    /**
     * @return string[]
     */
    public function getQazStringArray(): array
    {
        return $this->qazStringArray;
    }

    /**
     * @param string[] $qazStringArray
     */
    public function setQazStringArray(array $qazStringArray): void
    {
        $this->qazStringArray = $qazStringArray;
    }

    /**
     * @return int[]
     */
    public function getQazIntArray(): array
    {
        return $this->qazIntArray;
    }

    /**
     * @param int[] $qazIntArray
     */
    public function setQazIntArray(array $qazIntArray): void
    {
        $this->qazIntArray = $qazIntArray;
    }

    /**
     * @return float[]
     */
    public function getQazFloatArray(): array
    {
        return $this->qazFloatArray;
    }

    /**
     * @param float[] $qazFloatArray
     */
    public function setQazFloatArray(array $qazFloatArray): void
    {
        $this->qazFloatArray = $qazFloatArray;
    }

    /**
     * @return bool[]
     */
    public function getQazBoolArray(): array
    {
        return $this->qazBoolArray;
    }

    /**
     * @param bool[] $qazBoolArray
     */
    public function setQazBoolArray(array $qazBoolArray): void
    {
        $this->qazBoolArray = $qazBoolArray;
    }

    /**
     * @return \DateTime[]
     */
    public function getQazDateArray(): array
    {
        return $this->qazDateArray;
    }

    /**
     * @param \DateTime[] $qazDateArray
     */
    public function setQazDateArray(array $qazDateArray): void
    {
        $this->qazDateArray = $qazDateArray;
    }

    /**
     * @return Baz[]
     */
    public function getQazBazArray(): array
    {
        return $this->qazBazArray;
    }

    /**
     * @param Baz[] $qazBazArray
     */
    public function setQazBazArray(array $qazBazArray): void
    {
        $this->qazBazArray = $qazBazArray;
    }

    /**
     * @return Baz[]
     */
    public function getQazBazArrayOneItem(): array
    {
        return $this->qazBazArrayOneItem;
    }

    /**
     * @param Baz[] $qazBazArrayOneItem
     */
    public function setQazBazArrayOneItem(array $qazBazArrayOneItem): void
    {
        $this->qazBazArrayOneItem = $qazBazArrayOneItem;
    }

    /**
     * @return array
     */
    public function getQazArrayArray(): array
    {
        return $this->qazArrayArray;
    }

    /**
     * @param array $qazArrayArray
     */
    public function setQazArrayArray(array $qazArrayArray): void
    {
        $this->qazArrayArray = $qazArrayArray;
    }

    /**
     * @return string[][]
     */
    public function getQazStringArrayArray(): array
    {
        return $this->qazStringArrayArray;
    }

    /**
     * @param string[][] $qazStringArrayArray
     */
    public function setQazStringArrayArray(array $qazStringArrayArray): void
    {
        $this->qazStringArrayArray = $qazStringArrayArray;
    }

    /**
     * @return int[][]
     */
    public function getQazIntArrayArray(): array
    {
        return $this->qazIntArrayArray;
    }

    /**
     * @param int[][] $qazIntArrayArray
     */
    public function setQazIntArrayArray(array $qazIntArrayArray): void
    {
        $this->qazIntArrayArray = $qazIntArrayArray;
    }

    /**
     * @return float[][]
     */
    public function getQazFloatArrayArray(): array
    {
        return $this->qazFloatArrayArray;
    }

    /**
     * @param float[][] $qazFloatArrayArray
     */
    public function setQazFloatArrayArray(array $qazFloatArrayArray): void
    {
        $this->qazFloatArrayArray = $qazFloatArrayArray;
    }

    /**
     * @return bool[][]
     */
    public function getQazBoolArrayArray(): array
    {
        return $this->qazBoolArrayArray;
    }

    /**
     * @param bool[][] $qazBoolArrayArray
     */
    public function setQazBoolArrayArray(array $qazBoolArrayArray): void
    {
        $this->qazBoolArrayArray = $qazBoolArrayArray;
    }

    /**
     * @return \DateTime[][]
     */
    public function getQazDateArrayArray(): array
    {
        return $this->qazDateArrayArray;
    }

    /**
     * @param \DateTime[][] $qazDateArrayArray
     */
    public function setQazDateArrayArray(array $qazDateArrayArray): void
    {
        $this->qazDateArrayArray = $qazDateArrayArray;
    }

    /**
     * @return Baz[][]
     */
    public function getQazBazArrayArray(): array
    {
        return $this->qazBazArrayArray;
    }

    /**
     * @param Baz[][] $qazBazArrayArray
     */
    public function setQazBazArrayArray(array $qazBazArrayArray): void
    {
        $this->qazBazArrayArray = $qazBazArrayArray;
    }

    /**
     * @return Baz[][]
     */
    public function getQazBazArrayArrayOneItem(): array
    {
        return $this->qazBazArrayArrayOneItem;
    }

    /**
     * @param Baz[][] $qazBazArrayArrayOneItem
     */
    public function setQazBazArrayArrayOneItem(array $qazBazArrayArrayOneItem): void
    {
        $this->qazBazArrayArrayOneItem = $qazBazArrayArrayOneItem;
    }

    public function getQazStringOption(): ?string
    {
        return $this->qazStringOption;
    }

    public function setQazStringOption(?string $qazStringOption): void
    {
        $this->qazStringOption = $qazStringOption;
    }

    public function getQazIntOption(): ?int
    {
        return $this->qazIntOption;
    }

    public function setQazIntOption(?int $qazIntOption): void
    {
        $this->qazIntOption = $qazIntOption;
    }

    public function getQazDateOption(): ?\DateTime
    {
        return $this->qazDateOption;
    }

    public function setQazDateOption(?\DateTime $qazDateOption): void
    {
        $this->qazDateOption = $qazDateOption;
    }

    public function getQazBazOption(): ?Baz
    {
        return $this->qazBazOption;
    }

    public function setQazBazOption(?Baz $qazBazOption): void
    {
        $this->qazBazOption = $qazBazOption;
    }
}

class Quz
{
    /**
     * @var string[]
     */
    private $data;

    /**
     * @param string[] $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return string[]
     */
    public function getData(): array
    {
        return $this->data;
    }
}
