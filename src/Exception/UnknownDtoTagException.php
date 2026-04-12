<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Exception;

final class UnknownDtoTagException extends \OutOfBoundsException
{
    public function __construct(string $tag)
    {
        parent::__construct(\sprintf('Unknown DTO tag "%s".', $tag));
    }
}
