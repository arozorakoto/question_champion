<!-- index.php -->
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Quiz - Etudiant</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
  <div class="container py-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title">Quiz — Etudiant</h3>

        <!-- INSCRIPTION -->
        <div id="registerBox" class="row g-2">
          <div class="col-md-8">
            <input id="name" class="form-control" placeholder="Ton pseudo">
          </div>
          <div class="col-md-4">
            <button id="btnRegister" class="btn btn-primary w-100">S'enregistrer</button>
          </div>
        </div>

        <!-- ZONE DE JEU -->
        <div id="game" style="display:none;">
          <div class="row mt-3">
            <!-- Webcam / Score -->
            <div class="col-md-4 text-center">
              <video id="video" autoplay playsinline class="border" style="width:100%;height:auto;border-radius:.5rem;"></video>
              <p class="mt-2"><strong id="meName"></strong></p>
              <p>Score: <span id="meScore">0</span></p>
              <p>Etat: <span id="buzzState" class="badge bg-secondary">Pas buzzé</span></p>
            </div>

            <!-- Question / QCM / Chrono -->
            <div class="col-md-8">
              <div class="card">
                <div class="card-body">
                  <h5>Question</h5>
                  <div id="questionText" class="mb-3">Aucune question pour l'instant.</div>

                  <div id="qcmArea" class="mb-3"></div>

                  <div class="d-flex align-items-center gap-2">
                    <button id="btnBuzz" class="btn btn-danger">Buzz !</button>
                    <div>
                      <div>Temps restant : <span id="timer" class="fs-5">--</span></div>
                      <div>Statut : <span id="qstatus">idle</span></div>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div> <!-- row -->
        </div>
      </div>
    </div>
  </div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SCRIPT JOUEUR -->
<script>
let meName = "";
let currentQuestion = null;
let interval = null;
let timeLeft = 0;

// Inscription
document.getElementById("btnRegister").addEventListener("click", () => {
    const name = document.getElementById("name").value.trim();
    if(name === "") { alert("Pseudo obligatoire"); return; }
    meName = name;
    document.getElementById("meName").innerText = name;
    document.getElementById("registerBox").style.display = "none";
    document.getElementById("game").style.display = "block";
});

// Fonction pour lancer le timer
function startTimer(duration) {
    clearInterval(interval);
    timeLeft = duration;
    document.getElementById("timer").innerText = timeLeft;

    interval = setInterval(() => {
        timeLeft--;
        document.getElementById("timer").innerText = timeLeft;
        if(timeLeft <= 0) {
            clearInterval(interval);
            document.getElementById("qstatus").innerText = "Temps écoulé";
            document.getElementById("btnBuzz").disabled = true;
        }
    }, 1000);
}

// Récupérer question depuis le serveur
function pollQuestion() {
    fetch("get_question.php")
    .then(res => res.json())
    .then(data => {
        if(data.status === "wait") return;

        // Nouvelle question ?
        if(!currentQuestion || currentQuestion.text !== data.text) {
            currentQuestion = data;
            document.getElementById("questionText").innerText = data.text;

            // QCM
            const qcmArea = document.getElementById("qcmArea");
            qcmArea.innerHTML = "";
            data.options.forEach((opt,i) => {
                const btn = document.createElement("button");
                btn.className = "btn btn-outline-primary w-100 my-1";
                btn.innerText = opt;
                btn.onclick = () => checkAnswer(i);
                qcmArea.appendChild(btn);
            });

            document.getElementById("btnBuzz").disabled = false;
            document.getElementById("buzzState").innerText = "Pas buzzé";
            document.getElementById("qstatus").innerText = "En cours";

            startTimer(parseInt(data.duration));
        }
    });
}

// Vérifier réponse
function checkAnswer(index) {
    if(currentQuestion == null) return;
    if(index === currentQuestion.correct) {
        alert("Bonne réponse !");
        let scoreEl = document.getElementById("meScore");
        scoreEl.innerText = parseInt(scoreEl.innerText) + 1;
    } else {
        alert("Mauvaise réponse !");
        document.getElementById("btnBuzz").disabled = true;
        setTimeout(() => document.getElementById("btnBuzz").disabled = false, 3000);
    }
}

// Buzz
document.getElementById("btnBuzz").addEventListener("click", () => {
    document.getElementById("buzzState").innerText = "Buzzé !";
});

// Polling toutes les 1s
setInterval(pollQuestion, 1000);
</script>
</body>
</html>
