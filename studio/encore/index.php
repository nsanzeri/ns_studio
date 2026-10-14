<?php
require_once __DIR__ . '/_common.php';

function encore_ready(PDO $pdo): bool {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('encore_contacts','encore_interactions','encore_tasks')");
    return (int)$stmt->fetchColumn() === 3;
}
function encore_text($value, int $limit = 255): string { return mb_substr(trim((string)$value), 0, $limit); }
function encore_home(): never { header('Location: ' . base_url('/encore/index.php') . '#directory'); exit; }

$ready = encore_ready($pdo);
$isProUser = rss_current_user_is_pro($pdo);
$upgradeUrl = rss_tool_upgrade_url();
$notice = flash_get('encore_notice');
$error = '';
$channels = ['email'=>'Email','text'=>'Text','phone'=>'Phone','instagram'=>'Instagram','facebook'=>'Facebook','website'=>'Website form','in_person'=>'In person','other'=>'Other'];
$interactionKinds = ['touch'=>'Touch','ask'=>'Ask'];
$stageLabels = ['researching'=>'Researching','contacted'=>'Contacted','follow_up'=>'Follow-Up Due','conversation'=>'In Conversation','date_offered'=>'Date Offered','booked'=>'Booked','revisit'=>'Revisit Later','not_a_fit'=>'Not a Fit'];

if ($ready && is_post()) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) { $error = 'Your session expired. Refresh the page and try again.'; }
    else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if (!$isProUser) throw new RuntimeException('Encore is a Pro feature. Upgrade to save contacts, follow-ups, and history.');
            if ($action === 'update_stage') {
                $stage = encore_text($_POST['stage'] ?? '', 30);
                if (!array_key_exists($stage, $stageLabels)) throw new RuntimeException('Choose a valid pipeline stage.');
                $pdo->prepare('UPDATE encore_contacts SET stage=? WHERE id=? AND user_id=?')->execute([$stage, (int)($_POST['contact_id'] ?? 0), $userId]);
                flash_set('encore_notice', 'Pipeline stage updated.');
            } elseif ($action === 'update_relationship_status') {
                $status = encore_text($_POST['relationship_status'] ?? '', 20);
                if (!in_array($status, ['active','inactive','no_response'], true)) throw new RuntimeException('Choose a valid relationship status.');
                $pdo->prepare('UPDATE encore_contacts SET relationship_status=? WHERE id=? AND user_id=?')->execute([$status, (int)($_POST['contact_id'] ?? 0), $userId]);
                flash_set('encore_notice', 'Relationship status updated.');
            } elseif ($action === 'delete_contact') {
                $pdo->prepare('DELETE FROM encore_contacts WHERE id=? AND user_id=?')->execute([(int)($_POST['contact_id']??0),$userId]); flash_set('encore_notice','Contact deleted from Encore.');
            } elseif ($action === 'edit_contact') {
                $name = encore_text($_POST['name'] ?? '', 190);
                if ($name === '') throw new RuntimeException('A venue or client name is required.');
                $stmt = $pdo->prepare('UPDATE encore_contacts SET name=?,contact_name=?,kind=?,email=?,phone=?,city=?,estimated_value=?,fit_notes=? WHERE id=? AND user_id=?');
                $stmt->execute([$name,encore_text($_POST['contact_name'] ?? '',190) ?: null,encore_text($_POST['kind'] ?? 'Venue',80),encore_text($_POST['email'] ?? '',190) ?: null,encore_text($_POST['phone'] ?? '',64) ?: null,encore_text($_POST['city'] ?? '',120) ?: null,(float)($_POST['estimated_value'] ?? 0) ?: null,encore_text($_POST['fit_notes'] ?? '',2000) ?: null,(int)($_POST['contact_id'] ?? 0),$userId]);
                flash_set('encore_notice','Contact updated.');
            } elseif ($action === 'add_contact') {
                $name = encore_text($_POST['name'] ?? '', 190); if ($name === '') throw new RuntimeException('A venue or client name is required.');
                $stmt = $pdo->prepare('INSERT INTO encore_contacts (user_id,name,contact_name,kind,email,phone,city,stage,estimated_value,fit_notes) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$userId,$name,encore_text($_POST['contact_name'] ?? '',190) ?: null,encore_text($_POST['kind'] ?? 'Venue',80) ?: 'Venue',encore_text($_POST['email'] ?? '',190) ?: null,encore_text($_POST['phone'] ?? '',64) ?: null,encore_text($_POST['city'] ?? '',120) ?: null,'researching',(float)($_POST['estimated_value'] ?? 0) ?: null,encore_text($_POST['fit_notes'] ?? '',2000) ?: null]);
                flash_set('encore_notice','Contact added to Encore.');
            } elseif ($action === 'add_task') {
                $contactId=(int)($_POST['contact_id']??0); $title=encore_text($_POST['title']??'',190); $due=encore_text($_POST['due_at']??'',10);
                if ($contactId<=0 || $title==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$due)) throw new RuntimeException('Choose a contact, task, and due date.');
                $stmt=$pdo->prepare("INSERT INTO encore_tasks (contact_id,user_id,title,due_at) SELECT id,?,?,? FROM encore_contacts WHERE id=? AND user_id=?"); $stmt->execute([$userId,$title,$due,$contactId,$userId]); flash_set('encore_notice','Follow-up added.');
            } elseif ($action === 'complete_task') {
                $pdo->prepare("UPDATE encore_tasks SET status='done', completed_at=NOW() WHERE id=? AND user_id=?")->execute([(int)($_POST['task_id']??0),$userId]); flash_set('encore_notice','Follow-up marked complete.');
            } elseif ($action === 'log_interaction') {
                $contactId=(int)($_POST['contact_id']??0); $summary=encore_text($_POST['summary']??'',3000); if($contactId<=0||$summary==='') throw new RuntimeException('Choose a contact and add a short interaction summary.');
                $nextDate=encore_text($_POST['next_date']??'',10);
                $interactionKind = encore_text($_POST['interaction_kind'] ?? 'touch', 10);
                if (!array_key_exists($interactionKind, $interactionKinds)) $interactionKind = 'touch';
                $stmt=$pdo->prepare('INSERT INTO encore_interactions (contact_id,user_id,channel,direction,interaction_kind,occurred_at,subject,summary,next_step) SELECT id,?,?,?,?,?,?,?,? FROM encore_contacts WHERE id=? AND user_id=?');
                $stmt->execute([$userId,encore_text($_POST['channel']??'email',20),encore_text($_POST['direction']??'outbound',20),$interactionKind,encore_text($_POST['occurred_at']??date('Y-m-d H:i:s'),19),encore_text($_POST['subject']??'',190)?:null,$summary,$nextDate ? 'Follow up on '.$nextDate : null,$contactId,$userId]);
                if ($nextDate && preg_match('/^\d{4}-\d{2}-\d{2}$/',$nextDate)) { $pdo->prepare("UPDATE encore_tasks SET status='dismissed' WHERE contact_id=? AND user_id=? AND status='open'")->execute([$contactId,$userId]); $pdo->prepare('INSERT INTO encore_tasks (contact_id,user_id,title,due_at) VALUES (?,?,?,?)')->execute([$contactId,$userId,encore_text($_POST['next_step']??'Follow up',190),$nextDate]); }
                $pdo->prepare("UPDATE encore_contacts SET stage=IF(stage='researching','contacted',stage) WHERE id=? AND user_id=?")->execute([$contactId,$userId]); flash_set('encore_notice','Interaction added to the timeline.');
            }
            encore_home();
        } catch (Throwable $e) { $error=$e->getMessage(); }
    }
}

