// js/player.js
const api = 'api.php';
let myId = localStorage.getItem('player_id') || null;
let myName = localStorage.getItem('player_name') || null;
const registerBox = document.getElementById('registerBox');
const gameBox = document.getElementById('game');
const btnRegister = document.getElementById('btnRegister');
const nameInput = document.getElementById('name');
const meName = document.getElementById('meName');
const questionText = document.getElementById('questionText');
const btnBuzz = document.getElementById('btnBuzz');
const buzzState = document.getElementById('buzzState');
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
      } else alert('Erreur inscription');
    });
}

function startGame() {
  registerBox.style.display='none';
  gameBox.style.display='block';
  meName.textContent = myName;
  initCamera();
  pollState();
  setInterval(pollState, 2000); // poll every 2s
  setInterval(sendSnapshot, 3000); // send snapshot every 3s
}

btnBuzz.addEventListener('click', ()=>{
  if (!myId) return alert('Enregistrez-vous d\'abord');
  fetch(api, {method:'POST', body:new URLSearchParams({action:'buzz', id:myId})})
    .then(r=>r.json()).then(()=> {
      buzzState.textContent = 'Buzzé !';
      // auto-release after 5s frontend if not released by master (visual)
      setTimeout(()=>{ buzzState.textContent = 'Pas buzzé'; }, 5000);
    });
});

// poll for current question
function pollState() {
  const url = api + '?action=get_state&id=' + encodeURIComponent(myId||'');
  fetch(url).then(r=>r.json()).then(data=>{
    if (!data.ok) return;
    if (data.question !== undefined) questionText.textContent = data.question || 'Aucune question pour l\'instant.';
    if (data.me) {
      buzzState.textContent = data.me.buzzed ? 'Buzzé !' : 'Pas buzzé';
    }
  })
  .catch(e=>console.error(e));
}

// camera setup & snapshot
let stream=null;
async function initCamera(){
  try{
    stream = await navigator.mediaDevices.getUserMedia({video:true,audio:false});
    video.srcObject = stream;
  }catch(err){ console.warn('camera denied', err); }
}

async function sendSnapshot(){
  if (!stream || !myId) return;
  // capture one frame to canvas
  const track = stream.getVideoTracks()[0];
  if (!track) return;
  const canvas = document.createElement('canvas');
  canvas.width = 320; canvas.height = 240;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  const b64 = canvas.toDataURL('image/jpeg', 0.6);
  const form = new FormData();
  form.append('action', 'upload_snapshot');
  form.append('id', myId);
  form.append('b64', b64);
  try {
    await fetch(api, {method:'POST', body: form});
  } catch(e){ console.error('snapshot err', e); }
}

// auto-start if stored id exists
if (myId && myName) {
  nameInput.value = myName;
  startGame();
}
