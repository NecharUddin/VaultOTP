<?php
/** VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */
require_once __DIR__.'/app/config.php';
init_db();
require_login();
$sessionKey=key_from_session();
$platformList=platforms();
$id=(int)($_GET['id']??0);
$new=isset($_GET['new'])||$id===0;
if($new) require_permission('create_vault');
$editMode=isset($_GET['edit']) && !$new;
if($editMode) require_vault_access($id,'edit');
$vault=$new?['id'=>0,'name'=>'','account'=>'','category'=>'General','notes'=>'','favorite'=>0]:get_vault($id);
if(!$vault){http_response_code(404);exit('Vault not found');}
if(!$new) require_vault_access($id,'view');
try { $key=$new ? $sessionKey : key_for_vault($vault,$sessionKey); } catch (Throwable $e) { flash('error','This vault encryption key is unavailable to this account.'); redirect('vaults.php'); }
$matched=false;
if(!$new){foreach($platformList as $platform){if($vault['name']===$platform['name']){$matched=true;break;}}}
if($_SERVER['REQUEST_METHOD']!=='POST'&&!$new){log_activity('Opened vault',$id);}
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $a=$_POST['action']??'';
  if($a==='log_event'){
    $event=trim((string)($_POST['event']??''));$codeId=(int)($_POST['code_id']??0);
    if($event==='Revealed recovery code' && !user_can_vault($id,'reveal')){http_response_code(403);exit('Forbidden');}
    if($event==='Copied recovery code' && !user_can_vault($id,'copy')){http_response_code(403);exit('Forbidden');}
    if(in_array($event,['Revealed recovery code','Copied recovery code'],true)&&$codeId>0){$qv=db()->prepare('SELECT id FROM codes WHERE id=? AND vault_id=?');$qv->execute([$codeId,$id]);if($qv->fetchColumn())log_activity($event.' (#'.$codeId.')',$id);}
    http_response_code(204);exit;
  }
  if(in_array($a,['create','edit'],true)){
    if($a==='edit') require_vault_access($id,'edit');
    $name=trim((string)($_POST['name']??''));
    if($name==='__custom__'){flash('warning','Open Platforms to add a custom platform first.');redirect('platforms.php');}
    $account=trim((string)($_POST['account']??''));$category=trim((string)($_POST['category']??'General'))?:'General';$notes=trim((string)($_POST['notes']??''));
    if($name===''){flash('error','Choose a platform or enter a custom platform name.');redirect($new?'vault.php?new=1':'vault.php?id='.$id);}
    if($a==='create'){$s=db()->prepare('INSERT INTO vaults(name,account,category,notes,created_at,updated_at) VALUES(?,?,?,?,?,?)');$s->execute([$name,$account,$category,$notes,time(),time()]);$id=(int)db()->lastInsertId();$new=false;grant_creator_vault_access($id);}
    else{$s=db()->prepare('UPDATE vaults SET name=?,account=?,category=?,notes=?,updated_at=? WHERE id=?');$s->execute([$name,$account,$category,$notes,time(),$id]);}
    try { $n=(user_can_vault($id,'add'))?add_codes($id,(string)($_POST['codes']??''),$_FILES['code_file']??null,$key):0; } catch (Throwable $e) { flash('error','Could not save recovery codes: '.$e->getMessage()); redirect('vault.php?id='.$id); } log_activity($a==='create'?'Created vault':'Updated vault',$id);flash('success',($a==='create'?'Vault created. ':'Vault updated. ').($n?"$n new code(s) added.":''));redirect('vault.php?id='.$id);
  }
  if($a==='add_code'){require_vault_access($id,'add');try{$n=add_codes($id,(string)($_POST['codes']??''),$_FILES['code_file']??null,$key);}catch(Throwable $e){flash('error','Could not add recovery codes: '.$e->getMessage());redirect('vault.php?id='.$id);}log_activity('Added recovery codes',$id);flash('success',"$n new code(s) added.");redirect('vault.php?id='.$id);}
  if($a==='toggle_favorite'){require_vault_access($id,'edit');db()->prepare('UPDATE vaults SET favorite=1-favorite,updated_at=? WHERE id=?')->execute([time(),$id]);log_activity(((int)$vault['favorite'])?'Removed vault from favorites':'Added vault to favorites',$id);redirect('vault.php?id='.$id);}
  if($a==='toggle_used'){
    require_vault_access($id,'mark');
    $codeId=(int)$_POST['code_id'];
    $q=db()->prepare('SELECT id,used,used_by,used_at FROM codes WHERE id=? AND vault_id=? LIMIT 1');$q->execute([$codeId,$id]);$codeRow=$q->fetch();
    if(!$codeRow){http_response_code(404);exit('Recovery code not found.');}
    $now=time();
    if((int)$codeRow['used']===1){
        if(!can_undo_own_mark($codeId)){
            http_response_code(403);
            exit(is_owner()?'You cannot change this code status.':'Only the user who marked this code used can mark it unused within 15 minutes.');
        }
        db()->prepare('UPDATE codes SET used=0,used_at=NULL,used_by=NULL WHERE id=? AND vault_id=?')->execute([$codeId,$id]);
        log_activity('Marked recovery code unused (#'.$codeId.')',$id);
    } else {
        $uid=(int)($_SESSION['user_id']??0);
        db()->prepare('UPDATE codes SET used=1,used_at=?,used_by=? WHERE id=? AND vault_id=?')->execute([$now,$uid>0?$uid:null,$codeId,$id]);
        log_activity('Marked recovery code used (#'.$codeId.')',$id);
    }
    redirect('vault.php?id='.$id);
} 
  if($a==='delete_code'){require_vault_access($id,'delete_code');db()->prepare('DELETE FROM codes WHERE id=? AND vault_id=?')->execute([(int)$_POST['code_id'],$id]);log_activity('Deleted recovery code (#'.(int)$_POST['code_id'].')',$id);flash('success','Code deleted.');redirect('vault.php?id='.$id);}
  if($a==='delete_vault'){require_vault_access($id,'delete');db()->prepare('DELETE FROM vaults WHERE id=?')->execute([$id]);log_activity('Deleted vault');flash('success','Vault deleted.');redirect('index.php');}
}
$codes=[];$unused=0;$totalCodes=0;$page=1;$pageSize=10;$totalPages=1;$codeFilter=(string)($_GET['status']??'all');$codeSort=(string)($_GET['sort']??'latest');
if(!in_array($codeFilter,['all','unused','used'],true))$codeFilter='all';
if(!in_array($codeSort,['latest','oldest'],true))$codeSort='latest';
if(!$new){
  $q=db()->prepare('SELECT COUNT(*) FROM codes WHERE vault_id=?');$q->execute([$id]);$totalCodes=(int)$q->fetchColumn();
  $q=db()->prepare('SELECT COUNT(*) FROM codes WHERE vault_id=? AND used=0');$q->execute([$id]);$unused=(int)$q->fetchColumn();
  $filterSql=$codeFilter==='unused'?' AND used=0':($codeFilter==='used'?' AND used=1':'');
  $countStmt=db()->prepare('SELECT COUNT(*) FROM codes WHERE vault_id=?'.$filterSql);$countStmt->execute([$id]);$filteredTotal=(int)$countStmt->fetchColumn();
  $totalPages=max(1,(int)ceil($filteredTotal/$pageSize));
  $page=max(1,min($totalPages,(int)($_GET['page']??1)));
  $offset=($page-1)*$pageSize;
  $order=$codeSort==='oldest'?'id ASC':'id DESC';
  $sql='SELECT * FROM codes WHERE vault_id=?'.$filterSql.' ORDER BY '.$order.' LIMIT '.(int)$pageSize.' OFFSET '.(int)$offset;
  $s=db()->prepare($sql);
  $s->execute([$id]);
  foreach($s as $r){
    try { $r['plain']=decrypt_code($r['ciphertext'],$r['nonce'],$key); }
    catch (Throwable $e) {
      error_log('VaultOTP: failed to decrypt code ID '.(int)$r['id'].' in vault '.(int)$id.': '.$e->getMessage());
      $r['plain']='';
      $r['decrypt_error']=true;
    }
    $codes[]=$r;
  }
}
$pageTitle=$new?'New Vault':$vault['name'];include __DIR__.'/app/header.php';
?>
<div class="vault-editor-header">
  <div><div class="eyebrow"><?=$new?'Create vault':'Vault details'?></div><h1><?=$new?'Create a new vault':e($vault['name'])?></h1><p><?= $new?'Choose a service and add its recovery codes.':e($vault['account']?:'Keep your recovery codes safe and easy to access.') ?></p></div>
  <div class="vault-editor-actions">
    <?php if(!$new): ?>
      <?php if(user_can_vault($id,'edit')): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_favorite"><button class="btn" type="submit"><?=((int)$vault['favorite'])?'★ Favorited':'☆ Favorite'?></button></form><?php endif; ?>
      <?php if(user_can_vault($id,'edit')): ?>
      <?php if($editMode): ?><a class="btn" href="vault.php?id=<?=$id?>">Cancel</a><?php else: ?><a class="btn" href="vault.php?id=<?=$id?>&edit=1">✎ Edit vault</a><?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
    <a class="btn" href="index.php">← Back</a>
  </div>
