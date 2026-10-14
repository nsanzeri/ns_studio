const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
// Render the actual PHP view with isolated fixtures; never touch the application database.
const php = `
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf_token() { return 'test-token'; }
function base_url($path) { return '/ns_studio/studio'.$path; }
function encore_page_head($title) { echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>'; }
function encore_page_foot() { echo '</body></html>'; }
$ready=$isProUser=true; $notice=$error=''; $upgradeUrl='';
$channels=['email'=>'Email','phone'=>'Phone'];
$contacts=[
 ['id'=>1,'name'=>"Bandito Barney's",'contact_name'=>'Nick','kind'=>'Country club','email'=>'nick@example.test','phone'=>'555','city'=>'Roselle','estimated_value'=>300,'fit_notes'=>'Original notes','updated_at'=>'2026-10-14 12:00:00'],
 ['id'=>2,'name'=>'Second Venue','contact_name'=>'Alex','kind'=>'Casino','email'=>'','phone'=>'','city'=>'','estimated_value'=>null,'fit_notes'=>'','updated_at'=>'2026-10-13 12:00:00']
];
$tasks=[];
foreach([-1,0,1] as $offset) $tasks[]=['id'=>$offset+2,'contact_id'=>1,'contact_name'=>"Bandito Barney's",'due_at'=>date('Y-m-d',strtotime("$offset days")),'title'=>'Call about the next booking'];
$interactions=[]; $historyByContact=[];
for($n=0;$n<20;$n++) {
 $item=['id'=>$n,'contact_id'=>1,'contact_name'=>"Bandito Barney's",'occurred_at'=>'2026-10-14 12:00:00','channel'=>'email','subject'=>'A long subject with "quotes" and <b>literal text</b> that must stay on one row','summary'=>'Full message '.$n,'next_step'=>'Follow up tomorrow'];
 $historyByContact[1][]=$item;
 if($n<12) $interactions[]=$item;
}
require $argv[1];
`;
const html = execFileSync('php',['-r',php,path.join(root,'dashboard.php')],{encoding:'utf8'});
const common = fs.readFileSync(path.join(root,'_common.php'),'utf8');
const css = common.match(/<style>([\s\S]*?)<\/style>/)[1];
(async () => {
 const browser = await chromium.launch({headless:true,channel:'chrome'});
 try {
  const page = await browser.newPage({viewport:{width:1280,height:900}});
  const errors=[];
  const usageEvents=[];
  await page.route('**/api/log_tool_usage.php', async route=>{
    usageEvents.push(route.request().postDataJSON());
    await route.fulfill({json:{ok:true}});
  });
  page.on('pageerror',error=>errors.push(error.message));
  await page.setContent(html);
  await page.addStyleTag({path:path.resolve(root,'../../assets/css/style.css')});
  await page.addStyleTag({content:css});
  await page.locator('#encore-script').evaluate(script=>{script.dataset.usageUrl='http://localhost:8080/ns_studio/studio/api/log_tool_usage.php';});
  await page.addScriptTag({path:path.join(root,'encore.js')});
  assert.equal(await page.locator('.encore-table th').count(),4);
  assert.equal(await page.locator('[data-edit]').count(),2);
  assert.equal(await page.locator('.encore-table tbody tr').first().getByText('Nick',{exact:true}).count(),1);
  await page.locator('[data-add]').click();
  assert.equal(await page.locator('#encore-contact-dialog').evaluate(d=>d.open),true);
  assert.equal(await page.locator('#encore-contact-form [name="name"]').inputValue(),'');
  await page.locator('#encore-contact-dialog [data-close]').click();
  await page.locator('[data-edit="1"]').click();
  assert.equal(await page.locator('#encore-contact-form [name="name"]').inputValue(),"Bandito Barney's");
  assert.equal(await page.locator('#encore-contact-form [name="kind"]').inputValue(),'Country club');
  await page.locator('#encore-contact-form [name="name"]').fill('Renamed venue');
  await page.locator('#encore-contact-form').evaluate(form=>{
    form.addEventListener('submit',event=>{
      event.preventDefault();
      window.savedContact=Object.fromEntries(new FormData(form));
    });
  });
  await page.locator('#encore-contact-form button[type="submit"]').click();
  const saved=await page.evaluate(()=>window.savedContact);
  assert.equal(saved.action,'edit_contact');
  assert.equal(saved.contact_id,'1');
  assert.equal(saved.name,'Renamed venue');
  await page.keyboard.press('Escape');
  await page.locator('[data-add]').click();
  assert.equal(await page.locator('#encore-contact-form [name="name"]').inputValue(),'');
  await page.keyboard.press('Escape');
  await page.locator('[data-sort="0"]').click();
  await page.locator('#encore-search').fill('Alex');
  assert.equal(await page.locator('.encore-table tbody tr:visible').count(),1);
  await page.locator('[data-edit="2"]').click();
  assert.equal(await page.locator('#encore-contact-form [name="contact_name"]').inputValue(),'Alex');
  await page.keyboard.press('Escape');
  await page.locator('#encore-search').fill('');
  await page.locator('[data-edit-task]').first().click();
  assert.equal(await page.locator('#encore-entry-form [name="title"]').inputValue(),'Call about the next booking');
  await page.locator('#encore-entry-form [name="due_at"]').fill('2027-01-15');
  await page.locator('#encore-entry-form').evaluate(form=>{
    form.addEventListener('submit',event=>{
      event.preventDefault();
      window.savedEntry=Object.fromEntries(new FormData(form));
    });
  });
  await page.locator('#encore-entry-form button[type="submit"]').click();
  let entry=await page.evaluate(()=>window.savedEntry);
  assert.equal(entry.action,'edit_task');
  assert.equal(entry.task_id,'1');
  assert.equal(entry.due_at,'2027-01-15');
  assert.equal(entry.summary,undefined);
  await page.keyboard.press('Escape');
  await page.locator('[data-history="1"]').click();
  assert.equal(await page.locator('#encore-history-content .encore-activity').count(),20);
  assert.equal(await page.locator('#encore-history-content b').count(),0);
  await page.locator('#encore-history-content [data-edit-interaction]').first().click();
  assert.equal(await page.locator('#encore-entry-form [name="summary"]').inputValue(),'Full message 0');
  await page.locator('#encore-entry-form [name="direction"]').selectOption('inbound');
  await page.locator('#encore-entry-form [name="summary"]').fill('Corrected message');
  await page.locator('#encore-entry-form button[type="submit"]').click();
  entry=await page.evaluate(()=>window.savedEntry);
  assert.equal(entry.action,'edit_interaction');
  assert.equal(entry.interaction_id,'0');
  assert.equal(entry.summary,'Corrected message');
  assert.equal(entry.due_at,undefined);
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#encore-history-dialog').evaluate(d=>d.open),true);
  await page.locator('#encore-history-content [data-detail]').first().click();
  assert.match(await page.locator('#encore-history-content .encore-activity-detail').first().innerText(),/Full message 0/);
  await page.keyboard.press('Escape');
  await page.locator('.encore-table [data-log="2"]').click();
  assert.equal(await page.locator('#encore-log-form [name="contact_id"]').inputValue(),'2');
  assert.equal(await page.locator('#encore-log-form [name="next_date"]').getAttribute('type'),'date');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('.encore-followup-group').count(),3);
  for(const width of [1280,390]) {
    await page.setViewportSize({width,height:900});
    await page.locator('[data-add]').click();
    const formSize=await page.locator('#encore-contact-dialog').evaluate(d=>({scroll:d.scrollWidth,client:d.clientWidth}));
    assert.ok(formSize.scroll<=formSize.client+1);
    await page.keyboard.press('Escape');
    await page.screenshot({path:path.join(process.env.TEMP,'encore-dashboard-'+width+'.png')});
    await page.locator('[data-history="1"]').click();
    const dimensions=await page.locator('#encore-history-dialog').evaluate(d=>({width:d.getBoundingClientRect().width,scroll:d.scrollWidth,client:d.clientWidth}));
    assert.ok(dimensions.width<=width);
    assert.ok(dimensions.scroll<=dimensions.client+1);
    await page.screenshot({path:path.join(process.env.TEMP,'encore-history-'+width+'.png')});
    await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  assert.ok(usageEvents.some(event=>event.action_key==='open_edit'));
  assert.ok(usageEvents.some(event=>event.action_key==='view_contact_history'));
  assert.ok(usageEvents.some(event=>event.action_key==='view_interaction_detail'));
  assert.ok(usageEvents.every(event=>Object.keys(event).sort().join(',')==='action_key,feature_key,status'));
  console.log('PASS: Add/Edit fields and submit payload, row identity after search/sort, Log date picker, full history, escaped content, grouped tasks, desktop/mobile dialog fit; no browser errors.');
 } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
