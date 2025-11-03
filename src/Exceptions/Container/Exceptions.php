<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Container;

use RuntimeException;

/**
 * Container Exception Classes
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

/**
 * Base container exception
 */
class ContainerException extends RuntimeException
{
}

/**
 * Exception thrown when a binding cannot be resolved
 */
class BindingResolutionException extends ContainerException
{
}

/**
 * Exception thrown when circular dependency is detected
 */
class CircularDependencyException extends ContainerException
{
}