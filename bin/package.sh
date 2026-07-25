#!/usr/bin/env bash
# Gera o pacote de distribuição do tema Arena (pai + filho + mu-plugin + docs).
#
# Uso:  bash bin/package.sh [diretório-de-saída]
# Saída padrão: ../../../arena-package/  (ao lado de wp-content)
#
# O pacote contém APENAS o que o site precisa em tempo de execução.
# Ferramentas de desenvolvimento (node_modules, vendor, tests, .superpowers,
# configs de build) ficam de fora — ver DEPLOY.md, seção 9.
set -euo pipefail

THEME_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
THEMES_DIR="$(dirname "$THEME_DIR")"
OUT_DIR="${1:-$THEMES_DIR/../../arena-package}"
STAGE="$OUT_DIR/stage"
VERSION="$(grep -m1 '^Version:' "$THEME_DIR/style.css" | sed 's/Version:[[:space:]]*//' | tr -d '\r')"
STAMP="$(date +%Y%m%d)"
ZIP_NAME="arena-tema-v${VERSION}-${STAMP}.zip"

echo "==> Tema:    $THEME_DIR"
echo "==> Versão:  $VERSION"
echo "==> Saída:   $OUT_DIR/$ZIP_NAME"

# ---------------------------------------------------------------- build assets
echo "==> Compilando assets (npm run build)…"
( cd "$THEME_DIR" && npm run build >/dev/null 2>&1 )

MANIFEST="$THEME_DIR/assets/dist/.vite/manifest.json"
[ -s "$MANIFEST" ] || { echo "ERRO: manifest do Vite não foi gerado ($MANIFEST)"; exit 1; }
echo "    manifest OK ($(wc -c < "$MANIFEST") bytes)"

# ---------------------------------------------------------------- staging
rm -rf "$STAGE"; mkdir -p "$STAGE/arena"

# Só o que roda em produção. Nota: assets/dist/.vite/ (oculto) é essencial.
for item in \
  style.css theme.json functions.php index.php header.php footer.php \
  front-page.php page.php single.php archive.php search.php searchform.php \
  sidebar.php comments.php 404.php attachment.php \
  inc template-parts languages \
  README.md DEPLOY.md
do
  [ -e "$THEME_DIR/$item" ] && cp -R "$THEME_DIR/$item" "$STAGE/arena/"
done

mkdir -p "$STAGE/arena/assets"
for a in dist fonts img src; do
  [ -d "$THEME_DIR/assets/$a" ] && cp -R "$THEME_DIR/assets/$a" "$STAGE/arena/assets/"
done
# assets/src não é necessário em produção, mas é útil para futuras customizações
# no tema filho; remova a linha acima se quiser um pacote ainda menor.

# tema filho
if [ -d "$THEMES_DIR/arena-child" ]; then
  mkdir -p "$STAGE/arena-child"
  cp -R "$THEMES_DIR/arena-child/." "$STAGE/arena-child/"
  rm -rf "$STAGE/arena-child/.git"
fi

# mu-plugin de preview
if [ -d "$THEME_DIR/mu-plugins" ]; then
  mkdir -p "$STAGE/mu-plugins"
  cp "$THEME_DIR/mu-plugins/"*.php "$STAGE/mu-plugins/" 2>/dev/null || true
fi

cp "$THEME_DIR/DEPLOY.md" "$STAGE/" 2>/dev/null || true

# ---------------------------------------------------------------- sanidade
echo "==> Conferindo o conteúdo do stage…"
[ -s "$STAGE/arena/assets/dist/.vite/manifest.json" ] \
  || { echo "ERRO: manifest não entrou no pacote"; exit 1; }
[ -s "$STAGE/arena/style.css" ] || { echo "ERRO: style.css ausente"; exit 1; }
for forbidden in node_modules vendor tests .superpowers .git .wp-env.json \
                 .wp-env.override.json composer.json package.json vite.config.js \
                 phpunit.xml.dist
do
  if find "$STAGE" -name "$forbidden" -print -quit | grep -q .; then
    echo "ERRO: '$forbidden' não deveria estar no pacote"; exit 1
  fi
done
echo "    stage limpo"

# ---------------------------------------------------------------- zip
echo "==> Gerando o zip…"
rm -f "$OUT_DIR/$ZIP_NAME"
( cd "$STAGE" && zip -rq "$OUT_DIR/$ZIP_NAME" . -x '.DS_Store' )

# confirma que o arquivo oculto sobreviveu ao zip
if ! unzip -l "$OUT_DIR/$ZIP_NAME" | grep -q 'assets/dist/.vite/manifest.json'; then
  echo "ERRO: o manifest não está dentro do zip"; exit 1
fi

echo
echo "==> PRONTO"
echo "    $OUT_DIR/$ZIP_NAME  ($(du -h "$OUT_DIR/$ZIP_NAME" | cut -f1))"
echo "    arquivos no zip: $(unzip -l "$OUT_DIR/$ZIP_NAME" | tail -1 | awk '{print $2}')"
echo
echo "    Leia DEPLOY.md antes de subir — em especial a seção 3 (arquivos ocultos no FTP)."
