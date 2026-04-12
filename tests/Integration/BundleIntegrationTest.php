<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nano\DtoJsonEntityFieldBundle\Doctrine\Type\DtoJsonType;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration\IntegrationProductAddedDto;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration\Order;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration\TestKernel;
use Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration\TypedOrder;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BundleIntegrationTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[WithoutErrorHandler]
    public function testBundleRegistersDoctrineTypeAndCompilerPassMappings(): void
    {
        self::bootKernel();

        $registry = static::getContainer()->get('Nano\\DtoJsonEntityFieldBundle\\Registry\\EntityFieldDtoRegistryInterface');

        self::assertSame('integration_product_added', $registry->tagFor(IntegrationProductAddedDto::class));
    }

    #[WithoutErrorHandler]
    public function testDoctrineRoundTripRestoresConcreteDto(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = [$entityManager->getClassMetadata(Order::class)];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $order = new Order();
        $order->changePayload(new IntegrationProductAddedDto('uuid-1', 'ABC-123', 2));

        $entityManager->persist($order);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->getRepository(Order::class)->findOneBy([]);
        self::assertInstanceOf(Order::class, $reloaded);
        self::assertInstanceOf(IntegrationProductAddedDto::class, $reloaded->payload());
        self::assertSame('uuid-1', $reloaded->payload()?->instanceUuid);
    }

    #[WithoutErrorHandler]
    public function testTypedFieldMapperSetsDtoJsonType(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $metadata = $entityManager->getClassMetadata(TypedOrder::class);
        $mapping = $metadata->getFieldMapping('payload');

        self::assertSame(
            DtoJsonType::NAME,
            \is_array($mapping) ? $mapping['type'] : $mapping->type,
        );
    }
}
