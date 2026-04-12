<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Dto;

abstract readonly class AbstractEntityFieldDto
{
    public function __construct(
        public string $instanceUuid,
    ) {
    }
}
