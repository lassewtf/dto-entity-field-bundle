<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Unit\Envelope;

use Nano\DtoJsonEntityFieldBundle\Envelope\DtoJsonEnvelopeFactory;
use Nano\DtoJsonEntityFieldBundle\Exception\InvalidDtoPayloadException;
use PHPUnit\Framework\TestCase;

final class DtoJsonEnvelopeFactoryTest extends TestCase
{
    public function testCreateBuildsEnvelopeArray(): void
    {
        $factory = new DtoJsonEnvelopeFactory(3);

        $envelope = $factory->create('product_added', 'uuid-1', ['sku' => 'ABC-123']);

        self::assertSame([
            '_dto' => [
                'tag' => 'product_added',
                'instanceUuid' => 'uuid-1',
                'version' => 3,
            ],
            'data' => [
                'sku' => 'ABC-123',
            ],
        ], $envelope->toArray());
    }

    public function testFromArrayRejectsInvalidMetadata(): void
    {
        $factory = new DtoJsonEnvelopeFactory();

        $this->expectException(InvalidDtoPayloadException::class);
        $factory->fromArray(['data' => []]);
    }

    public function testFromArrayDefaultsVersionWhenMissing(): void
    {
        $factory = new DtoJsonEnvelopeFactory(7);

        $envelope = $factory->fromArray([
            '_dto' => [
                'tag' => 'product_added',
                'instanceUuid' => 'uuid-1',
            ],
            'data' => [],
        ]);

        self::assertSame(7, $envelope->version);
    }
}
