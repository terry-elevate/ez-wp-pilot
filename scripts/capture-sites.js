const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SITES = {
  "headers-simple": [
    "https://www.brandywinequarry.com/",
    "https://www.gsmindustrial.com/",
    "https://tlacc.org/",
    "https://www.lombardosrestaurant.com/"
  ],
  "headers-multilevel": [
    "https://www.envairtech.com/",
    "https://giftcpas.com/",
    "https://www.glickfire.com/",
    "https://pearlsolves.com/",
    "https://www.boardmaninc.com/"
  ],
  "headers-background": [
    "https://www.westmorelandinjurylawyers.com/",
    "https://www.dsdi1776.com/",
    "https://www.s-k.com/"
  ],
  "footers-basic": [
    "https://tlacc.org/",
    "https://sellmyhouseforcashpa.com/",
    "https://deliverthoughtfulimpact.com/",
    "https://brandywinevalleyfab.com/",
    "https://www.dutchcountrycatering.com/"
  ],
  "footers-navigation": [
    "https://www.cfpsprinkler.com/",
    "https://www.sensenigsfeed.com/",
    "https://www.nashvillecomputer.com/",
    "https://www.lancasterkitchens.net/",
    "https://www.westmorelandinjurylawyers.com/",
    "https://giftcpas.com/",
    "https://www.glickfire.com/"
  ]
};

const OUT_DIR = path.join(__dirname, '..', 'screenshots', 'ez-portfolio');

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  const results = [];

  for (const [category, urls] of Object.entries(SITES)) {
    const catDir = path.join(OUT_DIR, category);
    fs.mkdirSync(catDir, { recursive: true });

    for (const url of urls) {
      const domain = new URL(url).hostname.replace('www.', '');
      const filename = `${domain}.png`;
      const filepath = path.join(catDir, filename);

      try {
        const page = await context.newPage();
        await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 });
        await page.waitForTimeout(1500);

        // Full page screenshot
        await page.screenshot({ path: filepath, fullPage: false });

        // Footer screenshot - scroll to bottom
        const footerPath = path.join(catDir, `${domain}-footer.png`);
        if (category.startsWith('footers')) {
          await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
          await page.waitForTimeout(800);
          await page.screenshot({ path: footerPath, fullPage: false });
        }

        await page.close();
        results.push({ category, domain, status: 'ok', filepath: filename });
        console.log(`  ✓ ${domain} (${category})`);
      } catch (e) {
        results.push({ category, domain, status: 'fail', error: e.message.slice(0, 80) });
        console.log(`  ✗ ${domain}: ${e.message.slice(0, 60)}`);
      }
    }
  }

  await browser.close();

  // Write manifest
  fs.writeFileSync(path.join(OUT_DIR, 'manifest.json'), JSON.stringify(results, null, 2));
  console.log(`\nDone: ${results.filter(r => r.status === 'ok').length}/${results.length} captured`);
}

main().catch(console.error);
