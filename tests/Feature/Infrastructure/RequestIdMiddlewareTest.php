<?php

it('adds an X-Request-Id header to responses', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertHeader('X-Request-Id');
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

it('respects incoming X-Request-Id header if provided', function () {
    $incoming = 'test-fixed-request-id';
    $response = $this->withHeaders(['X-Request-Id' => $incoming])->get('/');

    $response->assertStatus(200);
    $response->assertHeader('X-Request-Id', $incoming);
});
