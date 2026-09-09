const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');

const SERVER_HOST = '54.169.31.68';
const BASE_URL = `http://${SERVER_HOST}`;
const OUTPUT_DIR = path.join(__dirname, '..', 'public');

function fetchBuffer(url) {
    return new Promise((resolve, reject) => {
        const client = url.startsWith('https') ? https : http;
        const req = client.get(url, { headers: { 'User-Agent': 'Mozilla/5.0' } }, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                let redirectUrl = res.headers.location;
                if (redirectUrl.startsWith('/')) {
                    redirectUrl = BASE_URL + redirectUrl;
                }
                return fetchBuffer(redirectUrl).then(resolve).catch(reject);
            }
            if (res.statusCode !== 200) {
                return reject(new Error(`HTTP ${res.statusCode}`));
            }
            const chunks = [];
            res.on('data', chunk => chunks.push(chunk));
            res.on('end', () => resolve(Buffer.concat(chunks)));
        });
        req.setTimeout(30000, () => {
            req.destroy();
            reject(new Error('Timeout'));
        });
        req.on('error', reject);
    });
}

async function fetchText(url) {
    const buf = await fetchBuffer(url);
    return buf.toString('utf-8');
}

const downloadedAssets = new Set();
const ASSET_EXTENSIONS = new Set([
    '.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp',
    '.woff', '.woff2', '.ttf', '.eot', '.otf', '.ico', '.json', '.pdf'
]);

async function downloadAsset(rawAssetUrl) {
    let cleanUrl = rawAssetUrl.split('?')[0].split('#')[0];
    const ext = path.extname(cleanUrl).toLowerCase();
    if (!ASSET_EXTENSIONS.has(ext)) {
        return;
    }

    if (downloadedAssets.has(cleanUrl)) return;
    downloadedAssets.add(cleanUrl);

    let relativePath = cleanUrl;
    if (relativePath.startsWith('http')) {
        try {
            relativePath = new URL(cleanUrl).pathname;
        } catch (e) {
            return;
        }
    }

    const targetFilePath = path.join(OUTPUT_DIR, relativePath);
    if (fs.existsSync(targetFilePath)) {
        return; // Already downloaded!
    }

    const targetDir = path.dirname(targetFilePath);
    if (!fs.existsSync(targetDir)) {
        fs.mkdirSync(targetDir, { recursive: true });
    }

    const fullUrl = cleanUrl.startsWith('http') ? cleanUrl : `${BASE_URL}${cleanUrl.startsWith('/') ? '' : '/'}${cleanUrl}`;
    try {
        const buffer = await fetchBuffer(fullUrl);
        fs.writeFileSync(targetFilePath, buffer);
        console.log(`  ✓ Asset: ${relativePath}`);
    } catch (err) {
        console.warn(`  ✗ Failed asset: ${relativePath} (${err.message})`);
    }
}

async function main() {
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }

    // Known full list of all 29 pages from WordPress
    const pageList = [
        '/',
        '/courses/',
        '/courses/life-skills/',
        '/courses/expanded-course/',
        '/courses/create-listing/',
        '/courses/create-listing/success/',
        '/jobs/',
        '/people/',
        '/people/join-us/',
        '/people/projects/',
        '/how-we-work/',
        '/how-we-work/assessments/',
        '/how-we-work/conduct/',
        '/how-we-work/fees/',
        '/how-we-work/internship/',
        '/how-we-work/feedback/',
        '/how-we-work/feedback-form/',
        '/blog/',
        '/blog/upcoming-events/',
        '/blog/announcements/',
        '/blog/highlights/',
        '/blog/highlights/reflections/',
        '/blog/highlights/student-projects/',
        '/blog/highlights/student-projects-2/',
        '/blog/highlights/community-service/',
        '/blog/highlights/learning-journeys/',
        '/blog/highlights/expanded/',
        '/contact/',
        '/contact/find-us/'
    ];

    console.log(`Exporting ${pageList.length} pages...`);

    const assetRegex = /(?:src|href|action|poster)=["']([^"']+)["']/gi;
    const styleBgRegex = /url\(["']?([^"')]+)['"]?\)/gi;

    for (const pagePath of pageList) {
        const pageUrl = `${BASE_URL}${pagePath}`;
        try {
            console.log(`Downloading: ${pagePath}`);
            let html = await fetchText(pageUrl);

            // Discover and download assets
            let match;
            while ((match = assetRegex.exec(html)) !== null) {
                const assetUrl = match[1];
                if (assetUrl.includes(SERVER_HOST) || assetUrl.startsWith('/wp-content/') || assetUrl.startsWith('/wp-includes/') || assetUrl.startsWith('/assets/')) {
                    await downloadAsset(assetUrl);
                }
            }

            while ((match = styleBgRegex.exec(html)) !== null) {
                const assetUrl = match[1];
                if (assetUrl.includes(SERVER_HOST) || assetUrl.startsWith('/wp-content/') || assetUrl.startsWith('/wp-includes/') || assetUrl.startsWith('/assets/')) {
                    await downloadAsset(assetUrl);
                }
            }

            // Convert server host references to root-relative
            html = html.split(`http://${SERVER_HOST}`).join('');
            html = html.split(`https://${SERVER_HOST}`).join('');

            // Inject Vercel Web Analytics if not already present
            if (!html.includes('/_vercel/insights/script.js')) {
                const analyticsScript = `  <script>
    window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
  </script>
  <script defer src="/_vercel/insights/script.js"></script>
</head>`;
                html = html.replace('</head>', analyticsScript);
            }

            // Write HTML file to disk
            let targetFile;
            if (pagePath === '/' || pagePath === '') {
                targetFile = path.join(OUTPUT_DIR, 'index.html');
            } else {
                const subDir = path.join(OUTPUT_DIR, pagePath.replace(/^\//, '').replace(/\/$/, ''));
                if (!fs.existsSync(subDir)) {
                    fs.mkdirSync(subDir, { recursive: true });
                }
                targetFile = path.join(subDir, 'index.html');
            }

            fs.writeFileSync(targetFile, html, 'utf-8');
            console.log(`  ✓ Saved: ${pagePath} -> index.html`);
        } catch (err) {
            console.warn(`  ✗ Failed page: ${pagePath} (${err.message})`);
        }
    }

    // Also copy local assets directory if any extra local files exist
    const localAssetsDir = path.join(__dirname, '..', 'assets');
    const destAssetsDir = path.join(OUTPUT_DIR, 'assets');
    if (fs.existsSync(localAssetsDir) && !fs.existsSync(destAssetsDir)) {
        fs.cpSync(localAssetsDir, destAssetsDir, { recursive: true });
    }

    console.log(`\n🎉 Mirroring complete! All pages and assets exported.`);
}

main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
