<?php
/**
 * VaultOTP
 * Author: ItNexBD
 * Website: https://itnexbd.com
 * Source: https://github.com/NecharUddin
 */
require_once __DIR__.'/app/config.php';
init_db(); require_login();
if(!is_owner()){http_response_code(403);exit('Only the owner can view all user activities.');}

$pdo=db();
$userId=(int)($_GET['user']??0);
$sort=strtolower((string)($_GET['sort']??'latest'));
$sort=$sort==='oldest'?'ASC':'DESC';
$users=$pdo->query('SELECT id,username,display_name,is_owner FROM users ORDER BY is_owner DESC, display_name ASC')->fetchAll();
$sql='SELECT a.*,v.name AS vault_name,u.display_name AS actor_name,u.username AS actor_username FROM activity a LEFT JOIN vaults v ON v.id=a.vault_id LEFT JOIN users u ON u.id=a.user_id';
$params=[];
if($userId>0){$sql.=' WHERE a.user_id=?';$params[]=$userId;}
$sql.=' ORDER BY a.id '.$sort.' LIMIT 1000';
$stmt=$pdo->prepare($sql);$stmt->execute($params);$activities=$stmt->fetchAll();
$selectedUser=$userId>0?array_values(array_filter($users,fn($u)=>(int)$u['id']===$userId))[0]??null:null;
$pageTitle='Activities'; include __DIR__.'/app/header.php';
?>
<div class="topbar activities-topbar">
  <div><div class="eyebrow">Owner audit center</div><div class="title">Activities</div><p class="subtitle">Review the latest 1,000 recorded actions across every user and vault.</p></div>
  <a class="btn" href="users.php">Manage users →</a>
</div>
<section class="card activity-console">
  <div class="activity-console-head"><div><h2>Recent activity</h2><p><?= $selectedUser ? 'Showing activity for '.e($selectedUser['display_name']).' (@'.e($selectedUser['username']).').' : 'Showing activity from all users.' ?></p></div><span class="activity-count"><?=count($activities)?> / 1,000</span></div>
  <form class="activity-filters" method="get">
    <label><span>USER</span><select name="user"><option value="0">All users</option><?php foreach($users as $u): ?><option value="<?=$u['id']?>" <?=$userId===(int)$u['id']?'selected':''?>><?=e($u['display_name'])?> (@<?=e($u['username'])?>)<?=((int)$u['is_owner'])?' · Owner':''?></option><?php endforeach; ?></select></label>
    <label><span>SORT</span><select name="sort"><option value="latest" <?=$sort==='DESC'?'selected':''?>>Latest first</option><option value="oldest" <?=$sort==='ASC'?'selected':''?>>Oldest first</option></select></label>
    <button class="btn primary" type="submit">Apply filters</button><?php if($userId>0): ?><a class="btn" href="activities.php">Clear</a><?php endif; ?>
  </form>
  <div class="activity-table-wrap"><div class="activity-table">
    <div class="activity-table-row activity-table-header"><span>Action</span><span>User</span><span>Vault</span><span>Time</span></div>
    <?php foreach($activities as $a): ?><div class="activity-table-row">
      <div class="activity-action"><i>•</i><strong><?=e($a['action'])?></strong></div>
      <div class="activity-user"><?=e($a['actor_name']?:'Unknown')?><?php if($a['actor_username']): ?><small>@<?=e($a['actor_username'])?></small><?php endif; ?></div>
      <div class="activity-vault"><?=e($a['vault_name']?:'—')?></div>
      <time datetime="<?=date('c',(int)$a['created_at'])?>"><?=date('M j, Y · g:i A',(int)$a['created_at'])?></time>
    </div><?php endforeach; ?>
    <?php if(!$activities): ?><div class="empty">No activities match the selected filter.</div><?php endif; ?>
  </div></div>
  <div class="activity-console-foot"><span>Only the owner can access this audit center. Recovery-code plaintext is never stored in the activity log.</span><span>Showing up to 1,000 most recent records.</span></div>
</section>
<?php include __DIR__.'/app/footer.php'; ?>
