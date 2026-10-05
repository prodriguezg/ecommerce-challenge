<?php

namespace Tests\Unit\Support;

use App\Support\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    public function test_redacts_payment_and_webhook_secrets(): void
    {
        $redactor = new SensitiveDataRedactor;

        $context = $redactor->redactArray([
            'payment_test_number' => '4000000000010001',
            'authorization' => 'Bearer webhook-secret',
            'callback' => 'https://commerce.test/webhook?token=callback-secret',
            'event_id' => '01EVENT',
        ]);

        $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertSame('01EVENT', $context['event_id']);
        $this->assertStringNotContainsString('4000000000010001', $encoded);
        $this->assertStringNotContainsString('webhook-secret', $encoded);
        $this->assertStringNotContainsString('callback-secret', $encoded);
    }
}
