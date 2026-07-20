# Radio CR Manager

Backend Laravel et interface d'administration pour la gestion des comptes
rendus de radiologie d'un radiologue exerçant dans plusieurs institutions à
Yaoundé (Cameroun). Ce dépôt construit également, dans un second temps, la
refonte professionnelle de la PWA existante (dictée vocale, mode hors ligne,
moteur d'insertion sémantique).

> Documentation d'installation complète : à venir en fin de projet (étape 11
> du plan de construction). Ce README est mis à jour à chaque étape.

## État d'avancement

- [x] Étape 1 — Squelette Laravel, authentification (Sanctum + sessions),
      rôles (`admin`, `radiologue`, `secretaire`), 2FA par e-mail,
      verrouillage de compte, en-têtes de sécurité, HTTPS forcé.
- [ ] Étape 2 — Référentiel hôpitaux & catalogue d'examens
- [ ] Étape 3 — CRUD comptes rendus + versions + historique
- [ ] Étape 4 — Moteur d'insertion sémantique
- [ ] Étape 5 — Génération DOCX/PDF depuis les templates réels
- [ ] Étape 6 — Proxys IA (transcription + rédaction)
- [ ] Étape 7 — API de synchronisation PWA
- [ ] Étape 8 — Assistant « Ajouter un hôpital »
- [ ] Étape 9 — Refonte professionnelle de la PWA
- [ ] Étape 10 — Sauvegardes, administration, audit
- [ ] Étape 11 — Durcissement final, README complet, revue de sécurité

## Démarrage rapide (développement local)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# En local sans serveur MySQL, vous pouvez utiliser SQLite :
#   DB_CONNECTION=sqlite dans .env, puis :
touch database/database.sqlite

php artisan migrate --seed
npm run build   # ou `npm run dev` pour le rechargement à chaud
php artisan serve
```

Comptes de démonstration créés par le seeder (`UserSeeder`) — **à changer en
production** :

| Rôle       | E-mail                              | Mot de passe     |
|------------|--------------------------------------|-------------------|
| Admin      | admin@radio-cr-manager.local         | Admin!2026Demo    |
| Radiologue | radiologue@radio-cr-manager.local    | Radio!2026Demo    |

## Tests

```bash
composer test        # ou : php artisan test / ./vendor/bin/pest
./vendor/bin/pint --test   # vérification du style PSR-12
```

## Stack

Laravel 12 · PHP 8.3+ · Sanctum (API + sessions) · MySQL 8 en production
(SQLite pour les tests) · Blade + Alpine.js + Tailwind pour l'admin ·
PhpOffice/PhpWord + LibreOffice headless pour la génération DOCX/PDF · Pest
pour les tests.

## Sécurité (F9)

- HTTPS forcé hors environnement local (`App\Http\Middleware\ForceHttps`).
- En-têtes de sécurité systématiques : CSP, `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`
  (`App\Http\Middleware\SecurityHeaders`).
- Verrouillage de compte 15 minutes après 5 échecs de connexion.
- Authentification à deux facteurs par e-mail, activable par utilisateur.
- Déconnexion automatique de la PWA après 15 minutes d'inactivité
  (`/api/v1/heartbeat`).
- Journalisation des connexions et actions sensibles (`audit_logs`).
