<?php

namespace Tests\Feature;

use Tests\TestCase;
use Zephyr\Http\Request;
use Zephyr\Support\Config;
use Firebase\JWT\JWT;

class SecurityMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Rotaları tanımla (normalde api.php'de)
        $this->app->router()->get('/protected', fn() => ['success' => true])
             ->middleware('auth'); //
             
        $this->app->router()->post('/protected-post', fn() => ['success' => true]);
        
        // Rapor #3: CSRF'i globale ekle
        $this->app->getKernel()->pushMiddleware(\Zephyr\Http\Middleware\VerifyCsrfToken::class);
    }
    
    // --- AuthMiddleware Testleri ---

    public function test_auth_middleware_rejects_without_token()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/protected';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);

        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Authentication required', $content['error']['message']); //
    }

    public function test_auth_middleware_rejects_with_invalid_token()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid-token-string';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);

        $response = $this->app->handle($request);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_auth_middleware_accepts_valid_token()
    {
        $token = $this->generateTestToken();
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);

        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    // --- CSRF Middleware Testi (Rapor #4) ---

    public function test_csrf_middleware_rejects_post_without_token()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected-post';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);

        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('CSRF token mismatch.', $content['error']['message']);
    }

    public function test_csrf_middleware_accepts_with_valid_tokens()
    {
        $csrfToken = bin2hex(random_bytes(16));
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected-post';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken; // Header
        $_COOKIE['XSRF-TOKEN'] = $csrfToken; // Cookie
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);

        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    // --- Helper ---
    private function generateTestToken(): string
    {
        $secret = Config::get('auth.jwt.secret'); //
        $algo = Config::get('auth.jwt.algo');
        
        $payload = [
            'iss' => 'test-issuer',
            'sub' => 123, // User ID
            'iat' => time(),
            'exp' => time() + 3600
        ];
        
        return JWT::encode($payload, $secret, $algo);
    }
}