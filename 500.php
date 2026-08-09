<?php
http_response_code(500);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>500 — Server Error | Safari Zangu Ventures</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;1,400&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Jost',sans-serif;background:#0a0a0a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:2rem}
    .wrap{max-width:480px}
    .code{font-family:'Playfair Display',serif;font-size:5rem;font-weight:500;line-height:1;margin-bottom:1rem}
    h1{font-size:1.3rem;font-weight:400;margin-bottom:.75rem}
    p{font-size:.88rem;color:rgba(255,255,255,.55);line-height:1.7;margin-bottom:2rem}
    .btn{display:inline-block;padding:.7rem 1.75rem;background:#fff;color:#000;font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;text-decoration:none;margin:.25rem}
    .btn-wa{background:#25D366;color:#fff}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="code">500</div>
    <h1>Something went wrong on our end</h1>
    <p>We're sorry — our team has been notified. Please try again in a moment,
       or reach us directly via WhatsApp.</p>
    <a href="<?= BASE_PATH ?>/" class="btn">← Home</a>
    <a href="https://wa.me/255663430934" target="_blank" class="btn btn-wa">WhatsApp Us</a>
  </div>
</body>
</html>
