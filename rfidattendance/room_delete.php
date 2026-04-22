<?php
require 'admin_guard.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: rooms.php"); exit(); }

// remove mapped devices first
$d = $conn->prepare("DELETE FROM room_devices WHERE room_id=?");
$d->bind_param("i", $id); $d->execute(); $d->close();

$del = $conn->prepare("DELETE FROM class_rooms WHERE room_id=?");
$del->bind_param("i", $id);
$ok = $del->execute();
$del->close();

flash($ok ? "Room deleted." : "Delete failed.", $ok ? "success" : "danger");
header("Location: rooms.php");
