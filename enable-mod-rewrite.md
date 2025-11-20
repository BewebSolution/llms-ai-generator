# Come abilitare mod_rewrite in Laragon

## Passaggi:

1. **Apri il file httpd.conf di Apache**
   - Posizione tipica: `C:\laragon\bin\apache\apache-2.4.54\conf\httpd.conf`
   - Oppure dal menu Laragon: Menu → Apache → httpd.conf

2. **Cerca e decommenta questa riga** (rimuovi il # all'inizio):
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```

3. **Verifica anche che AllowOverride sia impostato su All**
   Cerca la sezione `<Directory>` per il tuo document root e assicurati che sia:
   ```apache
   <Directory "C:/laragon/www">
       Options Indexes FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
   ```

4. **Salva il file**

5. **Riavvia Apache da Laragon**
   - Click destro sull'icona Laragon
   - Stop All
   - Start All

## Verifica che funzioni:

Dopo il riavvio, visita:
http://localhost/llms-generate/public/info.php

Dovresti vedere "mod_rewrite è abilitato"