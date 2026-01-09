# OpenAPI Documentation Guide

This package includes comprehensive OpenAPI 3.0 specification for API clients to integrate with idempotent endpoints.

## Overview

The [openapi.yaml](openapi.yaml) file provides a complete API specification including:
- Idempotency header requirements
- Request/response schemas
- Error responses
- Example requests
- Security schemes

## Quick Start

### 1. View Documentation

#### Using Swagger UI (Online)
Visit [Swagger Editor](https://editor.swagger.io/) and paste the contents of `openapi.yaml`.

#### Using Swagger UI (Local)
```bash
docker run -p 8080:8080 \
  -v $(pwd)/openapi.yaml:/openapi.yaml \
  -e SWAGGER_JSON=/openapi.yaml \
  swaggerapi/swagger-ui
```

Then open http://localhost:8080

#### Using Redoc
```bash
docker run -p 8080:80 \
  -v $(pwd)/openapi.yaml:/usr/share/nginx/html/openapi.yaml \
  -e SPEC_URL=openapi.yaml \
  redocly/redoc
```

### 2. Generate Client SDKs

#### Using OpenAPI Generator

**JavaScript/TypeScript Client:**
```bash
openapi-generator-cli generate \
  -i openapi.yaml \
  -g typescript-axios \
  -o ./clients/typescript
```

**PHP Client:**
```bash
openapi-generator-cli generate \
  -i openapi.yaml \
  -g php \
  -o ./clients/php
```

**Python Client:**
```bash
openapi-generator-cli generate \
  -i openapi.yaml \
  -g python \
  -o ./clients/python
```

**Other Languages:**
Available: Java, C#, Ruby, Go, Swift, Kotlin, and [50+ more](https://openapi-generator.tech/docs/generators)

### 3. API Testing with Postman

1. Import `openapi.yaml` into Postman
2. Collection will be auto-generated with all endpoints
3. Add your API token to collection variables
4. Run requests with auto-generated examples

## Laravel Integration

### Serve OpenAPI Documentation

#### Option 1: Direct File Serving

```php
// routes/web.php
Route::get('/api/docs', function () {
    return response()->file(base_path('openapi.yaml'))
        ->header('Content-Type', 'application/x-yaml');
});
```

#### Option 2: Using L5-Swagger Package

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

```php
// config/l5-swagger.php
'defaults' => [
    'routes' => [
        'api' => 'api/documentation',
    ],
    'paths' => [
        'docs' => base_path('openapi.yaml'),
    ],
],
```

Visit: `/api/documentation`

#### Option 3: Using Scramble Package

```bash
composer require dedoc/scramble
```

Scramble auto-generates OpenAPI docs from your routes and controllers.

### Integrate with Existing API

```php
// app/Http/Controllers/Controller.php

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Your API",
 *     description="API with idempotency support"
 * )
 * @OA\Server(
 *     url="https://api.example.com/v1",
 *     description="Production"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="BearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
class Controller extends BaseController
{
    //
}
```

```php
// app/Http/Controllers/PaymentController.php

/**
 * @OA\Post(
 *     path="/payments",
 *     tags={"Payments"},
 *     summary="Create a payment",
 *     @OA\Parameter(
 *         name="Idempotency-Key",
 *         in="header",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PaymentRequest")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Payment created",
 *         @OA\JsonContent(ref="#/components/schemas/Payment")
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Idempotency-Key required"
 *     ),
 *     security={{"BearerAuth": {}}}
 * )
 */
public function store(Request $request)
{
    // Your implementation
}
```

## Client Examples

### JavaScript/TypeScript

```typescript
import axios, { AxiosInstance } from 'axios';
import { v4 as uuidv4 } from 'uuid';

class IdempotentApiClient {
  private client: AxiosInstance;
  
  constructor(baseURL: string, apiKey: string) {
    this.client = axios.create({
      baseURL,
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
      },
    });
  }
  
  async createPayment(data: PaymentRequest, idempotencyKey?: string) {
    const key = idempotencyKey || uuidv4();
    
    try {
      const response = await this.client.post('/payments', data, {
        headers: {
          'Idempotency-Key': key,
        },
      });
      
      return response.data;
    } catch (error) {
      if (error.response?.status === 409) {
        // Request in progress, retry after delay
        await new Promise(resolve => setTimeout(resolve, 1000));
        return this.createPayment(data, key);
      }
      throw error;
    }
  }
}

// Usage
const client = new IdempotentApiClient(
  'https://api.example.com/v1',
  'your-api-key'
);

const payment = await client.createPayment({
  amount: 1000,
  currency: 'USD',
  customer_id: 'cus_123',
});
```

### PHP

```php
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

class IdempotentApiClient
{
    private Client $client;
    
    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }
    
    public function createPayment(array $data, ?string $idempotencyKey = null): array
    {
        $key = $idempotencyKey ?? Uuid::uuid4()->toString();
        
        try {
            $response = $this->client->post('/payments', [
                'json' => $data,
                'headers' => [
                    'Idempotency-Key' => $key,
                ],
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 409) {
                // Request in progress, retry
                sleep(1);
                return $this->createPayment($data, $key);
            }
            throw $e;
        }
    }
}

// Usage
$client = new IdempotentApiClient(
    'https://api.example.com/v1',
    'your-api-key'
);

$payment = $client->createPayment([
    'amount' => 1000,
    'currency' => 'USD',
    'customer_id' => 'cus_123',
]);
```

### Python

```python
import requests
import uuid
import time
from typing import Optional, Dict, Any

class IdempotentApiClient:
    def __init__(self, base_url: str, api_key: str):
        self.base_url = base_url
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json',
        })
    
    def create_payment(
        self, 
        data: Dict[str, Any], 
        idempotency_key: Optional[str] = None
    ) -> Dict[str, Any]:
        key = idempotency_key or str(uuid.uuid4())
        
        try:
            response = self.session.post(
                f'{self.base_url}/payments',
                json=data,
                headers={'Idempotency-Key': key}
            )
            response.raise_for_status()
            return response.json()
        except requests.HTTPError as e:
            if e.response.status_code == 409:
                # Request in progress, retry
                time.sleep(1)
                return self.create_payment(data, key)
            raise

# Usage
client = IdempotentApiClient(
    'https://api.example.com/v1',
    'your-api-key'
)

payment = client.create_payment({
    'amount': 1000,
    'currency': 'USD',
    'customer_id': 'cus_123',
})
```

### cURL

```bash
# Create payment with idempotency
curl -X POST https://api.example.com/v1/payments \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{
    "amount": 1000,
    "currency": "USD",
    "customer_id": "cus_123"
  }'

# Retry with same key (returns cached response)
curl -X POST https://api.example.com/v1/payments \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{
    "amount": 1000,
    "currency": "USD",
    "customer_id": "cus_123"
  }'
```

## Response Codes

### Success Codes
- `200 OK` - Request successful (GET, refund)
- `201 Created` - Resource created (POST payment)

### Client Error Codes
- `400 Bad Request` - Missing or invalid `Idempotency-Key` header
- `401 Unauthorized` - Authentication required or invalid
- `404 Not Found` - Resource not found
- `409 Conflict` - Request in progress (concurrent duplicate)
- `422 Unprocessable Entity` - Validation failed or payload mismatch

### Server Error Codes
- `500 Internal Server Error` - Server error
- `503 Service Unavailable` - Service temporarily unavailable

## Error Handling Best Practices

### Handle Missing Idempotency Key (400)

```typescript
try {
  await client.createPayment(data);
} catch (error) {
  if (error.response?.status === 400) {
    // Ensure idempotency key is provided
    const key = generateIdempotencyKey();
    await client.createPayment(data, key);
  }
}
```

### Handle Concurrent Requests (409)

```typescript
async function createPaymentWithRetry(data, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await client.createPayment(data, idempotencyKey);
    } catch (error) {
      if (error.response?.status === 409 && i < maxRetries - 1) {
        // Wait and retry
        await new Promise(resolve => setTimeout(resolve, 1000 * (i + 1)));
        continue;
      }
      throw error;
    }
  }
}
```

### Handle Payload Mismatch (422)

```typescript
try {
  await client.createPayment(data, cachedKey);
} catch (error) {
  if (error.response?.data?.message === 'Payload mismatch for idempotency key') {
    // Use a new key for the new payload
    const newKey = generateIdempotencyKey();
    return await client.createPayment(data, newKey);
  }
}
```

## Testing with OpenAPI

### 1. Contract Testing

```typescript
import { OpenAPIValidator } from 'express-openapi-validator';

describe('Payment API', () => {
  it('should match OpenAPI spec', async () => {
    const response = await request(app)
      .post('/payments')
      .set('Idempotency-Key', uuid())
      .send({ amount: 1000, currency: 'USD', customer_id: 'cus_123' })
      .expect(201);
    
    // Validate against OpenAPI schema
    expect(response.body).toMatchSchema('Payment');
  });
});
```

### 2. Mock Server

```bash
# Using Prism
npm install -g @stoplight/prism-cli

prism mock openapi.yaml

# API now available at http://localhost:4010
```

### 3. Load Testing

```yaml
# k6 test script
import http from 'k6/http';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.0.0/index.js';

export default function() {
  const url = 'https://api.example.com/v1/payments';
  const payload = JSON.stringify({
    amount: 1000,
    currency: 'USD',
    customer_id: 'cus_123'
  });
  
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Idempotency-Key': uuidv4(),
      'Authorization': 'Bearer YOUR_TOKEN'
    }
  };
  
  http.post(url, payload, params);
}
```

## Validation

### Validate OpenAPI Spec

```bash
# Using Spectral
npm install -g @stoplight/spectral-cli

spectral lint openapi.yaml

# Using OpenAPI CLI
npm install -g @redocly/openapi-cli

openapi lint openapi.yaml
```

### CI/CD Integration

```yaml
# .github/workflows/openapi.yml
name: OpenAPI Validation

on: [push, pull_request]

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Validate OpenAPI
        uses: char0n/swagger-editor-validate@v1
        with:
          definition-file: openapi.yaml
```

## API Versioning

The OpenAPI spec supports versioning through:

1. **URL versioning** (recommended):
   ```
   /v1/payments
   /v2/payments
   ```

2. **Header versioning**:
   ```
   Accept: application/vnd.api+json; version=1
   ```

3. **Query parameter**:
   ```
   /payments?version=1
   ```

## Documentation Hosting

### Option 1: Redoc (Recommended)

```html
<!DOCTYPE html>
<html>
<head>
  <title>API Documentation</title>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">
</head>
<body>
  <redoc spec-url='./openapi.yaml'></redoc>
  <script src="https://cdn.jsdelivr.net/npm/redoc@latest/bundles/redoc.standalone.js"></script>
</body>
</html>
```

### Option 2: SwaggerUI

```html
<!DOCTYPE html>
<html>
<head>
  <title>API Documentation</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@latest/swagger-ui.css" />
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@latest/swagger-ui-bundle.js"></script>
  <script>
    SwaggerUIBundle({
      url: './openapi.yaml',
      dom_id: '#swagger-ui',
      presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIBundle.SwaggerUIStandalonePreset
      ],
    });
  </script>
</body>
</html>
```

### Option 3: ReadMe.io

Upload `openapi.yaml` to ReadMe.io for interactive documentation with:
- Try-it console
- Code examples in multiple languages
- Search functionality
- Version management

## Customization

### Extend the Specification

```yaml
# Add custom endpoints
paths:
  /your-endpoint:
    post:
      tags:
        - YourTag
      parameters:
        - $ref: '#/components/parameters/IdempotencyKey'
      # ... rest of specification
```

### Add Custom Schemas

```yaml
components:
  schemas:
    YourCustomModel:
      type: object
      properties:
        id:
          type: string
        # ... your properties
```

### Environment-Specific Servers

```yaml
servers:
  - url: https://api.example.com/v1
    description: Production
    variables:
      environment:
        default: prod
        enum:
          - prod
          - staging
```

## Support

For issues or questions about the OpenAPI specification:
- Create an issue in the GitHub repository
- Email: support@example.com
- Check the [OpenAPI documentation](https://swagger.io/docs/specification/about/)

---

**Complete OpenAPI 3.0 specification available in [openapi.yaml](openapi.yaml)**
