<?php
declare(strict_types=1);
?>
<nav class="navbar">
    <a href="<?= SITE_URL ?>/user/dashboard.php">Dashboard</a>
    <a href="<?= SITE_URL ?>/user/profile.php">Profile</a>
    <a href="<?= SITE_URL ?>/user/diet_plan.php">Diet Plan</a>
    <a href="<?= SITE_URL ?>/user/food_log.php">Food Log</a>
    <a href="<?= SITE_URL ?>/user/water_tracker.php">Water</a>
    <a href="<?= SITE_URL ?>/user/progress.php">Progress</a>
    <a href="<?= SITE_URL ?>/user/messages.php">
        Messages
        <span id="userMsgBadge" class="badge" style="display:none;margin-left:4px;">0</span>
    </a>
    <a href="<?= SITE_URL ?>/user/favorites.php">Favorites</a>
    <a href="<?= SITE_URL ?>/auth/logout.php" class="right">Logout</a>
</nav>
<script>
async function updateMessageBadge() {
    const badge = document.getElementById('userMsgBadge');
    if (!badge) return;
    try {
        const res = await fetch('<?= SITE_URL ?>/api/messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'get_count' })
        });
        const data = await res.json();
        if (!data.success) return;
        const count = parseInt(data.unread_count || 0, 10);
        if (count > 0) {
            badge.textContent = String(count);
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    } catch (_) {
    }
}
updateMessageBadge();
setInterval(updateMessageBadge, 30000);
</script>
