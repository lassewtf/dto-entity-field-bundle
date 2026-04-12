<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Codec;

use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

interface DtoJsonCodecInterface
{
    /**
     * @return array<string, mixed>
     */
    public function encode(AbstractEntityFieldDto $dto): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function decode(array $payload): AbstractEntityFieldDto;
}
