# llms.txt Generator – Specifica Tecnica per Claude Code (PHP + MySQL)

## 1. Obiettivo dell’applicazione

Realizzare una web app in PHP + MySQL che:

- Prende in input uno o più URL di sitemap XML (anche `sitemapindex`).
- Esegue il parsing delle sitemap, estraendo e classificando le URL rilevanti.
- Permette di selezionare/curare le URL da includere in `llms.txt`.
- Organizza le URL in sezioni (H2) coerenti con la specifica ufficiale `llms.txt`.
- Genera e aggiorna un file `llms.txt` per ogni progetto (dominio).
- Espone una route pubblica che restituisce `llms.txt` per il dominio/progetto selezionato.
- (Nuovo) Espone una funzionalità di **generazione automatica della descrizione breve** tramite AI, a cui viene passato il titolo pagina e l’URL.

Versione iniziale: app **single-user**, ma con supporto a **più progetti/domìni**.


## 2. Stack Tecnologico

- **Linguaggio**: PHP >= 8.1
- **Database**: MySQL 8.x (o MariaDB equivalente)
- **Webserver**: Apache (XAMPP, Laragon, ecc.), con mod_rewrite abilitato
- **Librerie PHP via Composer**:
  - `vlucas/phpdotenv` – gestione variabili ambiente
  - `bramus/router` – routing minimale
  - `guzzlehttp/guzzle` – HTTP client per scaricare sitemap (se URL esterni)
  - *(opzionale ma consigliato)* `guzzlehttp/guzzle` usato anche per chiamare API AI esterne
- **Parsing XML**: `SimpleXML` integrato in PHP
- **Template**: semplice PHP (nessun template engine esterno nella v1)
- **Autoload**: PSR-4 tramite Composer


## 3. Architettura Generale

Pattern semplice **MVC-like**:

- **public/index.php**: front controller, inizializza autoload, env, DB, router.
- **/src/Config**: configurazioni di base (DB, env).
- **/src/Models**: accesso ai dati (Project, Sitemap, Url, Section, SectionUrl).
- **/src/Services**: logica applicativa (parsing sitemap, classificazione URL, generazione llms, generazione descrizioni AI).
- **/src/Controllers**: gestiscono le route, orchestrano Model + Service.
- **/src/Views**: file PHP per le interfacce (dashboard, liste, form).
- **/storage/llms**: cartella dove salvare i file `llms_{project_slug}.txt`.
- **/storage/logs**: log semplici (errori parsing ecc.).

L’app deve funzionare correttamente anche se spostata di dominio/cartella:
- nessun dominio hard-coded;
- tutte le route generate in modo **relativo** al path base dell’app.


## 4. Struttura delle Directory

Implementare la seguente struttura:

```text
project-root/
├─ public/
│  ├─ index.php
│  ├─ .htaccess
│  ├─ css/
│  │  └─ app.css
│  └─ js/
│     └─ app.js
├─ src/
│  ├─ Config/
│  │  ├─ Config.php
│  │  └─ Database.php
│  ├─ Models/
│  │  ├─ Project.php
│  │  ├─ Sitemap.php
│  │  ├─ Url.php
│  │  ├─ Section.php
│  │  └─ SectionUrl.php
│  ├─ Services/
│  │  ├─ SitemapParser.php
│  │  ├─ UrlClassifier.php
│  │  ├─ LlmsGenerator.php
│  │  └─ AiDescriptionService.php
│  ├─ Controllers/
│  │  ├─ ProjectController.php
│  │  ├─ SitemapController.php
│  │  ├─ UrlController.php
│  │  └─ LlmsController.php
│  └─ Views/
│     ├─ layout.php
│     ├─ projects/
│     │  ├─ index.php
│     │  ├─ create.php
│     │  ├─ edit.php
│     │  └─ show.php
│     ├─ sitemaps/
│     │  └─ manage.php
│     ├─ urls/
│     │  └─ index.php
│     └─ llms/
│        └─ preview.php
├─ storage/
│  ├─ llms/
│  └─ logs/
├─ vendor/
├─ .env
├─ composer.json
└─ sql/
   └─ schema.sql
```


## 5. Composer.json

Creare un file `composer.json` con il seguente contenuto:

```json
{
  "name": "beweb/llms-txt-generator",
  "description": "Web app per generare llms.txt a partire da sitemap XML",
  "require": {
    "php": "^8.1",
    "vlucas/phpdotenv": "^5.6",
    "bramus/router": "^1.6",
    "guzzlehttp/guzzle": "^7.9"
  },
  "autoload": {
    "psr-4": {
      "LlmsApp\\\\": "src/"
    }
  }
}
```

Dopo aver creato il file, eseguire:

```bash
composer install
```


## 6. Configurazione Ambiente (.env)

Creare un file `.env` nella root del progetto:

```env
APP_ENV=local
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=llms_app
DB_USERNAME=root
DB_PASSWORD=

APP_BASE_PATH=/   # path base dell'app (es. "/" o "/llms-app/")
STORAGE_PATH=storage

# --- CONFIGURAZIONE FUNZIONALITÀ AI ---
AI_ENABLED=true                  # se "false", l'endpoint AI restituisce errore 503
AI_API_BASE_URL=https://api.example-ai.com/v1/generate
AI_API_KEY=INSERISCI_LA_TUA_CHIAVE_AI_QUI
AI_MODEL_NAME=short-desc-it      # nome modello/preimpostazione lato AI (stringa libera)
```

Nel codice PHP, usare `vlucas/phpdotenv` per caricare queste variabili.


## 7. Configurazione DB e Classe Database

