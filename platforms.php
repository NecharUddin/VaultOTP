<?php
/** VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */
require_once __DIR__.'/app/config.php'; init_db(); require_login(); require_permission('manage_platforms');

$defaults = [
    'Google'=>'google.png','Facebook'=>'facebook.png','Instagram'=>'instagram.png','GitHub'=>'github.png',
    'Discord'=>'discord.png','Microsoft'=>'microsoft.png','Apple'=>'apple.png','X'=>'x.png','Reddit'=>'reddit.png',
    'LinkedIn'=>'linkedin.png','Steam'=>'steam.png','Amazon'=>'amazon.png','Dropbox'=>'dropbox.png','PayPal'=>'paypal.png',
    'Binance'=>'binance.png','OpenAI'=>'openai.png','Twitch'=>'twitch.png','Notion'=>'notion.png','Proton'=>'proton.png','Cloudflare'=>'cloudflare.png'
];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_permission('manage_platforms');
    verify_csrf();
    $action=$_POST['action']??'';

    if ($action==='add_platform') {
        $name=trim((string)($_POST['platform_name']??''));
        if ($name==='' || strlen($name)>100) { flash('error','Enter a platform name up to 100 characters.'); redirect('platforms.php'); }
        $icon='';
        if (!empty($_FILES['platform_icon']['tmp_name']) && ($_FILES['platform_icon']['error']??4)===0) {
            if (($_FILES['platform_icon']['size']??0)>262144) { flash('error','PNG icon must be 256 KB or smaller.'); redirect('platforms.php'); }
            $info=@getimagesize($_FILES['platform_icon']['tmp_name']);
            if (!$info || ($info['mime']??'')!=='image/png') { flash('error','Only PNG images are supported.'); redirect('platforms.php'); }
            if (($info[0]??0)>512 || ($info[1]??0)>512) { flash('error','PNG icon must be 512×512 or smaller.'); redirect('platforms.php'); }
            $slug=platform_slug($name); $filename=$slug.'-'.bin2hex(random_bytes(3)).'.png';
            $dir=__DIR__.'/assets/platforms'; if (!is_dir($dir)) @mkdir($dir,0755,true);
            if (!move_uploaded_file($_FILES['platform_icon']['tmp_name'],$dir.'/'.$filename)) { flash('error','Could not save the icon.'); redirect('platforms.php'); }
            $icon=$filename;
        }
        try {
            db()->prepare('INSERT INTO platforms(name,icon,is_default,enabled,created_at) VALUES(?,?,0,1,?)')->execute([$name,$icon,time()]);
            log_activity('Added platform'); flash('success','Platform added.');
        } catch(PDOException $e) {
            if($icon && is_file(__DIR__.'/assets/platforms/'.$icon)) @unlink(__DIR__.'/assets/platforms/'.$icon);
            flash('error','A platform with that name already exists.');
        }
        redirect('platforms.php');
    }

    if ($action==='delete_platform') {
        $id=(int)($_POST['platform_id']??0); $platform=get_platform($id);
        if($platform){
            $icon=(string)$platform['icon'];
            if ((int)$platform['is_default']===1) {
                db()->prepare('UPDATE platforms SET enabled=0 WHERE id=?')->execute([$id]);
                log_activity('Removed default platform');
                flash('success','Platform hidden. You can restore it from the default library.');
            } else {
                db()->prepare('DELETE FROM platforms WHERE id=?')->execute([$id]);
                if($icon && is_file(__DIR__.'/assets/platforms/'.$icon)) @unlink(__DIR__.'/assets/platforms/'.$icon);
                log_activity('Removed platform');
                flash('success','Custom platform removed. Existing vaults were not changed.');
            }
        }
        redirect('platforms.php');
    }

    if ($action==='restore_defaults') {
        $up=db()->prepare('UPDATE platforms SET enabled=1, icon=? WHERE name=? AND is_default=1');
        $ins=db()->prepare('INSERT IGNORE INTO platforms(name,icon,is_default,enabled,created_at) VALUES(?,?,?,?,?)');
        $now=time(); $restored=0;
        foreach($defaults as $name=>$icon){
            $before=platform_by_name($name);
            if($before && (int)$before['enabled']===0) $restored++;
            if($before) $up->execute([$icon,$name]); else $ins->execute([$name,$icon,1,1,$now]);
        }
        log_activity('Restored default platforms');
        flash('success',$restored>0 ? $restored.' default platform'.($restored===1?' was':'s were').' restored.' : 'All default platforms are already available.');
        redirect('platforms.php');
    }
}

