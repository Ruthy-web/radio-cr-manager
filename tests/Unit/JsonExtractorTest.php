<?php

use App\Services\Ai\Exceptions\UnparsableAiResponseException;
use App\Services\Ai\JsonExtractor;

it('décode un JSON strict', function () {
    $result = JsonExtractor::extract('{"heading":"Titre","results":["a","b"],"conclusion":"Normal."}');

    expect($result)->toBe(['heading' => 'Titre', 'results' => ['a', 'b'], 'conclusion' => 'Normal.']);
});

it('retire les balises Markdown ```json autour de la réponse', function () {
    $result = JsonExtractor::extract("```json\n{\"conclusion\":\"Normal.\"}\n```");

    expect($result)->toBe(['conclusion' => 'Normal.']);
});

it('extrait le premier objet JSON même entouré de texte parasite', function () {
    $result = JsonExtractor::extract('Voici le résultat : {"conclusion":"Normal."} Merci.');

    expect($result)->toBe(['conclusion' => 'Normal.']);
});

it('répare un JSON tronqué en fin de réponse (max_tokens atteint)', function () {
    $truncated = '{"heading":"COMPTE RENDU","results":["Ligne un.","Ligne deux';

    $result = JsonExtractor::extract($truncated);

    expect($result['heading'])->toBe('COMPTE RENDU')
        ->and($result['results'])->toBe(['Ligne un.', 'Ligne deux']);
});

it('lève une exception portant le texte brut quand rien n’est exploitable', function () {
    JsonExtractor::extract('ceci n\'est pas du JSON du tout');
})->throws(UnparsableAiResponseException::class);

it('conserve le texte brut sur l’exception pour permettre une nouvelle tentative', function () {
    try {
        JsonExtractor::extract('pas de json ici');
        $this->fail('une exception était attendue');
    } catch (UnparsableAiResponseException $e) {
        expect($e->rawText)->toBe('pas de json ici');
    }
});
