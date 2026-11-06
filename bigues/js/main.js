const menuToggle = document.querySelector(".menu-toggle");
const primaryNav = document.querySelector(".primary-nav");

if (menuToggle && primaryNav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
    menuToggle.setAttribute("aria-expanded", String(!isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Obre el menú" : "Tanca el menú");
    primaryNav.classList.toggle("is-open", !isOpen);
  });
  primaryNav.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Obre el menú");
    primaryNav.classList.remove("is-open");
  }));
}

const headerElement = document.querySelector(".site-header");
if (headerElement && primaryNav) {
  const sponsorImageSources = [
    ...Array.from({ length: 8 }, (_, index) => `patrocinadors/${index + 1}.jpeg`),
    "patrocinadors/a%20teu%20aire.jpg",
    "patrocinadors/ajuntament.jpg",
    "patrocinadors/barovari.png",
    "patrocinadors/can%20ribas.webp",
    "patrocinadors/carroseries%20barbany.webp",
    "patrocinadors/cirera.jpg",
    "patrocinadors/genial.jpg",
    "patrocinadors/hotel.jpg",
    "patrocinadors/iglesias.png",
    "patrocinadors/parc.png",
    "patrocinadors/pat3.jpg",
    "patrocinadors/solucions.png",
    "patrocinadors/viver.png",
    "patrocinadors/espai.png",
  ];
  const sponsorRotator = document.createElement("div");
  sponsorRotator.className = "header-sponsor-rotator";
  sponsorRotator.setAttribute("role", "img");
  sponsorRotator.setAttribute("aria-label", "Logotips dels patrocinadors del club");

  const sponsorImage = document.createElement("img");
  sponsorImage.src = sponsorImageSources[0];
  sponsorImage.alt = "";
  sponsorRotator.append(sponsorImage);
  headerElement.insertBefore(sponsorRotator, menuToggle || primaryNav);

  let sponsorImageIndex = 0;
  window.setInterval(() => {
    sponsorImage.classList.add("is-changing");
    window.setTimeout(() => {
      sponsorImageIndex = (sponsorImageIndex + 1) % sponsorImageSources.length;
      sponsorImage.src = sponsorImageSources[sponsorImageIndex];
      sponsorImage.classList.remove("is-changing");
    }, 250);
  }, 10000);
}

const sponsorWebsiteByImage = new Map([
  ["patrocinadors/1.jpeg", { href: "https://www.orkla.com/", name: "Orkla" }],
  ["patrocinadors/2.jpeg", { href: "https://rototank.com/", name: "Rototank" }],
  ["patrocinadors/3.jpeg", { href: "https://salvelox.es/", name: "Salvelox" }],
  ["patrocinadors/4.jpeg", { href: "https://savosa.cat/", name: "Serveis Ambientals del Vallès Oriental" }],
  ["patrocinadors/5.jpeg", { href: "https://carnia.es/ca/", name: "Carnia" }],
  ["patrocinadors/6.jpeg", { href: "https://insser.cat/ca/", name: "Insser" }],
  ["patrocinadors/7.jpeg", { href: "https://garrigagrup.com/", name: "Garriga Grup" }],
  ["patrocinadors/8.jpeg", { href: "https://somvera.cat/", name: "Vera" }],
  ["patrocinadors/a%20teu%20aire.jpg", { href: "https://alteuaire.es/", name: "A Teu Aire" }],
  ["patrocinadors/ajuntament.jpg", { href: "https://www.biguesiriells.cat/ca/index.htm", name: "Ajuntament de Bigues i Riells del Fai" }],
  ["patrocinadors/barovari.png", { href: "https://www.barovari.com/ca", name: "Barovari" }],
  ["patrocinadors/can%20ribas.webp", { href: "https://canribas.com/ca/", name: "Can Ribas" }],
  ["patrocinadors/carroseries%20barbany.webp", { href: "https://www.carroceriasbarbany.com/", name: "Carrosseries Barbany" }],
  ["patrocinadors/cirera.jpg", { href: "https://cancirera.cat/", name: "Can Cirera" }],
  ["patrocinadors/genial.jpg", { href: "https://www.genialsolutions.net/Hockey-Patines", name: "Genial Authentic Hockey" }],
  ["patrocinadors/hotel.jpg", { href: "https://www.molidelatorre.cat/", name: "Hotel Molí de la Torre" }],
  ["patrocinadors/iglesias.png", { href: "https://www.serveisimmobiliarisiglesias.cat/", name: "Iglesias" }],
  ["patrocinadors/parc.png", { href: "https://www.parcdegualba.cat/", name: "Parc Mediambiental de Gualba" }],
  ["patrocinadors/pat3.jpg", { href: "https://www.telic.group/es/", name: "OXD" }],
  ["patrocinadors/viver.png", { href: "https://www.viversernest.com/", name: "Vivers Ernest" }],
]);

