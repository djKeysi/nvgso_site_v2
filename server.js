import express from 'express';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = Number(process.env.PORT) || 3000;
const HOST = '0.0.0.0';
const themeDir = path.join(__dirname, 'deploy', 'theme-src');
const siteDir = path.join(__dirname, 'site');
const bootstrapPath = path.join(__dirname, 'wp-preview-bootstrap.php');

function translit(text) {
  const map = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo',
    ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm',
    н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u',
    ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '',
    ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
  };
  const s = String(text || '')
    .toLowerCase()
    .split('')
    .map((ch) => (Object.prototype.hasOwnProperty.call(map, ch) ? map[ch] : ch))
    .join('')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return s || 'item';
}

function buildSeedData() {
  const sandbox = { window: {} };
  sandbox.window = sandbox;
  vm.createContext(sandbox);

  const legalPath = path.join(siteDir, 'js', 'legal.js');
  const contentPath = path.join(siteDir, 'js', 'content.js');
  if (fs.existsSync(legalPath)) {
    vm.runInContext(fs.readFileSync(legalPath, 'utf8'), sandbox, { filename: 'legal.js' });
  }
  if (fs.existsSync(contentPath)) {
    vm.runInContext(fs.readFileSync(contentPath, 'utf8'), sandbox, { filename: 'content.js' });
  }

  const content = sandbox.VGS_CONTENT || {};
  const posts = [];
  const terms = [];
  let nextId = 100;
  let nextTermId = 10;

  const termMap = new Map();
  const ensureTerm = (taxonomy, name, customSlug) => {
    const slug = customSlug || translit(name);
    const key = `${taxonomy}:${slug}`;
    if (termMap.has(key)) return termMap.get(key);
    const term = {
      term_id: nextTermId++,
      name,
      slug,
      taxonomy,
      description: '',
      count: 0,
    };
    termMap.set(key, term);
    terms.push(term);
    return term;
  };

  // 1. Image attachments (photo-01 .. photo-16)
  const photoAttIds = new Map();
  for (let i = 1; i <= 16; i++) {
    const num = String(i).padStart(2, '0');
    const key = `photo-${num}`;
    const id = nextId++;
    photoAttIds.set(key, id);
    posts.push({
      ID: id,
      post_type: 'attachment',
      post_status: 'inherit',
      post_title: key,
      post_name: key,
      guid: `/uploads/img/${key}-1400.webp`,
      meta: {
        _nvgso_image_key: key,
        _nvgso_thumb_url: `/uploads/img/${key}-700.webp`,
      },
      terms: {},
    });
  }

  // 2. Pages (including legal pages from site/js/legal.js)
  const pageList = [
    { slug: 'about', title: 'Об отряде', lead: content.settings?.about_lead || '' },
    { slug: 'structure', title: 'Структура', lead: content.settings?.structure_lead || '' },
    { slug: 'activity', title: 'Деятельность', lead: content.settings?.activity_lead || '' },
    { slug: 'contacts', title: 'Контакты', lead: content.settings?.contacts_lead || '' },
    { slug: 'documents', title: 'Документы', lead: content.settings?.documents_lead || '' },
    { slug: 'gallery', title: 'Фотогалерея', lead: content.settings?.gallery_lead || '' },
    { slug: 'veterans', title: 'Ветеранам и память', lead: content.settings?.veterans_lead || '' },
    { slug: 'news', title: 'Новости', lead: content.settings?.news_lead || '' },
  ];

  for (const p of content.pages || []) {
    if (!p.slug) continue;
    const existing = pageList.find((x) => x.slug === p.slug);
    const cleanedHtml = String(p.bodyHtml || '')
      .replace(/href="privacy-policy\.html"/g, 'href="/privacy-policy/"')
      .replace(/href="company-policy\.html"/g, 'href="/company-policy/"')
      .replace(/href="cookie-policy\.html"/g, 'href="/cookie-policy/"')
      .replace(/href="contacts\.html"/g, 'href="/contacts/"')
      .replace(/href="documents\.html"/g, 'href="/documents/"');

    if (existing) {
      existing.bodyHtml = cleanedHtml;
      existing.lead = p.lead || existing.lead;
      existing.title = p.title || existing.title;
    } else {
      pageList.push({
        slug: p.slug,
        title: p.title || p.slug,
        lead: p.lead || '',
        bodyHtml: cleanedHtml,
      });
    }
  }

  let newsPageId = 0;
  for (const p of pageList) {
    const id = nextId++;
    if (p.slug === 'news') newsPageId = id;
    posts.push({
      ID: id,
      post_type: 'page',
      post_status: 'publish',
      post_title: p.title,
      post_name: p.slug,
      post_excerpt: p.lead || '',
      post_content: p.bodyHtml || '',
      post_date: '2026-11-09 10:00:00',
      menu_order: 0,
      meta: {},
      terms: {},
    });
  }

  // 3. News posts
  for (const item of content.news || []) {
    if (item.published === 0) continue;
    const cat = ensureTerm('category', item.category || 'События');
    cat.count++;
    const thumbId = photoAttIds.get(item.image) || 0;
    const bodyHtml = String(item.body || '')
      .split(/\n{2,}/)
      .filter(Boolean)
      .map((para) => `<p>${para}</p>`)
      .join('\n');

    posts.push({
      ID: nextId++,
      post_type: 'post',
      post_status: 'publish',
      post_title: item.title,
      post_name: translit(item.title),
      post_excerpt: item.excerpt || '',
      post_content: bodyHtml,
      post_date: `${item.date || '2026-10-01'} 12:00:00`,
      menu_order: 0,
      meta: thumbId ? { _thumbnail_id: thumbId } : {},
      terms: { category: [cat.term_id] },
    });
  }

  // 4. Documents (PDF attachments + document CPT)
  (content.documents || []).forEach((doc, idx) => {
    if (doc.published === 0) return;
    const docUrl = String(doc.url || '').replace(/^assets\//, '/uploads/');
    const fileId = nextId++;
    posts.push({
      ID: fileId,
      post_type: 'attachment',
      post_status: 'inherit',
      post_title: doc.title,
      post_name: translit(doc.title) + '-file',
      guid: docUrl,
      meta: {},
      terms: {},
    });

    const cat = ensureTerm('doc_category', doc.category || 'Документ');
    cat.count++;

    posts.push({
      ID: nextId++,
      post_type: 'document',
      post_status: 'publish',
      post_title: doc.title,
      post_name: translit(doc.title),
      post_excerpt: doc.note || '',
      post_content: doc.note || '',
      post_date: `${doc.date || '2026-01-01'} 10:00:00`,
      menu_order: idx,
      meta: {
        _nvgso_file_id: fileId,
        _nvgso_pages: doc.pages || 1,
        _nvgso_note: doc.note || '',
        _nvgso_doc_date: doc.date || '',
      },
      terms: { doc_category: [cat.term_id] },
    });
  });

  // 5. Divisions
  (content.divisions || []).forEach((div, idx) => {
    if (div.published === 0) return;
    posts.push({
      ID: nextId++,
      post_type: 'division',
      post_status: 'publish',
      post_title: div.name,
      post_name: translit(div.name),
      post_excerpt: div.description || '',
      post_content: div.description || '',
      post_date: '2026-01-01 10:00:00',
      menu_order: idx,
      meta: {
        _nvgso_type: div.type || 'Взвод',
        _nvgso_city: div.city || '',
        _nvgso_address: div.address || '',
        _nvgso_phone: div.phone || '',
        _nvgso_units: div.units || '',
      },
      terms: {},
    });
  });

  // 6. Command / staff_contact
  (content.command || []).forEach((person, idx) => {
    if (person.published === 0) return;
    posts.push({
      ID: nextId++,
      post_type: 'staff_contact',
      post_status: 'publish',
      post_title: person.position,
      post_name: translit(person.position) + '-' + idx,
      post_excerpt: '',
      post_content: '',
      post_date: '2026-01-01 10:00:00',
      menu_order: idx,
      meta: {
        _nvgso_name: person.name || '',
        _nvgso_rank: person.rank || '',
        _nvgso_phone: person.phone || '',
        _nvgso_reception: person.reception || '',
      },
      terms: {},
    });
  });

  // 7. Gallery albums & items
  const albumById = new Map();
  for (const alb of content.albums || []) {
    const t = ensureTerm('album', alb.title);
    t.description = alb.description || '';
    albumById.set(alb.id, t);
  }
  for (const g of content.gallery || []) {
    if (g.published === 0) continue;
    const albTerm = albumById.get(g.album);
    if (albTerm) albTerm.count++;
    const imgId = photoAttIds.get(g.image || g.poster) || 0;
    posts.push({
      ID: nextId++,
      post_type: 'gallery_item',
      post_status: 'publish',
      post_title: g.title,
      post_name: translit(g.title) + '-' + g.id,
      post_excerpt: g.caption || '',
      post_content: '',
      post_date: `${g.date || '2024-01-01'} 10:00:00`,
      menu_order: 0,
      meta: {
        _nvgso_image_id: imgId,
        _nvgso_caption: g.caption || '',
        _nvgso_date: g.date || '',
        _nvgso_type: g.type || 'photo',
        _nvgso_video_url: g.videoUrl || g.externalUrl || '',
      },
      terms: albTerm ? { album: [albTerm.term_id] } : {},
    });
  }

  return {
    posts,
    terms,
    options: {
      page_for_posts: newsPageId,
      nvgso_phones: content.phones || [],
      nvgso_milestones: content.milestones || [],
      nvgso_zone: content.zone || [],
    },
  };
}

const seedData = buildSeedData();

let phpInstance = null;
let phpQueue = Promise.resolve();

async function initPhp() {
  const runtime = await loadNodeRuntime('8.3', {
    emscriptenOptions: { processId: 1 },
  });
  const php = new PHP(runtime);

  php.mkdir('/theme');
  await php.mount('/theme', createNodeFsMountHandler(themeDir));

  php.mkdir('/runtime');
  php.mkdir('/runtime/wp-stub');
  php.mkdir('/runtime/wp-stub/wp-admin');
  php.mkdir('/runtime/wp-stub/wp-admin/includes');
  php.writeFile('/runtime/wp-stub/wp-admin/includes/upgrade.php', '<?php // stub');
  php.writeFile('/runtime/seed.json', JSON.stringify(seedData));
  php.writeFile('/runtime/bootstrap.php', fs.readFileSync(bootstrapPath, 'utf8'));

  phpInstance = php;
}

function runPhpRequest(req) {
  const task = phpQueue.then(async () => {
    if (!phpInstance) {
      await initPhp();
    }
    // Reload bootstrap.php from disk if updated during development
    phpInstance.writeFile('/runtime/bootstrap.php', fs.readFileSync(bootstrapPath, 'utf8'));

    const body =
      req.method === 'POST' && req.body && typeof req.body === 'object'
        ? req.body
        : undefined;

    const response = await phpInstance.run({
      scriptPath: '/runtime/bootstrap.php',
      relativeUri: req.originalUrl,
      method: req.method,
      headers: {
        host: req.headers.host || `localhost:${PORT}`,
        'user-agent': req.headers['user-agent'] || 'NVGSO-Preview',
        referer: req.headers.referer || '',
        cookie: req.headers.cookie || '',
      },
      body,
    });

    return response;
  });

  phpQueue = task.catch(() => {});
  return task;
}

const app = express();
app.disable('x-powered-by');
app.use(express.urlencoded({ extended: true }));
app.use(express.json());

// Live theme static assets (CSS, JS, images directly from deploy/theme-src/assets)
app.use(
  '/wp-content/themes/nvgso/assets',
  express.static(path.join(themeDir, 'assets'), { etag: false, maxAge: 0 })
);

// Media library uploads (photos & PDFs from site/assets)
app.use('/uploads', express.static(path.join(siteDir, 'assets')));

// Favicon shortcut
app.get('/favicon.ico', (req, res) => {
  res.sendFile(path.join(themeDir, 'assets', 'img', 'favicon.ico'));
});

// Original static prototype remains accessible under /prototype/
app.use(
  '/prototype',
  express.static(siteDir, {
    extensions: ['html'],
    index: 'index.html',
    setHeaders(res, filePath) {
      if (filePath.endsWith('.webmanifest')) {
        res.setHeader('Content-Type', 'application/manifest+json; charset=utf-8');
      }
    },
  })
);

// Redirect /wp-admin/ login button to the prototype admin panel
app.get('/wp-admin', (req, res) => res.redirect('/prototype/admin.html'));
app.get('/wp-admin/', (req, res) => res.redirect('/prototype/admin.html'));

// All WordPress theme routes rendered live via PHP 8.3 WASM
app.use(async (req, res) => {
  try {
    const phpRes = await runPhpRequest(req);
    const status = phpRes.httpStatusCode || 200;

    if (phpRes.headers) {
      for (const [key, values] of Object.entries(phpRes.headers)) {
        const lower = key.toLowerCase();
        if (lower === 'x-powered-by' || lower === 'transfer-encoding') continue;
        if (Array.isArray(values) && values.length > 0) {
          res.setHeader(key, values.length === 1 ? values[0] : values);
        }
      }
    }

    if (!res.getHeader('Content-Type')) {
      res.setHeader('Content-Type', 'text/html; charset=utf-8');
    }

    if (phpRes.errors && phpRes.errors.trim()) {
      console.error('[PHP stderr]', phpRes.errors.trim());
    }

    res.status(status).send(phpRes.text);
  } catch (err) {
    console.error('[WP Preview Error]', err);
    res.status(500).send(`<pre>WP Preview Error:\n${String(err?.stack || err)}</pre>`);
  }
});

initPhp()
  .then(() => {
    app.listen(PORT, HOST, () => {
      console.log(`NVGSO WordPress theme preview listening at http://${HOST}:${PORT}`);
    });
  })
  .catch((err) => {
    console.error('Failed to initialize PHP-WASM runtime:', err);
    process.exit(1);
  });
