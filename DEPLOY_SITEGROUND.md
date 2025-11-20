# 📦 Guida Deployment su SiteGround

## 🎯 Deployment su clementeteodonno.it/llms-generator

### 1. Preparazione Files Locali

Prima di caricare, modifica questi file:

#### A. File `public/.htaccess`
Aggiungi dopo `RewriteEngine On`:
```apache
# Per sottocartella llms-generator
RewriteBase /llms-generator/public/
```

#### B. Crea file `.env` con queste configurazioni:
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=[nome_database_siteground]
DB_USERNAME=[username_database]
DB_PASSWORD=[password_database]

# App settings
APP_BASE_PATH=/llms-generator/public
APP_URL=https://clementeteodonno.it/llms-generator
STORAGE_PATH=../storage
```

### 2. Creazione Database su SiteGround

1. Accedi al **cPanel** di SiteGround
2. Vai su **MySQL Databases**
3. Crea nuovo database (es: `clemweb_llms`)
4. Crea nuovo utente database
5. Assegna l'utente al database con **TUTTI i privilegi**
6. Salva credenziali per il file `.env`

### 3. Upload Files via FTP

#### Credenziali FTP SiteGround:
- Host: `ftp.clementeteodonno.it` o IP del server
- Username: [tuo username cPanel]
- Password: [tua password]
- Porta: 21

#### Struttura cartelle su SiteGround:
```
/home/[username]/public_html/
└── llms-generator/
    ├── public/        # DocumentRoot
    │   ├── index.php
    │   ├── .htaccess
    │   ├── css/
    │   └── js/
    ├── src/
    ├── vendor/
    ├── storage/       # Permessi 777
    ├── composer.json
    └── .env          # Da creare
```

### 4. Upload Step-by-Step

1. **Crea cartella** `/public_html/llms-generator/`

2. **Carica tutti i file** ECCETTO:
   - `.git/`
   - `.claude/`
   - `node_modules/`
   - Files di test (`test-*.php`)
   - Files di debug (`debug-*.php`)

3. **Imposta permessi** (via FTP o File Manager):
   ```
   storage/ → 777 (ricorsivo)
   public/ → 755
   public/.htaccess → 644
   ```

### 5. Configurazione Apache su SiteGround

Se necessario, aggiungi nel `.htaccess` principale di `public_html`:

```apache
# Redirect per llms-generator
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/llms-generator/?$
    RewriteRule ^(.*)$ /llms-generator/public/ [L]
</IfModule>
```

### 6. Installazione Composer su SiteGround

Via **SSH** (se disponibile):
```bash
cd ~/public_html/llms-generator
php composer.phar install --no-dev --optimize-autoloader
```

O via **cPanel Terminal**:
```bash
cd /home/[username]/public_html/llms-generator
/usr/local/bin/composer install --no-dev
```

### 7. Primo Accesso e Setup

1. Naviga su: `https://clementeteodonno.it/llms-generator/`
2. Il sistema rileverà automaticamente che serve il setup
3. Inserisci le credenziali database dal punto 2
4. L'app creerà automaticamente tutte le tabelle
5. Vai in **Impostazioni** e configura la tua OpenAI API key

### 8. Troubleshooting

#### Errore 500
- Controlla il file `.htaccess`
- Verifica che mod_rewrite sia abilitato
- Controlla error log in cPanel

#### Errore database
- Verifica credenziali in `.env`
- Controlla che l'utente abbia tutti i privilegi
- Testa connessione con script test

#### Pagine non trovate
- Verifica `RewriteBase` in `.htaccess`
- Controlla `APP_BASE_PATH` in `.env`

### 9. File di Test Connessione

Crea `public_html/llms-generator/public/test-db.php`:
```php
<?php
require_once '../vendor/autoload.php';

$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$db = $_ENV['DB_NAME'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✅ Connessione database OK!";
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage();
}
```

Accedi a: `https://clementeteodonno.it/llms-generator/public/test-db.php`

**IMPORTANTE**: Elimina test-db.php dopo il test!

### 10. URL Finali

- **Applicazione**: `https://clementeteodonno.it/llms-generator/`
- **File llms.txt pubblici**: `https://clementeteodonno.it/llms-generator/llms/[slug].txt`

### 11. Backup

Configura backup automatico su SiteGround:
1. cPanel → Backup
2. Configura backup database giornaliero
3. Backup files settimanale della cartella `llms-generator`

---

## 🚀 Comandi Utili

### Reset Database (ATTENZIONE!)
```sql
DROP DATABASE IF EXISTS clemweb_llms;
CREATE DATABASE clemweb_llms;
```

### Permessi via SSH
```bash
find ~/public_html/llms-generator -type d -exec chmod 755 {} \;
find ~/public_html/llms-generator -type f -exec chmod 644 {} \;
chmod -R 777 ~/public_html/llms-generator/storage
```

### Clear Cache
```bash
rm -rf ~/public_html/llms-generator/storage/cache/*
```

---

✅ Dopo questi step, la tua app sarà live su SiteGround!