document.querySelectorAll(".sponsor-gallery").forEach((gallery) => {
  if (gallery.querySelector('img[src="patrocinadors/espai.png"]')) return;

  const logo = document.createElement("figure");
  logo.className = "sponsor-logo";
  const image = document.createElement("img");
  image.src = "patrocinadors/espai.png";
  image.alt = "Espai";
  logo.append(image);
  gallery.append(logo);
});

document.querySelectorAll(".sponsor-gallery .sponsor-logo").forEach((logo) => {
  const image = logo.querySelector("img");
  const website = image && sponsorWebsiteByImage.get(image.getAttribute("src"));
  if (!website || logo.tagName !== "FIGURE") return;

  const link = document.createElement("a");
  link.className = logo.className;
  link.href = website.href;
  link.target = "_blank";
  link.rel = "noopener noreferrer";
  link.setAttribute("aria-label", `Visita ${website.name}`);
  logo.replaceWith(link);
  link.append(image);
});

document.querySelectorAll(".social-links").forEach((group) => {
  const links = group.querySelectorAll("a");
  const instagramLink = links[0];
  const tiktokLink = links[1];

  if (instagramLink) {
    instagramLink.href = "https://www.instagram.com/chpbiguesiriells/";
    instagramLink.target = "_blank";
    instagramLink.rel = "noopener noreferrer";
    instagramLink.setAttribute("aria-label", "Instagram del Club Hoquei Patí Bigues i Riells");
    const instagramIcon = document.createElement("img");
    instagramIcon.src = "assets/icons/instagram.svg";
    instagramIcon.alt = "";
    instagramLink.replaceChildren(instagramIcon);
  }

  if (tiktokLink) {
    tiktokLink.href = "https://www.tiktok.com/@chpbiguesiriells";
    tiktokLink.target = "_blank";
    tiktokLink.rel = "noopener noreferrer";
    tiktokLink.setAttribute("aria-label", "TikTok del Club Hoquei Patí Bigues i Riells");
    const tiktokIcon = document.createElement("img");
    tiktokIcon.src = "assets/icons/tiktok.svg";
    tiktokIcon.alt = "";
    tiktokLink.replaceChildren(tiktokIcon);
  }
});

const seniorCatalanLeagueTitle = [...document.querySelectorAll(".palmares-group li")].find((item) => (
  item.querySelector("strong")?.textContent.trim() === "Temporada 2026/27"
  && item.textContent.includes("Campiones de la Lliga Catalana")
));
if (seniorCatalanLeagueTitle && !seniorCatalanLeagueTitle.textContent.includes("Sènior femení")) {
  seniorCatalanLeagueTitle.append(" · Sènior femení");
}

const siteHeader = document.querySelector(".site-header");
const updateHeader = () => siteHeader?.classList.toggle("is-scrolled", window.scrollY > 12);
window.addEventListener("scroll", updateHeader, { passive: true });
updateHeader();

const normalizeNewsTitle = (title) => title
  .replace(/[\p{Extended_Pictographic}\p{Regional_Indicator}\p{Emoji_Modifier}\uFE0F\u200D\u20E3]/gu, "")
  .replace(/\s+/g, " ")
  .trim()
  .toLocaleUpperCase("ca");

document.querySelectorAll(".news-card-title, .news-card h3").forEach((title) => {
  title.textContent = normalizeNewsTitle(title.textContent || "");
});

