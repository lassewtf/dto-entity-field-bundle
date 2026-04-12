<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle;

use Doctrine\DBAL\Types\Type;
use Nano\DtoJsonEntityFieldBundle\Attribute\EntityFieldDtoType;
use Nano\DtoJsonEntityFieldBundle\DependencyInjection\Compiler\EnableTypedFieldMapperPass;
use Nano\DtoJsonEntityFieldBundle\DependencyInjection\Compiler\RegisterEntityFieldDtoPass;
use Nano\DtoJsonEntityFieldBundle\Doctrine\Type\DtoJsonType;
use Nano\DtoJsonEntityFieldBundle\Doctrine\Type\DtoJsonTypeRuntime;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class NanoDtoJsonEntityFieldBundle extends AbstractBundle
{
    public const DTO_SERVICE_TAG = 'nano_dto_json_entity_field.dto';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('register_type')->defaultTrue()->end()
                        ->booleanNode('enable_typed_field_mapper')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('envelope')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('version')->min(1)->defaultValue(1)->end()
                    ->end()
                ->end()
                ->arrayNode('registry')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('mappings')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(
            EntityFieldDtoType::class,
            static function (ChildDefinition $definition, EntityFieldDtoType $attribute, \Reflector $reflector): void {
                $definition->addTag(self::DTO_SERVICE_TAG);
            },
        );

        $container->addCompilerPass(new EnableTypedFieldMapperPass());
        $container->addCompilerPass(new RegisterEntityFieldDtoPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__.'/../config/services.php');

        $builder->setParameter('nano_dto_json_entity_field.envelope.version', $config['envelope']['version']);
        $builder->setParameter('nano_dto_json_entity_field.registry.mappings', $config['registry']['mappings']);
        $builder->setParameter('nano_dto_json_entity_field.doctrine.register_type', $config['doctrine']['register_type']);
        $builder->setParameter('nano_dto_json_entity_field.doctrine.enable_typed_field_mapper', $config['doctrine']['enable_typed_field_mapper']);
    }

    public function boot(): void
    {
        $container = $this->container;
        if (null === $container) {
            return;
        }

        if ($container->hasParameter('nano_dto_json_entity_field.doctrine.register_type')
            && true === $container->getParameter('nano_dto_json_entity_field.doctrine.register_type')
        ) {
            if (Type::hasType(DtoJsonType::NAME)) {
                Type::overrideType(DtoJsonType::NAME, new DtoJsonType());
            } else {
                Type::addType(DtoJsonType::NAME, new DtoJsonType());
            }
        }

        if ($container->has('nano_dto_json_entity_field.runtime_codec')) {
            DtoJsonTypeRuntime::setContainer($container);
        }
    }

    public function shutdown(): void
    {
        DtoJsonTypeRuntime::reset();
    }
}
