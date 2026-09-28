#!/bin/bash
set -e

cd /var/www/html

if [ ! -f vendor/autoload_runtime.php ]; then
    echo "[entrypoint] FATAL: vendor/ missing in image"
    exit 1
fi

mkdir -p public var/cache var/log uploads
chown -R www-data:www-data uploads var public 2>/dev/null || true
chmod -R 0775 uploads var 2>/dev/null || true

# Vérifie que le bundle source est bien bind-mounté
if [ ! -f vendor/bnine/filesbundle/composer.json ] || [ ! -d vendor/bnine/filesbundle/src ]; then
    echo "[entrypoint] FATAL: vendor/bnine/filesbundle missing (bind-mount absent?)"
    exit 1
fi

# Regenerer l'autoload a CHAQUE demarrage (sans --classmap-authoritative).
# C'est necessaire car les nouveaux fichiers ajoutes dans sample/src/ ou
# dans le bundle bind-monte ne sont pas dans la classmap generee au build.
# Le PSR-4 dynamique scanne les namespaces a chaque autoload, ce qui evite
# d'avoir a lancer manuellement 'composer dump-autoload' apres chaque
# ajout de classe. Cout : ~1-2s par demarrage.
echo "[entrypoint] composer dump-autoload (PSR-4 dynamique)"
composer dump-autoload --no-scripts --optimize 2>&1 | tail -3 || true

echo "[entrypoint] assets:install"
su www-data -s /bin/bash -c "php bin/console assets:install public" 2>&1 | tail -3 || true

# Recopie les assets du bundle depuis vendor/ (qui est bind-monté sur le bundle source)
# pour s'assurer qu'ils reflètent bien les dernières modifs.
# assets:install publie le bundle mais on force aussi manuellement pour être sur :
# - bninefiles.js/css (assets bundle natifs)
# - lib/{js,css,webfonts}/ (libs tierces embarquées : jQuery, Bootstrap, etc.)
echo "[entrypoint] sync bundle assets"
mkdir -p public/bundles/bninefiles/css public/bundles/bninefiles/js public/bundles/bninefiles/lib/css public/bundles/bninefiles/lib/js public/bundles/bninefiles/lib/webfonts
cp -rf vendor/bnine/filesbundle/Resources/public/css/* public/bundles/bninefiles/css/
cp -rf vendor/bnine/filesbundle/Resources/public/js/* public/bundles/bninefiles/js/
cp -rf vendor/bnine/filesbundle/Resources/public/lib/css/* public/bundles/bninefiles/lib/css/
cp -rf vendor/bnine/filesbundle/Resources/public/lib/js/* public/bundles/bninefiles/lib/js/
cp -rf vendor/bnine/filesbundle/Resources/public/lib/webfonts/* public/bundles/bninefiles/lib/webfonts/
chown www-data:www-data public/bundles -R 2>/dev/null || true

echo "[entrypoint] cache:clear"
su www-data -s /bin/bash -c "php bin/console cache:clear --no-warmup" 2>&1 | tail -3 || true

echo "[entrypoint] seed initial images"
bash /usr/local/bin/seed.sh

echo "[entrypoint] Starting Apache..."
exec apache2-foreground
