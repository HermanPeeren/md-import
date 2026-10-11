/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

/**
 * Drives the import: asks the server for one batch at a time and follows the
 * `next` step it answers with, until there is none.
 *
 * Batches keep every request well inside a site's max_execution_time; the
 * server decides how big a batch is and which step follows, so this file only
 * has to loop and report.
 */
const root = document.getElementById('mdimport');
const button = document.getElementById('mdimport-start');

const STEPS = ['download', 'images', 'categories', 'articles', 'content', 'menu', 'cleanup'];

const log = (text, kind = 'info') => {
  const item = document.createElement('li');
  item.className = `mdimport-log-${kind}`;
  item.textContent = text;
  document.getElementById('mdimport-log').append(item);

  return item;
};

const progress = (step, offset, total) => {
  const index = STEPS.indexOf(step);
  const within = total > 0 ? offset / total : 1;
  const percent = Math.round(((index + within) / STEPS.length) * 100);
  const bar = document.getElementById('mdimport-progress');

  bar.style.width = `${percent}%`;
  bar.textContent = `${percent}%`;
  bar.parentElement.setAttribute('aria-valuenow', String(percent));
};

const request = async (step, offset) => {
  const body = new FormData();
  body.append('step', step);
  body.append('offset', String(offset));
  body.append(root.dataset.token, '1');

  const response = await fetch(root.dataset.endpoint, { method: 'POST', body, credentials: 'same-origin' });
  let json;

  try {
    json = await response.json();
  } catch (e) {
    throw new Error(`${response.status} ${response.statusText}`);
  }

  if (!json.success) {
    throw new Error(json.message || `${response.status} ${response.statusText}`);
  }

  return json.data;
};

const run = async () => {
  button.disabled = true;
  document.getElementById('mdimport-progress-card').classList.remove('d-none');
  log(Joomla.Text._('COM_MDIMPORT_IMPORT_RUNNING'));

  let step = 'download';
  let offset = 0;
  let line = null;

  try {
    while (step) {
      if (offset === 0) {
        line = log(Joomla.Text._(`COM_MDIMPORT_STEP_${step.toUpperCase()}`));
      }

      // Sequential on purpose: each batch depends on the one before it.
      // eslint-disable-next-line no-await-in-loop
      const result = await request(step, offset);

      line.textContent = result.message;
      progress(result.step, result.offset, result.total);

      offset = result.done ? 0 : result.offset;
      step = result.next;
    }

    progress('cleanup', 1, 1);
    log(Joomla.Text._('COM_MDIMPORT_IMPORT_FINISHED'), 'success');
    root.dataset.finished = '1';
  } catch (error) {
    log(`${Joomla.Text._('COM_MDIMPORT_IMPORT_FAILED')} ${error.message}`, 'error');
    root.dataset.failed = error.message;
    button.disabled = false;
  }
};

if (root && button) {
  button.addEventListener('click', run);
}
