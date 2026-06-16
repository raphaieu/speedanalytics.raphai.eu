import { config } from './config.js';
import { logger } from './logger.js';

const HORAS_BUTTON_LABELS = {
  Horas48: '48 Horas',
  Horas24: '24 Horas',
  Horas12: '12 Horas',
  Horas6: '6 Horas',
  Horas3: '3 Horas',
};

function horasButtonLabel(horas) {
  return HORAS_BUTTON_LABELS[horas] ?? horas.replace(/^Horas/, '') + ' Horas';
}

export async function applySpeedwayView(page) {
  await page.waitForSelector('select.form-select', { timeout: 60_000 });

  const filtro = config.speedwayFiltroExibicao;
  const filtroSelect = page.locator('select').filter({
    has: page.locator(`option[value="${filtro}"]`),
  });

  await filtroSelect.first().selectOption(filtro);
  logger.info('Filtro de exibição aplicado', { filtro_exibicao: filtro });

  const horasLabel = horasButtonLabel(config.speedwayHoras);
  const horasDropdown = page.locator('.dropdown').filter({ hasText: 'Horas' });
  const horasToggle = horasDropdown.locator('button, .dropdown-toggle').first();
  await horasToggle.click();
  await page.getByRole('button', { name: horasLabel }).click();
  logger.info('Janela de horas aplicada', { horas: config.speedwayHoras, label: horasLabel });

  const futuroSwitch = page.locator('#switchFuturo');
  if (await futuroSwitch.count()) {
    const checked = await futuroSwitch.isChecked();
    if (config.speedwayFuturo && !checked) {
      await futuroSwitch.check();
    }
    if (!config.speedwayFuturo && checked) {
      await futuroSwitch.uncheck();
    }
    logger.info('Toggle futuro aplicado', { futuro: config.speedwayFuturo });
  }

  await page.waitForTimeout(1_500);
}