$contacts=$tasks=$interactions=[]; $historyByContact=[];
if($ready){$s=$pdo->prepare('SELECT * FROM encore_contacts WHERE user_id=? ORDER BY updated_at DESC,name ASC');$s->execute([$userId]);$contacts=$s->fetchAll(PDO::FETCH_ASSOC);$s=$pdo->prepare("SELECT t.*,c.name contact_name FROM encore_tasks t JOIN encore_contacts c ON c.id=t.contact_id WHERE t.user_id=? AND t.status='open' ORDER BY t.due_at,t.id");$s->execute([$userId]);$tasks=$s->fetchAll(PDO::FETCH_ASSOC);$s=$pdo->prepare('SELECT i.*,c.name contact_name FROM encore_interactions i JOIN encore_contacts c ON c.id=i.contact_id WHERE i.user_id=? ORDER BY i.occurred_at DESC,i.id DESC LIMIT 12');$s->execute([$userId]);$interactions=$s->fetchAll(PDO::FETCH_ASSOC);$s=$pdo->prepare('SELECT i.*,c.name contact_name FROM encore_interactions i JOIN encore_contacts c ON c.id=i.contact_id WHERE i.user_id=? ORDER BY i.occurred_at DESC,i.id DESC');$s->execute([$userId]);foreach($s->fetchAll(PDO::FETCH_ASSOC) as $history){$historyByContact[(string)$history['contact_id']][]=$history;}}
require __DIR__ . '/dashboard.php';
