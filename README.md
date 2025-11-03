# 🚀 Zephyr Framework

Modern, minimal PHP 8.3+ API Framework optimized for shared hosting.

## ✨ Features

- 🎯 **API-First**: Built specifically for REST APIs
- 🔥 **Modern PHP**: Uses PHP 8.3+ features (attributes, enums, typed properties)
- 🏠 **Shared Hosting Ready**: No special extensions required
- ⚡ **Fast**: Minimal overhead, optimized routing
- 🛡️ **Secure**: Built-in protection (CORS, rate limiting, JWT)
- 🧪 **Testable**: Dependency injection, clean architecture

## 📦 Installation

```bash
# Clone the repository
git clone https://github.com/biyonik/zephyr-framework.git
cd zephyr-framework

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Run tests
php test.php
```

## 🚀 Quick Start

### 1. Start Development Server

```bash
php -S localhost:8000 -t public
```

### 2. Test the API

```bash
# Welcome endpoint
curl http://localhost:8000/

# Health check
curl http://localhost:8000/health

# API info
curl http://localhost:8000/api
```

### 3. Create Your First Route

Edit `routes/api.php`:

```php
$router->get('/hello/{name}', function(Request $request) {
    $name = $request->param('name');
    return Response::success([
        'message' => "Hello, {$name}!"
    ]);
});
```

### 4. Create a Controller

Create `app/Controllers/UserController.php`:

```php
<?php

namespace App\Controllers;

use Zephyr\Http\{Request, Response};

class UserController
{
    public function index(Request $request): Response
    {
        return Response::success([
            'users' => ['Alice', 'Bob', 'Charlie']
        ]);
    }
    
    public function show(Request $request): Response
    {
        $id = $request->param('id');
        return Response::success([
            'user' => "User #{$id}"
        ]);
    }
}
```

Register routes:

```php
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);
```

## 🏗️ Architecture

```
Request → Router → Controller → Response
           ↓           ↓
      Middleware   Services
                      ↓
                   Database
```

## 📚 Core Components

### Router
- Dynamic route parameters: `/users/{id}`
- Route groups with prefix
- Method-based routing (GET, POST, PUT, DELETE)
- Route constraints: `->where('id', '[0-9]+')`

### Request
- Access query params: `$request->query('page')`
- Access body data: `$request->input('name')`
- Access route params: `$request->param('id')`
- Get headers: `$request->header('Authorization')`

### Response
- JSON responses: `Response::json($data)`
- Success responses: `Response::success($data)`
- Error responses: `Response::error($message, $code)`
- Status codes and headers

### Container
- Automatic dependency injection
- Service binding: `$app->bind(Interface::class, Implementation::class)`
- Singletons: `$app->singleton(Service::class)`

## 🧪 Testing

Run the test suite:

```bash
# Basic framework test
php test.php

# PHPUnit tests
./vendor/bin/phpunit
```

## 📝 Environment Configuration

Edit `.env` file:

```env
APP_NAME="My API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=your-secret-key
```

## 🛠️ Development Status

### ✅ Completed
- [x] Core application container
- [x] Dependency injection
- [x] HTTP Request/Response
- [x] Router with dynamic parameters
- [x] Basic exception handling
- [x] Environment configuration

### 🚧 In Progress
- [ ] Middleware pipeline
- [ ] Database query builder
- [ ] JWT authentication
- [ ] Validation system
- [ ] Cache system

### 📋 Planned
- [ ] Rate limiting
- [ ] CORS middleware
- [ ] File uploads
- [ ] Database migrations
- [ ] CLI commands

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

MIT License

## 👤 Author

**Ahmet ALTUN**
- Email: ahmet.altun60@gmail.com
- GitHub: [@biyonik](https://github.com/biyonik)

---

Built with ❤️ using PHP 8.3+