### 7.1 Schema Database (MySQL)

Creare il file `sql/schema.sql` con il seguente contenuto:

```sql
CREATE DATABASE IF NOT EXISTS llms_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE llms_app;

-- Progetti (uno per dominio/sito)
CREATE TABLE projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  domain VARCHAR(255) NOT NULL,             -- es: www.amevista.com
  site_summary TEXT NULL,                   -- riassunto 1-2 frasi per il blockquote
  description TEXT NULL,                    -- paragrafo aggiuntivo opzionale
  slug VARCHAR(255) NOT NULL,               -- es: amevista
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_projects_slug (slug),
  UNIQUE KEY uq_projects_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sitemap associate al progetto
CREATE TABLE sitemaps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  url VARCHAR(512) NOT NULL,                -- URL della sitemap o path locale
  last_parsed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sitemaps_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- URL estratte dalla/e sitemap
CREATE TABLE urls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  loc VARCHAR(1024) NOT NULL,
  lastmod DATETIME NULL,
  type ENUM('POLICY','CATEGORY','GUIDE','SUPPORT','OTHER') DEFAULT 'OTHER',
  is_selected TINYINT(1) DEFAULT 0,         -- scelto per llms
  title VARCHAR(255) NULL,                  -- titolo leggibile (opzionale)
  short_description VARCHAR(512) NULL,      -- descrizione breve per llms
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_urls_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE,
  INDEX idx_urls_project_type (project_id, type),
  INDEX idx_urls_project_selected (project_id, is_selected)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sezioni (H2) nel file llms.txt per ogni progetto
CREATE TABLE sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,               -- es. "Struttura e tassonomia prodotti"
  slug VARCHAR(255) NOT NULL,               -- es. "structure", "policies", "guides"
  position INT UNSIGNED NOT NULL DEFAULT 1,
  is_optional TINYINT(1) DEFAULT 0,         -- sezione "## Optional" -> 1
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sections_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_sections_project_slug (project_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relazione sezione <-> URL
CREATE TABLE section_url (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id INT UNSIGNED NOT NULL,
  url_id BIGINT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sectionurl_section
    FOREIGN KEY (section_id) REFERENCES sections(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_sectionurl_url
    FOREIGN KEY (url_id) REFERENCES urls(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_section_url (section_id, url_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 7.2 Classe Database

Creare `src/Config/Database.php`:

```php
<?php

namespace LlmsApp\\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db   = $_ENV['DB_DATABASE'] ?? 'llms_app';
            $user = $_ENV['DB_USERNAME'] ?? 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                die('Database connection error: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }
}
```


## 8. Bootstrap Applicazione (public/index.php)

Creare `public/index.php`:

```php
<?php

use Bramus\\Router\\Router;
use Dotenv\\Dotenv;
use LlmsApp\\Config\\Database;

require __DIR__ . '/../vendor/autoload.php';

// Carica .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Inizializza connessione DB (per validare subito che funzioni)
Database::getConnection();

// Base path dinamico
$appBasePath = rtrim($_ENV['APP_BASE_PATH'] ?? '/', '/');
if ($appBasePath === '') {
    $appBasePath = '/';
}

$router = new Router();
$router->setBasePath($appBasePath);

// Controllers (usare callable [ClassName, 'method'])
$router->get('/', '\\LlmsApp\\Controllers\\ProjectController@index');

$router->get('/projects/create', '\\LlmsApp\\Controllers\\ProjectController@create');
$router->post('/projects/store', '\\LlmsApp\\Controllers\\ProjectController@store');
$router->get('/projects/{id}/edit', '\\LlmsApp\\Controllers\\ProjectController@edit');
$router->post('/projects/{id}/update', '\\LlmsApp\\Controllers\\ProjectController@update');
$router->get('/projects/{id}', '\\LlmsApp\\Controllers\\ProjectController@show');

$router->get('/projects/{id}/sitemaps', '\\LlmsApp\\Controllers\\SitemapController@index');
$router->post('/projects/{id}/sitemaps/add', '\\LlmsApp\\Controllers\\SitemapController@store');
$router->post('/sitemaps/{id}/parse', '\\LlmsApp\\Controllers\\SitemapController@parse');

$router->get('/projects/{id}/urls', '\\LlmsApp\\Controllers\\UrlController@index');
$router->post('/projects/{id}/urls/bulk-update', '\\LlmsApp\\Controllers\\UrlController@bulkUpdate');

$router->get('/projects/{id}/llms/preview', '\\LlmsApp\\Controllers\\LlmsController@preview');
$router->post('/projects/{id}/llms/generate', '\\LlmsApp\\Controllers\\LlmsController@generate');

$router->get('/llms/{slug}.txt', '\\LlmsApp\\Controllers\\LlmsController@publicTxt');

// --- API AI: generazione descrizione breve ---
$router->post('/api/ai/description', '\\LlmsApp\\Controllers\\AiController@generateDescription');

$router->run();
```


## 9. .htaccess per Routing

Creare `public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Sostituire /llms-app/ con il path reale se l'app non è in root
    RewriteBase /llms-app/

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

Se l’app è in root del dominio, usare:

```apache
RewriteBase /
```


## 10. Modelli di Base (Models)

### 10.1 Project.php

```php
<?php

namespace LlmsApp\\Models;

use LlmsApp\\Config\\Database;
use PDO;

class Project
{
    public static function all(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM projects ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO projects (name, domain, site_summary, description, slug)
            VALUES (:name, :domain, :site_summary, :description, :slug)
        ');
        $stmt->execute([
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'site_summary' => $data['site_summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $data['slug'],
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE projects
            SET name = :name,
                domain = :domain,
                site_summary = :site_summary,
                description = :description,
                slug = :slug
            WHERE id = :id
        ');
        $stmt->execute([
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'site_summary' => $data['site_summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $data['slug'],
            'id'           => $id,
        ]);
    }
}
```

