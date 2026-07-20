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
- [x] Étape 2 — Référentiel hôpitaux & catalogue d'examens (185 examens réels
      extraits automatiquement des 5 DOCX institutionnels, CRUD admin complet)
- [x] Étape 3 — CRUD comptes rendus + versions + historique groupé par
      journée, recherche (nom/hôpital/date), statuts brouillon/finalisé/signé
- [x] Étape 4 — Moteur d'insertion sémantique (portage fidèle de
      frontend-existant/app.js : synonymes, latéralité, valeurs numériques)
- [x] Étape 5 — Génération DOCX/PDF depuis les templates réels (modification
      XML directe des 5 templates institutionnels, jamais de reconstruction)
- [x] Étape 6 — Proxys IA (transcription Groq, raffinage et rédaction
      Anthropic, clés chiffrées en base, journalisation d'usage sans PHI)
- [x] Étape 7 — API de synchronisation PWA (`/api/v1/reports/sync`, idempotente
      par `client_uuid`, dernier écrivain gagnant, la PWA reste offline-first)
- [ ] Étape 8 — Assistant « Ajouter un hôpital »
- [ ] Étape 9 — Refonte professionnelle de la PWA
- [ ] Étape 10 — Sauvegardes, administration, audit
- [ ] Étape 11 — Durcissement final, README complet, revue de sécurité

## Démarrage rapide (développement local)

La conversion PDF des comptes rendus (F3) requiert LibreOffice sur la machine
(`libreoffice-writer`, binaire `soffice`) :

```bash
sudo apt-get install -y libreoffice-writer   # ou LIBREOFFICE_BINARY dans .env
```

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

## Référentiel hôpitaux (F2)

Le catalogue des 5 hôpitaux (185 examens) est généré dans
`database/seeders/data/templates.json` par analyse automatique des DOCX
institutionnels stockés dans `storage/app/templates/` :

```bash
php artisan app:generate-hospital-catalog   # régénère templates.json
php artisan db:seed --class=HospitalCatalogSeeder
```

Le moteur d'extraction (`App\Services\HospitalDocxParser`) détecte un examen
par bloc (saut de page + titre), ses sections TECHNIQUE/RÉSULTATS/CONCLUSION,
la latéralité et la couleur dominante des titres — il sera réutilisé et
enrichi d'une interface de prévisualisation à l'étape 8 (assistant
« Ajouter un hôpital »). Gestion des hôpitaux et de leur catalogue d'examens :
`/admin/hopitaux` (réservé au rôle `admin`).

## Moteur d'insertion sémantique (F5)

`App\Services\SemanticInsertionService` est un portage fidèle des fonctions
`semanticInsert`, `matchScore` et `TERM_SYNONYMS` (~40 groupes) de
`frontend-existant/app.js`, la PWA de référence. Mêmes règles exactes :
score de correspondance par jetons + synonymes, bonus/malus de latéralité
gauche/droite, remplacement ciblé des valeurs numériques (ex. « battement
cardiaque = 377 bpm »), réécriture de la conclusion si une anomalie est
dictée alors qu'elle dit encore « normal ». Contrairement à la version JS
qui manipule le DOM, ce service opère directement sur le tableau `results`
du JSON `content` d'un `Report`. Il n'est pas encore branché à une
interface (ce sera fait avec la dictée vocale/STT à l'étape 6 et la refonte
de la PWA à l'étape 9) ; voir `tests/Unit/SemanticInsertionServiceTest.php`
pour les cas obligatoires du cahier des charges.

## Stack

Laravel 12 · PHP 8.3+ · Sanctum (API + sessions) · MySQL 8 en production
(SQLite pour les tests) · Blade + Alpine.js + Tailwind pour l'admin ·
manipulation XML OOXML directe (ZipArchive/DOMDocument, sans reconstruction
du document) + LibreOffice headless pour la génération DOCX/PDF · Pest pour
les tests.

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
- CSP : `script-src 'self' 'unsafe-eval'` — `unsafe-eval` est nécessaire au
  fonctionnement d'Alpine.js (évaluation de `x-data`/`x-on`), mais aucun
  script inline n'est autorisé (`unsafe-inline` volontairement absent) ; tout
  le JS est servi en fichiers externes versionnés via Vite.

## Comptes rendus (F3)

CRUD complet sous `/admin/comptes-rendus`, accessible aux trois rôles avec
des droits différenciés :
- **Radiologue / admin** : rédigent le contenu médical (technique, résultats,
  conclusion) et peuvent finaliser puis signer un compte rendu.
- **Secrétaire** : saisit l'identité patient et choisit l'examen ; le contenu
  médical est repris tel quel du template (elle ne peut pas le rédiger ni
  valider — F1).

Chaque sauvegarde du contenu médical crée automatiquement une entrée dans
`report_versions` (restauration possible). L'historique (`/admin/comptes-rendus`)
est groupé par journée avec recherche par nom de patient (déchiffré en
mémoire, R3), hôpital et plage de dates. Aucune suppression physique : un
compte rendu archivé reste consultable en base (soft delete).

## Génération DOCX/PDF (F3 complet)

`App\Services\DocxReportGenerator` produit le document final en modifiant le
XML (`word/document.xml`) du template institutionnel réel de l'hôpital —
jamais par reconstruction (R1) :

1. Le DOCX source contient tous les examens de l'hôpital, un par bloc
   (mêmes règles de détection que `HospitalDocxParser`, F2) ; seul le bloc de
   l'examen demandé est conservé, les autres sont supprimés du corps du
   document. Deux structures institutionnelles sont gérées : bloc de
   paragraphes (Nkoulou, HMR1, Zalom, CHRACERH) ou ligne de tableau (CHM).
2. Seul le *texte* des runs identité/technique/résultats/conclusion est
   remplacé, en réutilisant la mise en forme (`rPr`) d'origine de chaque
   paragraphe — police (Arial Narrow), taille, gras restent ceux du template.
   Une anomalie dictée colore ses runs en rouge (`#C00000`) sans jamais
   ajouter de soulignement.
3. Les titres et intitulés de section sont explicitement désoulignés même si
   le template source porte encore un `<w:u/>` hérité (le cahier des charges
   interdit tout soulignement de titre — priorité sur le fichier d'origine).
4. En-tête, pied de page, logo et couleurs institutionnelles sont des parties
   DOCX distinctes (`word/header1.xml`, `word/footer1.xml`) : jamais touchées
   par la génération, donc préservées à l'identique.

`App\Services\DocxToPdfService` convertit ensuite le DOCX en PDF via
LibreOffice headless (`soffice --convert-to pdf`, binaire configurable via
`LIBREOFFICE_BINARY`). `App\Jobs\GenerateReportDocumentJob` orchestre les deux
étapes et enregistre les fichiers comme `Attachment` du compte rendu ; si la
conversion PDF échoue (LibreOffice indisponible), le DOCX reste exploitable et
l'échec est seulement journalisé. Déclenché depuis la fiche d'un compte rendu
(`POST /admin/comptes-rendus/{report}/document`, réservé radiologue/admin) de
façon synchrone (`dispatchSync`) pour un retour immédiat sans dépendre d'un
worker de file d'attente actif ; téléchargement via
`GET /admin/comptes-rendus/{report}/pieces-jointes/{attachment}`.

Voir `tests/Feature/DocxReportGenerationTest.php` (entête/logo préservés à
l'identique, police Arial Narrow conservée, anomalie en rouge, titre jamais
souligné, seul l'examen demandé subsiste) et
`tests/Feature/GenerateReportDocumentJobTest.php`.

## Proxys IA (F4)

La PWA existante (`frontend-existant/app.js`) appelle aujourd'hui Groq et
Anthropic directement depuis le navigateur, avec des clés stockées dans le
`localStorage` du poste — contraire à R4. Le backend introduit trois proxys
qui portent fidèlement la même logique métier côté serveur, clé jamais
exposée au client :

- `POST /api/v1/stt` — transcription vocale via Groq (Whisper). Le fichier
  audio n'est jamais écrit sur disque côté serveur : transmis en flux au
  fournisseur depuis l'upload temporaire de la requête, puis abandonné (R3).
- `POST /api/v1/ai/refine` — intègre une dictée dans les résultats existants
  (remplace la ligne de la structure anatomique concernée ou l'ajoute, met à
  jour la conclusion si nécessaire) ; portage fidèle du prompt système de
  `insertDictationWithClaude()`.
- `POST /api/v1/ai/draft` — génère un compte rendu complet (heading,
  technique, résultats, conclusion) à partir d'une demande libre, du style de
  l'hôpital (exemples issus du catalogue) et d'un contexte patient explicite
  (R2 : zéro invention au-delà de ce qui est fourni) ; portage fidèle du
  prompt système et de la boucle de nouvelle tentative sur troncature
  (`stop_reason: max_tokens`) de `generateAiDraft()`.

Toutes les routes sont protégées par `auth:sanctum` + `token.active` et
limitées à 20 requêtes/minute/utilisateur (`throttle:ai`) — les appels IA ont
un coût réel côté fournisseur.

**Clés API** — écran admin `/admin/parametres/cles-api` (rôle `admin`) : les
clés Groq/Anthropic sont saisies puis chiffrées en base
(`api_credentials.api_key`, cast `encrypted`) et ne sont plus jamais
réaffichées en clair. `App\Services\Ai\ApiCredentialResolver` se replie sur
`GROQ_API_KEY`/`ANTHROPIC_API_KEY` (`.env`) tant qu'aucune clé n'est encore
enregistrée en base.

**Journalisation d'usage** — chaque appel crée une entrée dans
`ai_usage_logs` (utilisateur, endpoint, fournisseur, modèle, succès, code
HTTP, durée). Ni le texte dicté ni le texte généré n'y sont jamais stockés
(R3) : `tests/Feature/Api/Ai/*` vérifient explicitement l'absence du contenu
médical dans la table de journalisation.

## Synchronisation PWA (F6)

La PWA reste offline-first : elle continue de fonctionner et de stocker
localement même sans réseau (R5). Le backend n'est qu'une couche de
rattrapage au retour du réseau, jamais un point de passage obligé.

- `POST /api/v1/reports/sync` — envoi des comptes rendus créés/modifiés hors
  ligne. Idempotent par `client_uuid` (généré côté appareil, jamais
  régénéré, colonne unique) : rejouer le même envoi après une coupure réseau
  ne crée jamais de doublon. Résolution de conflit par dernier écrivain
  gagnant, comparée sur l'horodatage `updated_at` fourni par le client — un
  envoi plus ancien que l'état serveur est ignoré (`outcome: unchanged`), pas
  écrasé. Un compte rendu archivé côté serveur n'est jamais réanimé
  silencieusement par un renvoi tardif (`outcome: conflict`).
- `GET /api/v1/reports/sync?since=<ISO 8601>` — récupère les comptes rendus
  créés/modifiés/archivés depuis `since` (pagination par curseur implicite
  via `has_more` + `updated_at` du dernier élément), y compris les comptes
  rendus archivés entre-temps (`deleted: true`) pour que la PWA purge son
  stockage local.

Voir `tests/Feature/Api/ReportSyncTest.php`.