const featuredNewsCard = document.querySelector(".news-card-feature");
if (featuredNewsCard) {
  fetch("bigues-resultats/api.php", { cache: "no-store" })
    .then((response) => (response.ok ? response.json() : Promise.reject(new Error("api"))))
    .then((data) => {
      const latestNews = (data.news || []).reduce((latest, item) => (
        !latest || (Date.parse(item.created_at) || 0) > (Date.parse(latest.created_at) || 0) ? item : latest
      ), null);
      if (!latestNews) return;

      const image = featuredNewsCard.querySelector(".news-image img");
      const meta = featuredNewsCard.querySelector(".news-meta");
      const title = featuredNewsCard.querySelector(".news-copy h3");
      const excerpt = featuredNewsCard.querySelector(".news-copy > p:not(.news-meta)");
      if (latestNews.image && image) {
        image.src = latestNews.image;
        image.alt = normalizeNewsTitle(latestNews.title);
      }
      if (meta) {
        const date = new Intl.DateTimeFormat("ca", { day: "2-digit", month: "2-digit", year: "2-digit" }).format(new Date(latestNews.created_at));
        meta.textContent = `NOTÍCIES · ${date}`;
      }
      if (title) title.textContent = normalizeNewsTitle(latestNews.title);
      if (excerpt) excerpt.textContent = (latestNews.body || "").split(/\n{2,}/, 1)[0].trim();
    })
    .catch(() => {});
}

const filterButtons = document.querySelectorAll(".filter-button");
const filterEmpty = document.querySelector(".filter-empty");
filterButtons.forEach((button) => button.addEventListener("click", () => {
  const selectedFilter = button.dataset.filter;
  let visibleCount = 0;
  filterButtons.forEach((filterButton) => {
    const isSelected = filterButton === button;
    filterButton.classList.toggle("is-active", isSelected);
    filterButton.setAttribute("aria-pressed", String(isSelected));
  });
  document.querySelectorAll(".news-page-grid .news-card").forEach((card) => {
    const isVisible = selectedFilter === "all" || card.dataset.category === selectedFilter;
    card.hidden = !isVisible;
    if (isVisible) visibleCount += 1;
  });
  if (filterEmpty) filterEmpty.hidden = visibleCount > 0;
}));
filterButtons[0]?.click();

// Notícies i resultats publicats des de l'app bigues-resultats; el contingut estàtic es manté.
const newsGrid = document.querySelector(".news-page-grid");
if (newsGrid) {
  const formatDate = (iso) => new Intl.DateTimeFormat("ca", { day: "2-digit", month: "2-digit", year: "2-digit" }).format(new Date(iso));
  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const imageLink = (href, label) => {
    const link = el("a", "text-link");
    link.href = href;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.append(`${label} `, el("span", "", "↗"));
    return link;
  };

  const appendParagraphs = (parent, text) => {
    text.split(/\n{2,}/).forEach((paragraph) => {
      const node = el("p", "", paragraph.trim());
      node.style.whiteSpace = "pre-line";
      parent.append(node);
    });
  };

  const buildNews = (item) => {
    const card = el("details", "news-card news-card-page news-card-disclosure");
    card.dataset.category = "noticies";
    const summary = el("summary");
    if (item.image) {
      const image = el("img");
      image.src = item.image;
      image.alt = "";
      image.style.objectPosition = item.focus || "";
      summary.append(image);
    }
    summary.append(el("span", "news-card-title", normalizeNewsTitle(item.title)));
    const copy = el("div", "news-copy");
    copy.append(el("p", "news-meta", `NOTÍCIES · ${formatDate(item.created_at)}`));
    appendParagraphs(copy, item.body);
    if (item.image) copy.append(imageLink(item.image, "VEURE FOTO EN GRAN"));
    card.append(summary, copy);
    return card;
  };

  const buildResult = (item) => {
    const date = formatDate(item.created_at);
    const card = el("article", "news-card news-card-page");
    card.dataset.category = "resultats";
    const imageWrap = el("a", "news-image");
    imageWrap.href = item.image;
    imageWrap.target = "_blank";
    imageWrap.rel = "noopener noreferrer";
    imageWrap.setAttribute("aria-label", "Obre la imatge dels resultats en mida gran");
    const image = el("img");
    image.src = item.image;
    image.alt = item.title || `Resultats publicats el ${date}`;
    image.style.objectPosition = item.focus || "";
    imageWrap.append(image);
    const copy = el("div", "news-copy");
    copy.append(
      el("p", "news-meta", `RESULTATS · ${date}`),
      el("h2", "", item.title || `Resultats ${date}`),
    );
    appendParagraphs(copy, item.text || "Resultats dels partits de la setmana.");
    copy.append(imageLink(item.image, "OBRIR IMATGE EN GRAN"));
    card.append(imageWrap, copy);
    return card;
  };

  fetch("bigues-resultats/api.php", { cache: "no-store" })
    .then((response) => (response.ok ? response.json() : Promise.reject(new Error("api"))))
    .then((data) => {
      const cards = [
        ...(data.news || []).map(buildNews),
        ...(data.results || []).filter((item) => item.image).map(buildResult),
      ];
      if (!cards.length) return;
      newsGrid.prepend(...cards);
      document.querySelector(".filter-button.is-active")?.click();
    })
    .catch(() => {});
}

