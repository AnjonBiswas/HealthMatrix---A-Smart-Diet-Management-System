<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
redirectIfNotLoggedIn(['dietitian']);
$pageTitle = 'Messages';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar_dietitian.php';
?>
<section class="card"><h1>Messages</h1><p>This page is ready for implementation.</p></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