$list=platforms();
$removed=removed_default_platforms();
$builtIn=[]; $custom=[];
foreach($list as $p) { if((int)$p['is_default']===1) $builtIn[]=$p; else $custom[]=$p; }
$pageTitle='Platforms'; include __DIR__.'/app/header.php';
?>
<div class="topbar platform-topbar">
  <div><div class="eyebrow">Platform library</div><div class="title">Platforms</div><p class="subtitle">Manage the services available when you create a vault.</p></div>
  <div class="actions"><button class="btn theme-btn" data-theme-toggle type="button"><span data-theme-label>◐ Dark mode</span></button><?php if(can('create_vault')): ?><a class="btn primary" href="vault.php?new=1">＋ New vault</a><?php endif; ?></div>
</div>

<section class="platform-hero card">
  <div class="platform-hero-copy">
    <div class="platform-hero-icon">✦</div>
    <div><h2>Platform library</h2><p>Keep your most-used services ready with their own icons. Add custom services whenever you need them.</p></div>
  </div>
  <div class="platform-stats"><div><strong><?=count($list)?></strong><span>Available</span></div><div><strong><?=count($removed)?></strong><span>Hidden defaults</span></div></div>
</section>

<?php if(can('manage_platforms')): ?><section class="card platform-create-card">
  <div class="section-head"><div><b>Add a platform</b><p class="subtitle" style="margin:5px 0 0">Create a custom service with an optional PNG icon.</p></div></div>
  <form method="post" enctype="multipart/form-data" class="platform-create-form">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_platform">
    <div class="field"><label>PLATFORM NAME</label><input name="platform_name" maxlength="100" required placeholder="e.g. My Service"></div>
    <div class="field"><label>PNG ICON <span class="muted">OPTIONAL</span></label><label class="image-picker"><input type="file" name="platform_icon" accept="image/png"><span data-file-name>Choose PNG icon</span><small>PNG · max 512×512 · 256 KB</small></label></div>
    <div class="platform-create-submit"><button class="btn primary" type="submit">Add platform</button></div>
  </form>
</section><?php endif; ?>

<section class="platform-section">
  <div class="platform-section-head"><div><h2>Default platforms</h2><p>Popular services included with VaultOTP.</p></div><div class="platform-section-count"><?=count($builtIn)?> active</div></div>
  <?php if($builtIn): ?><div class="platform-library-grid platform-library-grid-modern">
    <?php foreach($builtIn as $p): ?>
      <article class="platform-card platform-card-modern">
        <?=platform_image($p,'platform-card-icon')?>
        <div class="platform-card-body"><strong><?=e($p['name'])?></strong><small>Included by default</small></div>
        <?php if(can('manage_platforms')): ?><form method="post" onsubmit="return confirm('Hide <?=e($p['name'])?> from the platform library? You can restore it later.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_platform"><input type="hidden" name="platform_id" value="<?=$p['id']?>"><button class="icon-btn platform-remove" type="submit" aria-label="Hide platform" title="Hide platform">×</button></form><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div><?php else: ?><div class="platform-empty card"><strong>All default platforms are hidden.</strong><span>Use Restore defaults to bring them back.</span></div><?php endif; ?>
</section>

<section class="platform-section custom-platform-section">
  <div class="platform-section-head"><div><h2>Your platforms</h2><p>Custom services you have added.</p></div><div class="platform-section-count"><?=count($custom)?> custom</div></div>
  <?php if($custom): ?><div class="platform-library-grid platform-library-grid-modern">
    <?php foreach($custom as $p): ?>
      <article class="platform-card platform-card-modern">
        <?=platform_image($p,'platform-card-icon')?>
        <div class="platform-card-body"><strong><?=e($p['name'])?></strong><small>Custom platform</small></div>
        <?php if(can('manage_platforms')): ?><form method="post" onsubmit="return confirm('Remove <?=e($p['name'])?>? Existing vaults will not be changed.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_platform"><input type="hidden" name="platform_id" value="<?=$p['id']?>"><button class="icon-btn platform-remove" type="submit" aria-label="Remove platform" title="Remove platform">×</button></form><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div><?php else: ?><div class="platform-empty card"><strong>No custom platforms yet.</strong><span>Use the form above to add your own service.</span></div><?php endif; ?>
</section>

<?php if(can('manage_platforms')): ?><section class="platform-restore card">
  <div><strong>Restore default platforms</strong><p>Bring back any built-in services you previously hid.</p></div>
  <form method="post" onsubmit="return confirm('Restore all hidden default platforms?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore_defaults"><button class="btn" type="submit">Restore defaults<?=count($removed)?' · '.count($removed):''?></button></form>
</section><?php endif; ?>

<?php include __DIR__.'/app/footer.php'; ?>
<script>
document.querySelectorAll('input[type=file][name=platform_icon]').forEach(input=>input.addEventListener('change',()=>{const el=input.closest('.image-picker')?.querySelector('[data-file-name]');if(el)el.textContent=input.files[0]?.name||'Choose PNG icon'}));
</script>
