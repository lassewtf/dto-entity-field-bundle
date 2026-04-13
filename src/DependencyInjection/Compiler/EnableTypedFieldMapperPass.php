<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\IdGeneratorPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class EnableTypedFieldMapperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nano_dto_json_entity_field.doctrine.enable_typed_field_mapper')
            || true !== $container->getParameter('nano_dto_json_entity_field.doctrine.enable_typed_field_mapper')
        ) {
            return;
        }

        foreach ($container->findTaggedServiceIds(IdGeneratorPass::CONFIGURATION_TAG) as $id => $_tags) {
            $definition = $container->getDefinition($id);
            $definition->addMethodCall('setTypedFieldMapper', [
                new Reference('nano_dto_json_entity_field.doctrine.chain_typed_field_mapper'),
            ]);
        }
    }
}