### 10.2 Sitemap.php

```php
<?php

namespace LlmsApp\\Models;

use LlmsApp\\Config\\Database;
use PDO;

class Sitemap
{
    public static function forProject(int $projectId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM sitemaps WHERE project_id = :pid ORDER BY created_at DESC');
        $stmt->execute(['pid' => $projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM sitemaps WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $projectId, string $url): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO sitemaps (project_id, url)
            VALUES (:pid, :url)
        ');
        $stmt->execute([
            'pid' => $projectId,
            'url' => $url,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateLastParsed(int $id): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE sitemaps
            SET last_parsed_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
    }
}
```

### 10.3 Url.php

```php
<?php

namespace LlmsApp\\Models;

use LlmsApp\\Config\\Database;
use PDO;

class Url
{
    public static function upsert(
        int $projectId,
        string $loc,
        ?string $lastmod,
        string $type
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT id FROM urls
            WHERE project_id = :pid AND loc = :loc
        ');
        $stmt->execute(['pid' => $projectId, 'loc' => $loc]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE urls
                SET lastmod = :lastmod, type = :type
                WHERE id = :id
            ');
            $stmt->execute([
                'lastmod' => $lastmod,
                'type'    => $type,
                'id'      => $existing['id'],
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO urls (project_id, loc, lastmod, type)
                VALUES (:pid, :loc, :lastmod, :type)
            ');
            $stmt->execute([
                'pid'     => $projectId,
                'loc'     => $loc,
                'lastmod' => $lastmod,
                'type'    => $type,
            ]);
        }
    }

    public static function forProject(int $projectId, array $filters = []): array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT * FROM urls WHERE project_id = :pid';
        $params = ['pid' => $projectId];

        if (!empty($filters['type'])) {
            $sql .= ' AND type = :type';
            $params['type'] = $filters['type'];
        }

        if (isset($filters['is_selected'])) {
            $sql .= ' AND is_selected = :sel';
            $params['sel'] = (int)$filters['is_selected'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (loc LIKE :search OR title LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function bulkUpdateSelection(
        int $projectId,
        array $selections
    ): void {
        $pdo = Database::getConnection();

        $pdo->beginTransaction();

        foreach ($selections as $urlId => $data) {
            $stmt = $pdo->prepare('
                UPDATE urls
                SET is_selected = :sel,
                    title = :title,
                    short_description = :desc
                WHERE id = :id AND project_id = :pid
            ');
            $stmt->execute([
                'sel'   => (int)($data['is_selected'] ?? 0),
                'title' => $data['title'] ?? null,
                'desc'  => $data['short_description'] ?? null,
                'id'    => $urlId,
                'pid'   => $projectId,
            ]);
        }

        $pdo->commit();
    }
}
```


### 10.4 Section.php e SectionUrl.php (schemi)


Creare `src/Models/Section.php`:

```php
<?php

namespace LlmsApp\\Models;

use LlmsApp\\Config\\Database;
use PDO;

class Section
{
    public static function forProject(int $projectId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT * FROM sections
            WHERE project_id = :pid
            ORDER BY position ASC
        ');
        $stmt->execute(['pid' => $projectId]);
        return $stmt->fetchAll();
    }

    public static function createDefaultsForProject(int $projectId): void
    {
        $pdo = Database::getConnection();

        $defaults = [
            ['name' => 'Struttura e tassonomia prodotti', 'slug' => 'structure', 'position' => 1, 'is_optional' => 0],
            ['name' => 'Policy e informazioni legali',    'slug' => 'policies',  'position' => 2, 'is_optional' => 0],
            ['name' => 'Guide all\'acquisto e contenuti editoriali', 'slug' => 'guides', 'position' => 3, 'is_optional' => 0],
            ['name' => 'Assistenza e supporto',           'slug' => 'support',   'position' => 4, 'is_optional' => 0],
            ['name' => 'Optional',                        'slug' => 'optional',  'position' => 5, 'is_optional' => 1],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO sections (project_id, name, slug, position, is_optional)
            VALUES (:pid, :name, :slug, :position, :is_optional)
        ');

        foreach ($defaults as $row) {
            $stmt->execute([
                'pid'         => $projectId,
                'name'        => $row['name'],
                'slug'        => $row['slug'],
                'position'    => $row['position'],
                'is_optional' => $row['is_optional'],
            ]);
        }
    }
}
```

Creare `src/Models/SectionUrl.php`:

```php
<?php

namespace LlmsApp\\Models;

use LlmsApp\\Config\\Database;
use PDO;

class SectionUrl
{
    public static function assign(int $sectionId, int $urlId, int $position = 1): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT id FROM section_url
            WHERE section_id = :sid AND url_id = :uid
        ');
        $stmt->execute([
            'sid' => $sectionId,
            'uid' => $urlId,
        ]);
        $exists = $stmt->fetch();

        if ($exists) {
            $stmt = $pdo->prepare('
                UPDATE section_url
                SET position = :position
                WHERE id = :id
            ');
            $stmt->execute([
                'position' => $position,
                'id'       => $exists['id'],
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO section_url (section_id, url_id, position)
                VALUES (:sid, :uid, :position)
            ');
            $stmt->execute([
                'sid'      => $sectionId,
                'uid'      => $urlId,
                'position' => $position,
            ]);
        }
    }

    public static function urlsForSection(int $sectionId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT su.*, u.*
            FROM section_url su
            INNER JOIN urls u ON u.id = su.url_id
            WHERE su.section_id = :sid AND u.is_selected = 1
            ORDER BY su.position ASC
        ');
        $stmt->execute(['sid' => $sectionId]);
        return $stmt->fetchAll();
    }
}
```


