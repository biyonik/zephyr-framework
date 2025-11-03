<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Container;

use RuntimeException;

/**
 * Circular Dependency Exception
 * 
 * Thrown when circular dependency is detected in the container.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class CircularDependencyException extends RuntimeException
{
}