// js/player.js
const api = 'api.php';
let myId = localStorage.getItem('player_id') || null;
let myName = localStorage.getItem('player_name') || null;

const registerBox = document.getElementById('registerBox');
const gameBox = document.getElementById('game');
const btnRegister = document.getElementById('btnRegister');
const nameInput = document.getElementById('name');
const meName = document.getElementById('meName');
const meScore = document.getElementById('meScore');
const questionText = document.getElementById('questionText');
const qcmArea = document.getElementById('qcmArea');
const btnBuzz = document.getElementById('btnBuzz');
const buzzState = document.getElementById('buzzState');
const timerEl = document.getElementById('timer');
const qstatusEl = document.getElementById('qstatus');
const video = document.getElementById('video');

btnRegister.addEventListener('click', register);

function register(){
  const name = nameInput.value.trim();
  if (!name) return alert('Entrez un pseudo');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'register', name})})
    .then(r=>r.json()).then(data=>{
      if (data.ok) {
        myId = data.id; myName = name;
        localStorage.setItem('player_id', myId);
        localStorage.setItem('player_name', myName);
        startGame();
      } else alert('Erreur inscription: ' + (data.msg||''));
    });
}

function startGame() {
  registerBox.style.display='none';
  gameBox.style.display='block';
  meName.textContent = myName;
  initCamera();
  pollState();
  setInterval(pollState, 1000); // poll every 1s
  setInterval(sendSnapshot, 4000);
}

btnBuzz.addEventListener('click', ()=>{
  if (!myId) return alert('Enregistrez-vous d\'abord');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'buzz', id:myId})})
    .then(r=>r.json()).then(data=>{
      if (!data.ok) {
        alert('Buzz refusé: ' + (data.msg||''));
      } else {
        buzzState.textContent = 'Buzzé !';
        buzzState.className = 'badge bg-danger';
      }
    });
});

// handle QCM choice click
function submitChoice(choiceIndex) {
  if (!myId) return alert('Enregistrez-vous d\'abord');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'submit_answer', id:myId, choice:choiceIndex})})
    .then(r=>r.json()).then(data=>{
      if (!data.ok) {
        alert('Réponse non acceptée: ' + (data.msg||''));
      } else {
        // feedback
        if (data.correct) {
          alert('Bonne réponse !');
        } else {
          alert('Mauvaise réponse.');
        }
      }
    });
}

function pollState() {
  const url = api + '?action=get_state&id=' + encodeURIComponent(myId||'');
  fetch(url).then(r=>r.json()).then(data=>{
    if (!data.ok) return;
    if (data.role === 'player') {
      const me = data.me;
      meScore.textContent = me.score || 0;
      buzzState.textContent = me.buzzed ? 'Buzzé !' : 'Pas buzzé';
      buzzState.className = me.buzzed ? 'badge bg-danger' : 'badge bg-secondary';
      // question
      const q = data.question;
      const status = data.current_status;
      qstatusEl.textContent = status;
      if (q) {
        questionText.textContent = q.text;
        renderQCM(q);
        // timer compute
        if (status === 'running') {
          const started = data.started_at || 0;
          const now = Math.floor(Date.now()/1000);
          const elapsed = now - started;
          const remaining = Math.max(0, (q.duration || 0) - elapsed);
          timerEl.textContent = secToStr(remaining);
          if (remaining===0) {
            // do nothing, server will mark finished on next poll
          }
        } else if (status === 'finished') {
          timerEl.textContent = '00:00';
        } else {
          timerEl.textContent = '--';
        }
      } else {
        questionText.textContent = 'Aucune question pour l\'instant.';
        qcmArea.innerHTML = '';
        timerEl.textContent = '--';
      }
    } else {
      // master view fallback (not used here)
    }
  }).catch(e=>console.error(e));
}

function renderQCM(q) {
  qcmArea.innerHTML = '';
  if (!q.options || q.options.length===0) return;
  q.options.forEach((opt, idx)=>{
    const btn = document.createElement('button');
    btn.className = 'btn btn-outline-primary d-block w-100 mb-2 text-start';
    btn.innerHTML = `<strong>${String.fromCharCode(65+idx)}.</strong> ${opt}`;
    btn.onclick = ()=> {
      if (!confirm('Confirmer la réponse ' + String.fromCharCode(65+idx) + '?')) return;
      submitChoice(idx);
    };
    qcmArea.appendChild(btn);
  });
}

function secToStr(s) {
  const mm = String(Math.floor(s/60)).padStart(2,'0');
  const ss = String(s%60).padStart(2,'0');
  return `${mm}:${ss}`;
}

// camera
let stream=null;
async function initCamera(){
  try{
    stream = await navigator.mediaDevices.getUserMedia({video:true,audio:false});
    video.srcObject = stream;
  }catch(err){ console.warn('camera denied', err); }
}

async function sendSnapshot(){
  if (!stream || !myId) return;
  const canvas = document.createElement('canvas');
  canvas.width = 320; canvas.height = 240;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video,0,0,canvas.width,canvas.height);
  const b64 = canvas.toDataURL('image/jpeg', 0.6);
  const form = new FormData();
  form.append('action','upload_snapshot');
  form.append('id', myId);
  form.append('b64', b64);
  try { await fetch(api, {method:'POST', body: form}); } catch(e){ console.error(e); }
}

// auto-start if stored id exists
if (myId && myName) {
  nameInput.value = myName;
  startGame();
}
