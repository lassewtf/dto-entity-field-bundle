<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Unit\Doctrine\Type;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Nano\DtoJsonEntityFieldBundle\Doctrine\Type\DtoJsonType;
use Nano\DtoJsonEntityFieldBundle\Doctrine\Type\DtoJsonTypeRuntime;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto\ProductAddedDto;
use Nano\DtoJsonEntityFieldBundle\Tests\Unit\Doctrine\Type\Stub\StubCodec;
use PHPUnit\Framework\TestCase;

final class DtoJsonTypeTest extends TestCase
{
    protected function tearDown(): void
    {
        DtoJsonTypeRuntime::reset();
    }

    public function testNullRoundTripStaysNull(): void
    {
        $type = new DtoJsonType();
        $platform = new SQLitePlatform();

        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testDtoRoundTripUsesCodec(): void
    {
        DtoJsonTypeRuntime::setCodec(new StubCodec());

        $type = new DtoJsonType();
        $platform = new SQLitePlatform();
        $dto = new ProductAddedDto('uuid-1', 'ABC-123', 2);

        $databaseValue = $type->convertToDatabaseValue($dto, $platform);
        self::assertIsString($databaseValue);

        $phpValue = $type->convertToPHPValue($databaseValue, $platform);
        self::assertInstanceOf(ProductAddedDto::class, $phpValue);
        self::assertSame('uuid-1', $phpValue->instanceUuid);
    }

    public function testInvalidPhpValueFails(): void
    {
        $this->expectException(ConversionException::class);

        $type = new DtoJsonType();
        $type->convertToDatabaseValue(new \stdClass(), new SQLitePlatform());
    }
}
