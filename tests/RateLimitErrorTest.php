<?php

namespace ThirtybeesStripe;

class RateLimitErrorTest extends TestCase
{
    private function rateLimitErrorResponse()
    {
        return [
            'error' => [],
        ];
    }

    /**
     * @expectedException ThirtybeesStripe\Error\RateLimit
     */
    public function testRateLimit()
    {
        $this->mockRequest('GET', '/v1/accounts/acct_DEF', [], $this->rateLimitErrorResponse(), 429);
        Account::retrieve('acct_DEF');
    }
}
