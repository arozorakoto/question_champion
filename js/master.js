// js/master.js
const api = 'api.php';
const loginBox = document.getElementById('loginBox');
const masterArea = document.getElementById('masterArea');
const btnMasterLogin = document.getElementById('btnMasterLogin');
const masterPass = document.getElementById('masterPass');
const btnLogout = document.getElementById('btnLogout');

const playlistArea = document.getElementById('playlistArea');
const btnLoadPlaylist = document.getElementById('btnLoadPlaylist');
const btnStart = document.getElementById('btnStart');
const btnNext = document.getElementById('btnNext');
const btnFinish = document.getElementById('btnFinish');
const btnAddManual = document.getElementById('btnAddManual');
const manualText = document.getElementById('manualText');
const manualOptions = document.getElementById('manualOptions');
const manualCorrect = document.getElementById('manualCorrect');
const manualDuration = document.getElementById('manualDuration');

const playersGrid = document.getElementById('playersGrid');
const curQuestionText = document.getElementById('curQuestionText');
const curStatus = document.getElementById('curStatus');
const curTimer = document.getElementById('curTimer');
const curIndex = document.getElementById('curIndex');

btnMasterLogin.addEventListener('click', ()=>{
  const pw = masterPass.value.trim();
  if (!pw) return alert('Entrer mot de passe');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'master_login', password:pw})})
    .then(r=>r.json()).then(data=>{
      if (data.ok) {
        loginBox.style.display='none';
        masterArea.style.display='block';
        pollMaster();
        setInterval(pollMaster, 1000);
      } else alert('Erreur login: ' + (data.msg||''));
    });
});

btnLogout.addEventListener('click', ()=>{
  fetch(api, {method:'POST', body:new URLSearchParams({action:'master_logout'})})
    .then(()=> location.reload());
});

btnLoadPlaylist.addEventListener('click', ()=>{
  const payload = playlistArea.value.trim();
  if (!payload) return alert('Rien à charger');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'set_playlist', playlist: payload})})
    .then(r=>r.json()).then(data=>{
      if (data.ok) {
        alert('Playlist chargée (' + data.playlist_count + ' questions)');
        playlistArea.value = '';
        pollMaster();
      } else alert('Erreur: ' + (data.msg||''));
    });
});

btnAddManual.addEventListener('click', ()=>{
  const text = manualText.value.trim();
  const opts = manualOptions.value.trim();
  const correct = manualCorrect.value.trim();
  const duration = manualDuration.value.trim();
  if (!text || !opts) return alert('text & options requis');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'set_current_manual', text:text, options:opts, correct: correct||0, duration: duration||15})})
    .then(r=>r.json()).then(data=>{
      if (data.ok) { alert('Ajouté index ' + data.index); manualText.value=''; manualOptions.value=''; manualCorrect.value=''; manualDuration.value=''; pollMaster(); }
      else alert('Erreur');
    });
});

btnStart.addEventListener('click', ()=>{
  const idx = parseInt(prompt('Index de la question à démarrer (0-based):','0'),10);
  if (isNaN(idx)) return;
  fetch(api, {method:'POST', body:new URLSearchParams({action:'start_question', index: idx})})
    .then(r=>r.json()).then(data=>{
      if (!data.ok) alert('Erreur: ' + (data.msg||''));
      pollMaster();
    });
});

btnNext.addEventListener('click', ()=>{
  // compute next index from state (we'll call get_state and then start next)
  fetch(api + '?action=get_state').then(r=>r.json()).then(d=>{
    if (!d.ok) return;
    const st = d.state;
    let next = st.current_index + 1;
    if (next < 0) next = 0;
    if (next >= st.playlist.length) return alert('Plus de questions');
    fetch(api, {method:'POST', body:new URLSearchParams({action:'start_question', index: next})})
      .then(r=>r.json()).then(p=>{
        pollMaster();
      });
  });
});

btnFinish.addEventListener('click', ()=>{
  if (!confirm('Terminer la question en cours ?')) return;
  fetch(api, {method:'POST', body:new URLSearchParams({action:'finish_question'})})
    .then(()=> pollMaster());
});

function pollMaster(){
  fetch(api + '?action=get_state').then(r=>r.json()).then(data=>{
    if (!data.ok) return;
    const st = data.state;
    renderMaster(st);
  }).catch(e=>console.error(e));
}

function renderMaster(st) {
  // current question and timer
  const ci = st.current_index;
  curIndex.textContent = ci;
  if (ci >=0 && st.playlist[ci]) {
    curQuestionText.textContent = st.playlist[ci].text;
    curStatus.textContent = st.current_status;
    // compute remaining
    if (st.current_status === 'running') {
      const dur = parseInt(st.playlist[ci].duration || 0);
      const elapsed = Math.floor(Date.now()/1000) - st.started_at;
      const remaining = Math.max(0, dur - elapsed);
      curTimer.textContent = secToStr(remaining);
    } else if (st.current_status === 'finished') {
      curTimer.textContent = '00:00';
    } else curTimer.textContent = '--';
  } else {
    curQuestionText.textContent = '---';
    curStatus.textContent = st.current_status;
    curTimer.textContent = '--';
  }

  // players
  playersGrid.innerHTML = '';
  for (const id in st.players) {
    const p = st.players[id];
    const col = document.createElement('div');
    col.className = 'col-12';
    const card = document.createElement('div');
    card.className = 'd-flex align-items-center gap-2 p-2 border rounded';
    const img = document.createElement('img');
    img.src = 'snapshots/' + id + '.jpg?ts=' + (p.last_snapshot || 0);
    img.style.width='120px'; img.style.height='90px'; img.style.objectFit='cover'; img.className='rounded';
    const info = document.createElement('div');
    info.innerHTML = `<strong>${p.name}</strong><br>Score: ${p.score || 0}<br>Buzz: ${p.buzzed ? '<span class="badge bg-danger">BUZZED</span>' : '<span class="badge bg-secondary">---</span>'}`;
    const actions = document.createElement('div');
    actions.className = 'ms-auto';
    const releaseBtn = document.createElement('button');
    releaseBtn.className = 'btn btn-sm btn-outline-secondary';
    releaseBtn.textContent = 'Release';
    releaseBtn.onclick = ()=> {
      fetch(api, {method:'POST', body:new URLSearchParams({action:'release_buzz', id: id})})
        .then(()=> pollMaster());
    };
    actions.appendChild(releaseBtn);
    card.appendChild(img);
    card.appendChild(info);
    card.appendChild(actions);
    col.appendChild(card);
    playersGrid.appendChild(col);
  }
}

function secToStr(s) {
  const mm = String(Math.floor(s/60)).padStart(2,'0');
  const ss = String(s%60).padStart(2,'0');
  return `${mm}:${ss}`;
}

// initial poll to check session
pollMaster();

