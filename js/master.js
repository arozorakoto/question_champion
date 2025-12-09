// js/master.js
const api = 'api.php';
const playersGrid = document.getElementById('playersGrid');
const playlistArea = document.getElementById('playlistArea');
const btnLoadPlaylist = document.getElementById('btnLoadPlaylist');
const btnSetQuestion = document.getElementById('btnSetQuestion');
const manualQuestion = document.getElementById('manualQuestion');

btnLoadPlaylist.addEventListener('click', ()=>{
  const lines = playlistArea.value.split('\n').map(s=>s.trim()).filter(s=>s);
  fetch(api, {method:'POST', body:new URLSearchParams({action:'set_question', playlist: JSON.stringify(lines)})})
    .then(r=>r.json()).then(()=>console.log('playlist saved'));
});

btnSetQuestion.addEventListener('click', ()=>{
  const q = manualQuestion.value.trim();
  if (!q) return alert('Question vide');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'set_question', question:q})})
    .then(r=>r.json()).then(()=>{ manualQuestion.value=''; });
});

function pollMaster(){
  fetch(api + '?action=get_state').then(r=>r.json()).then(data=>{
    if (!data.ok) return;
    const state = data.state;
    renderPlayers(state.players, state.current_question);
  }).catch(e=>console.error(e));
}

function renderPlayers(players, question){
  playersGrid.innerHTML = '';
  for (const id in players) {
    const p = players[id];
    const div = document.createElement('div');
    div.className = 'player-card';
    const img = document.createElement('img');
    img.width=220; img.height=165;
    img.alt = p.name;
    // add cache-buster
    img.src = 'snapshots/' + id + '.jpg?ts=' + (p.last_snapshot || 0);
    const name = document.createElement('div'); name.textContent = p.name;
    const buzz = document.createElement('div'); buzz.textContent = p.buzzed ? 'BUZZED' : '---';
    buzz.className = p.buzzed ? 'buzzed' : 'not-buzzed';
    const btnRelease = document.createElement('button');
    btnRelease.textContent = 'Release';
    btnRelease.onclick = ()=> {
      fetch(api, {method:'POST', body:new URLSearchParams({action:'release_buzz', id:p.id})})
        .then(r=>r.json()).then(()=> pollMaster());
    };
    div.appendChild(img);
    div.appendChild(name);
    div.appendChild(buzz);
    div.appendChild(btnRelease);
    playersGrid.appendChild(div);
  }
}

pollMaster();
setInterval(pollMaster, 1500);
