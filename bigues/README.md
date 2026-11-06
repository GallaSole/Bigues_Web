# Club Hoquei Patí Bigues i Riells

Web pública del club, feta amb HTML, CSS i JavaScript. La pàgina inicial és `index.html` i no necessita cap procés de compilació.

## Desplegament de la web

1. Puja el repositori a GitHub.
2. A Vercel, importa el repositori.
3. Selecciona `Other` com a framework. Deixa en blanc la comanda de build i indica `.` com a directori de sortida.

Vercel publicarà les pàgines estàtiques. El fitxer `.vercelignore` evita que el codi PHP i les dades del backend s'incloguin en aquest desplegament.

## Resultats i notícies

El panell d'administració i l'API de notícies/resultats són a `bigues-resultats/` i necessiten un servidor amb PHP i permisos d'escriptura a les carpetes de dades i pujades. No funcionaran a Vercel tal com estan.

La web pública consulta actualment l'API amb una ruta del mateix domini (`bigues-resultats/api.php`). Quan el backend tingui una adreça pròpia, caldrà configurar la URL de l'API a la web i permetre-hi les peticions CORS des del domini de Vercel. Fins aleshores, la web estàtica es pot desplegar, però no carregarà les publicacions dinàmiques del servidor PHP.

No pugis mai `data/settings.php` ni `bigues-resultats/data/settings.php`: contenen el hash de la contrasenya d'administració. Aquestes rutes ja estan excloses a `.gitignore`.
