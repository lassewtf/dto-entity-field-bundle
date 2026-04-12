<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

final class DtoJsonType extends JsonType
{
    public const NAME = 'dto_json';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof AbstractEntityFieldDto) {
            throw InvalidType::new(
                $value,
                self::NAME,
                ['null', AbstractEntityFieldDto::class],
            );
        }

        return parent::convertToDatabaseValue(
            DtoJsonTypeRuntime::codec()->encode($value),
            $platform,
        );
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $decoded = parent::convertToPHPValue($value, $platform);

        if (null === $decoded) {
            return null;
        }

        if (!\is_array($decoded)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return DtoJsonTypeRuntime::codec()->decode($decoded);
    }
}
