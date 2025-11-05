<?php

declare(strict_types=1);

namespace Zephyr\Database\Exception;

use RuntimeException;

/**
 * Mass Assignment Exception
 *
 * Thrown when attempting mass assignment on protected attributes.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class MassAssignmentException extends RuntimeException
{
    /**
     * Create exception for fillable not set
     */
    public static function fillableNotSet(string $model): self
    {
        return new self(
            "Add [fillable] property to enable mass assignment on [{$model}]."
        );
    }

    /**
     * Create exception for guarded attribute
     */
    public static function guardedAttribute(string $model, string $key): self
    {
        return new self(
            "Attribute [{$key}] is guarded on model [{$model}]."
        );
    }
}