const contactForm = document.querySelector("#contact-form");
const formStatus = document.querySelector("#form-status");
contactForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  if (!contactForm.reportValidity()) return;

  const formData = new FormData(contactForm);
  const subject = `Contacte web: ${formData.get("assumpte")}`;
  const body = [
    `Nom: ${formData.get("nom")}`,
    `Cognoms: ${formData.get("cognoms")}`,
    `Email: ${formData.get("email")}`,
    `Telèfon: ${formData.get("telefon") || "No indicat"}`,
    "",
    "Missatge:",
    formData.get("missatge"),
  ].join("\n");

  if (formStatus) {
    formStatus.textContent = "S'obrirà el gestor de correu amb el missatge preparat. Revisa'l i prem Enviar.";
  }
  window.location.href = `mailto:secreteria@hoqueibiguesiriells.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
});

const liveClassifications = document.querySelector("#live-classifications");
const okLiveClassifications = document.querySelector("#ok-live-classifications");
if (liveClassifications || okLiveClassifications) {

  const createStandingsTable = (sourceTable, groupName) => {
    const group = document.createElement("section");
    group.className = "live-standing-group";
    const heading = document.createElement("h3");
    heading.textContent = groupName;
    group.append(heading);

    const wrapper = document.createElement("div");
    wrapper.className = "league-table-wrap";
    const table = document.createElement("table");
    table.className = "league-table";
    const caption = document.createElement("caption");
    caption.textContent = `Classificació ${groupName}`;
    table.append(caption);

    const header = document.createElement("thead");
    const headerRow = document.createElement("tr");
    ["POSICIÓ", "EQUIP", "PUNTS"].forEach((label, index) => {
      const cell = document.createElement("th");
      cell.scope = "col";
      cell.textContent = label;
      if (index === 1) cell.className = "team-name";
      headerRow.append(cell);
    });
    header.append(headerRow);
    table.append(header);

    const body = document.createElement("tbody");
    sourceTable.querySelectorAll("tbody tr").forEach((sourceRow) => {
      const position = sourceRow.querySelector("td:first-child")?.textContent.trim();
      const teamName = sourceRow.querySelector(".no_mobile")?.textContent.trim()
        || sourceRow.querySelector(".mobile")?.textContent.trim();
      const points = sourceRow.querySelector(".stats_table_special")?.textContent.trim();
      if (!position || !teamName || points === undefined) return;

      const row = document.createElement("tr");
      if (teamName.toUpperCase().includes("BIGUES I RIELLS")) row.className = "club-standings-row";
      const rankCell = document.createElement("td");
      rankCell.textContent = position;
      const teamCell = document.createElement("th");
      teamCell.scope = "row";
      teamCell.className = "team-name";
      teamCell.textContent = teamName;
      const pointsCell = document.createElement("td");
      pointsCell.textContent = points;
      row.append(rankCell, teamCell, pointsCell);
      body.append(row);
    });

    table.append(body);
    wrapper.append(table);
    group.append(wrapper);
    return group;
  };

  const loadLiveClassifications = async ({ container, status, updated, endpoint, sourceName, sourceUrl, groupFilter, groupLabel }) => {
    container.setAttribute("aria-busy", "true");
    try {
      const response = await fetch(endpoint, { cache: "no-store" });
      if (!response.ok) throw new Error(`${sourceName} ha respost ${response.status}`);

      const source = new DOMParser().parseFromString(await response.text(), "text/html");
      const sourceTables = [...source.querySelectorAll("table.tabla_standard")];
      const headings = [...source.querySelectorAll(".div_titulo_fase_idc")];
      const standingsGroups = sourceTables
        .map((table, index) => ({ table, sourceName: headings[index]?.textContent.trim() || "" }))
        .filter(({ table, sourceName: name }) => table && groupFilter(name, table))
        .map(({ table, sourceName: name }) => ({ table, name: groupLabel || name }));
      if (!standingsGroups.length) throw new Error("La resposta no conté cap taula de classificació");

      const groupList = document.createElement("div");
      groupList.className = "live-standing-groups";
      standingsGroups.forEach(({ table, name }) => {
        groupList.append(createStandingsTable(table, name));
      });
      container.replaceChildren(groupList);
      if (status) status.textContent = "DADES OFICIALS · EN DIRECTE";
      if (updated) {
        const time = new Intl.DateTimeFormat("ca", { hour: "2-digit", minute: "2-digit" }).format(new Date());
        updated.textContent = `Actualitzat a les ${time} · refresc cada 5 minuts.`;
      }
    } catch (error) {
      if (status) status.textContent = "CLASSIFICACIÓ NO DISPONIBLE";
      const message = document.createElement("p");
      message.className = "standings-loading";
      message.textContent = `No s'ha pogut carregar la classificació de ${sourceName}. Es tornarà a provar d'aquí a 5 minuts.`;
      const link = document.createElement("a");
      link.href = sourceUrl;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.textContent = `Consulta ${sourceName}`;
      container.replaceChildren(message, link);
    } finally {
      container.setAttribute("aria-busy", "false");
    }
  };

  const refreshEveryFiveMinutes = (options) => {
    const refresh = () => loadLiveClassifications(options);
    refresh();
    window.setInterval(() => {
      if (!document.hidden) refresh();
    }, 5 * 60 * 1000);
  };

  if (liveClassifications) {
    refreshEveryFiveMinutes({
      container: liveClassifications,
      status: document.querySelector("#standings-status"),
      updated: document.querySelector("#standings-updated"),
      endpoint: "fecapa-classificacio.php",
      sourceName: "FECAPA",
      sourceUrl: "https://www.hoqueipatins.fecapa.cat/",
      groupFilter: (name) => /GRUP A$/i.test(name),
    });
  }

  if (okLiveClassifications) {
    refreshEveryFiveMinutes({
      container: okLiveClassifications,
      status: document.querySelector("#ok-standings-status"),
      updated: document.querySelector("#ok-standings-updated"),
      endpoint: "fecapa-classificacio.php?competition=ok",
      sourceName: "FEP",
      sourceUrl: "https://www.hockeypatines.fep.es/league/3569",
      groupFilter: () => true,
      groupLabel: "OK LLIGA IBERDROLA",
    });
  }
}

if ("IntersectionObserver" in window) {
  document.documentElement.classList.add("reveal-ready");
  const revealObserver = new IntersectionObserver((entries, observer) => entries.forEach((entry) => {
    if (entry.isIntersecting) { entry.target.classList.add("is-visible"); observer.unobserve(entry.target); }
  }), { threshold: 0.12 });
  document.querySelectorAll(".section-kicker, .stat-item, .team-feature, .news-card, .roster-card, .sponsor-tier, .fact-tile, .values-grid article").forEach((element) => {
    element.setAttribute("data-reveal", ""); revealObserver.observe(element);
  });
}