</div>

<?php if(!$new): ?>
<section class="codes-section vault-primary-section">
  <div class="content-section-head compact"><div><div class="section-kicker">Recovery codes</div><h2>Your codes</h2><p>Find the code you need quickly. Filter by status and choose newest or oldest first.</p></div><div class="code-count"><b><?=$totalCodes?></b> total · <b><?=$unused?></b> unused</div></div>
  <div class="code-toolbar">
    <div class="code-filter-tabs" role="tablist" aria-label="Recovery code status">
      <?php foreach(["all"=>"All","unused"=>"Unused","used"=>"Used"] as $f=>$label): ?><a class="code-filter-tab <?=($codeFilter===$f)?'active':''?>" href="vault.php?id=<?=$id?>&status=<?=$f?>&sort=<?=e($codeSort)?>"><?=$label?><?php if($f==='unused'): ?> <span><?=$unused?></span><?php endif; ?></a><?php endforeach; ?>
    </div>
    <label class="code-sort-control"> <span>Sort</span><select onchange="location.href=this.value"><option value="vault.php?id=<?=$id?>&status=<?=e($codeFilter)?>&sort=latest" <?=$codeSort==='latest'?'selected':''?>>Latest first</option><option value="vault.php?id=<?=$id?>&status=<?=e($codeFilter)?>&sort=oldest" <?=$codeSort==='oldest'?'selected':''?>>Oldest first</option></select></label>
  </div>
  <div class="card codes-card"><div class="code-list">
  <?php foreach($codes as $c): ?>
    <article class="code-row <?=((int)$c['used'])?'used':''?>">
      <div class="code-main">
        <span class="code-status-dot" aria-hidden="true"></span>
        <span class="code-value" data-value="<?=e($c['plain'])?>" data-hidden="1"><?=!empty($c['decrypt_error'])?'Unavailable':'••••••••••••'?></span>
      </div>
      <div class="code-actions">
        <?php if(user_can_vault($id,'reveal')): ?><button class="btn" type="button" data-reveal data-code-id="<?=$c['id']?>" <?=!empty($c['decrypt_error'])?'disabled title="This code could not be decrypted."':''?>>Reveal</button><?php endif; ?>
        <?php if(user_can_vault($id,'copy')): ?><button class="btn" type="button" data-copy="<?=e($c['plain'])?>" data-code-id="<?=$c['id']?>" <?=!empty($c['decrypt_error'])?'disabled title="This code could not be decrypted."':''?>>Copy</button><?php endif; ?>
        <?php if(user_can_vault($id,'mark')): ?>
          <?php $canUndo=(int)$c['used']===0 || can_undo_own_mark((int)$c['id']); ?>
          <?php if($canUndo): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_used"><input type="hidden" name="code_id" value="<?=$c['id']?>"><button class="btn" type="submit"><?=((int)$c['used'])?'Mark unused':'Mark used'?></button></form><?php elseif((int)$c['used']===1): ?><span class="code-lock-note" title="This code was marked used by another user or the 15-minute undo window has expired.">Used by another user</span><?php endif; ?>
        <?php endif; ?>
        <?php if(user_can_vault($id,'delete_code')): ?><form method="post" onsubmit="return confirm('Delete this recovery code?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_code"><input type="hidden" name="code_id" value="<?=$c['id']?>"><button class="btn danger" type="submit" aria-label="Delete code" title="Delete code">×</button></form><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  </div><?php if(!$codes): ?><div class="empty"><strong>No recovery codes yet.</strong><br>Add your first codes below.</div><?php endif; ?></div>
  <?php if($totalPages>1): ?><nav class="code-pagination" aria-label="Recovery code pages">
    <?php if($page>1): ?><a class="btn" href="vault.php?id=<?=$id?>&status=<?=e($codeFilter)?>&sort=<?=e($codeSort)?>&page=<?=$page-1?>">← Previous</a><?php else: ?><span class="btn is-disabled">← Previous</span><?php endif; ?>
    <span class="page-status">Page <b><?=$page?></b> of <b><?=$totalPages?></b></span>
    <?php if($page<$totalPages): ?><a class="btn" href="vault.php?id=<?=$id?>&status=<?=e($codeFilter)?>&sort=<?=e($codeSort)?>&page=<?=$page+1?>">Next →</a><?php else: ?><span class="btn is-disabled">Next →</span><?php endif; ?>
  </nav><?php endif; ?>
  <?php if(user_can_vault($id,'add')): ?><div class="card add-codes-card"><div class="content-section-head compact"><div><h2>Add more codes</h2><p>Paste additional codes or import a TXT file.</p></div></div><form method="post" enctype="multipart/form-data" class="add-codes-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_code"><textarea name="codes" placeholder="One code per line…" spellcheck="false"></textarea><div class="add-code-controls"><label class="file-picker"><input type="file" name="code_file" accept=".txt,text/plain"><span>Choose TXT file</span></label><button class="btn primary" type="submit">＋ Add codes</button></div></form></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if($new || $editMode): ?>
