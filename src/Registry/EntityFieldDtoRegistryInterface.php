<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Registry;

use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

interface EntityFieldDtoRegistryInterface
{
    public function tagFor(string $class): string;

    /**
     * @return class-string<AbstractEntityFieldDto>
     */
    public function classForTag(string $tag): string;

    public function isSupported(string $class): bool;
}
