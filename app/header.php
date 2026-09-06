<?php
/** VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */
$pageTitle=$pageTitle??'VaultOTP';$flashes=flashes();$scriptName=basename($_SERVER['SCRIPT_NAME']??'');$currentPage=$scriptName;$isAuthPage=in_array($scriptName,['setup.php','login.php'],true);$layoutClass=$isAuthPage?'auth-main':(is_logged_in()?'main':'auth-main');
$autoLock=(!$isAuthPage&&is_logged_in())?get_auto_lock():0;?><!doctype html><html lang="en" data-theme="light" class="vaultotp-root"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="author" content="ItNexBD"><meta name="generator" content="VaultOTP"><script>
(function(){
  "use strict";
  var root=document.documentElement;
  var saved="light";
  try { saved=localStorage.getItem("vaultotp-theme") || "light"; } catch(e) {}
  if(saved !== "dark" && saved !== "light") saved="light";
  root.setAttribute("data-theme", saved);

  function applyTheme(theme){
    var next = theme === "dark" ? "dark" : "light";
    root.setAttribute("data-theme", next);
    root.classList.toggle("theme-dark", next === "dark");
    root.classList.toggle("theme-light", next === "light");
    try { localStorage.setItem("vaultotp-theme", next); } catch(e) {}
    var buttons=document.querySelectorAll("[data-theme-toggle]");
    for(var i=0;i<buttons.length;i++){
      var label=buttons[i].querySelector("[data-theme-label]");
      if(label) label.textContent=next === "dark" ? "◐ Light mode" : "◐ Dark mode";
      buttons[i].setAttribute("aria-label", next === "dark" ? "Switch to light mode" : "Switch to dark mode");
    }
  }

  window.VaultOTPTheme={
    get:function(){ return root.getAttribute("data-theme") === "dark" ? "dark" : "light"; },
    set:applyTheme,
    toggle:function(){ applyTheme(this.get() === "dark" ? "light" : "dark"); }
  };

  // Bind in the head as well as the footer so setup/login never depend on footer JS.
  document.addEventListener("click", function(event){
    var button=event.target.closest ? event.target.closest("[data-theme-toggle]") : null;
    if(!button) return;
    event.preventDefault();
    event.stopPropagation();
    window.VaultOTPTheme.toggle();
  }, true);

  applyTheme(saved);
})();
</script><title><?=e($pageTitle)?> · VaultOTP</title><link rel="icon" type="image/png" href="favicon.png"><link rel="apple-touch-icon" href="favicon.png"><link rel="stylesheet" href="assets/app.css"></head><body data-autolock="<?=e((string)$autoLock)?>" data-authpage="<?= $isAuthPage?'1':'0' ?>">
<?php if(is_logged_in()&&!$isAuthPage): ?><aside class="sidebar"><a class="brand" href="index.php">VaultOTP</a><nav><a class="<?= $currentPage==='index.php' ? 'nav-active' : '' ?>" href="index.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10.5 12 4l8 6.5v8a1 1 0 0 1-1 1h-5v-5H10v5H5a1 1 0 0 1-1-1z"/></svg></span><i>Home</i></a><a class="<?= $currentPage==='vaults.php' ? 'nav-active' : '' ?>" href="vaults.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h4l2 2H17.5A2.5 2.5 0 0 1 20 9.5v7A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5zM6 9h12"/></svg></span><i>Vaults</i></a><?php if(can('manage_platforms')): ?><a class="<?= $currentPage==='platforms.php' ? 'nav-active' : '' ?>" href="platforms.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 3v5h10V6H7Zm0 8v5h4v-5H7Zm6 0v5h4v-5h-4Z"/></svg></span><i>Platforms</i></a><?php endif; ?><a class="<?= $currentPage==='settings.php' ? 'nav-active' : '' ?>" href="settings.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.6 3h4.8l.7 2.1 1.8 1 2.1-.5 2.4 4.1-1.5 1.6v2.1l1.5 1.6-2.4 4.1-2.1-.5-1.8 1-.7 2.1H9.6l-.7-2.1-1.8-1-2.1.5-2.4-4.1 1.5-1.6v-2.1L2.6 9.7 5 5.6l2.1.5 1.8-1zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg></span><i>Settings</i></a><?php if(is_owner()): ?><a class="<?= $currentPage==='activities.php' ? 'nav-active' : '' ?>" href="activities.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm3 2v2h10V7H7Zm0 4v2h7v-2H7Zm0 4v2h10v-2H7Z"/></svg></span><i>Activities</i></a><?php endif; ?><?php if(is_owner()): ?><a class="<?= $currentPage==='users.php' ? 'nav-active' : '' ?>" href="users.php"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-3.9-4.8A4 4 0 0 0 16 11Zm-8 0a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm8 2c-3.3 0-6 1.7-6 4v2h12v-2c0-2.3-2.7-4-6-4ZM8 13c-2.8 0-5 1.5-5 3.5V19h5v-1c0-1.6.8-3 2.2-4A8.7 8.7 0 0 0 8 13Z"/></svg></span><i>Users</i></a><?php endif; ?></nav><div class="sidebottom"><small class="side-user"><b><?=e(user_display_name())?></b><span><?=is_owner()?'Owner':e(ucfirst(current_role()))?></span></small><small title="Your vault data is encrypted at rest">● Encrypted &amp; protected</small><a href="logout.php">↪ <i>Lock Vault</i></a></div></aside><button class="mobile-menu" type="button" data-menu>☰</button><?php endif; ?><main class="<?=$layoutClass?>"><?php foreach($flashes as $f): ?><div class="flash <?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach; ?>
