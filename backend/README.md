
## Installation rapide
1. Clone repo / crée dossier.
2. Crée `.env` (exemple plus bas).
3. Crée la base de données et exécute les migrations SQL (fichiers `migrations.sql` fournis).
4. Configure ton serveur web pour pointer `/public` comme docroot.
   - Exemple dev rapide :
     ```bash
     php -S 127.0.0.1:8080 -t public
     ```
5. Assure toi que `uploads/messages` est inscriptible par PHP:
   ```bash
   mkdir -p uploads/messages
   chown -R www-data:www-data uploads
   chmod -R 775 uploads
