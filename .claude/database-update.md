# Istruzioni per l'Aggiornamento del Database

## Aggiornamento Schema per Web Crawling

Dopo l'implementazione della funzionalità di web crawling, è necessario aggiornare il database esistente con le nuove tabelle e colonne.

### Metodo 1: Aggiornamento Manuale (Consigliato per database esistenti)

Esegui queste query SQL per aggiungere le nuove colonne e tabelle senza perdere i dati esistenti:

```sql
-- 1. Aggiungi nuove colonne alla tabella projects
ALTER TABLE projects
    ADD COLUMN homepage_url VARCHAR(500) AFTER description,
    ADD COLUMN crawl_depth INT DEFAULT 3 AFTER homepage_url,
    ADD COLUMN crawl_status ENUM('pending', 'in_progress', 'completed', 'failed', 'stopped') DEFAULT 'pending' AFTER crawl_depth,
    ADD COLUMN max_urls INT DEFAULT 500 AFTER crawl_status,
    ADD COLUMN last_crawl_at TIMESTAMP NULL AFTER max_urls,
    ADD COLUMN crawl_error TEXT AFTER last_crawl_at;

-- 2. Aggiungi indice per crawl_status
ALTER TABLE projects ADD INDEX idx_crawl_status (crawl_status);

-- 3. Aggiorna ENUM type nella tabella urls con nuovi tipi di classificazione
ALTER TABLE urls MODIFY COLUMN type ENUM(
    'HOMEPAGE', 'LANGUAGE', 'COUNTRY', 'PROMOTION', 'CATEGORY',
    'PRODUCT', 'BRAND', 'FEATURE', 'SUPPORT', 'POLICY',
    'COMPANY', 'BLOG', 'OTHER'
) DEFAULT 'OTHER';

-- 4. Aggiungi nuove colonne alla tabella urls
ALTER TABLE urls
    ADD COLUMN content_hash VARCHAR(64) AFTER is_selected,
    ADD COLUMN crawl_depth INT DEFAULT 0 AFTER content_hash,
    ADD COLUMN http_status INT AFTER crawl_depth;

-- 5. Crea tabella crawl_queue
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

-- 6. Crea tabella crawl_stats
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
```

### Metodo 2: Reset Completo (Solo per nuove installazioni)

Se stai iniziando da zero o vuoi resettare completamente il database:

```bash
# Esegui lo schema completo
mysql -u root -p < sql/schema.sql
```

**ATTENZIONE**: Questo cancellerà tutti i dati esistenti!

### Metodo 3: Via Applicazione

Se l'applicazione non trova le tabelle richieste, proverà a crearle automaticamente al primo avvio. Tuttavia, per le colonne aggiuntive su tabelle esistenti, è necessario eseguire manualmente le query ALTER TABLE.

## Verifica dell'Aggiornamento

Dopo l'aggiornamento, verifica che tutto sia corretto:

```sql
-- Verifica colonne projects
DESCRIBE projects;

-- Verifica colonne urls
DESCRIBE urls;

-- Verifica nuove tabelle
SHOW TABLES LIKE 'crawl%';

-- Verifica indici
SHOW INDEX FROM projects WHERE Key_name = 'idx_crawl_status';
```

## Rollback (Se necessario)

Per rimuovere le modifiche del crawling:

```sql
-- Rimuovi colonne da projects
ALTER TABLE projects
    DROP COLUMN homepage_url,
    DROP COLUMN crawl_depth,
    DROP COLUMN crawl_status,
    DROP COLUMN max_urls,
    DROP COLUMN last_crawl_at,
    DROP COLUMN crawl_error,
    DROP INDEX idx_crawl_status;

-- Rimuovi colonne da urls
ALTER TABLE urls
    DROP COLUMN content_hash,
    DROP COLUMN crawl_depth,
    DROP COLUMN http_status;

-- Rimuovi tabelle crawling
DROP TABLE IF EXISTS crawl_stats;
DROP TABLE IF EXISTS crawl_queue;
```

## Note Importanti

1. **Backup**: Fai sempre un backup del database prima di eseguire modifiche
2. **Ordine**: Esegui le query nell'ordine indicato per rispettare le dipendenze delle foreign key
3. **Compatibilità**: Le modifiche sono retrocompatibili - i progetti esistenti continueranno a funzionare
4. **Sitemap**: La funzionalità sitemap esistente rimane funzionante, il crawling è un'alternativa aggiuntiva
