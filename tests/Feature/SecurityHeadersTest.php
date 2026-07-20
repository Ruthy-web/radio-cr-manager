<?php

it('ajoute les en-têtes de sécurité sur toutes les réponses', function () {
    $response = $this->get('/admin/connexion');

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy');
});
