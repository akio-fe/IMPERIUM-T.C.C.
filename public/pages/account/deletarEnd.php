<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$loginPage = url_path('public/pages/auth/login.php');
$enderecosPage = url_path('public/pages/account/enderecos.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . $loginPage);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $enderecosPage);
    exit();
}

$userEmail = $_SESSION['email'] ?? '';
$userId = null;

if ($userEmail !== '') {
    $stmtUser = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuEmail = ? LIMIT 1');
    if ($stmtUser) {
        $stmtUser->bind_param('s', $userEmail);
        $stmtUser->execute();
        $stmtUser->bind_result($foundId);
        if ($stmtUser->fetch()) {
            $userId = (int)$foundId;
        }
        $stmtUser->close();
    }
}

if ($userId === null) {
    header('Location: ' . $enderecosPage . '?status=error');
    exit();
}

$addressId = isset($_POST['endereco_id']) ? (int)$_POST['endereco_id'] : 0;
if ($addressId <= 0) {
    header('Location: ' . $enderecosPage . '?status=error');
    exit();
}

$stmtDelete = $conn->prepare('DELETE FROM EnderecoEntrega WHERE EndEntId = ? AND UsuId = ?');
if ($stmtDelete) {
    $stmtDelete->bind_param('ii', $addressId, $userId);
    $stmtDelete->execute();

    if ($stmtDelete->affected_rows > 0) {
        $stmtDelete->close();
        header('Location: ' . $enderecosPage . '?status=deleted');
        exit();
    }

    $stmtDelete->close();
}

header('Location: ' . $enderecosPage . '?status=error');
exit();
