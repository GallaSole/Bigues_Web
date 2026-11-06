# App de publicació (Bigues i Riells)

PWA privada per a dues persones: publica **resultats** (una foto) i **notícies** (títol, text i foto opcional) a la web del club. No permet editar ni eliminar.

## Provar la demo local

1. Requisits: Node i PHP al PATH.
2. A l'arrel del projecte: `node dev-server.mjs`
3. App: http://127.0.0.1:4173/bigues-resultats/admin.php
   Web: http://127.0.0.1:4173/noticies.html
4. La primera vegada l'app demana crear els dos usuaris (contrasenya mínim 8 caràcters). Només és per a la demo.
5. Publica un resultat o una notícia, confirma, i recarrega `noticies.html`.

Per provar-ho a l'iPhone, l'ordinador i el mòbil han d'estar a la mateixa xarxa i el servidor ha d'escoltar a la xarxa local. Per a la PWA a l'iPhone cal HTTPS, que només tindrà el hosting definitiu.

## Com funciona

- `admin.php`: pantalla de l'app (login, inici, formularis) i les accions de publicar.
- `inc/common.php`: usuaris, sessió, CSRF, límit d'intents, imatges i dades.
- `api.php`: JSON públic que llegeix `js/main.js` de la web per pintar notícies i resultats.
- `data/*.php`: dades (a `settings.php` només hi ha els hashes de les contrasenyes). Ignorat per git.
- `uploads/`: imatges publicades (convertides a WebP).
- `dev-server.mjs` (arrel): serveix la web i reenvia les peticions PHP a `php -S` en un port intern.

## Només de demostració

- Creació dels usuaris des del navegador.
- Servidor de desenvolupament (`dev-server.mjs` + `php -S`), HTTP sense xifrar.
- Dades en fitxers locals: no es comparteixen entre dispositius.

## Pas al hosting definitiu

Cal un hosting amb PHP, HTTPS i escriptura a `data/` i `uploads/`. Crea els usuaris reals i no pugis mai `data/settings.php`. Si el web i l'app estan en dominis diferents, caldrà configurar la URL de l'API i CORS.