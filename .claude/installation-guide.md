# Guida Installazione LLMS.txt Generator su XAMPP

## Istruzioni per Claude Code

Questa guida contiene tutti i passaggi necessari per installare e configurare l'applicazione LLMS.txt Generator in un ambiente XAMPP locale. Claude Code deve eseguire tutti questi passaggi in sequenza.

---

## Requisiti

- XAMPP installato con Apache e MySQL attivi
- PHP 8.1 o superiore
- Composer installato globalmente
- Git installato

## Step 1: Verifica Ambiente

Prima di procedere, verifica che l'ambiente sia corretto:

```bash
# Verifica versione PHP
php -v

# Verifica Composer
composer --version

# Verifica che MySQL sia attivo (XAMPP deve essere avviato)
mysql --version
```

## Step 2: Clona Repository

Se non già fatto, clona il repository nella cartella htdocs di XAMPP:

```bash
# Su Windows tipicamente:
cd C:/xampp/htdocs

# Su macOS:
cd /Applications/XAMPP/htdocs

# Su Linux:
cd /opt/lampp/htdocs

# Clona il repository
git clone https://github.com/BewebSolution/llms-ai-generator.git
cd llms-ai-generator
```

## Step 3: Installa Dipendenze PHP

```bash
composer install
```

## Step 4: Crea File .env

Crea il file `.env` nella root del progetto con il seguente contenuto:

```env
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=llms_app
DB_USERNAME=root
DB_PASSWORD=

# Application
APP_ENV=local
APP_DEBUG=true
APP_BASE_PATH=/llms-ai-generator/public

# Storage
STORAGE_PATH=storage

# OpenAI (opzionale - configurare dopo)
OPENAI_ENABLED=false
OPENAI_API_KEY=
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_TEMPERATURE=0.7
```

**NOTA**: Su XAMPP la password di root MySQL è vuota di default. Se hai impostato una password, modificala nel file .env.

## Step 5: Crea Database e Schema

Esegui questi comandi MySQL per creare il database e le tabelle:

```bash
# Connettiti a MySQL (su XAMPP la password di default è vuota)
mysql -u root -p
```

Poi esegui queste query SQL:

```sql
-- Crea il database
CREATE DATABASE IF NOT EXISTS llms_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE llms_app;

-- Crea tabella projects
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    site_summary TEXT,
    description TEXT,
    homepage_url VARCHAR(500),
    crawl_depth INT DEFAULT 3,
    crawl_status ENUM('pending', 'in_progress', 'completed', 'failed', 'stopped') DEFAULT 'pending',
    max_urls INT DEFAULT 500,
    last_crawl_at TIMESTAMP NULL,
    crawl_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_crawl_status (crawl_status)
) ENGINE=InnoDB;

-- Crea tabella sitemaps
CREATE TABLE IF NOT EXISTS sitemaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    last_parsed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB;

-- Crea tabella urls
CREATE TABLE IF NOT EXISTS urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    loc VARCHAR(500) NOT NULL,
    lastmod DATE NULL,
    type ENUM('HOMEPAGE', 'LANGUAGE', 'COUNTRY', 'PROMOTION', 'CATEGORY', 'PRODUCT', 'BRAND', 'FEATURE', 'SUPPORT', 'POLICY', 'COMPANY', 'BLOG', 'OTHER') DEFAULT 'OTHER',
    title VARCHAR(255),
    short_description TEXT,
    is_selected TINYINT(1) DEFAULT 1,
    content_hash VARCHAR(64),
    crawl_depth INT DEFAULT 0,
    http_status INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_loc (project_id, loc),
    INDEX idx_project (project_id),
    INDEX idx_type (type),
    INDEX idx_selected (is_selected)
) ENGINE=InnoDB;

-- Crea tabella sections
CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50),
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB;

-- Crea tabella section_url
CREATE TABLE IF NOT EXISTS section_url (
    section_id INT NOT NULL,
    url_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    PRIMARY KEY (section_id, url_id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Crea tabella settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'boolean', 'integer', 'float', 'json') DEFAULT 'string',
    description VARCHAR(500),
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- Crea tabella ai_usage_log
CREATE TABLE IF NOT EXISTS ai_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    url_id INT,
    operation_type VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    input_tokens INT DEFAULT 0,
    output_tokens INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    estimated_cost DECIMAL(10, 6) DEFAULT 0,
    success TINYINT(1) DEFAULT 1,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE SET NULL,
    INDEX idx_project (project_id),
    INDEX idx_created (created_at),
    INDEX idx_operation (operation_type)
) ENGINE=InnoDB;

-- Crea tabella crawl_queue
CREATE TABLE IF NOT EXISTS crawl_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    depth INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    error_message TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_status (project_id, status),
    INDEX idx_status (status),
    UNIQUE KEY unique_project_url (project_id, url)
) ENGINE=InnoDB;

-- Crea tabella crawl_stats
CREATE TABLE IF NOT EXISTS crawl_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    total_discovered INT DEFAULT 0,
    total_crawled INT DEFAULT 0,
    total_failed INT DEFAULT 0,
    total_skipped INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Inserisci impostazioni di default
INSERT INTO settings (setting_key, setting_value, setting_type, description, category) VALUES
('app_name', 'llms.txt Generator', 'string', 'Nome dell''applicazione', 'general'),
('app_debug', 'true', 'boolean', 'Modalità debug', 'general'),
('db_initialized', 'true', 'boolean', 'Database inizializzato', 'system'),
('openai_enabled', 'false', 'boolean', 'Abilita servizio OpenAI', 'openai'),
('openai_api_key', '', 'string', 'Chiave API OpenAI', 'openai'),
('openai_model', 'gpt-3.5-turbo', 'string', 'Modello OpenAI', 'openai'),
('openai_temperature', '0.7', 'float', 'Temperature (0-2)', 'openai'),
('storage_path', 'storage', 'string', 'Percorso di storage', 'storage')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Esci da MySQL
EXIT;
```

