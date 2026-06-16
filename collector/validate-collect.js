import { config, isCaptureTargetUrl } from './lib/config.js';
import { launchBrowser } from './lib/browser.js';
import { attachSpeedwayInterceptor } from './lib/intercept.js';
import { applySpeedwayView } from './lib/speedway-ui.js';

const state = { payloadCount: 0, lastPayloadAt: null, filtersReady: false };

const { browser, page } = await launchBrowser({ headless: true });
attachSpeedwayInterceptor(page, state);

await page.goto(config.bbtipsSpeedwayUrl, { waitUntil: 'networkidle', timeout: 120_000 });

state.filtersReady = true;

const initialPayloadPromise = page.waitForResponse(
  (response) => isCaptureTargetUrl(response.url()) && response.status() === 200,
  { timeout: 90_000 },
);

await applySpeedwayView(page);
await initialPayloadPromise;
await page.waitForTimeout(5_000);

console.log(JSON.stringify({ payload_count: state.payloadCount, last_payload_at: state.lastPayloadAt }, null, 2));

await browser.close();
process.exit(state.payloadCount > 0 ? 0 : 1);
