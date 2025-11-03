<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Container;

use RuntimeException;

/**
 * Binding Resolution Exception
 * 
 * Thrown when a binding cannot be resolved from the container.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BindingResolutionException extends RuntimeException
{
}