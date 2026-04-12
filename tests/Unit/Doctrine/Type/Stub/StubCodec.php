<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Unit\Doctrine\Type\Stub;

use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodecInterface;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto\ProductAddedDto;

final class StubCodec implements DtoJsonCodecInterface
{
    public function encode(AbstractEntityFieldDto $dto): array
    {
        return [
            '_dto' => [
                'tag' => 'product_added',
                'instanceUuid' => $dto->instanceUuid,
                'version' => 1,
            ],
            'data' => [
                'sku' => 'ABC-123',
                'quantity' => 2,
            ],
        ];
    }

    public function decode(array $payload): AbstractEntityFieldDto
    {
        return new ProductAddedDto(
            $payload['_dto']['instanceUuid'],
            $payload['data']['sku'],
            $payload['data']['quantity'],
        );
    }
}
