# Radio CR Manager

Backend Laravel et interface d'administration pour la gestion des comptes
rendus de radiologie d'un radiologue exerçant dans plusieurs institutions à
Yaoundé (Cameroun). Ce dépôt construit également, dans un second temps, la
refonte professionnelle de la PWA existante (dictée vocale, mode hors ligne,
moteur d'insertion sémantique).

> Les 11 étapes du plan de construction sont terminées. Ce README documente
> l'installation locale, le déploiement en production, l'architecture et la
> conformité aux règles du cahier des charges.

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
- [x] Étape 8 — Assistant « Ajouter un hôpital » (import DOCX, prévisualisation
      corrigible, aucun cas particulier codé en dur)
- [x] Étape 9 — Refonte professionnelle de la PWA (cœur métier : auth réelle,
      catalogue hors ligne, dictée + moteur sémantique, IA, synchronisation)
- [x] Étape 10 — Sauvegardes planifiées, tableau de bord, journal d'audit
- [x] Étape 11 — Durcissement final (gestion des utilisateurs, limitation de
      débit générale de l'API, cohérence des rôles sur la synchronisation),
      README complet, revue de sécurité
- [x] Étape 12 — Parité fonctionnelle complète de la PWA avec l'application de
      référence (lecture IA vision du bulletin patient, assistant de recherche
      IA avec sources web, bibliothèque de modèles consultable hors ligne,
      import/export JSON de l'historique, dictée locale Whisper dans le
      navigateur) et modernisation visuelle de l'interface

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
la latéralité et la couleur dominante des titres. Gestion des hôpitaux et de
leur catalogue d'examens : `/admin/hopitaux` (réservé au rôle `admin`).

### Assistant « Ajouter un hôpital » (F2 complet)

`/admin/hopitaux/importer` réutilise directement `HospitalDocxParser` — le
même moteur que le catalogue des 5 hôpitaux de départ, sans aucun cas
particulier codé en dur — pour ajouter un nombre illimité d'hôpitaux :

1. **Upload** — nom, radiologue signataire, DOCX de comptes rendus normaux.
2. **Analyse** — le document est parsé côté serveur et mis en attente
   (`storage/app/private/hospital-imports/`, `App\Services\HospitalImportStaging`,
   30 min) : ni Hospital ni ExamTemplate ne sont créés à ce stade.
3. **Prévisualisation corrigible** — nom/slug/couleur/radiologue de l'hôpital
   et tableau des examens détectés (titre, latéralité, aperçu technique,
   nombre de résultats, aperçu conclusion) ; le titre et la latéralité de
   chaque examen sont corrigibles avant validation (les deux champs à
   l'origine des vrais faux positifs de détection). Le contenu détaillé
   (technique, résultats, conclusion) reste modifiable individuellement après
   import via l'écran d'édition d'examen existant.
4. **Confirmation** — création de l'hôpital et de son catalogue d'examens
   (`updateOrCreate` par titre, comme le seeder initial), déplacement du DOCX
   vers `storage/app/templates/{slug}.docx` (chemin lu par
   `DocxReportGenerator`, F3).

Voir `tests/Feature/Admin/HospitalImportControllerTest.php`.

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
(SQLite pour les tests) · Blade + Alpine.js + Tailwind pour l'admin · PWA en
JS natif sans framework (IndexedDB, service worker) pour la dictée de
terrain · manipulation XML OOXML directe (ZipArchive/DOMDocument, sans
reconstruction du document) + LibreOffice headless pour la génération
DOCX/PDF · Pest pour les tests.

## Sécurité (F9)

- HTTPS forcé hors environnement local (`App\Http\Middleware\ForceHttps`).
- En-têtes de sécurité systématiques : CSP, `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`
  (`App\Http\Middleware\SecurityHeaders`).
- Mots de passe : 10 caractères minimum, majuscule/minuscule/chiffre/symbole,
  vérifiés contre les fuites connues (`Password::uncompromised()`,
  `AppServiceProvider`).
- Verrouillage de compte 15 minutes après 5 échecs de connexion.
- Authentification à deux facteurs par e-mail, activable par utilisateur.
- Déconnexion automatique de la PWA après 15 minutes d'inactivité
  (`/api/v1/heartbeat`), révocation du jeton à la déconnexion et à la
  désactivation d'un compte (`/admin/utilisateurs`).
- Limitation de débit : connexion (10/min/e-mail+IP), API générale
  authentifiée (120/min/utilisateur, `throttle:api`), proxys IA
  (20/min/utilisateur, `throttle:ai`, en plus du contrôle d'accès).
- Rôles appliqués identiquement sur tous les canaux : l'interface admin
  (`ReportRequest::canEditMedicalContent()`) et l'API de synchronisation
  (`ReportSyncService`) empêchent toutes deux une secrétaire de rédiger le
  contenu médical ou de finaliser/signer un compte rendu (F1).
- Journalisation des connexions et actions sensibles (`audit_logs`),
  consultable en lecture seule par un administrateur (`/admin/audit`).
- CSP : `script-src 'self' 'unsafe-eval'` sur l'admin — `unsafe-eval` est
  nécessaire au fonctionnement d'Alpine.js (évaluation de `x-data`/`x-on`),
  mais aucun script inline n'est autorisé (`unsafe-inline` volontairement
  absent) ; tout le JS est servi en fichiers externes versionnés via Vite. La
  PWA (`/app/`) a une CSP plus stricte encore : ni `unsafe-eval` ni
  `unsafe-inline`, JS natif sans framework.
- Aucune donnée cross-origin : la PWA et l'admin sont servies par la même
  origine que l'API, aucune configuration CORS permissive n'est nécessaire.

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
- `POST /api/v1/ai/bulletin` — lecture par IA vision (Claude) d'une photo ou
  d'un PDF de bulletin de demande d'examen, souvent manuscrit ; renvoie les
  champs identifiés (identité, âge/date de naissance, sexe, n° dossier,
  médecin, examen, côté) pour préremplissage, jamais pour écriture directe
  (R2) ; portage fidèle du prompt de `readWithClaudeVision()`.
- `POST /api/v1/ai/chat` — assistant de recherche clinique/radiologique,
  recherche web optionnelle (`web_search_20250305`), sources dédupliquées
  renvoyées séparément du texte ; portage fidèle du prompt système et du
  parsing de citations de l'assistant de référence.

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
  stockage local. Rôle appliqué identiquement à `/admin/comptes-rendus`
  (F1) : une secrétaire ne peut pas rédiger le contenu médical ni
  finaliser/signer via la synchronisation — le contenu envoyé est ignoré et
  repris tel quel depuis le template de l'examen, le statut ne peut pas
  dépasser « brouillon ».
- `GET /api/v1/catalog` — hôpitaux actifs et leurs examens actifs, pour mise
  en cache locale (IndexedDB) et création de compte rendu hors ligne.

Voir `tests/Feature/Api/ReportSyncTest.php` et `tests/Feature/Api/CatalogControllerTest.php`.

## Refonte de la PWA (F11)

`public/app/` (servi statiquement, `/app/`) remplace `frontend-existant/` par
une interface professionnelle, en JS natif sans framework ni étape de build
(cohérent avec l'app de référence), qui reste **offline-first** (R5) : le
backend n'est qu'une couche de synchronisation, jamais un point de passage
obligé.

**Périmètre** — authentification réelle (Sanctum, F1), catalogue
hôpitaux/examens mis en cache pour fonctionner hors ligne, dictée vocale
(Web Speech API, import de vocal transcrit, moteur d'insertion sémantique
porté fidèlement en JS dans `public/app/js/semantic.js` — copie fonctionnelle
de `App\Services\SemanticInsertionService`, vérifiée sur les mêmes cas que
`tests/Unit/SemanticInsertionServiceTest.php`), raffinage et rédaction
assistée par IA (F4), synchronisation (F6), ainsi que — depuis l'étape 12 —
la parité complète avec l'application de référence : lecture IA vision du
bulletin patient (préremplissage des champs, jamais de donnée inventée —
R2), assistant de recherche conversationnel avec citation de sources web,
bibliothèque de modèles d'examens consultable et filtrable hors ligne,
import/export JSON de l'historique local. La finalisation, la signature et
la génération du DOCX/PDF officiel (étape 5) restent du ressort de
l'interface d'administration, pas de la PWA.

**Dictée vocale multi-fournisseur (R3/R4)** — contrairement à l'application
de référence, qui pouvait envoyer l'audio directement à Groq/OpenAI avec une
clé API stockée côté navigateur, seuls deux moteurs conformes sont proposés :
« serveur » (proxy `/api/v1/stt` existant, la clé reste côté backend) et
« local » (Whisper exécuté entièrement dans le navigateur via
`@huggingface/transformers`, l'audio ne quitte jamais l'appareil et
fonctionne hors ligne une fois le modèle mis en cache). Les options
d'appel direct à un fournisseur tiers avec clé côté client ont été
volontairement omises du portage.

- `public/app/index.html` + `css/app.css` — interface (connexion, dossier,
  bulletin patient, assistant IA, modèles, historique, paramètres).
- `js/db.js` — IndexedDB (`reports`, `catalog`, `meta` : jeton, dernière
  synchronisation, préférences de dictée).
- `js/api.js` — client HTTP (jeton Sanctum, déconnexion locale sur 401).
- `js/semantic.js` — moteur d'insertion sémantique hors ligne.
- `js/stt.js` + `js/stt-worker.js` — abstraction de dictée multi-fournisseur
  et worker dédié à l'inférence Whisper locale (WebGPU avec repli WASM).
- `js/markdown.js` — rendu Markdown minimal et sûr pour les réponses de
  l'assistant IA.
- `js/icons.js` — pictogrammes SVG monoline injectés côté client (aucune
  police d'icônes externe, cohérent avec la CSP stricte).
- `js/app.js` — contrôleur applicatif (auth, dossier, bulletin, dictée, IA,
  assistant, modèles, historique, sync).
- `service-worker.js` — cache uniquement la coquille applicative (jamais les
  réponses `/api/*`, qui portent des données patient et vivent dans
  IndexedDB) pour un chargement hors ligne instantané.

## Sauvegardes (F7)

`App\Services\BackupService` (`php artisan app:backup`, planifié
quotidiennement à 02h00 via `routes/console.php`) archive en zip : le dump de
la base (fichier SQLite copié tel quel, ou `mysqldump --single-transaction`
en production — les champs nominatifs y restent chiffrés, R3), les templates
institutionnels (`storage/app/templates/`) et les documents déjà générés
(`storage/app/private/reports/`). Rotation automatique sur `BACKUP_KEEP`
archives (14 par défaut). Destination configurable via `BACKUP_DISK`
(`local` par défaut, `backup` pour un stockage S3-compatible distant via les
variables `BACKUP_S3_*`). La clé applicative (`APP_KEY`) n'est jamais incluse
dans l'archive. Voir `tests/Feature/BackupServiceTest.php`.

## Administration et audit (F8)

- **Tableau de bord** (`/admin`) — statistiques (comptes rendus par statut,
  activité du jour, hôpitaux/utilisateurs actifs) ; pour un administrateur :
  date de la dernière sauvegarde et 10 dernières entrées du journal d'audit.
- **Journal d'audit** (`/admin/audit`, rôle `admin`) — consultation en lecture
  seule de `audit_logs` (connexions, créations/modifications d'hôpitaux,
  d'examens, de comptes rendus, clés API, imports…), filtrable par action,
  utilisateur et plage de dates, paginé.
- **Utilisateurs** (`/admin/utilisateurs`, rôle `admin`) — création et
  modification des comptes (nom, e-mail, rôle, 2FA, mot de passe — laisser le
  champ vide en modification conserve le mot de passe actuel), désactivation
  par soft delete avec révocation immédiate des jetons API (jamais de
  suppression physique, jamais de désactivation de son propre compte).

## Conformité aux règles absolues du cahier des charges

- **R1** (le DOCX généré modifie le template institutionnel existant, jamais
  de reconstruction ; Arial Narrow ; titres/sous-titres jamais soulignés ;
  en-tête/logo/couleurs de l'hôpital préservés à l'identique) —
  `App\Services\DocxReportGenerator`, étape 5. Vérifié par
  `tests/Feature/DocxReportGenerationTest.php`.
- **R2** (ne jamais inventer une donnée patient absente) — les prompts IA
  (`App\Services\Ai\ReportDraftService`, `DictationRefinerService`)
  l'imposent explicitement ; les champs identité laissés vides côté
  formulaire (admin comme PWA) restent vides, jamais complétés
  automatiquement.
- **R3** (données patient chiffrées au repos, HTTPS, aucune PHI dans les
  journaux) — `patient_name`/`file_number` en cast `encrypted`
  (`App\Models\Report`) ; `ForceHttps` ; `AuditLogger` et `AiUsageLogger` ne
  journalisent que des métadonnées techniques (action, durée, statut),
  jamais de contenu médical — vérifié explicitement par
  `tests/Feature/Api/Ai/*`.
- **R4** (clés API IA uniquement côté serveur) — clés Groq/Anthropic saisies
  via l'écran admin, chiffrées en base (`api_credentials`), jamais exposées
  au client ; la PWA n'appelle que les proxys
  `/api/v1/{stt,ai/refine,ai/draft,ai/bulletin,ai/chat}` du backend
  (étapes 6 et 12). La dictée locale (Whisper dans le navigateur, étape 12)
  ne nécessite aucune clé : l'inférence tourne entièrement côté client.
- **R5** (PWA offline-first préservée, le backend n'est qu'une couche de
  synchronisation) — `public/app/` fonctionne sans réseau (IndexedDB,
  service worker limité à la coquille applicative) ; la synchronisation
  (`/api/v1/reports/sync`) est un rattrapage, jamais un prérequis (étape 9).
- **R6** (tous les textes visibles par l'utilisateur en français) — admin et
  PWA intégralement en français ; seuls les identifiants techniques (noms de
  colonnes, routes, code) sont en anglais, comme il est d'usage.

## Déploiement en production

1. **Serveur** : PHP 8.3+ avec extensions `pdo_mysql` (ou `pdo_sqlite`),
   `gd`, `zip`, `mbstring`, `intl` ; MySQL 8 ; `libreoffice-writer` installé
   (`soffice` dans le `PATH`, ou `LIBREOFFICE_BINARY`) ; `mysqldump`
   disponible si les sauvegardes (F7) sont activées sur MySQL.
2. **Configuration** : copier `.env.example` en `.env`, renseigner
   `APP_URL` (HTTPS), `DB_*`, `MAIL_*` (obligatoire pour la 2FA et les
   notifications), `GROQ_API_KEY`/`ANTHROPIC_API_KEY` (ou les configurer
   ensuite via l'écran admin « Clés API »), `SESSION_SECURE_COOKIE=true`.
   Générer la clé applicative : `php artisan key:generate`. **Ne jamais**
   committer `.env` ni la clé applicative.
3. **Installation** :
   ```bash
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   php artisan migrate --force
   php artisan db:seed --class=HospitalCatalogSeeder --force
   php artisan storage:link
   ```
   Créer ensuite le premier compte administrateur (`php artisan tinker`, ou
   temporairement via `UserSeeder` adapté) puis gérer les comptes suivants
   depuis `/admin/utilisateurs`.
4. **Worker de file d'attente** : superviser `php artisan queue:work` (ex.
   Supervisor/systemd) — utilisé par les jobs en arrière-plan
   (`GenerateReportDocumentJob` reste toutefois synchrone par défaut, voir
   F3 complet). Sans worker actif, seules les fonctionnalités synchrones
   restent disponibles.
5. **Planificateur** : une entrée cron unique appelle le planificateur
   Laravel, qui déclenche la sauvegarde quotidienne (F7) et toute tâche
   planifiée future :
   ```
   * * * * * cd /chemin/vers/le/projet && php artisan schedule:run >> /dev/null 2>&1
   ```
6. **Permissions fichiers** : `storage/` et `bootstrap/cache/` inscriptibles
   par l'utilisateur du serveur web ; `storage/app/templates/` doit rester
   accessible en lecture (contient les DOCX institutionnels).
7. **PWA** : accessible à `https://votre-domaine/app/` — à installer sur les
   téléphones/tablettes de terrain (« Ajouter à l'écran d'accueil »).
