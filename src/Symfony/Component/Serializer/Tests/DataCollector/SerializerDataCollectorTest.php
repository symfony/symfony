<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class SerializerDataCollectorTest extends TestCase
{
    public function testCollectSerialize()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectSerialize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->collectDeserialize('traceIdTwo', 'data', 'type', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');

        $dataCollector->lateCollect();
        $collectedData = $this->castCollectedData($dataCollector->getData());

        $this->assertSame([[
            'data' => 'data',
            'dataType' => 'string',
            'type' => null,
            'format' => 'format',
            'time' => 1.0,
            'context' => ['foo' => 'bar'],
            'normalization' => [],
            'encoding' => [],
            'caller' => $caller,
            'name' => 'default',
        ]], $collectedData['serialize']);

        $this->assertSame([[
            'data' => 'data',
            'dataType' => 'string',
            'type' => 'type',
            'format' => 'format',
            'time' => 1.0,
            'context' => ['foo' => 'bar'],
            'normalization' => [],
            'encoding' => [],
            'caller' => $caller,
            'name' => 'default',
        ]], $collectedData['deserialize']);
    }

    public function testCollectNormalize()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectNormalize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->collectDenormalize('traceIdTwo', 'data', 'type', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');

        $dataCollector->lateCollect();
        $collectedData = $this->castCollectedData($dataCollector->getData());

        $this->assertSame([[
            'data' => 'data',
            'dataType' => 'string',
            'type' => null,
            'format' => 'format',
            'time' => 1.0,
            'context' => ['foo' => 'bar'],
            'normalization' => [],
            'encoding' => [],
            'caller' => $caller,
            'name' => 'default',
        ]], $collectedData['normalize']);

        $this->assertSame([[
            'data' => 'data',
            'dataType' => 'string',
            'type' => 'type',
            'format' => 'format',
            'time' => 1.0,
            'context' => ['foo' => 'bar'],
            'normalization' => [],
            'encoding' => [],
            'caller' => $caller,
            'name' => 'default',
        ]], $collectedData['denormalize']);
    }

    public function testCollectEncode()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectEncode('traceIdOne', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->collectDecode('traceIdTwo', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');

        $dataCollector->lateCollect();
        $collectedData = $this->castCollectedData($dataCollector->getData());

        $this->assertSame([[
            'data' => 'data',
            'dataType' => 'string',
            'type' => null,
            'format' => 'format',
            'time' => 1.0,
            'context' => ['foo' => 'bar'],
            'normalization' => [],
            'encoding' => [],
            'caller' => $caller,
            'name' => 'default',
        ]], $collectedData['encode']);

        $this->assertSame([[
            'data' => 'data',
            'dataType' => 'string',
            'type' => null,
            'format' => 'format',
            'time' => 1.0,
            'context' => ['foo' => 'bar'],
            'normalization' => [],
            'encoding' => [],
            'caller' => $caller,
            'name' => 'default',
        ]], $collectedData['decode']);
    }

    public function testCollectNormalization()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectNormalize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');
        $dataCollector->collectDenormalize('traceIdTwo', 'data', 'type', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');

        $dataCollector->collectNormalization('traceIdOne', DateTimeNormalizer::class, 1.0, 'default');
        $dataCollector->collectNormalization('traceIdOne', DateTimeNormalizer::class, 2.0, 'default');
        $dataCollector->collectNormalization('traceIdOne', ObjectNormalizer::class, 5.0, 'default');
        $dataCollector->collectNormalization('traceIdOne', ObjectNormalizer::class, 10.0, 'default');

        $dataCollector->collectNormalization('traceIdTwo', DateTimeNormalizer::class, 1.0, 'default');
        $dataCollector->collectNormalization('traceIdTwo', DateTimeNormalizer::class, 2.0, 'default');
        $dataCollector->collectNormalization('traceIdTwo', ObjectNormalizer::class, 5.0, 'default');
        $dataCollector->collectNormalization('traceIdTwo', ObjectNormalizer::class, 10.0, 'default');

        $dataCollector->lateCollect();
        $collectedData = $dataCollector->getData();

        $this->assertSame(10.0, $collectedData['normalize'][0]['normalizer']['time']);
        $this->assertSame('ObjectNormalizer', $collectedData['normalize'][0]['normalizer']['class']);
        $this->assertArrayHasKey('file', $collectedData['normalize'][0]['normalizer']);
        $this->assertArrayHasKey('line', $collectedData['normalize'][0]['normalizer']);

        $this->assertSame(3.0, $collectedData['normalize'][0]['normalization'][DateTimeNormalizer::class]['time']);
        $this->assertSame(2, $collectedData['normalize'][0]['normalization'][DateTimeNormalizer::class]['calls']);
        $this->assertSame('DateTimeNormalizer', $collectedData['normalize'][0]['normalization'][DateTimeNormalizer::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['normalize'][0]['normalization'][DateTimeNormalizer::class]);
        $this->assertArrayHasKey('line', $collectedData['normalize'][0]['normalization'][DateTimeNormalizer::class]);

        $this->assertSame(5.0, $collectedData['normalize'][0]['normalization'][ObjectNormalizer::class]['time']);
        $this->assertSame(1, $collectedData['normalize'][0]['normalization'][ObjectNormalizer::class]['calls']);
        $this->assertSame('ObjectNormalizer', $collectedData['normalize'][0]['normalization'][ObjectNormalizer::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['normalize'][0]['normalization'][ObjectNormalizer::class]);
        $this->assertArrayHasKey('line', $collectedData['normalize'][0]['normalization'][ObjectNormalizer::class]);

        $this->assertSame(10.0, $collectedData['denormalize'][0]['normalizer']['time']);
        $this->assertSame('ObjectNormalizer', $collectedData['denormalize'][0]['normalizer']['class']);
        $this->assertArrayHasKey('file', $collectedData['denormalize'][0]['normalizer']);
        $this->assertArrayHasKey('line', $collectedData['denormalize'][0]['normalizer']);

        $this->assertSame(3.0, $collectedData['denormalize'][0]['normalization'][DateTimeNormalizer::class]['time']);
        $this->assertSame(2, $collectedData['denormalize'][0]['normalization'][DateTimeNormalizer::class]['calls']);
        $this->assertSame('DateTimeNormalizer', $collectedData['denormalize'][0]['normalization'][DateTimeNormalizer::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['denormalize'][0]['normalization'][DateTimeNormalizer::class]);
        $this->assertArrayHasKey('line', $collectedData['denormalize'][0]['normalization'][DateTimeNormalizer::class]);

        $this->assertSame(5.0, $collectedData['denormalize'][0]['normalization'][ObjectNormalizer::class]['time']);
        $this->assertSame(1, $collectedData['denormalize'][0]['normalization'][ObjectNormalizer::class]['calls']);
        $this->assertSame('ObjectNormalizer', $collectedData['denormalize'][0]['normalization'][ObjectNormalizer::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['denormalize'][0]['normalization'][ObjectNormalizer::class]);
        $this->assertArrayHasKey('line', $collectedData['denormalize'][0]['normalization'][ObjectNormalizer::class]);
    }

    public function testCollectEncoding()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectEncode('traceIdOne', 'data', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');
        $dataCollector->collectDecode('traceIdTwo', 'data', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');

        $dataCollector->collectEncoding('traceIdOne', JsonEncoder::class, 1.0, 'default');
        $dataCollector->collectEncoding('traceIdOne', JsonEncoder::class, 2.0, 'default');
        $dataCollector->collectEncoding('traceIdOne', CsvEncoder::class, 5.0, 'default');
        $dataCollector->collectEncoding('traceIdOne', CsvEncoder::class, 10.0, 'default');

        $dataCollector->collectDecoding('traceIdTwo', JsonEncoder::class, 1.0, 'default');
        $dataCollector->collectDecoding('traceIdTwo', JsonEncoder::class, 2.0, 'default');
        $dataCollector->collectDecoding('traceIdTwo', CsvEncoder::class, 5.0, 'default');
        $dataCollector->collectDecoding('traceIdTwo', CsvEncoder::class, 10.0, 'default');

        $dataCollector->lateCollect();
        $collectedData = $dataCollector->getData();

        $this->assertSame(10.0, $collectedData['encode'][0]['encoder']['time']);
        $this->assertSame('CsvEncoder', $collectedData['encode'][0]['encoder']['class']);
        $this->assertArrayHasKey('file', $collectedData['encode'][0]['encoder']);
        $this->assertArrayHasKey('line', $collectedData['encode'][0]['encoder']);

        $this->assertSame(3.0, $collectedData['encode'][0]['encoding'][JsonEncoder::class]['time']);
        $this->assertSame(2, $collectedData['encode'][0]['encoding'][JsonEncoder::class]['calls']);
        $this->assertSame('JsonEncoder', $collectedData['encode'][0]['encoding'][JsonEncoder::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['encode'][0]['encoding'][JsonEncoder::class]);
        $this->assertArrayHasKey('line', $collectedData['encode'][0]['encoding'][JsonEncoder::class]);

        $this->assertSame(5.0, $collectedData['encode'][0]['encoding'][CsvEncoder::class]['time']);
        $this->assertSame(1, $collectedData['encode'][0]['encoding'][CsvEncoder::class]['calls']);
        $this->assertSame('CsvEncoder', $collectedData['encode'][0]['encoding'][CsvEncoder::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['encode'][0]['encoding'][CsvEncoder::class]);
        $this->assertArrayHasKey('line', $collectedData['encode'][0]['encoding'][CsvEncoder::class]);

        $this->assertSame(10.0, $collectedData['decode'][0]['encoder']['time']);
        $this->assertSame('CsvEncoder', $collectedData['decode'][0]['encoder']['class']);
        $this->assertArrayHasKey('file', $collectedData['decode'][0]['encoder']);
        $this->assertArrayHasKey('line', $collectedData['decode'][0]['encoder']);

        $this->assertSame(3.0, $collectedData['decode'][0]['encoding'][JsonEncoder::class]['time']);
        $this->assertSame(2, $collectedData['decode'][0]['encoding'][JsonEncoder::class]['calls']);
        $this->assertSame('JsonEncoder', $collectedData['decode'][0]['encoding'][JsonEncoder::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['decode'][0]['encoding'][JsonEncoder::class]);
        $this->assertArrayHasKey('line', $collectedData['decode'][0]['encoding'][JsonEncoder::class]);

        $this->assertSame(5.0, $collectedData['decode'][0]['encoding'][CsvEncoder::class]['time']);
        $this->assertSame(1, $collectedData['decode'][0]['encoding'][CsvEncoder::class]['calls']);
        $this->assertSame('CsvEncoder', $collectedData['decode'][0]['encoding'][CsvEncoder::class]['class']);
        $this->assertArrayHasKey('file', $collectedData['decode'][0]['encoding'][CsvEncoder::class]);
        $this->assertArrayHasKey('line', $collectedData['decode'][0]['encoding'][CsvEncoder::class]);
    }

    public function testCountHandled()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectSerialize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->collectDeserialize('traceIdTwo', 'data', 'type', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->collectNormalize('traceIdThree', 'data', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');
        $dataCollector->collectDenormalize('traceIdFour', 'data', 'type', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');
        $dataCollector->collectEncode('traceIdFive', 'data', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');
        $dataCollector->collectDecode('traceIdSix', 'data', 'format', ['foo' => 'bar'], 20.0, $caller, 'default');
        $dataCollector->collectSerialize('traceIdSeven', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');

        $dataCollector->lateCollect();

        $this->assertSame(7, $dataCollector->getHandledCount());
    }

    public function testGetTotalTime()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];

        $dataCollector->collectSerialize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->collectDeserialize('traceIdTwo', 'data', 'type', 'format', ['foo' => 'bar'], 2.0, $caller, 'default');
        $dataCollector->collectNormalize('traceIdThree', 'data', 'format', ['foo' => 'bar'], 3.0, $caller, 'default');
        $dataCollector->collectDenormalize('traceIdFour', 'data', 'type', 'format', ['foo' => 'bar'], 4.0, $caller, 'default');
        $dataCollector->collectEncode('traceIdFive', 'data', 'format', ['foo' => 'bar'], 5.0, $caller, 'default');
        $dataCollector->collectDecode('traceIdSix', 'data', 'format', ['foo' => 'bar'], 6.0, $caller, 'default');
        $dataCollector->collectSerialize('traceIdSeven', 'data', 'format', ['foo' => 'bar'], 7.0, $caller, 'default');

        $dataCollector->lateCollect();

        $this->assertSame(28.0, $dataCollector->getTotalTime());
    }

    public function testReset()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectSerialize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 1.0, $caller, 'default');
        $dataCollector->lateCollect();

        $this->assertNotSame([], $dataCollector->getData());

        $dataCollector->reset();
        $this->assertSame([], $dataCollector->getData());
    }

    public function testDoNotCollectPartialTraces()
    {
        $dataCollector = new SerializerDataCollector();

        $dataCollector->collectNormalization('traceIdOne', DateTimeNormalizer::class, 1.0, 'default');
        $dataCollector->collectDenormalization('traceIdTwo', DateTimeNormalizer::class, 1.0, 'default');
        $dataCollector->collectEncoding('traceIdThree', CsvEncoder::class, 10.0, 'default');
        $dataCollector->collectDecoding('traceIdFour', JsonEncoder::class, 1.0, 'default');

        $dataCollector->lateCollect();

        $data = $dataCollector->getData();

        $this->assertSame([], $data['serialize']);
        $this->assertSame([], $data['deserialize']);
        $this->assertSame([], $data['normalize']);
        $this->assertSame([], $data['denormalize']);
        $this->assertSame([], $data['encode']);
        $this->assertSame([], $data['decode']);
    }

    public function testNamedSerializers()
    {
        $dataCollector = new SerializerDataCollector();

        $caller = ['name' => 'Foo.php', 'file' => 'src/Foo.php', 'line' => 123];
        $dataCollector->collectNormalization('traceIdOne', DateTimeNormalizer::class, 3.0, 'default');
        $dataCollector->collectEncoding('traceIdOne', CsvEncoder::class, 4.0, 'default');
        $dataCollector->collectSerialize('traceIdOne', 'data', 'format', ['foo' => 'bar'], 7.0, $caller, 'default');
        $dataCollector->collectNormalization('traceIdTwo', ObjectNormalizer::class, 3.0, 'default');
        $dataCollector->collectNormalize('traceIdTwo', 'data', 'format', ['foo' => 'bar'], 5.0, $caller, 'default');

        $dataCollector->collectEncoding('traceIdThree', JsonEncoder::class, 4.0, 'api');
        $dataCollector->collectEncode('traceIdThree', 'data', 'format', ['foo' => 'bar'], 5.0, $caller, 'api');
        $dataCollector->collectDenormalization('traceIdFour', DateTimeNormalizer::class, 3.0, 'api');
        $dataCollector->collectDecoding('traceIdFour', CsvEncoder::class, 4.0, 'api');
        $dataCollector->collectDeserialize('traceIdFour', 'data', 'type', 'format', ['foo' => 'bar'], 7.0, $caller, 'api');
        $dataCollector->collectDenormalization('traceIdFive', ObjectNormalizer::class, 3.0, 'api');
        $dataCollector->collectDenormalize('traceIdFive', 'data', 'type', 'format', ['foo' => 'bar'], 5.0, $caller, 'api');
        $dataCollector->collectDecoding('traceIdSix', JsonEncoder::class, 4.0, 'api');
        $dataCollector->collectDecode('traceIdSix', 'data', 'format', ['foo' => 'bar'], 5.0, $caller, 'api');

        $dataCollector->lateCollect();

        $this->assertSame(6, $dataCollector->getHandledCount());

        $collectedData = $dataCollector->getData();

        $this->assertSame('default', $collectedData['serialize'][0]['name']);
        $this->assertSame('DateTimeNormalizer', $collectedData['serialize'][0]['normalizer']['class']);
        $this->assertSame('CsvEncoder', $collectedData['serialize'][0]['encoder']['class']);
        $this->assertSame('default', $collectedData['normalize'][0]['name']);
        $this->assertSame('ObjectNormalizer', $collectedData['normalize'][0]['normalizer']['class']);

        $this->assertSame('api', $collectedData['encode'][0]['name']);
        $this->assertSame('JsonEncoder', $collectedData['encode'][0]['encoder']['class']);
        $this->assertSame('api', $collectedData['deserialize'][0]['name']);
        $this->assertSame('DateTimeNormalizer', $collectedData['deserialize'][0]['normalizer']['class']);
        $this->assertSame('CsvEncoder', $collectedData['deserialize'][0]['encoder']['class']);
        $this->assertSame('api', $collectedData['denormalize'][0]['name']);
        $this->assertSame('ObjectNormalizer', $collectedData['denormalize'][0]['normalizer']['class']);
        $this->assertSame('api', $collectedData['decode'][0]['name']);
        $this->assertSame('JsonEncoder', $collectedData['decode'][0]['encoder']['class']);

        $this->assertSame(['default', 'api'], $dataCollector->getSerializerNames());

        $this->assertSame(2, $dataCollector->getHandledCount('default'));

        $collectedData = $dataCollector->getData('default');

        $this->assertSame('default', $collectedData['serialize'][0]['name']);
        $this->assertSame('DateTimeNormalizer', $collectedData['serialize'][0]['normalizer']['class']);
        $this->assertSame('CsvEncoder', $collectedData['serialize'][0]['encoder']['class']);
        $this->assertSame('default', $collectedData['normalize'][0]['name']);
        $this->assertSame('ObjectNormalizer', $collectedData['normalize'][0]['normalizer']['class']);

        $this->assertSame([], $collectedData['encode']);
        $this->assertSame([], $collectedData['deserialize']);
        $this->assertSame([], $collectedData['denormalize']);
        $this->assertSame([], $collectedData['decode']);

        $this->assertSame(4, $dataCollector->getHandledCount('api'));

        $collectedData = $dataCollector->getData('api');

        $this->assertSame([], $collectedData['serialize']);
        $this->assertSame([], $collectedData['normalize']);

        $this->assertSame('api', $collectedData['encode'][0]['name']);
        $this->assertSame('JsonEncoder', $collectedData['encode'][0]['encoder']['class']);
        $this->assertSame('api', $collectedData['deserialize'][0]['name']);
        $this->assertSame('DateTimeNormalizer', $collectedData['deserialize'][0]['normalizer']['class']);
        $this->assertSame('CsvEncoder', $collectedData['deserialize'][0]['encoder']['class']);
        $this->assertSame('api', $collectedData['denormalize'][0]['name']);
        $this->assertSame('ObjectNormalizer', $collectedData['denormalize'][0]['normalizer']['class']);
        $this->assertSame('api', $collectedData['decode'][0]['name']);
        $this->assertSame('JsonEncoder', $collectedData['decode'][0]['encoder']['class']);
    }

    /**
     * Cast cloned vars to be able to test nested values.
     */
    private function castCollectedData(array $collectedData): array
    {
        foreach ($collectedData as $method => $collectedMethodData) {
            foreach ($collectedMethodData as $i => $collected) {
                $collectedData[$method][$i]['data'] = $collected['data']->getValue();
                $collectedData[$method][$i]['context'] = $collected['context']->getValue(true);
            }
        }

        return $collectedData;
    }
}
