<?php

test('guests can open legal footer pages', function (string $routeName, string $title) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/legal/show')
            ->where('title', $title)
            ->has('summary')
            ->has('sections'));
})->with([
    'terms' => ['legal.terms', 'Términos y condiciones'],
    'privacy' => ['legal.privacy', 'Aviso de privacidad'],
    'feedback' => ['legal.feedback', 'Quejas y sugerencias'],
    'affiliation' => ['legal.affiliation', 'Contacto para afiliación'],
]);
