<?php
/** VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */
require_once __DIR__.'/app/config.php';
init_db();
require_login();

$q=trim((string)($_GET['q']??''));
$cat=trim((string)($_GET['category']??''));
[$scopeSql,$scopeParams]=accessible_vault_sql('v');
$sql="SELECT v.*,p.icon platform_icon,COUNT(c.id) total,COALESCE(SUM(c.used=0),0) unused FROM vaults v LEFT JOIN platforms p ON p.name=v.name LEFT JOIN codes c ON c.vault_id=v.id WHERE 1{$scopeSql}";
$params=$scopeParams;
if($q!==''){$sql.=" AND (v.name LIKE ? OR v.account LIKE ? OR v.category LIKE ?)";$x="%$q%";$params=array_merge($params,[$x,$x,$x]);}
if($cat!==''){$sql.=" AND v.category=?";$params[]=$cat;}
$sql.=" GROUP BY v.id ORDER BY v.favorite DESC,v.updated_at DESC LIMIT 6";
$stmt=db()->prepare($sql);$stmt->execute($params);$vaults=$stmt->fetchAll();
[$allScope,$allParams]=accessible_vault_sql('v');
$allSql="SELECT v.id,v.name,v.account,v.category,v.favorite,p.icon platform_icon,COUNT(c.id) total,COALESCE(SUM(c.used=0),0) unused FROM vaults v LEFT JOIN platforms p ON p.name=v.name AND p.enabled=1 LEFT JOIN codes c ON c.vault_id=v.id WHERE 1{$allScope} GROUP BY v.id ORDER BY v.favorite DESC,v.updated_at DESC";
$stmtAll=db()->prepare($allSql);$stmtAll->execute($allParams);$allVaults=$stmtAll->fetchAll();
[$countScope,$countParams]=accessible_vault_sql('v');$stmtCount=db()->prepare("SELECT COUNT(*) FROM vaults v WHERE 1{$countScope}");$stmtCount->execute($countParams);$total=(int)$stmtCount->fetchColumn();
$stmtCount=db()->prepare("SELECT COUNT(*) FROM codes c INNER JOIN vaults v ON v.id=c.vault_id WHERE 1{$countScope}");$stmtCount->execute($countParams);$codes=(int)$stmtCount->fetchColumn();
$stmtCount=db()->prepare("SELECT COUNT(*) FROM codes c INNER JOIN vaults v ON v.id=c.vault_id WHERE c.used=0{$countScope}");$stmtCount->execute($countParams);$unused=(int)$stmtCount->fetchColumn();
$cats=categories();
$activities=db()->query("SELECT a.*,v.name,u.display_name AS actor_name FROM activity a LEFT JOIN vaults v ON v.id=a.vault_id LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 6")->fetchAll();
$pageTitle='Home';
include __DIR__.'/app/header.php';
?>
<div class="dashboard-topbar">
  <div class="dashboard-heading">
    <div class="eyebrow">Private security vault</div>
    <h1>Your secure space.</h1>
    <p>Keep recovery codes organized, encrypted, and ready when you need them.</p>
  </div>
  <div class="dashboard-actions">
    <button class="btn" data-global-search type="button"><span class="button-icon">⌕</span> Search <kbd>Ctrl K</kbd></button>
    <button class="btn" data-theme-toggle type="button"><span class="button-icon">◐</span> Theme</button>
    <?php if(can('create_vault')): ?><a class="btn primary" href="vault.php?new=1"><span class="button-icon">＋</span> New vault</a><?php endif; ?>
  </div>
</div>

<section class="dashboard-stats">
  <div class="dashboard-stat"><span class="stat-icon">▣</span><div><small>Vaults</small><strong><?=$total?></strong><span>Saved services</span></div></div>
  <div class="dashboard-stat"><span class="stat-icon">⌘</span><div><small>Recovery codes</small><strong><?=$codes?></strong><span>Total stored codes</span></div></div>
  <div class="dashboard-stat"><span class="stat-icon">✓</span><div><small>Unused codes</small><strong><?=$unused?></strong><span>Available to use</span></div></div>
</section>

