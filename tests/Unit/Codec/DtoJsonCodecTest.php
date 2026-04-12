<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Unit\Codec;

use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodec;
use Nano\DtoJsonEntityFieldBundle\Envelope\DtoJsonEnvelopeFactory;
use Nano\DtoJsonEntityFieldBundle\Exception\InvalidDtoPayloadException;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistry;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto\ProductAddedDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class DtoJsonCodecTest extends TestCase
{
    private DtoJsonCodec $codec;

    protected function setUp(): void
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $registry = new EntityFieldDtoRegistry(['product_added' => ProductAddedDto::class]);
        $this->codec = new DtoJsonCodec($serializer, $serializer, $registry, new DtoJsonEnvelopeFactory());
    }

    public function testEncodeRemovesInstanceUuidFromDataAndAddsEnvelope(): void
    {
        $payload = $this->codec->encode(new ProductAddedDto('uuid-1', 'ABC-123', 2));

        self::assertSame('product_added', $payload['_dto']['tag']);
        self::assertSame('uuid-1', $payload['_dto']['instanceUuid']);
        self::assertSame(['sku' => 'ABC-123', 'quantity' => 2], $payload['data']);
    }

    public function testDecodeRestoresDto(): void
    {
        $dto = $this->codec->decode([
            '_dto' => [
                'tag' => 'product_added',
                'instanceUuid' => 'uuid-1',
                'version' => 1,
            ],
            'data' => [
                'sku' => 'ABC-123',
                'quantity' => 2,
            ],
        ]);

        self::assertInstanceOf(ProductAddedDto::class, $dto);
        self::assertSame('uuid-1', $dto->instanceUuid);
        self::assertSame('ABC-123', $dto->sku);
        self::assertSame(2, $dto->quantity);
    }

    public function testEncodeRejectsReservedMetadataKeyInNormalizedData(): void
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturn([
            'instanceUuid' => 'uuid-1',
            '_dto' => ['tag' => 'bad'],
        ]);
        $denormalizer = $this->createMock(DenormalizerInterface::class);

        $codec = new DtoJsonCodec(
            $normalizer,
            $denormalizer,
            new EntityFieldDtoRegistry(['product_added' => ProductAddedDto::class]),
            new DtoJsonEnvelopeFactory(),
        );

        $this->expectException(InvalidDtoPayloadException::class);
        $codec->encode(new ProductAddedDto('uuid-1', 'ABC-123', 2));
    }
}
