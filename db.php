<?php
$pdo = new PDO(
  "mysql:host=localhost;dbname=mobilhotell;charset=utf8mb4",
  "mobilhotell",
  "velg_et_passord",
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]
);
