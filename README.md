# 🚀 LLMS.txt AI Generator

> Generatore automatico di file llms.txt per l'ottimizzazione SEO con AI (ChatGPT, Claude, Gemini)

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-production%20ready-success.svg)](https://github.com/BewebSolution/llms-ai-generator)

## 🎯 Descrizione

LLMS.txt AI Generator è un'applicazione web professionale che automatizza completamente la creazione di file llms.txt per l'ottimizzazione dei siti web per i Large Language Models (LLM).

Il tool parsa automaticamente le sitemap XML, genera titoli e descrizioni ottimizzate tramite OpenAI, classifica i contenuti e crea file llms.txt pronti all'uso seguendo le specifiche ufficiali.

## ✨ Funzionalità Principali

- 🗺️ **Parsing Sitemap XML** - Importa automaticamente tutte le URL dal tuo sito
- 🤖 **AI-Powered** - Genera titoli, descrizioni e classificazioni con OpenAI GPT-3.5
- 📊 **Smart Classification** - 8 categorie di contenuto (Homepage, Product, Blog, etc.)
- 💰 **Cost Tracking** - Monitora in tempo reale i costi delle API OpenAI
- 🚀 **Performance** - Gestisce progetti con 5000+ URL senza problemi
- 📱 **Responsive UI** - Interfaccia moderna con paginazione professionale
- 🗑️ **Bulk Operations** - Elimina o processa centinaia di URL in un click
- 💾 **Auto-save** - I risultati AI vengono salvati automaticamente nel database

## 🛠️ Requisiti

- PHP 8.1 o superiore
- MySQL 8.x
- Composer
- OpenAI API Key
- Apache con mod_rewrite abilitato

## 📦 Installazione

### 1. Clona il repository

```bash
git clone https://github.com/BewebSolution/llms-ai-generator.git
cd llms-ai-generator
```

### 2. Installa le dipendenze

```bash
composer install
```

### 3. Configura Apache

Assicurati che il DocumentRoot punti alla cartella `public/`:

```apache
<VirtualHost *:80>
    ServerName tuodominio.com
    DocumentRoot /path/to/llms-ai-generator/public

    <Directory /path/to/llms-ai-generator/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. Accedi all'applicazione

```
http://tuodominio.com/
```

Il sistema ti guiderà automaticamente attraverso il setup del database.

## 🚀 Quick Start

1. **Setup Iniziale** - L'applicazione configura automaticamente il database
2. **Configura OpenAI** - Vai in Impostazioni e inserisci la tua API Key
3. **Crea Progetto** - Aggiungi nome e dominio del sito
4. **Importa Sitemap** - Incolla l'URL della sitemap XML (es: `https://example.com/sitemap.xml`)
5. **Parsing** - Clicca su "Parsing" per importare tutte le URL
6. **Genera con AI** - Usa i bottoni AI per generare titoli e descrizioni
7. **Esporta** - Genera il file llms.txt ottimizzato!

## 📊 Performance

- ⚡ **3 secondi** per generare un file llms.txt completo
- 💾 **16MB** footprint totale dell'applicazione
- 🔄 **Batch processing** fino a 100 URL/minuto con AI
- 📈 **Scalabile** fino a 10.000+ URL per progetto

## 🌐 Deployment su SiteGround

### Preparazione Files

1. Crea una sottocartella nel tuo dominio (es: `llms-generator`)
2. Carica tutti i file tramite FTP/File Manager
3. Imposta i permessi:
   ```bash
   chmod 755 public/
   chmod 644 public/.htaccess
   chmod 777 storage/
   ```

### Configurazione Database

1. Crea un nuovo database MySQL dal cPanel
2. Crea un utente database con tutti i privilegi
3. L'applicazione configurerà automaticamente le tabelle al primo accesso

### URL Finale

La tua applicazione sarà accessibile su:
```
https://clementeteodonno.it/llms-generator/
```

## 🔒 Sicurezza

- Validazione input su tutti i form
- Prepared statements per prevenire SQL injection
- Escape output per prevenire XSS
- Doppia conferma per azioni distruttive
- API key criptate nel database

## 📄 File llms.txt

L'applicazione genera file llms.txt conformi alle [specifiche ufficiali](https://llmstxt.org/):

```markdown
# Nome Sito

> Breve descrizione del sito

## Pagina Principale
- [Homepage](https://example.com): Descrizione homepage

## Prodotti e Servizi
- [Prodotto 1](https://example.com/product1): Descrizione prodotto
- [Prodotto 2](https://example.com/product2): Descrizione prodotto

## Optional
Policy e termini di servizio...
```

## 🤝 Contributi

I contributi sono benvenuti! Sentiti libero di:
- 🐛 Segnalare bug
- 💡 Suggerire nuove funzionalità
- 🔧 Inviare pull request

## 📝 License

MIT License - vedi file [LICENSE](LICENSE) per dettagli.

## 👨‍💻 Autore

**Clemente Teodonno**
- Website: [clementeteodonno.it](https://clementeteodonno.it)
- GitHub: [@BewebSolution](https://github.com/BewebSolution)

## 🙏 Credits

- OpenAI per le API GPT-3.5
- Bramus Router per il routing PHP
- Tutti i contributor del progetto

---

Made with ❤️ in Italy