## 11. Services

### 11.1 UrlClassifier.php

Creare `src/Services/UrlClassifier.php`:

```php
<?php

namespace LlmsApp\\Services;

class UrlClassifier
{
    public function classify(string $urlPath): string
    {
        $path = strtolower($urlPath);

        $policyKeywords = [
            'termini', 'condizioni', 'terms', 'conditions',
            'privacy', 'cookie', 'cookies', 'resi', 'res',
            'refund', 'spedizioni', 'shipping', 'pagamenti', 'payments'
        ];

        foreach ($policyKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'POLICY';
            }
        }

        $categoryKeywords = [
            '/categoria', '/category', 'occhiali-', '/prodotti', '/products'
        ];
        foreach ($categoryKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'CATEGORY';
            }
        }

        $guideKeywords = [
            '/blog', '/magazine', 'guida', 'guide', 'how-to', 'come-'
        ];
        foreach ($guideKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'GUIDE';
            }
        }

        $supportKeywords = [
            'contatti', 'contact', 'faq', 'assistenza', 'support', 'help'
        ];
        foreach ($supportKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'SUPPORT';
            }
        }

        return 'OTHER';
    }
}
```

### 11.2 SitemapParser.php

Creare `src/Services/SitemapParser.php`:

```php
<?php

namespace LlmsApp\\Services;

use GuzzleHttp\\Client;
use LlmsApp\\Models\\Url;
use LlmsApp\\Models\\Sitemap;

class SitemapParser
{
    private Client $client;
    private UrlClassifier $classifier;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
        ]);
        $this->classifier = new UrlClassifier();
    }

    public function parseSitemap(int $sitemapId, int $projectId, string $sitemapUrl): void
    {
        $xmlContent = $this->fetchSitemap($sitemapUrl);

        if ($xmlContent === null) {
            return;
        }

        $xml = @simplexml_load_string($xmlContent);
        if ($xml === false) {
            return;
        }

        $this->processXmlNode($xml, $projectId);

        Sitemap::updateLastParsed($sitemapId);
    }

    private function fetchSitemap(string $url): ?string
    {
        if (preg_match('#^https?://#i', $url)) {
            $response = $this->client->get($url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            return (string)$response->getBody();
        }

        if (is_file($url) && is_readable($url)) {
            return file_get_contents($url);
        }

        return null;
    }

    private function processXmlNode(\\SimpleXMLElement $xml, int $projectId): void
    {
        if (isset($xml->url)) {
            foreach ($xml->url as $urlNode) {
                $loc = (string)$urlNode->loc;
                $lastmod = isset($urlNode->lastmod) ? (string)$urlNode->lastmod : null;

                $path = parse_url($loc, PHP_URL_PATH) ?? '/';
                $type = $this->classifier->classify($path);

                if ($this->shouldSkipUrl($path)) {
                    continue;
                }

                Url::upsert($projectId, $loc, $lastmod, $type);
            }
        } elseif (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemapNode) {
                $loc = (string)$sitemapNode->loc;
                $content = $this->fetchSitemap($loc);
                if ($content) {
                    $childXml = @simplexml_load_string($content);
                    if ($childXml !== false) {
                        $this->processXmlNode($childXml, $projectId);
                    }
                }
            }
        }
    }

    private function shouldSkipUrl(string $path): bool
    {
        if (strpos($path, '?') !== false) {
            return true;
        }

        if (preg_match('#/page/\\d+#', $path)) {
            return true;
        }

        $technical = ['/cart', '/checkout', '/login', '/account', '/my-account'];
        foreach ($technical as $t) {
            if (strpos($path, $t) !== false) {
                return true;
            }
        }

        return false;
    }
}
```

### 11.3 LlmsGenerator.php

Creare `src/Services/LlmsGenerator.php`:

```php
<?php

namespace LlmsApp\\Services;

use LlmsApp\\Models\\Project;
use LlmsApp\\Models\\Section;
use LlmsApp\\Models\\SectionUrl;

class LlmsGenerator
{
    public function generateForProject(int $projectId): string
    {
        $project = Project::find($projectId);
        if (!$project) {
            return '';
        }

        $sections = Section::forProject($projectId);

        $lines = [];

        $lines[] = '# ' . $project['name'];
        $lines[] = '';
        if (!empty($project['site_summary'])) {
            $lines[] = '> ' . trim($project['site_summary']);
            $lines[] = '';
        }
        if (!empty($project['description'])) {
            $lines[] = trim($project['description']);
            $lines[] = '';
        }

        foreach ($sections as $section) {
            $sectionName = $section['is_optional'] ? 'Optional' : $section['name'];
            $lines[] = '## ' . $sectionName;
            $lines[] = '';

            $urls = SectionUrl::urlsForSection((int)$section['id']);

            foreach ($urls as $u) {
                $title = $u['title'] ?: $this->deriveTitleFromPath($u['loc']);
                $desc = $u['short_description'] ?: $this->defaultDescriptionForType($u['type']);

                $line = '- [' . $title . '](' . $u['loc'] . ')';
                if ($desc) {
                    $line .= ': ' . $desc;
                }
                $lines[] = $line;
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function deriveTitleFromPath(string $loc): string
    {
        $path = parse_url($loc, PHP_URL_PATH) ?? '';
        $path = trim($path, '/');
        if ($path === '') {
            return 'Homepage';
        }
        $segments = explode('/', $path);
        $last = end($segments);
        $last = preg_replace('#\.[a-z0-9]+$#i', '', $last);
        $last = str_replace('-', ' ', $last);
        return mb_convert_case($last, MB_CASE_TITLE, 'UTF-8');
    }

    private function defaultDescriptionForType(string $type): string
    {
        return match ($type) {
            'CATEGORY' => 'Pagina di categoria prodotto.',
            'POLICY'   => 'Pagina informativa legale o di policy.',
            'GUIDE'    => 'Guida o contenuto editoriale.',
            'SUPPORT'  => 'Pagina di supporto o contatti.',
            default    => 'Pagina informativa del sito.',
        };
    }

    public function saveToFile(int $projectId, string $baseStoragePath): ?string
    {
        $project = Project::find($projectId);
        if (!$project) {
            return null;
        }

        $content = $this->generateForProject($projectId);

        $slug = $project['slug'];
        $dir = rtrim($baseStoragePath, '/\\') . DIRECTORY_SEPARATOR . 'llms';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . 'llms_' . $slug . '.txt';
        file_put_contents($filePath, $content);

        return $filePath;
    }
}
```


