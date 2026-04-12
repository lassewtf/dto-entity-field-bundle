<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Doctrine\TypedFieldMapper;

use Doctrine\ORM\Mapping\TypedFieldMapper;
use Nano\DtoJsonEntityFieldBundle\Doctrine\Type\DtoJsonType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

final class EntityFieldDtoTypedFieldMapper implements TypedFieldMapper
{
    public function validateAndComplete(array $mapping, \ReflectionProperty $field): array
    {
        if (isset($mapping['type'])) {
            return $mapping;
        }

        $type = $field->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return $mapping;
        }

        if (is_a($type->getName(), AbstractEntityFieldDto::class, true)) {
            $mapping['type'] = DtoJsonType::NAME;
        }

        return $mapping;
    }
}
