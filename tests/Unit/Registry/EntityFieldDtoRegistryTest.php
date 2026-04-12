<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Unit\Registry;

use Nano\DtoJsonEntityFieldBundle\Exception\DtoJsonConfigurationException;
use Nano\DtoJsonEntityFieldBundle\Exception\UnknownDtoTagException;
use Nano\DtoJsonEntityFieldBundle\Exception\UnsupportedDtoClassException;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistry;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto\CouponAppliedDto;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto\ProductAddedDto;
use PHPUnit\Framework\TestCase;

final class EntityFieldDtoRegistryTest extends TestCase
{
    public function testMapsTagsAndClassesBothWays(): void
    {
        $registry = new EntityFieldDtoRegistry([
            'product_added' => ProductAddedDto::class,
            'coupon_applied' => CouponAppliedDto::class,
        ]);

        self::assertSame('product_added', $registry->tagFor(ProductAddedDto::class));
        self::assertSame(CouponAppliedDto::class, $registry->classForTag('coupon_applied'));
        self::assertTrue($registry->isSupported(ProductAddedDto::class));
    }

    public function testUnknownTagFailsClearly(): void
    {
        $registry = new EntityFieldDtoRegistry(['product_added' => ProductAddedDto::class]);

        $this->expectException(UnknownDtoTagException::class);
        $registry->classForTag('missing');
    }

    public function testUnsupportedClassFailsClearly(): void
    {
        $registry = new EntityFieldDtoRegistry(['product_added' => ProductAddedDto::class]);

        $this->expectException(UnsupportedDtoClassException::class);
        $registry->tagFor(CouponAppliedDto::class);
    }

    public function testDuplicateClassesAreRejected(): void
    {
        $this->expectException(DtoJsonConfigurationException::class);

        new EntityFieldDtoRegistry([
            'product_added' => ProductAddedDto::class,
            'product_added_v2' => ProductAddedDto::class,
        ]);
    }
}
