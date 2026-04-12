<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\DependencyInjection\Compiler;

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

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'doctrine.orm.') || !str_ends_with($id, '_configuration')) {
                continue;
            }

            $definition->addMethodCall('setTypedFieldMapper', [
                new Reference('nano_dto_json_entity_field.doctrine.chain_typed_field_mapper'),
            ]);
        }
    }
}
