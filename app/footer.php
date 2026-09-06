<?php /* VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */ ?></main><footer class="site-footer"><span>Built by <a href="https://itnexbd.com" target="_blank" rel="noopener noreferrer">ItNexBD</a></span></footer>
<script>
(()=>{
const updateThemeButtons=()=>{
  const getTheme=()=>window.VaultOTPTheme?window.VaultOTPTheme.get():(document.documentElement.getAttribute('data-theme')==='dark'?'dark':'light');
  document.querySelectorAll('[data-theme-toggle]').forEach(b=>{
    const label=b.querySelector('[data-theme-label]');
    if(label)label.textContent=getTheme()==='dark'?'◐ Light mode':'◐ Dark mode';
    b.setAttribute('aria-label',getTheme()==='dark'?'Switch to light mode':'Switch to dark mode');
  });
};
updateThemeButtons();
const csrf=document.querySelector('input[name="csrf"]')?.value||'';
const vaultId=new URLSearchParams(location.search).get('id');
const logEvent=(event,codeId)=>{if(!vaultId||!csrf)return;const body=new URLSearchParams({csrf,action:'log_event',event,code_id:String(codeId||0)});fetch(`vault.php?id=${encodeURIComponent(vaultId)}`,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body,credentials:'same-origin',keepalive:true}).catch(()=>{});};
document.querySelectorAll('[data-copy]').forEach(b=>b.onclick=async()=>{try{await navigator.clipboard.writeText(b.dataset.copy||'');const old=b.textContent;b.textContent='Copied';b.classList.add('copied');logEvent('Copied recovery code',b.dataset.codeId);setTimeout(()=>{b.textContent=old;b.classList.remove('copied')},1200)}catch(e){alert('Copy failed')}});
document.querySelectorAll('[data-reveal]').forEach(b=>b.onclick=()=>{const row=b.closest('.code-row'),v=row?.querySelector('.code-value');if(!v)return;const hidden=v.dataset.hidden==='1';if(hidden){v.textContent=v.dataset.value;b.textContent='Hide';v.dataset.hidden='0';logEvent('Revealed recovery code',b.dataset.codeId);setTimeout(()=>{if(v.dataset.hidden==='0'){v.textContent='••••••••••••';v.dataset.hidden='1';b.textContent='Reveal'}},15000)}else{v.textContent='••••••••••••';v.dataset.hidden='1';b.textContent='Reveal'}});
const picker=document.querySelector('[data-platform-picker]');if(picker){const trigger=picker.querySelector('[data-platform-trigger]'),hidden=document.querySelector('#platform-select'),search=picker.querySelector('.platform-picker-search'),options=[...picker.querySelectorAll('[data-platform-option]')],label=picker.querySelector('[data-selected-platform]'),icon=picker.querySelector('[data-selected-platform-icon]');const choose=(o)=>{const name=o.dataset.name||'';hidden.value=name;label.textContent=o.querySelector(':scope > span:last-of-type')?.textContent?.replace(/\s*\(current custom\).*$/,'')||name;icon.innerHTML='';const img=o.querySelector('img');if(img){const clone=img.cloneNode(true);icon.appendChild(clone)}else{const fallback=document.createElement('span');fallback.className='platform-fallback';fallback.textContent='•';icon.appendChild(fallback)}options.forEach(x=>x.classList.remove('selected'));o.classList.add('selected');picker.classList.remove('open');trigger.setAttribute('aria-expanded','false')};const selected=options.find(o=>o.dataset.selected==='1');if(selected)choose(selected);else{hidden.value='';label.textContent='Choose a platform…';icon.innerHTML=''}trigger?.addEventListener('click',()=>{picker.classList.toggle('open');trigger.setAttribute('aria-expanded',picker.classList.contains('open')?'true':'false');if(picker.classList.contains('open'))search?.focus()});options.forEach(o=>o.addEventListener('click',()=>choose(o)));picker.querySelector('[data-platform-manage]')?.addEventListener('click',()=>{window.location.href='platforms.php'});search?.addEventListener('input',()=>{const q=search.value.toLowerCase().trim();options.forEach(o=>{const text=(o.textContent||'').toLowerCase();o.style.display=(!q||text.includes(q))?'flex':'none'})});document.addEventListener('click',e=>{if(!picker.contains(e.target)){picker.classList.remove('open');trigger?.setAttribute('aria-expanded','false')}})}
// Show selected upload filenames consistently across all file pickers.
document.querySelectorAll('input[type=file]').forEach(input=>{
  input.addEventListener('change',()=>{
    const file=input.files?.[0];
    const picker=input.closest('.file-picker,.image-picker');
    if(!picker)return;
    const label=picker.querySelector('[data-file-name]') || picker.querySelector(':scope > span');
    if(!label)return;
    if(!file){
      label.textContent=picker.classList.contains('image-picker')?'Choose PNG icon':(input.accept||'').includes('votp')?'Choose a .votp backup file':(input.accept||'').includes('txt')?'Choose TXT file':'Choose file';
      picker.classList.remove('file-selected');
      return;
    }
    const size=file.size<1024?`${file.size} B`:file.size<1048576?`${(file.size/1024).toFixed(1)} KB`:`${(file.size/1048576).toFixed(1)} MB`;
    label.textContent=`${file.name} · ${size}`;
    picker.classList.add('file-selected');
    picker.title=file.name;
  });
});
const menu=document.querySelector('[data-menu]'),side=document.querySelector('.sidebar');if(menu&&side)menu.onclick=()=>side.classList.toggle('open');
const globalSearch=document.querySelector('[data-global-search]'),modal=document.querySelector('[data-search-modal]'),input=document.querySelector('[data-search-input]'),searchClose=document.querySelector('[data-search-close]'),searchResults=document.querySelector('[data-search-results]');
let searchIndex=-1;
const searchItems=()=>searchResults?[...searchResults.querySelectorAll('[data-search-item]:not([hidden])')]:[];
function highlightSearch(){const items=searchItems();items.forEach((item,i)=>item.classList.toggle('keyboard-active',i===searchIndex));if(searchIndex>=0&&items[searchIndex])items[searchIndex].scrollIntoView({block:'nearest'});}
function openSearch(){if(!modal)return;modal.classList.add('show');modal.setAttribute('aria-hidden','false');document.body.classList.add('search-open');searchIndex=-1;if(input){input.value='';input.dispatchEvent(new Event('input'));setTimeout(()=>input.focus(),20)}}
function closeSearch(){if(!modal)return;modal.classList.remove('show');modal.setAttribute('aria-hidden','true');document.body.classList.remove('search-open');searchIndex=-1;input?.blur();globalSearch?.focus()}
function toggleSearch(){modal?.classList.contains('show')?closeSearch():openSearch()}
globalSearch?.addEventListener('click',toggleSearch);searchClose?.addEventListener('click',closeSearch);modal?.addEventListener('click',e=>{if(e.target===modal)closeSearch()});
input?.addEventListener('input',()=>{if(!searchResults)return;const q=input.value.toLowerCase().trim(),tokens=q.split(/\s+/).filter(Boolean),items=[...searchResults.querySelectorAll('[data-search-item]')],empty=searchResults.querySelector('[data-search-empty]');let visible=0;items.forEach(item=>{const text=item.dataset.searchText||item.textContent.toLowerCase();const match=tokens.every(t=>text.includes(t));item.hidden=!match;if(match)visible++});searchIndex=visible?0:-1;if(empty)empty.hidden=visible!==0;highlightSearch()});
searchResults?.addEventListener('mousemove',e=>{const item=e.target.closest('[data-search-item]');if(!item)return;const items=searchItems(),i=items.indexOf(item);if(i>=0){searchIndex=i;highlightSearch()}});
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();toggleSearch();return}if(!modal?.classList.contains('show'))return;if(e.key==='Escape'){e.preventDefault();closeSearch();return}if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();const items=searchItems();if(!items.length)return;searchIndex=(searchIndex+(e.key==='ArrowDown'?1:-1)+items.length)%items.length;highlightSearch();return}if(e.key==='Enter'){const items=searchItems();if(items.length&&searchIndex>=0){e.preventDefault();items[searchIndex].click()}}});
const lockSeconds=parseInt(document.body.dataset.autolock||'0',10);if(lockSeconds>0&&!document.body.dataset.authpage){let timer;const reset=()=>{clearTimeout(timer);timer=setTimeout(()=>location.href='logout.php?reason=timeout',lockSeconds*1000)};['click','keydown','mousemove','touchstart','scroll'].forEach(e=>window.addEventListener(e,reset,{passive:true}));reset();}
const toast=document.querySelector('.flash');if(toast)setTimeout(()=>toast.remove(),5000);
})();
</script></body></html>
