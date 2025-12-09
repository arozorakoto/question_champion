<!-- index.php -->
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Quiz - Joueur</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <h1>Quiz — Joueur</h1>
    <div id="registerBox">
      <input id="name" placeholder="Ton pseudo" />
      <button id="btnRegister">S'enregistrer</button>
    </div>

    <div id="game" style="display:none;">
      <div class="player-info">
        <div id="videoPreviewWrap">
          <video id="video" autoplay playsinline width="240" height="180"></video>
        </div>
        <div>
          <div>Pseudo: <span id="meName"></span></div>
          <div>Etat: <span id="buzzState">Pas buzzé</span></div>
        </div>
      </div>

      <div class="question">
        <h2>Question</h2>
        <div id="questionText">Aucune question pour l'instant.</div>
      </div>

      <div class="controls">
        <button id="btnBuzz">Buzz !</button>
      </div>
    </div>
  </div>

<script src="js/player.js"></script>
</body>
</html>