<section class="vault-editor card<?=(!$new?' vault-edit-panel':'')?>">
  <div class="editor-intro"><div class="editor-icon">✦</div><div><h2><?= $new?'Vault information':'Edit vault information' ?></h2><p><?= $new?'Choose the service, account details and recovery codes to secure.':'Update the vault details. Your recovery codes stay encrypted at rest.' ?></p></div></div>
  <form method="post" enctype="multipart/form-data" class="vault-form">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=$new?'create':'edit'?>">
    <div class="vault-form-grid">
      <div class="field field-wide"><label>PLATFORM / SERVICE</label>
        <div class="platform-picker" data-platform-picker>
          <button class="platform-picker-trigger" type="button" aria-expanded="false" data-platform-trigger><span class="selected-platform"><span data-selected-platform-icon></span><span data-selected-platform>Choose a platform…</span></span><span class="picker-chevron">⌄</span></button>
          <div class="platform-picker-menu" data-platform-menu><input class="platform-picker-search" type="search" placeholder="Search platforms…" autocomplete="off"><div class="platform-picker-options">
          <?php foreach($platformList as $platform):$selected=$vault['name']===$platform['name']; ?><button class="platform-option" type="button" data-platform-option data-name="<?=e($platform['name'])?>" data-image="<?=e($platform['icon'])?>" data-selected="<?=$selected?'1':'0'?>"><?=platform_image($platform,'platform-option-icon')?><span><?=e($platform['name'])?></span></button><?php endforeach; ?>
          <?php if(!$new&&!$matched): ?><button class="platform-option" type="button" data-platform-option data-name="<?=e($vault['name'])?>" data-image="" data-selected="1"><span class="platform-option-icon platform-fallback">•</span><span><?=e($vault['name'])?> <small>(current custom)</small></span></button><?php endif; ?>
          <?php if(can('manage_platforms')): ?><button class="platform-option platform-manage-option" type="button" data-platform-manage><span class="platform-option-icon platform-fallback">+</span><span>Custom platform <small>Manage on Platforms</small></span><span class="platform-option-arrow">↗</span></button><?php endif; ?>
          </div></div>
          <input type="hidden" name="name" id="platform-select" value="<?=e((!$new&&$vault['name']!=='')?$vault['name']:'')?>">
        </div>
      </div>
      <div class="field"><label>ACCOUNT / EMAIL <span>OPTIONAL</span></label><input name="account" value="<?=e($vault['account'])?>" placeholder="you@example.com" autocomplete="off"></div>
      <div class="field"><label>CATEGORY</label><input name="category" value="<?=e($vault['category'])?>" placeholder="General"></div>
      <?php if($new || user_can_vault($id,'add')): ?><div class="field"><label>TXT IMPORT <span>MAX 1 MB</span></label><label class="file-picker vault-file-picker"><input type="file" name="code_file" accept=".txt,text/plain"><span>Choose a TXT file</span></label></div>
      <div class="field field-wide"><div class="field-label-row"><label>RECOVERY CODES</label><span>One code per line</span></div><textarea name="codes" placeholder="Paste recovery codes here…" spellcheck="false"></textarea><small>Duplicates are skipped automatically. Codes are encrypted at rest.</small></div><?php endif; ?>
      <div class="field field-wide"><label>NOTES <span>OPTIONAL</span></label><textarea class="notes-area" name="notes" placeholder="Add a private note for this vault…"><?=e($vault['notes'])?></textarea></div>
    </div>
    <div class="vault-form-footer"><div><strong><?= $new?'Ready to secure your codes?':'Save your changes' ?></strong><span>Your recovery codes remain encrypted at rest.</span></div><button class="btn primary vault-submit" type="submit"><?=$new?'Create vault':'Save changes'?> <span>→</span></button></div>
  </form>
</section>
<?php endif; ?>

<?php if(!$new && user_can_vault($id,'delete')): ?>
<section class="card danger-zone"><div><h2>Danger zone</h2><p>Permanently delete this vault and every recovery code stored inside it.</p></div><form method="post" onsubmit="return confirm('Delete the entire vault and all recovery codes? This cannot be undone.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_vault"><button class="btn danger" type="submit">Delete vault</button></form></section>
<?php endif; ?>
<?php include __DIR__.'/app/footer.php'; ?>
