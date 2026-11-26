<?php

declare(strict_types=1);

it('shows the page in the locale of the url', function (): void {
    $this->getJson(route('pages.show', ['locale' => 'nl', 'slug' => 'over']))
        ->assertOk()
        ->assertExactJson(['page' => 'about', 'locale' => 'nl', 'slug' => 'over', 'title' => 'Over deze starter']);

    $this->getJson(route('pages.show', ['locale' => 'en', 'slug' => 'about']))
        ->assertOk()
        ->assertJsonPath('title', 'About this starter');
});

it('answers not found for an unknown slug', function (): void {
    $this->getJson(route('pages.show', ['locale' => 'en', 'slug' => 'missing']))->assertNotFound();
});

it('answers not found for an unknown locale', function (): void {
    $this->getJson(route('pages.show', ['locale' => 'de', 'slug' => 'about']))->assertNotFound();
});
