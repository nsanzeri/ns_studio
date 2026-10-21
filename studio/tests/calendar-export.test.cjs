// Run with: node studio/tests/calendar-export.test.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../tools/bandsintown.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1]
  .replace(/<\?= json_encode\(\$userTimezone\) \?>/, '"America/Chicago"')
  .replace(/<\?= \$isProUser \? 'true' : 'false' \?>/, 'true')
  .replace(/<\?= json_encode\(base_url\('[^']+'\)\) \?>/, '"/usage"')
  .replace(/<\?= e\(base_url\('[^']+'\)\) \?>/, '/events');

const elements = {};
for (const id of ['artistName', 'startDate', 'endDate', 'songkickArtistId', 'songkickFields',
  'songkickNote', 'previewTitle', 'previewTable', 'errorBox', 'thead', 'tbody']) {
  elements[id] = { value: '', style: {}, classList: { toggle() {} } };
}
Object.assign(elements.artistName, { value: 'Test Artist' });
Object.assign(elements.startDate, { value: '2026-11-01' });
Object.assign(elements.endDate, { value: '2026-11-30' });
Object.assign(elements.songkickArtistId, { value: '123456' });
let format = 'bandsintown';
let events = [];
let pending;
const context = vm.createContext({
  Intl, Date, console,
  document: {
    getElementById: id => elements[id],
    querySelector: selector => {
      if (selector.includes('export_format')) return { value: format };
      if (selector.includes('calendar_id')) return { value: '1' };
      if (selector.endsWith('thead')) return elements.thead;
      if (selector.endsWith('tbody')) return elements.tbody;
    },
    addEventListener() {}
  },
  fetch: async url => {
    if (url === '/usage') return {};
    if (pending) return pending;
    return { ok: true, json: async () => ({ success: true, events: [...events] }) };
  }
});
vm.runInContext(script, context);
const read = expression => vm.runInContext(expression, context);
const event = {
  summary: 'Live "music", tonight',
  location: 'Music Hall, 123 Main St, Chicago, IL 60601, US',
  start: '2026-11-10T19:30:00-06:00',
  description: 'First line\nSecond & <third>'
};

(async () => {
  events = [event, { ...event, summary: 'Private party' }, { ...event, location: '' }];
  await context.generateBIT();
  assert.equal(read('LAST_BIT_RESULT_COUNT'), 1);
  assert.equal(read('FINAL_FILENAME'), 'bandsintown_export.csv');
  assert.ok(read('FINAL_CSV').startsWith('Artist Name,Venue*,Country*,Address,City*,Region*'));
  assert.ok(read('FINAL_CSV').includes('Test Artist,Music Hall,United States,123 Main St,Chicago,IL,60601,,2026-11-10,19:30'));

  format = 'songkick';
  context.resetExport();
  assert.equal(read('FINAL_CSV'), '');
  assert.equal(elements.songkickArtistId.required, true);
  await context.generateBIT();
  assert.equal(read('LAST_BIT_RESULT_COUNT'), 1);
  assert.equal(read('FINAL_FILENAME'), 'songkick_123456_export.csv');
  const csv = read('FINAL_CSV');
  assert.equal(csv.split('\n')[0], 'Venue*,City*,Country or State*,Address,Date*,Time*,Duration,Ticket Link,Ticket Type,Ticket Link 2,Ticket Type 2,On-Sale Date,On-Sale Time,Lineup,Event Name,Description,Publish Date,Publish Time,Publish Timezone');
  assert.ok(csv.includes('Music Hall,Chicago,IL,123 Main St,2026-11-10,19:30'));
  assert.ok(csv.includes('"Live ""music"", tonight"'));
  assert.ok(csv.includes('"First line\nSecond & <third>"'));
  assert.ok(elements.tbody.innerHTML.includes('Second &amp; &lt;third&gt;'));
  assert.equal(context.csvEscape('one\rtwo'), '"one\rtwo"');

  elements.songkickArtistId.value = '';
  await context.generateBIT();
  assert.equal(read('FINAL_CSV'), '');
  assert.match(elements.errorBox.textContent, /Artist ID/);
  elements.songkickArtistId.value = '123456';
  events = [];
  await context.generateBIT();
  assert.equal(read('FINAL_CSV'), '');
  assert.match(elements.errorBox.textContent, /No exportable/);

  let resolve;
  pending = new Promise(done => { resolve = done; });
  const run = context.generateBIT();
  format = 'bandsintown';
  context.resetExport();
  resolve({ ok: true, json: async () => ({ success: true, events: [event] }) });
  await run;
  assert.equal(read('FINAL_CSV'), '');
  assert.equal(elements.previewTitle.textContent, 'Bands In Town CSV');
  assert.equal(elements.songkickArtistId.disabled, true);
  console.log('Passed: both export formats, filtering, CSV escaping, validation, empty results, and format changes during generation.');
})().catch(error => { console.error(error); process.exitCode = 1; });
