import { createServer, request as httpRequest } from "node:http";
import { spawn } from "node:child_process";
import { readFile } from "node:fs/promises";
import { extname, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(fileURLToPath(new URL(".", import.meta.url)));

// Només DEMO: PHP corre en un port privat i aquest servidor només hi reenvia l'app de publicació.
const phpPort = 4174;
const phpEntrypoints = new Set(["/bigues-resultats/admin.php", "/bigues-resultats/api.php", "/bigues-resultats/index.php"]);
const php = spawn("php", ["-S", `127.0.0.1:${phpPort}`, "-t", root, "-d", "upload_max_filesize=10M", "-d", "post_max_size=12M"], { stdio: "ignore" });
php.on("error", () => console.warn("PHP no s'ha pogut arrencar: l'app de publicació no estarà disponible."));
process.on("exit", () => php.kill());
process.on("SIGINT", () => process.exit());
const standingsSources = {
  "1a": {
    url: "https://www.server2.sidgad.es/fecapa/fecapa_clasif_idc_4776_1.php",
    origin: "https://www.hoqueipatins.fecapa.cat",
    referer: "https://www.hoqueipatins.fecapa.cat/league/4776",
    form: { idc: "4776", tipo_stats: "", site_lang: "ca" },
  },
  ok: {
    url: "https://www.server2.sidgad.es/rfep/rfep_clasif_idc_3569_1.php",
    origin: "https://www.hockeypatines.fep.es",
    referer: "https://www.hockeypatines.fep.es/league/3569",
    form: { idc: "3569", tipo_stats: "", site_lang: "es" },
  },
};
const mimeTypes = {
  ".css": "text/css; charset=utf-8",
  ".html": "text/html; charset=utf-8",
  ".jpeg": "image/jpeg",
  ".jpg": "image/jpeg",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".mjs": "text/javascript; charset=utf-8",
  ".png": "image/png",
  ".svg": "image/svg+xml",
  ".webp": "image/webp",
  ".webmanifest": "application/manifest+json; charset=utf-8",
};

const server = createServer(async (request, response) => {
  const { pathname, searchParams } = new URL(request.url || "/", "http://localhost");

  if (phpEntrypoints.has(pathname)) {
    const upstream = httpRequest(
      { host: "127.0.0.1", port: phpPort, path: request.url, method: request.method, headers: request.headers },
      (phpResponse) => {
        response.writeHead(phpResponse.statusCode || 502, phpResponse.headers);
        phpResponse.pipe(response);
      },
    );
    upstream.on("error", () => {
      response.writeHead(502, { "Content-Type": "text/plain; charset=utf-8" });
      response.end("El servei PHP local no està disponible.");
    });
    request.pipe(upstream);
    return;
  }

  if (pathname === "/fecapa-classificacio.php") {
    try {
      const competition = searchParams.get("competition") || "1a";
      const source = standingsSources[competition];
      if (!source) {
        response.writeHead(400, { "Content-Type": "text/plain; charset=utf-8" });
        response.end("La competició indicada no és vàlida.");
        return;
      }

      const upstream = await fetch(source.url, {
        method: "POST",
        headers: {
          Accept: "*/*",
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          Origin: source.origin,
          Referer: source.referer,
          "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: new URLSearchParams(source.form),
      });
      const html = await upstream.text();
      if (!upstream.ok || !html.includes("tabla_standard")) throw new Error("FECAPA no ha retornat cap classificació.");

      response.writeHead(200, { "Cache-Control": "no-store", "Content-Type": "text/html; charset=utf-8" });
      response.end(html);
    } catch {
      response.writeHead(502, { "Cache-Control": "no-store", "Content-Type": "text/plain; charset=utf-8" });
      response.end("La classificació de FECAPA no està disponible.");
    }
    return;
  }

  if (request.method !== "GET" && request.method !== "HEAD") {
    response.writeHead(405);
    response.end();
    return;
  }

  const decodedPath = decodeURIComponent(pathname === "/" ? "/index.html" : pathname);
  const filePath = resolve(root, `.${decodedPath}`);
  if (!filePath.startsWith(`${root}${sep}`) || extname(filePath).toLowerCase() === ".php") {
    response.writeHead(404);
    response.end("Not found");
    return;
  }

  try {
    const file = await readFile(filePath);
    response.writeHead(200, { "Content-Type": mimeTypes[extname(filePath).toLowerCase()] || "application/octet-stream" });
    response.end(request.method === "HEAD" ? undefined : file);
  } catch {
    response.writeHead(404);
    response.end("Not found");
  }
});

// LAN=1 obre el servidor a la xarxa local (només per provar des de l'iPhone).
server.listen(4173, process.env.LAN === "1" ? "0.0.0.0" : "127.0.0.1", () => {
  console.log("Local preview: http://127.0.0.1:4173/equips.html");
  console.log("App de publicació (demo): http://127.0.0.1:4173/bigues-resultats/admin.php");
});