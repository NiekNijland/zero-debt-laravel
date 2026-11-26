<?php

declare(strict_types=1);

it('redirects the slug of another locale to the canonical slug', function (): void {
    $this->get('/nl/about?ref=1')->assertRedirect('http://localhost/nl/over?ref=1')->assertStatus(301);
});

it('lets the canonical slug through', function (): void {
    $this->get('/nl/over')->assertOk();
});

it('lets an unknown slug through to the controller', function (): void {
    $this->get('/nl/missing')->assertNotFound();
});

it('does not redirect a request that is not safe', function (): void {
    $this->post('/nl/about')->assertStatus(405);
});
