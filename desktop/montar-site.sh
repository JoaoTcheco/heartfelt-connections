#!/usr/bin/env bash
# ============================================================
# FarmaPonto - montagem da pasta "site" da versao de secretaria
# ============================================================
# Junta os recursos publicos (estilos, imagens, tipos de letra), os
# arranques minimos e o cofre cifrado com todo o codigo da aplicacao.
#
# Uso: desktop/montar-site.sh <pasta-destino> <executavel-do-programa> [php]
set -euo pipefail

DESTINO="${1:?indique a pasta de destino}"
EXE="${2:?indique o executavel do programa}"
PHP="${3:-php}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"

rm -rf "$DESTINO"
mkdir -p "$DESTINO"

# 1. Recursos publicos (nao contem logica de negocio)
cp -r "$RAIZ/assets" "$DESTINO/assets"
for f in sw.js manifest.webmanifest offline.html; do
  [ -f "$RAIZ/$f" ] && cp "$RAIZ/$f" "$DESTINO/$f"
done
[ -f "$RAIZ/assets/images/favicon.ico" ] && cp "$RAIZ/assets/images/favicon.ico" "$DESTINO/favicon.ico"

# 2. Arranques minimos + carregador do cofre
cp "$RAIZ/desktop/site/index.php" "$RAIZ/desktop/site/router.php" \
   "$RAIZ/desktop/site/preparar.php" "$RAIZ/desktop/site/carregador.php" "$DESTINO/"

# 3. Cofre cifrado com todo o codigo
"$PHP" "$RAIZ/scripts/proteger.php" --destino="$DESTINO" --exe="$EXE"

echo "Site montado em $DESTINO"
