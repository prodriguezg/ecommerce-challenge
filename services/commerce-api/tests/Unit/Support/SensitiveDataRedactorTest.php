<?php

namespace Tests\Unit\Support;

use App\Support\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    public function test_redacts_sensitive_context_and_embedded_secrets(): void
    {
        $redactor = new SensitiveDataRedactor;

        $context = $redactor->redactArray([
            'password' => 'SecretPass1!',
            'nested' => ['customer_email' => 'customer@example.test', 'image_path' => '/private/uploads/product.png', 'order_id' => '01ORDER'],
            'message' => 'Authorization: Bearer bearer-secret card 4000000000010001',
            'url' => '/orders?guest_token=guest-secret&view=status',
        ]);

        $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertSame('[REDACTED]', $context['password']);
        $this->assertSame('01ORDER', $context['nested']['order_id']);
        $this->assertStringNotContainsString('SecretPass1!', $encoded);
        $this->assertStringNotContainsString('customer@example.test', $encoded);
        $this->assertStringNotContainsString('/private/uploads/product.png', $encoded);
        $this->assertStringNotContainsString('bearer-secret', $encoded);
        $this->assertStringNotContainsString('4000000000010001', $encoded);
        $this->assertStringNotContainsString('guest-secret', $encoded);
    }
}