### 11.4 AiDescriptionService.php (NUOVO)

Creare `src/Services/AiDescriptionService.php`:

```php
<?php

namespace LlmsApp\\Services;

use GuzzleHttp\\Client;
use RuntimeException;

class AiDescriptionService
{
    private Client $client;
    private bool $enabled;
    private string $baseUrl;
    private string $apiKey;
    private string $modelName;

    public function __construct()
    {
        $this->enabled = filter_var($_ENV['AI_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = (string)($_ENV['AI_API_BASE_URL'] ?? '');
        $this->apiKey = (string)($_ENV['AI_API_KEY'] ?? '');
        $this->modelName = (string)($_ENV['AI_MODEL_NAME'] ?? 'short-desc-it');

        $this->client = new Client([
            'timeout' => 20,
        ]);
    }

    public function generateShortDescription(string $title, string $url): string
    {
        if (!$this->enabled) {
            throw new RuntimeException('AI service is disabled');
        }

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('AI service not configured');
        }

        $prompt = $this->buildPrompt($title, $url);

        $response = $this->client->post($this->baseUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'  => $this->modelName,
                'prompt' => $prompt,
                'max_tokens' => 60,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('AI service error: HTTP ' . $response->getStatusCode());
        }

        $body = json_decode((string)$response->getBody(), true);

        // Adatta questa parte alla struttura specifica della tua AI
        $text = $body['choices'][0]['text'] ?? '';

        return trim($this->sanitizeOutput($text));
    }

    private function buildPrompt(string $title, string $url): string
    {
        return sprintf(
            "Scrivi una singola frase breve (max 150 caratteri) in italiano che descrive la pagina per un file llms.txt.\n".
            "La frase deve essere asciutta, descrittiva, senza tono promozionale.\n\n".
            "Titolo pagina: %s\nURL: %s\n\nDescrizione:",
            $title,
            $url
        );
    }

    private function sanitizeOutput(string $text): string
    {
        // Rimuove eventuali ritorni a capo e doppi spazi
        $text = preg_replace('/\s+/', ' ', $text);
        // Rimuove virgolette di troppo
        $text = trim($text, "\"' \t\n\r\0\x0B");
        return $text;
    }
}
```


## 12. Controllers (schemi)

### 12.1 ProjectController.php

Creare `src/Controllers/ProjectController.php`:

```php
<?php

namespace LlmsApp\\Controllers;

use LlmsApp\\Models\\Project;
use LlmsApp\\Models\\Section;

class ProjectController
{
    public function index()
    {
        $projects = Project::all();
        $this->render('projects/index', ['projects' => $projects, 'view' => 'projects/index']);
    }

    public function create()
    {
        $this->render('projects/create', ['view' => 'projects/create']);
    }

    public function store()
    {
        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $siteSummary = trim($_POST['site_summary'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        $projectId = Project::create([
            'name'         => $name,
            'domain'       => $domain,
            'site_summary' => $siteSummary,
            'description'  => $description,
            'slug'         => $slug,
        ]);

        Section::createDefaultsForProject($projectId);

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId);
        exit;
    }

    public function edit($id)
    {
        $project = Project::find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $this->render('projects/edit', ['project' => $project, 'view' => 'projects/edit']);
    }

    public function update($id)
    {
        $project = Project::find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $siteSummary = trim($_POST['site_summary'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        Project::update((int)$id, [
            'name'         => $name,
            'domain'       => $domain,
            'site_summary' => $siteSummary,
            'description'  => $description,
            'slug'         => $slug,
        ]);

        header('Location: ' . $this->baseUrl() . '/projects/' . $id);
        exit;
    }

    public function show($id)
    {
        $project = Project::find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $this->render('projects/show', ['project' => $project, 'view' => 'projects/show']);
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();
        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $base = $_ENV['APP_BASE_PATH'] ?? '/';
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'project';
    }
}
```


### 12.2 SitemapController.php (schema)

Creare `src/Controllers/SitemapController.php`:

