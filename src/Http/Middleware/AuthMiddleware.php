<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\UnauthorizedException;
use Zephyr\Support\Config; //
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

/**
 * Authentication Middleware
 *
 * Gelen isteğin geçerli bir JWT token'a sahip olup olmadığını doğrular.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Handle authentication
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     *
     * @throws UnauthorizedException If not authenticated
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from Authorization header
        $token = $request->bearerToken(); //

        if (!$token) {
            throw new UnauthorizedException('Authentication required');
        }

        // Get config settings
        $secret = Config::get('auth.jwt.secret');
        $algo = Config::get('auth.jwt.algo');

        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET is not defined in your environment file.');
        }

        try {
            // Decode and validate the token
            $decoded = JWT::decode($token, new Key($secret, $algo));

            // Token geçerli. Kullanıcı bilgilerini (payload)
            // daha sonra erişebilmek için container'a kaydedelim.
            // Not: $decoded->data (veya $decoded->sub) token'ı nasıl
            // oluşturduğunuza bağlı olarak değişir. Biz tüm payload'ı saklayalım.
            app()->instance('auth.user', $decoded); //

        } catch (ExpiredException $e) {
            // Token'ın süresi dolmuş
            throw new UnauthorizedException('Token has expired');
        } catch (SignatureInvalidException $e) {
            // İmza geçersiz
            throw new UnauthorizedException('Invalid token signature');
        } catch (UnexpectedValueException $e) {
            // Token formatı bozuk veya algoritma eşleşmiyor
            throw new UnauthorizedException('Invalid token');
        } catch (\Exception $e) {
            // Diğer tüm JWT veya beklenmedik hatalar
            throw new UnauthorizedException('Invalid authentication token: ' . $e->getMessage());
        }

        // Continue to next middleware/controller
        return $next($request);
    }
}