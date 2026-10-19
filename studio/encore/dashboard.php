<?php
// Included by index.php after authentication and user-scoped queries.
if (!isset($contacts, $isProUser)) { http_response_code(404); exit; }
$taskGroups = ['Overdue'=>[], 'Today'=>[], 'Upcoming'=>[]];
foreach ($tasks as $task) {
    $group = $task['due_at'] < date('Y-m-d') ? 'Overdue' : ($task['due_at'] === date('Y-m-d') ? 'Today' : 'Upcoming');
    $taskGroups[$group][] = $task;
}
function encore_csrf_field(): void { ?><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><?php }
function encore_activity_row(array $item, array $channels, bool $venue = false): void { ?>
  <article class="encore-activity">
    <time datetime="<?=e($item['occurred_at'])?>"><?=e(date('M j, Y', strtotime($item['occurred_at'])))?></time>
    <span class="encore-channel"><?=e($channels[$item['channel']] ?? $item['channel'])?></span>
    <strong class="encore-ellipsis" title="<?=e($item['subject'] ?: $item['summary'])?>"><?php if($venue): ?><?=e($item['contact_name'])?> &middot; <?php endif; ?><?=e($item['subject'] ?: $item['summary'])?></strong>
    <div class="encore-row-actions">
      <button type="button" class="btn btn-outline encore-small" data-detail aria-expanded="false">View</button>
      <button type="button" class="btn btn-outline encore-small" data-edit-interaction="<?=e(json_encode($item,JSON_INVALID_UTF8_SUBSTITUTE))?>">Edit</button>
    </div>
    <div class="encore-activity-detail" hidden>
      <?php if($item['subject']): ?><strong><?=e($item['subject'])?></strong><?php endif; ?>
      <p><?=nl2br(e($item['summary']))?></p>
      <?php if($item['next_step']): ?><p class="encore-muted"><?=e($item['next_step'])?></p><?php endif; ?>
    </div>
  </article>
<?php }
encore_page_head('Encore | Ready Set Shows');
?>
<main class="container encore-shell">
  <?php if($notice): ?><div class="encore-alert encore-alert-success"><?=e($notice)?></div><?php endif; ?>
  <?php if($error): ?><div class="encore-alert encore-alert-error" role="alert"><?=e($error)?></div><?php endif; ?>
  <section class="encore-card encore-hero">
    <div class="encore-hero-copy"><span class="encore-pill">Encore</span><h1>Keep booking conversations moving.</h1><p class="encore-muted">Your venues, clients, follow-ups, and communication history.</p></div>
    <?php if($ready && $isProUser): ?><div class="encore-actions"><button type="button" class="btn btn-primary" data-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Contact</button><button type="button" class="btn btn-outline" data-log=""><i class="fa-regular fa-message" aria-hidden="true"></i> Log Interaction</button></div><?php endif; ?>
  </section>
  <?php if(!$ready): ?><p>Encore setup is required.</p>
  <?php elseif(!$isProUser): ?><section class="encore-card"><h2>Encore is a Pro feature</h2><a class="btn btn-primary" href="<?=e($upgradeUrl)?>">Upgrade to Pro</a></section>
  <?php else: ?>
    <section class="encore-summary">
      <article class="encore-card encore-stat"><span>Contacts</span><strong><?=count($contacts)?></strong></article>
      <article class="encore-card encore-stat"><span>Follow-ups open</span><strong><?=count($tasks)?></strong></article>
      <article class="encore-card encore-stat"><span>Due today / overdue</span><strong><?=count($taskGroups['Today']) + count($taskGroups['Overdue'])?></strong></article>
      <article class="encore-card encore-stat"><span>Recent interactions</span><strong><?=count($interactions)?></strong></article>
    </section>
    <section class="encore-card encore-compact" id="followups">
      <h2>Follow-Ups</h2>
      <?php foreach($taskGroups as $group=>$groupTasks): ?>
        <h3 class="encore-followup-group"><?=e($group)?> <span>(<?=count($groupTasks)?>)</span></h3>
        <?php if(!$groupTasks): ?><p class="encore-empty">None <?= $group === 'Upcoming' ? 'scheduled' : 'due' ?>.</p><?php endif; ?>
        <?php foreach($groupTasks as $task): ?>
          <article class="encore-task-row">
            <time datetime="<?=e($task['due_at'])?>"><?=e(date('M j, Y',strtotime($task['due_at'])))?></time>
            <strong class="encore-ellipsis" title="<?=e($task['contact_name'])?>"><?=e($task['contact_name'])?></strong>
            <div class="encore-task-context">
              <span class="encore-ellipsis encore-muted" title="<?=e($task['title'])?>"><?=e($task['title'])?></span>
              <?php if(!empty($historyByContact[(string)$task['contact_id']])): ?>
                <button type="button" class="encore-message-link" data-history="<?=(int)$task['contact_id']?>" data-latest>Last message</button>
              <?php else: ?><span class="encore-muted encore-no-message">No messages yet</span><?php endif; ?>
            </div>
            <div class="encore-row-actions">
              <button type="button" class="btn btn-outline encore-small" data-edit-task="<?=e(json_encode($task,JSON_INVALID_UTF8_SUBSTITUTE))?>">Edit</button>
              <button type="button" class="btn btn-outline encore-small" data-log="<?=(int)$task['contact_id']?>">Log</button>
              <button type="button" class="btn btn-outline encore-small" data-history="<?=(int)$task['contact_id']?>">History</button>
              <form method="post" action="<?=e(base_url('/encore/index.php'))?>#followups"><?php encore_csrf_field(); ?><input type="hidden" name="action" value="complete_task"><input type="hidden" name="task_id" value="<?=(int)$task['id']?>"><button class="btn btn-outline encore-small" type="submit" title="Mark complete" aria-label="Mark <?=e($task['title'])?> for <?=e($task['contact_name'])?> complete"><i class="fa-solid fa-check" aria-hidden="true"></i></button></form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </section>
    <section class="encore-card encore-directory" id="directory">
      <h2>Venue &amp; Client Directory</h2>
      <input type="search" id="encore-search" class="encore-directory-search" placeholder="Search venues or contacts" aria-label="Search venues or contacts">
      <div class="encore-table-wrap"><table class="encore-table">
        <thead><tr><th aria-sort="none"><button type="button" data-sort="0">Venue</button></th><th aria-sort="none"><button type="button" data-sort="1">Contact</button></th><th aria-sort="none"><button type="button" data-sort="2">Last update</button></th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($contacts as $contact): ?>
          <tr data-contact-id="<?=(int)$contact['id']?>">
            <td><strong><?=e($contact['name'])?></strong></td>
            <td><?=e($contact['contact_name'] ?: '-')?></td>
            <td data-sort-value="<?=e($contact['updated_at'])?>"><?=e(date('M j, Y',strtotime($contact['updated_at'])))?></td>
            <td><div class="encore-row-actions">
              <button type="button" class="btn btn-outline" data-edit="<?=(int)$contact['id']?>" aria-label="Edit <?=e($contact['name'])?>">Edit</button>
              <button type="button" class="btn btn-outline" data-log="<?=(int)$contact['id']?>">Log</button>
              <button type="button" class="btn btn-outline" data-history="<?=(int)$contact['id']?>">History</button>
              <form method="post" action="<?=e(base_url('/encore/index.php'))?>#directory" data-delete><?php encore_csrf_field(); ?><input type="hidden" name="action" value="delete_contact"><input type="hidden" name="contact_id" value="<?=(int)$contact['id']?>"><button class="btn btn-danger" type="submit" title="Delete contact" aria-label="Delete <?=e($contact['name'])?>"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p id="encore-no-results" class="encore-empty" <?= $contacts ? 'hidden' : '' ?>>No contacts found.</p>
    </section>
    <section class="encore-card encore-directory encore-compact">
      <h2>Recent History</h2>
      <?php foreach($interactions as $item) encore_activity_row($item,$channels,true); ?>
      <?php if(!$interactions): ?><p class="encore-empty">No interactions logged yet.</p><?php endif; ?>
    </section>
    <dialog id="encore-contact-dialog" class="encore-dialog" aria-labelledby="encore-contact-title">
      <div class="encore-dialog-inner"><button type="button" class="encore-dialog-close" data-close aria-label="Close">&times;</button>
        <h2 id="encore-contact-title">Add Contact</h2>
        <form method="post" action="<?=e(base_url('/encore/index.php'))?>#directory" id="encore-contact-form" class="encore-form-grid encore-contact-fields">
          <?php encore_csrf_field(); ?><input type="hidden" name="action" value="add_contact"><input type="hidden" name="contact_id">
          <label>Venue / client name<input name="name" required maxlength="190"></label>
          <label>Type<select name="kind"><?php foreach(['Venue','Restaurant','Winery','Casino','Country club','Planner','Festival','Private client','Agency'] as $kind): ?><option><?=e($kind)?></option><?php endforeach; ?></select></label>
          <label>Contact person<input name="contact_name" maxlength="190"></label>
          <label>Email<input name="email" type="email" maxlength="190"></label>
          <label>Phone<input name="phone" maxlength="64"></label>
          <label>City<input name="city" maxlength="120"></label>
          <label>Estimated value<input name="estimated_value" type="number" min="0" step="0.01"></label>
          <label class="encore-field-wide">Notes<textarea name="fit_notes" rows="3" maxlength="2000"></textarea></label>
          <button class="btn btn-primary encore-field-wide" type="submit">Save Contact</button>
        </form>
      </div>
    </dialog>
    <dialog id="encore-log-dialog" class="encore-dialog" aria-labelledby="encore-log-title">
      <div class="encore-dialog-inner"><button type="button" class="encore-dialog-close" data-close aria-label="Close">&times;</button><h2 id="encore-log-title">Log Interaction</h2>
        <form method="post" action="<?=e(base_url('/encore/index.php'))?>#directory" id="encore-log-form" class="encore-form-grid">
          <?php encore_csrf_field(); ?><input type="hidden" name="action" value="log_interaction">
          <label>Contact<select name="contact_id" required><option value="">Choose a contact</option><?php foreach($contacts as $contact): ?><option value="<?=(int)$contact['id']?>"><?=e($contact['name'])?></option><?php endforeach; ?></select></label>
          <label>Channel<select name="channel"><?php foreach($channels as $key=>$label): ?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach; ?></select></label>
          <label>Direction<select name="direction"><option value="outbound">I reached out</option><option value="inbound">They reached out</option><option value="internal">Note to self</option></select></label>
          <label>Subject<input name="subject" maxlength="190"></label>
          <label>Summary<textarea name="summary" rows="3" required maxlength="3000"></textarea></label>
          <label>Next contact date<input name="next_date" type="date"></label>
          <button class="btn btn-primary" type="submit">Add to History</button>
        </form>
      </div>
    </dialog>
    <dialog id="encore-entry-dialog" class="encore-dialog" aria-labelledby="encore-entry-title">
      <div class="encore-dialog-inner">
        <button type="button" class="encore-dialog-close" data-close aria-label="Close">&times;</button>
        <h2 id="encore-entry-title">Edit Entry</h2><p id="encore-entry-contact" class="encore-muted"></p>
        <form method="post" action="<?=e(base_url('/encore/index.php'))?>#followups" id="encore-entry-form" class="encore-form-grid">
          <?php encore_csrf_field(); ?><input type="hidden" name="action"><input type="hidden" name="task_id"><input type="hidden" name="interaction_id">
          <label data-task-field>Task<input name="title" maxlength="190" required></label>
          <label data-task-field>Due date<input name="due_at" type="date" required></label>
          <label data-history-field>Interaction date<input name="occurred_at" type="datetime-local" step="1" required></label>
          <label data-history-field>Channel<select name="channel"><?php foreach($channels as $key=>$label): ?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach; ?></select></label>
          <label data-history-field>Direction<select name="direction"><option value="outbound">I reached out</option><option value="inbound">They reached out</option><option value="internal">Note to self</option></select></label>
          <label data-history-field>Subject<input name="subject" maxlength="190"></label>
          <label data-history-field>Summary<textarea name="summary" rows="4" maxlength="3000" required></textarea></label>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    </dialog>
    <dialog id="encore-history-dialog" class="encore-dialog" aria-labelledby="encore-history-title"><div class="encore-dialog-inner"><button type="button" class="encore-dialog-close" data-close aria-label="Close">&times;</button><h2 id="encore-history-title">History</h2><div id="encore-history-content"></div></div></dialog>
    <?php foreach($contacts as $contact): ?><template id="encore-history-<?=(int)$contact['id']?>"><?php foreach($historyByContact[(string)$contact['id']] ?? [] as $item) encore_activity_row($item,$channels); ?><?php if(empty($historyByContact[(string)$contact['id']])): ?><p class="encore-empty">No interactions logged yet.</p><?php endif; ?></template><?php endforeach; ?>
    <script type="application/json" id="encore-data"><?=json_encode(['contacts'=>$contacts,'failedAction'=>$error ? ($_POST['action'] ?? '') : '', 'submitted'=>$error ? $_POST : new stdClass()], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
    <script id="encore-script" data-usage-url="<?=e(base_url('/api/log_tool_usage.php'))?>" src="encore.js?v=<?=filemtime(__DIR__.'/encore.js')?>" defer></script>
  <?php endif; ?>
</main>
<?php encore_page_foot(); ?>