```php
<?php

namespace LlmsApp\\Controllers;

use LlmsApp\\Models\\Project;
use LlmsApp\\Models\\Sitemap;
use LlmsApp\\Services\\SitemapParser;

class SitemapController
{
    public function index($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $sitemaps = Sitemap::forProject($projectId);

        $this->render('sitemaps/manage', [
            'project'  => $project,
            'sitemaps' => $sitemaps,
            'view'     => 'sitemaps/manage',
        ]);
    }

    public function store($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $url = trim($_POST['url'] ?? '');
        if ($url === '') {
            header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/sitemaps');
            exit;
        }

        Sitemap::create($projectId, $url);

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/sitemaps');
        exit;
    }

    public function parse($sitemapId)
    {
        $sitemapId = (int)$sitemapId;
        $sitemap = Sitemap::find($sitemapId);
        if (!$sitemap) {
            http_response_code(404);
            echo 'Sitemap not found';
            return;
        }

        $projectId = (int)$sitemap['project_id'];
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $parser = new SitemapParser();
        $parser->parseSitemap($sitemapId, $projectId, $sitemap['url']);

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/urls');
        exit;
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();
        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $base = $_ENV['APP_BASE_PATH'] ?? '/';
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }
}
```


### 12.3 UrlController.php (schema)

Creare `src/Controllers/UrlController.php`:

```php
<?php

namespace LlmsApp\\Controllers;

use LlmsApp\\Models\\Project;
use LlmsApp\\Models\\Url;
use LlmsApp\\Models\\Section;
use LlmsApp\\Models\\SectionUrl;

class UrlController
{
    public function index($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $filters = [
            'type'        => $_GET['type'] ?? null,
            'is_selected' => isset($_GET['is_selected']) && $_GET['is_selected'] !== '' ? (int)$_GET['is_selected'] : null,
            'search'      => $_GET['search'] ?? null,
        ];

        $urls = Url::forProject($projectId, $filters);
        $sections = Section::forProject($projectId);

        $this->render('urls/index', [
            'project'  => $project,
            'urls'     => $urls,
            'sections' => $sections,
            'filters'  => $filters,
            'view'     => 'urls/index',
        ]);
    }

    public function bulkUpdate($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $selections = $_POST['urls'] ?? [];
        Url::bulkUpdateSelection($projectId, $selections);

        $sectionAssignments = $_POST['section'] ?? [];

        foreach ($sectionAssignments as $urlId => $sectionId) {
            if ($sectionId) {
                SectionUrl::assign((int)$sectionId, (int)$urlId, 1);
            }
        }

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/urls');
        exit;
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();
        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $base = $_ENV['APP_BASE_PATH'] ?? '/';
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }
}
```


### 12.4 LlmsController.php (schema)

Creare `src/Controllers/LlmsController.php`:

```php
<?php

namespace LlmsApp\\Controllers;

use LlmsApp\\Models\\Project;
use LlmsApp\\Services\\LlmsGenerator;

class LlmsController
{
    public function preview($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $generator = new LlmsGenerator();
        $content = $generator->generateForProject($projectId);

        $this->render('llms/preview', [
            'project' => $project,
            'content' => $content,
            'view'    => 'llms/preview',
        ]);
    }

    public function generate($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $storageBase = $_ENV['STORAGE_PATH'] ?? 'storage';
        $baseDir = realpath(__DIR__ . '/../../' . $storageBase) ?: (__DIR__ . '/../../storage');

        $generator = new LlmsGenerator();
        $generator->saveToFile($projectId, $baseDir);

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/llms/preview');
        exit;
    }

    public function publicTxt($slug)
    {
        $project = Project::findBySlug($slug);
        if (!$project) {
            http_response_code(404);
            echo 'llms.txt not found';
            return;
        }

        $storageBase = $_ENV['STORAGE_PATH'] ?? 'storage';
        $baseDir = realpath(__DIR__ . '/../../' . $storageBase) ?: (__DIR__ . '/../../storage');
        $filePath = $baseDir . '/llms/llms_' . $project['slug'] . '.txt';

        if (!is_file($filePath)) {
            $generator = new LlmsGenerator();
            $generator->saveToFile((int)$project['id'], $baseDir);
        }

        if (!is_file($filePath)) {
            http_response_code(404);
            echo 'llms.txt not found';
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        readfile($filePath);
        exit;
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();
        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $base = $_ENV['APP_BASE_PATH'] ?? '/';
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }
}
```


### 12.5 AiController.php (NUOVO – endpoint per generazione descrizione AI)

Creare `src/Controllers/AiController.php`:

```php
<?php

namespace LlmsApp\\Controllers;

use LlmsApp\\Services\\AiDescriptionService;
use RuntimeException;

class AiController
{
    public function generateDescription()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $title = trim($input['title'] ?? '');
        $url   = trim($input['url'] ?? '');

        if ($title === '' || $url === '') {
            http_response_code(400);
            echo json_encode([
                'error' => 'Missing title or url'
            ]);
            return;
        }

        try {
            $service = new AiDescriptionService();
            $description = $service->generateShortDescription($title, $url);

            echo json_encode([
                'description' => $description,
            ]);
        } catch (RuntimeException $e) {
            http_response_code(503);
            echo json_encode([
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Unexpected error',
            ]);
        }
    }
}
```


## 13. Views (schemi sintetici)

### 13.1 layout.php

Creare `src/Views/layout.php` (layout base molto semplice):

```php
<?php
// Variabili disponibili: $baseUrl, $view, e quelle passate nei singoli controller
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>llms.txt Generator</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/css/app.css">
    <script src="<?= htmlspecialchars($baseUrl) ?>/js/app.js" defer></script>
</head>
<body>
<header>
    <h1><a href="<?= htmlspecialchars($baseUrl) ?>/">llms.txt Generator</a></h1>
    <nav>
        <a href="<?= htmlspecialchars($baseUrl) ?>/">Progetti</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/create">Nuovo progetto</a>
    </nav>
</header>
<main>
<?php
$viewFile = __DIR__ . '/' . $view . '.php';
if (is_file($viewFile)) {
    include $viewFile;
} else {
    echo '<p>View non trovata: ' . htmlspecialchars($view) . '</p>';
}
?>
</main>
</body>
</html>
```


