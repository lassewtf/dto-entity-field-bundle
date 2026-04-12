<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class EntityFieldDtoType
{
    public function __construct(
        public string $tag,
    ) {
    }
}
