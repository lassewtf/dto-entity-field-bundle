<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nano\DtoJsonEntityFieldBundle\NanoDtoJsonEntityFieldBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new NanoDtoJsonEntityFieldBundle(),
        ];
    }

    public function getCacheDir(): string
    {
        return $this->baseRuntimeDir().'/cache';
    }

    public function getBuildDir(): string
    {
        return $this->baseRuntimeDir().'/build';
    }

    public function getLogDir(): string
    {
        return $this->baseRuntimeDir().'/log';
    }

    private function baseRuntimeDir(): string
    {
        return sys_get_temp_dir().'/nano_dto_json_entity_field_bundle/'.getmypid();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'serializer' => ['enabled' => true],
            'test' => true,
            'http_method_override' => false,
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => 'sqlite:///:memory:',
            ],
            'orm' => [
                'mappings' => [
                    'NanoDtoJsonEntityFieldBundleTests' => [
                        'type' => 'attribute',
                        'dir' => __DIR__,
                        'prefix' => 'Nano\\DtoJsonEntityFieldBundle\\Tests\\Fixtures\\Integration',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->extension('nano_dto_json_entity_field', [
            'doctrine' => [
                'register_type' => true,
                'enable_typed_field_mapper' => true,
            ],
        ]);

        $services = $container->services()
            ->defaults()
            ->autoconfigure();

        $services
            ->set(IntegrationProductAddedDto::class)
            ->autowire(false)
            ->autoconfigure();
    }
}