### 13.2 Esempio projects/index.php

Creare `src/Views/projects/index.php`:

```php
<?php /** @var array $projects */ ?>
<h2>Progetti</h2>

<p><a href="<?= htmlspecialchars($baseUrl) ?>/projects/create">Crea nuovo progetto</a></p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Nome</th>
        <th>Dominio</th>
        <th>Slug</th>
        <th>Azioni</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($projects as $project): ?>
        <tr>
            <td><?= (int)$project['id'] ?></td>
            <td><?= htmlspecialchars($project['name']) ?></td>
            <td><?= htmlspecialchars($project['domain']) ?></td>
            <td><?= htmlspecialchars($project['slug']) ?></td>
            <td>
                <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>">Dettagli</a> |
                <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/edit">Modifica</a> |
                <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps">Sitemap</a> |
                <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls">URL</a> |
                <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/preview">Preview llms.txt</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
```


### 13.3 Esempio projects/create.php

Creare `src/Views/projects/create.php`:

```php
<h2>Nuovo progetto</h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/store">
    <p>
        <label>Nome<br>
            <input type="text" name="name" required>
        </label>
    </p>
    <p>
        <label>Dominio (es. www.amevista.com)<br>
            <input type="text" name="domain" required>
        </label>
    </p>
    <p>
        <label>Slug (se vuoto viene generato)<br>
            <input type="text" name="slug">
        </label>
    </p>
    <p>
        <label>Riassunto sito (1-2 frasi per il blockquote)<br>
            <textarea name="site_summary" rows="3"></textarea>
        </label>
    </p>
    <p>
        <label>Descrizione aggiuntiva (paragrafo opzionale)<br>
            <textarea name="description" rows="5"></textarea>
        </label>
    </p>
    <p>
        <button type="submit">Salva</button>
    </p>
</form>
```


### 13.4 Esempio sitemaps/manage.php

Creare `src/Views/sitemaps/manage.php`:

```php
<h2>Gestione sitemap per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<h3>Aggiungi sitemap</h3>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps/add">
    <p>
        <label>URL o path sitemap<br>
            <input type="text" name="url" required>
        </label>
    </p>
    <p>
        <button type="submit">Aggiungi sitemap</button>
    </p>
</form>

<h3>Sitemap esistenti</h3>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>URL</th>
        <th>Ultimo parsing</th>
        <th>Azioni</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($sitemaps as $sitemap): ?>
        <tr>
            <td><?= (int)$sitemap['id'] ?></td>
            <td><?= htmlspecialchars($sitemap['url']) ?></td>
            <td><?= htmlspecialchars($sitemap['last_parsed_at'] ?? '-') ?></td>
            <td>
                <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/sitemaps/<?= (int)$sitemap['id'] ?>/parse" style="display:inline;">
                    <button type="submit">Parsa sitemap</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
```


### 13.5 Esempio urls/index.php (AGGIUNTA FUNZIONALITÀ AI PER OGNI RIGA)

Creare `src/Views/urls/index.php`:

```php
<h2>URL per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<form method="get" action="">
    <label>Tipo:
        <select name="type">
            <option value="">Tutti</option>
            <option value="POLICY" <?= ($filters['type'] ?? '') === 'POLICY' ? 'selected' : '' ?>>Policy</option>
            <option value="CATEGORY" <?= ($filters['type'] ?? '') === 'CATEGORY' ? 'selected' : '' ?>>Categoria</option>
            <option value="GUIDE" <?= ($filters['type'] ?? '') === 'GUIDE' ? 'selected' : '' ?>>Guide</option>
            <option value="SUPPORT" <?= ($filters['type'] ?? '') === 'SUPPORT' ? 'selected' : '' ?>>Supporto</option>
            <option value="OTHER" <?= ($filters['type'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Altro</option>
        </select>
    </label>

    <label>Selezionati:
        <select name="is_selected">
            <option value="">Tutti</option>
            <option value="1" <?= (isset($filters['is_selected']) && $filters['is_selected'] === 1) ? 'selected' : '' ?>>Solo selezionati</option>
            <option value="0" <?= (isset($filters['is_selected']) && $filters['is_selected'] === 0) ? 'selected' : '' ?>>Non selezionati</option>
        </select>
    </label>

    <label>Cerca:
        <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
    </label>

    <button type="submit">Filtra</button>
</form>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls/bulk-update">
    <table>
        <thead>
        <tr>
            <th>Seleziona</th>
            <th>URL</th>
            <th>Tipo</th>
            <th>Titolo</th>
            <th>Descrizione breve</th>
            <th>AI</th>
            <th>Sezione</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($urls as $url): ?>
            <?php
                $rowId = (int)$url['id'];
                $titleValue = $url['title'] ?? '';
                $loc = $url['loc'];
            ?>
            <tr data-url-row-id="<?= $rowId ?>">
                <td>
                    <input type="checkbox"
                           name="urls[<?= $rowId ?>][is_selected]"
                           value="1" <?= $url['is_selected'] ? 'checked' : '' ?>>
                </td>
                <td><?= htmlspecialchars($loc) ?></td>
                <td><?= htmlspecialchars($url['type']) ?></td>
                <td>
                    <input type="text"
                           class="url-title-input"
                           name="urls[<?= $rowId ?>][title]"
                           value="<?= htmlspecialchars($titleValue) ?>"
                           size="20"
                           data-url="<?= htmlspecialchars($loc) ?>">
                </td>
                <td>
                    <input type="text"
                           class="url-desc-input"
                           name="urls[<?= $rowId ?>][short_description]"
                           value="<?= htmlspecialchars($url['short_description'] ?? '') ?>"
                           size="40">
                </td>
                <td>
                    <button type="button"
                            class="btn-ai-desc"
                            data-row-id="<?= $rowId ?>"
                            data-url="<?= htmlspecialchars($loc) ?>">
                        AI descrizione
                    </button>
                    <span class="ai-status" data-row-id="<?= $rowId ?>"></span>
                </td>
                <td>
                    <select name="section[<?= $rowId ?>]">
                        <option value="">(nessuna)</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= (int)$section['id'] ?>"><?= htmlspecialchars($section['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <button type="submit">Salva selezioni e sezioni</button>
    </p>
</form>
```


