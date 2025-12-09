<!-- master.php -->
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Quiz - Maître</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <h1>Quiz — Maître du jeu</h1>

    <div class="playlist">
      <h3>Playlist / Questions</h3>
      <textarea id="playlistArea" placeholder="Une question par ligne"></textarea>
      <button id="btnLoadPlaylist">Charger playlist</button>
      <div>
        <input id="manualQuestion" placeholder="Question manuelle" />
        <button id="btnSetQuestion">Définir la question</button>
      </div>
    </div>

    <div class="players-grid" id="playersGrid">
      <!-- dynamique -->
    </div>
  </div>

<script src="js/master.js"></script>
</body>
</html>