<section class="vaults-section">
  <div class="content-section-head">
    <div><div class="section-kicker">Your vaults</div><h2>Everything in one place</h2><p>Open a vault to view and manage its recovery codes.</p></div>
    <div class="vaults-section-actions"><form class="vault-search-form" method="get"><span>⌕</span><input name="q" value="<?=e($q)?>" placeholder="Search vaults…" aria-label="Search vaults"></form><a class="btn" href="vaults.php">View all</a></div>
  </div>
  <?php if($cats): ?><div class="chips dashboard-chips"><?php foreach($cats as $c): ?><a class="<?=($cat===$c)?'active':''?>" href="?category=<?=urlencode($c)?>"><?=e($c)?></a><?php endforeach; ?><?php if($cat): ?><a href="index.php">Clear</a><?php endif; ?></div><?php endif; ?>
  <?php if($vaults): ?>
  <div class="vault-grid dashboard-vault-grid">
    <?php foreach($vaults as $v): ?>
      <a class="vault-card dashboard-vault-card" href="vault.php?id=<?=$v['id']?>">
        <div class="vault-card-top"><div class="vault-icon-large"><?=platform_image(['name'=>$v['name'],'icon'=>$v['platform_icon']],'vault-platform-icon')?></div><span class="vault-arrow">↗</span></div>
        <div class="vault-name-row"><strong><?=e($v['name'])?></strong><?php if((int)$v['favorite']): ?><span class="favorite-mark">★</span><?php endif; ?></div>
        <div class="vault-account"><?=e($v['account']?:'No account label')?></div>
        <div class="vault-card-footer"><span><?=e($v['category'])?></span><span><b><?=$v['unused']?></b> unused <i>·</i> <?=$v['total']?> total</span></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty-vault-state card">
      <div class="empty-vault-icon">＋</div>
      <h3><?=($q||$cat)?'No matching vaults':'Your vault is ready to be created'?></h3>
      <p><?=($q||$cat)?'Try another search or clear the current filter.':'Create your first vault and keep your recovery codes in one secure place.'?></p>
      <?php if($q||$cat): ?><a class="btn" href="index.php">Clear filters</a><?php else: ?><?php if(can('create_vault')): ?><a class="btn primary" href="vault.php?new=1">Create your first vault</a><?php endif; ?><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if($activities && can('view_activity')): ?>
<section class="recent-activity card">
  <div class="content-section-head compact"><div><div class="section-kicker">Activity</div><h2>Recent activity</h2></div><a class="btn" href="settings.php#activity">View all</a></div>
  <div class="dashboard-activity-list">
    <?php foreach($activities as $a): ?><div class="dashboard-activity-row"><span class="activity-symbol">•</span><div><strong><?=e($a['action'])?></strong><?php if($a['name']): ?><span><?=e($a['name'])?></span><?php endif; ?><?php if(!empty($a['actor_name']) && !is_owner()): ?><span>by <?=e($a['actor_name'])?></span><?php endif; ?></div><time><?=date('M j · g:i A',(int)$a['created_at'])?></time></div><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="modal-backdrop" data-search-modal aria-hidden="true">
  <div class="search-modal" role="dialog" aria-modal="true" aria-labelledby="global-search-title">
    <div class="search-modal-head">
      <div class="search-input-wrap"><span class="search-input-icon">⌕</span><input id="global-search-title" data-search-input autocomplete="off" spellcheck="false" placeholder="Search your vaults…" aria-label="Search your vaults"><kbd>ESC</kbd></div>
      <button class="search-close" data-search-close type="button" aria-label="Close search">×</button>
    </div>
    <div class="search-hint" data-search-hint>Search by platform, account, or category.</div>
    <div class="search-results" data-search-results>
      <?php foreach($allVaults as $v): ?>
        <a href="vault.php?id=<?=$v['id']?>" class="search-result-item" data-search-item data-search-text="<?=e(strtolower($v['name'].' '.$v['account'].' '.$v['category']))?>">
          <span class="search-result-icon"><?=platform_image(['name'=>$v['name'],'icon'=>$v['platform_icon']],'search-platform-icon')?></span>
          <span class="search-result-main"><strong><?=e($v['name'])?></strong><small><?=e($v['account']?:'No account label')?></small></span>
          <span class="search-result-meta"><em><?=e($v['category'])?></em><small><?= (int)$v['unused'] ?> unused · <?= (int)$v['total'] ?> total</small></span>
          <span class="search-result-arrow">↗</span>
        </a>
      <?php endforeach; ?>
      <div class="empty search-empty" data-search-empty <?= $allVaults?'hidden':'' ?>><strong>No matching vaults</strong><span>Try another platform, account, or category.</span></div>
    </div>
  </div>
</div>
<?php include __DIR__.'/app/footer.php'; ?>
