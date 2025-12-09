<!-- master.php -->
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Quiz - Maître</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">
  <div class="container py-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title">Quiz — Maître du jeu</h3>

        <!-- AUTHENTIFICATION -->
        <div id="loginBox" class="mb-3">
          <div class="row g-2">
            <div class="col-md-8">
              <input id="masterPass" type="password" class="form-control" placeholder="Mot de passe maître">
            </div>
            <div class="col-md-4">
              <button id="btnMasterLogin" class="btn btn-primary w-100">
                Se connecter
              </button>
            </div>
          </div>
        </div>

        <!-- ZONE MAÎTRE -->
        <div id="masterArea" style="display:none;">
          <div class="row">

            <!-- PLAYLIST -->
            <div class="col-md-6">
              <h5>Playlist (JSON ou lignes simples)</h5>

              <textarea id="playlistArea" class="form-control" rows="8"
                placeholder='Exemple JSON: [{"text":"Q1","options":["A","B"],"correct":0,"duration":15}]'>
              </textarea>

              <div class="d-flex gap-2 mt-2">
                <button id="btnLoadPlaylist" class="btn btn-success">Charger playlist</button>
                <button id="btnNext" class="btn btn-outline-primary">Suivant</button>
                <button id="btnStart" class="btn btn-primary">Démarrer sélection</button>
                <button id="btnFinish" class="btn btn-warning">Terminer question</button>
                <button id="btnLogout" class="btn btn-danger ms-auto">Déconnexion</button>
              </div>

              <div class="mt-3">
                <label>Question manuelle :</label>

                <input id="manualText" class="form-control mt-1" placeholder="Texte">
                <input id="manualOptions" class="form-control mt-1" placeholder="Option1||Option2||Option3">

                <div class="d-flex gap-2 mt-2">
                  <input id="manualCorrect" type="number" class="form-control" placeholder="Index correct (0..)">
                  <input id="manualDuration" type="number" class="form-control" placeholder="Durée (s)">
                  <button id="btnAddManual" class="btn btn-secondary">Ajouter à la playlist</button>
                </div>
              </div>
            </div>

            <!-- CONTROLE & JOUEURS -->
            <div class="col-md-6">
              <h5>Contrôle & joueurs</h5>

              <div class="mb-2">
                <div>Question courante: <strong id="curQuestionText">Aucune</strong></div>
                <div>Statut: <span id="curStatus">idle</span></div>
                <div>Temps restant: <span id="curTimer">--</span></div>
                <div>Index: <span id="curIndex">-</span></div>
              </div>

              <div id="playersGrid" class="row gy-2"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- SCRIPT MAÎTRE CORRIGÉ -->
  <script src="js/master.js"></script>

</body>
</html>
