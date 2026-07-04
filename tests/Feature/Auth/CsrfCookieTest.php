<?php

it('returns 204 with XSRF-TOKEN cookie from /sanctum/csrf-cookie', function (): void {
    $response = $this->get('/sanctum/csrf-cookie');

    $response->assertNoContent();

    $cookies = $response->headers->getCookies();

    $xsrfCookie = null;
    foreach ($cookies as $cookie) {
        if ($cookie->getName() === 'XSRF-TOKEN') {
            $xsrfCookie = $cookie;

            break;
        }
    }

    expect($xsrfCookie)->not->toBeNull();
    expect($xsrfCookie->getValue())->not->toBeEmpty();
});