### 13.6 Esempio llms/preview.php

Creare `src/Views/llms/preview.php`:

```php
<h2>Preview llms.txt per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/generate">
    <button type="submit">Salva/aggiorna file llms.txt</button>
</form>

<h3>Contenuto generato</h3>
<pre style="white-space: pre-wrap; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">
<?= htmlspecialchars($content) ?>
</pre>
```


## 14. JS per Gestione Pulsante "AI descrizione" (NUOVO)

Creare `public/js/app.js` (o estenderlo se esiste già):

```javascript
document.addEventListener('DOMContentLoaded', function () {
    const aiButtons = document.querySelectorAll('.btn-ai-desc');

    aiButtons.forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const rowId = this.getAttribute('data-row-id');
            const row = document.querySelector('tr[data-url-row-id="' + rowId + '"]');
            if (!row) return;

            const titleInput = row.querySelector('.url-title-input');
            const descInput = row.querySelector('.url-desc-input');
            const statusSpan = row.querySelector('.ai-status');

            const title = titleInput.value.trim();
            const url = titleInput.getAttribute('data-url');

            if (!title || !url) {
                alert('Titolo e URL sono necessari per generare la descrizione tramite AI.');
                return;
            }

            statusSpan.textContent = '...';
            statusSpan.classList.add('ai-loading');

            try {
                const baseUrl = document.querySelector('base')?.getAttribute('href') || '';
                const endpoint = baseUrl.replace(/\/$/, '') + '/api/ai/description';

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ title, url })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error || 'Errore AI';
                    statusSpan.textContent = 'Errore';
                    statusSpan.title = message;
                    statusSpan.classList.remove('ai-loading');
                    statusSpan.classList.add('ai-error');
                    return;
                }

                const data = await response.json();
                if (data.description) {
                    descInput.value = data.description;
                    statusSpan.textContent = 'OK';
                    statusSpan.classList.remove('ai-loading');
                    statusSpan.classList.add('ai-success');
                } else {
                    statusSpan.textContent = 'Nessun testo';
                    statusSpan.classList.remove('ai-loading');
                    statusSpan.classList.add('ai-error');
                }
            } catch (e) {
                statusSpan.textContent = 'Errore';
                statusSpan.classList.remove('ai-loading');
                statusSpan.classList.add('ai-error');
            }
        });
    });
});
```


## 15. CSS Minimo (facoltativo ma consigliato)

Creare `public/css/app.css` con uno stile minimale, ad esempio:

```css
body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    margin: 0;
    padding: 0;
    background: #f5f5f5;
}

header {
    background: #222;
    color: #fff;
    padding: 10px 20px;
}

header h1 {
    margin: 0;
    font-size: 20px;
}

header a {
    color: #fff;
    text-decoration: none;
}

header nav a {
    margin-right: 15px;
}

main {
    padding: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #fff;
}

th, td {
    padding: 8px;
    border: 1px solid #ddd;
    font-size: 14px;
}

th {
    background: #f0f0f0;
}

form p {
    margin-bottom: 10px;
}

button.btn-ai-desc {
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
}

.ai-status {
    margin-left: 6px;
    font-size: 12px;
}

.ai-status.ai-loading {
    color: #555;
}

.ai-status.ai-success {
    color: #1a7f37;
}

.ai-status.ai-error {
    color: #b3261e;
}
```


## 16. Flusso Operativo Riassunto

1. Clonare/provare il progetto in ambiente locale (XAMPP/Laragon).  
2. Eseguire `composer install`.  
3. Creare il DB ed eseguire `sql/schema.sql`.  
4. Configurare `.env` con i parametri corretti (inclusi AI\_* se si vuole usare la funzione AI).  
5. Impostare il virtual host o la cartella di `public/` come root del sito.  
6. Aprire `/` nel browser → creare un progetto (es. Amevista).  
7. Aggiungere una o più URL di sitemap nella sezione “Sitemap” del progetto.  
8. Eseguire il parsing (bottone “Parsa sitemap”).  
9. Nella sezione “URL”, filtrare, selezionare e assegnare le URL alle sezioni.  
10. Per ogni URL, usare (se desiderato) il pulsante **“AI descrizione”**: l’app invia titolo e URL all’endpoint `/api/ai/description` che, tramite `AiDescriptionService`, chiama l’API AI esterna e popola automaticamente il campo “Descrizione breve”.  
11. Nella sezione “Preview llms.txt”, controllare il contenuto generato.  
12. Cliccare “Salva/aggiorna file llms.txt” per scrivere il file su disco.  
13. Esportare/collegare pubblicamente l’endpoint `/llms/{slug}.txt` (o configurare alias per `https://dominio/llms.txt`).  