**Alternativa con file SQL**:

```bash
mysql -u root -p < sql/schema.sql
```

Poi inserisci le impostazioni di default:

```bash
mysql -u root -p llms_app -e "INSERT INTO settings (setting_key, setting_value, setting_type, description, category) VALUES ('app_name', 'llms.txt Generator', 'string', 'Nome applicazione', 'general'), ('db_initialized', 'true', 'boolean', 'Database inizializzato', 'system'), ('openai_enabled', 'false', 'boolean', 'Abilita OpenAI', 'openai'), ('openai_api_key', '', 'string', 'API Key OpenAI', 'openai'), ('openai_model', 'gpt-3.5-turbo', 'string', 'Modello OpenAI', 'openai'), ('openai_temperature', '0.7', 'float', 'Temperature', 'openai'), ('storage_path', 'storage', 'string', 'Percorso storage', 'storage') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);"
```

## Step 6: Crea Directory Storage

```bash
# Crea la directory storage se non esiste
mkdir -p storage/llms

# Imposta permessi (su Linux/macOS)
chmod -R 777 storage
```

Su Windows, assicurati che la cartella `storage` sia scrivibile.

## Step 7: Verifica Configurazione Apache

Il file `.htaccess` nella cartella `public/` dovrebbe già essere configurato correttamente. Verifica che mod_rewrite sia abilitato in XAMPP:

1. Apri `C:\xampp\apache\conf\httpd.conf` (o equivalente)
2. Trova la riga `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Rimuovi il `#` se presente per abilitare mod_rewrite
4. Riavvia Apache

## Step 8: Test Applicazione

Apri il browser e vai a:

```
http://localhost/llms-ai-generator/public/
```

Dovresti vedere la homepage dell'applicazione con la lista dei progetti (vuota inizialmente).

## Step 9: Primo Utilizzo

1. Clicca su "Nuovo Progetto"
2. Inserisci:
   - Nome progetto (es. "Il mio sito")
   - URL Homepage (es. "https://www.esempio.com")
   - Il dominio verrà estratto automaticamente
3. Clicca "Crea progetto"
4. Verrai reindirizzato alla pagina di scansione
5. Clicca "Avvia scansione" per iniziare il crawling

## Configurazione OpenAI (Opzionale)

Per abilitare la generazione automatica di descrizioni:

1. Vai su `/settings`
2. Imposta "OpenAI Enabled" su "true"
3. Inserisci la tua API Key OpenAI
4. Seleziona il modello (gpt-3.5-turbo consigliato)
5. Salva le impostazioni

---

## Risoluzione Problemi

### Errore "Class not found"
```bash
composer dump-autoload
```

### Errore connessione database
- Verifica che MySQL sia attivo nel pannello XAMPP
- Controlla le credenziali nel file `.env`
- Verifica che il database `llms_app` esista

### Pagina bianca o errore 500
- Abilita `display_errors` in `php.ini`
- Controlla i log di Apache: `C:\xampp\apache\logs\error.log`

### mod_rewrite non funziona
- Verifica che sia abilitato in `httpd.conf`
- Verifica che `AllowOverride All` sia impostato per la directory

### Errore permessi storage
```bash
chmod -R 777 storage
```

---

## Comandi Utili

```bash
# Verifica sintassi PHP
php -l public/index.php

# Verifica configurazione PHP
php -i | grep -i "loaded configuration"

# Test connessione MySQL
mysql -u root -p -e "SHOW DATABASES;"

# Verifica tabelle create
mysql -u root -p llms_app -e "SHOW TABLES;"
```

---

## Struttura URL dell'Applicazione

- Homepage: `http://localhost/llms-ai-generator/public/`
- Nuovo progetto: `http://localhost/llms-ai-generator/public/projects/create`
- Impostazioni: `http://localhost/llms-ai-generator/public/settings`
- Costi AI: `http://localhost/llms-ai-generator/public/costs`

I file llms.txt generati saranno disponibili su:
`http://localhost/llms-ai-generator/public/llms/{slug-progetto}.txt`
