#!/bin/bash
# Seed initial :
#  - PURGE les dossiers uploads/sample/1/ et uploads/avatar/0/ pour eviter
#    les residus d'essais precedents (fichiers uploadés via l'UI, dossiers
#    parasites comme 'sqdfqsdf', etc.)
#  - copie les wallpapers du dossier bind-monte /var/www/html/wallpaper
#    dans uploads/sample/1/ et uploads/avatar/0/
#  - genere les thumbnails 300xN via PHP/GD pour chaque image
#
# Note : ce script PURGE systematiquement les dossiers cibles.
# Si vous voulez conserver vos uploads manuels, commentez la section PURGE.
set -e

UPLOADS="/var/www/html/uploads"
WALLPAPER="/var/www/html/wallpaper"
THUMBS_MIN_SIZE=300

mkdir -p "$UPLOADS/sample/1" "$UPLOADS/avatar/0"

# ---------- 0. PURGE des dossiers cibles ----------
# On vide uploads/sample/1/ et uploads/avatar/0/ completement (fichiers + _thumbs)
# pour repartir d'un etat propre. Les volumes Docker app_uploads sont preservee
# mais le contenu applicatif est reset.
echo "[seed] PURGE uploads/sample/1/ et uploads/avatar/0/"
rm -rf "$UPLOADS/sample/1"/* "$UPLOADS/sample/1"/.* 2>/dev/null || true
rm -rf "$UPLOADS/avatar/0"/* "$UPLOADS/avatar/0"/.* 2>/dev/null || true
# Recreer les dossiers apres purge (rm -rf sur le contenu, pas sur le dir parent)
mkdir -p "$UPLOADS/sample/1" "$UPLOADS/avatar/0"

# ---------- 1. copie des wallpapers ----------
if [ -d "$WALLPAPER" ]; then
    echo "[seed] copie wallpaper -> sample/1"
    idx=0
    for f in "$WALLPAPER"/*.jpg "$WALLPAPER"/*.jpeg "$WALLPAPER"/*.png; do
        [ -f "$f" ] || continue
        idx=$((idx+1))
        cp "$f" "$UPLOADS/sample/1/$(printf '%02d' $idx)-$(basename "$f")"
        [ $idx -ge 12 ] && break
    done

    echo "[seed] copie wallpaper -> avatar/0"
    idx=0
    for f in "$WALLPAPER"/*.jpg "$WALLPAPER"/*.jpeg "$WALLPAPER"/*.png; do
        [ -f "$f" ] || continue
        idx=$((idx+1))
        ext="${f##*.}"
        cp "$f" "$UPLOADS/avatar/0/avatar-$(printf '%02d' $idx).${ext}"
        [ $idx -ge 4 ] && break
    done
else
    echo "[seed] /var/www/html/wallpaper absent : aucun fichier source"
fi

# ---------- 1b. fichiers dedies a la demo CachePolicy ----------
# On copie 2 fichiers previsibles utilises par /cache-policy :
#   - cache-demo-avatar.jpg dans avatar/0 (sensible -> private+5min via SampleCachePolicy)
#   - cache-demo-sample.jpg dans sample/1 (public -> public+30j defaut bundle)
echo "[seed] copie fichiers dedies demo CachePolicy"
SAMPLE_FILE=$(ls "$WALLPAPER"/*.jpg "$WALLPAPER"/*.jpeg "$WALLPAPER"/*.png 2>/dev/null | head -1)
if [ -n "$SAMPLE_FILE" ] && [ -f "$SAMPLE_FILE" ]; then
    cp "$SAMPLE_FILE" "$UPLOADS/avatar/0/cache-demo-avatar.jpg"
    cp "$SAMPLE_FILE" "$UPLOADS/sample/1/cache-demo-sample.jpg"
    echo "[seed]   - avatar/0/cache-demo-avatar.jpg (sensible)"
    echo "[seed]   - sample/1/cache-demo-sample.jpg (public)"
fi

# ---------- 2. génération des thumbnails 300xN ----------
generate_thumb() {
    local src="$1" dst="$2"
    php -r "
        \$src = '$src';
        \$dst = '$dst';
        if (!file_exists(\$src)) { fwrite(STDERR, \"miss: \$src\n\"); exit(1); }
        \$im = imagecreatefromstring(file_get_contents(\$src));
        if (!\$im) { fwrite(STDERR, \"not image: \$src\n\"); exit(1); }
        \$w = imagesx(\$im); \$h = imagesy(\$im);
        \$minDim = min(\$w, \$h);
        \$ratio = $THUMBS_MIN_SIZE / \$minDim;
        \$newW = (int) (\$w * \$ratio);
        \$newH = (int) (\$h * \$ratio);
        \$thumb = imagescale(\$im, \$newW, \$newH);
        imagepng(\$thumb, \$dst, 6);
        imagedestroy(\$im); imagedestroy(\$thumb);
    "
}

echo "[seed] génération des thumbnails 300xN"

for domain_dir in "$UPLOADS/sample/1" "$UPLOADS/avatar/0"; do
    [ -d "$domain_dir" ] || continue
    thumbs_dir="$domain_dir/_thumbs/300xN"
    mkdir -p "$thumbs_dir"

    for src in "$domain_dir"/*.jpg "$domain_dir"/*.jpeg "$domain_dir"/*.png "$domain_dir"/*.JPG; do
        [ -f "$src" ] || continue
        base="$(basename "$src")"
        thumb_name="${base%.*}.png"
        dst="$thumbs_dir/$thumb_name"
        if [ ! -f "$dst" ]; then
            generate_thumb "$src" "$dst" || echo "[seed] WARN: thumb failed for $src"
        fi
    done
done

# ---------- 3. permissions ----------
chown -R www-data:www-data "$UPLOADS"
echo "[seed] done"