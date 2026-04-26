<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';

$dashboardPath = '/auth/login.php';
$dashboardLabel = 'Login';
$motionMode = $_GET['motion'] ?? 'subtle';

if (!in_array($motionMode, ['subtle', 'energetic'], true)) {
    $motionMode = 'subtle';
}

if (isLoggedIn()) {
    $type = $_SESSION['user_type'] ?? 'user';
    if ($type === 'admin') {
        $dashboardPath = '/admin/dashboard.php';
        $dashboardLabel = 'Go To Dashboard';
    } elseif ($type === 'dietitian') {
        $dashboardPath = '/dietitian/dashboard.php';
        $dashboardLabel = 'Go To Dashboard';
    } else {
        $dashboardPath = '/user/dashboard.php';
        $dashboardLabel = 'Go To Dashboard';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HealthMatrix | Smart Diet & Nutrition Platform</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/landing.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="motion-<?= htmlspecialchars($motionMode, ENT_QUOTES, 'UTF-8') ?>">
<div class="landing-bg-glow glow-1"></div>
<div class="landing-bg-glow glow-2"></div>

<header class="topbar">
    <a href="<?= SITE_URL ?>" class="brand" aria-label="HealthMatrix Home">
        <img src="<?= SITE_URL ?>/assets/images/HealthMatrix.svg" alt="HealthMatrix Logo">
    </a>
    <nav class="menu">
        <a href="#features">Features</a>
        <a href="#journey">How It Works</a>
        <a href="#plans">Plans</a>
        <a href="#stories">Stories</a>
    </nav>
    <a href="<?= SITE_URL . $dashboardPath ?>" class="btn btn-login"><?= htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8') ?></a>
</header>

<main>
    <section class="hero">
        <div class="hero-copy">
            <span class="pill"><i class="fa-solid fa-leaf"></i> Balanced Nutrition, Better Life</span>
            <h1>Eat Better, Feel Better, Live Better With HealthMatrix.</h1>
            <p>
                HealthMatrix helps you track food, follow structured meal plans, connect with dietitians, and
                measure progress in one modern dashboard.
            </p>
            <div class="hero-actions">
                <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary">Login Now</a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-outline">Create Free Account</a>
            </div>
            <div class="hero-metrics">
                <div><strong>500+</strong><span>Healthy Meals</span></div>
                <div><strong>24/7</strong><span>Progress Visibility</span></div>
                <div><strong>1 App</strong><span>User + Dietitian Space</span></div>
            </div>
        </div>
        <div class="hero-visual hero-visual--video">
            <video
                class="hero-visual-video"
                autoplay
                muted
                loop
                playsinline
                preload="metadata"
                poster="https://images.unsplash.com/photo-1498837167922-ddd27525d352?auto=format&fit=crop&w=900&q=80"
            >
                <source src="<?= SITE_URL ?>/assets/images/hero_video.mp4" type="video/mp4">
            </video>
            <div class="floating-card card-a">
                <i class="fa-solid fa-fire"></i>
                <div>
                    <p>Daily Calorie Goal</p>
                    <strong>2,050 kcal</strong>
                </div>
            </div>
            <div class="floating-card card-b">
                <i class="fa-solid fa-glass-water"></i>
                <div>
                    <p>Water Intake</p>
                    <strong>8 / 10 glasses</strong>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="features section">
        <div class="section-head">
            <h2>Everything You Need For A Sustainable Diet Routine</h2>
            <p>Designed for users and dietitians with a simple but powerful daily workflow.</p>
        </div>
        <div class="feature-grid">
            <article class="feature-card">
                <img src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=800&q=80" alt="Meal plan preparation">
                <div>
                    <h3><i class="fa-solid fa-utensils"></i> Personalized Meal Plans</h3>
                    <p>Get assigned plans based on your calorie target, body profile, and long-term goal.</p>
                </div>
            </article>
            <article class="feature-card">
                <img src="https://images.pexels.com/photos/7089401/pexels-photo-7089401.jpeg?auto=compress&cs=tinysrgb&w=1200" alt="Doctor consulting a patient about diet and health">
                <div>
                    <h3><i class="fa-solid fa-user-doctor"></i> Direct Dietitian Collaboration</h3>
                    <p>Message your dietitian, request support, and receive plan updates quickly.</p>
                </div>
            </article>
            <article class="feature-card">
                <img src="https://images.pexels.com/photos/4498606/pexels-photo-4498606.jpeg?auto=compress&cs=tinysrgb&w=1200" alt="Woman reviewing health progress chart on tablet">
                <div>
                    <h3><i class="fa-solid fa-chart-line"></i> Smart Progress Tracking</h3>
                    <p>Track food logs, hydration, and outcomes to stay motivated and consistent.</p>
                </div>
            </article>
        </div>
    </section>

    <section id="journey" class="journey section">
        <div class="journey-content">
            <h2>Your Healthy Journey In 3 Simple Steps</h2>
            <div class="steps">
                <div class="step"><span>1</span><p>Create your profile with goals, activity level, and body data.</p></div>
                <div class="step"><span>2</span><p>Receive personalized meal structure and actionable daily targets.</p></div>
                <div class="step"><span>3</span><p>Log meals, monitor progress, and optimize with dietitian feedback.</p></div>
            </div>
        </div>
        <div class="journey-image">
            <img src="https://images.unsplash.com/photo-1482049016688-2d3e1b311543?auto=format&fit=crop&w=900&q=80" alt="Healthy breakfast and coffee">
        </div>
    </section>

    <section id="plans" class="plans section">
        <div class="section-head">
            <h2>Popular Goal Types</h2>
            <p>Choose what fits your objective and let the system organize the right meal strategy.</p>
        </div>
        <div class="plan-grid">
            <div class="plan-card">
                <h3>Weight Loss</h3>
                <p>Calorie deficit based planning with balanced macro distribution and consistency prompts.</p>
            </div>
            <div class="plan-card">
                <h3>Maintain</h3>
                <p>Steady nutrition approach to preserve body composition and improve energy levels.</p>
            </div>
            <div class="plan-card">
                <h3>Lean Gain</h3>
                <p>Progressive calorie and protein planning focused on clean muscle development.</p>
            </div>
        </div>
    </section>

    <section id="stories" class="stories section">
        <div class="section-head">
            <h2>What Users Love</h2>
        </div>
        <div class="story-grid">
            <blockquote>"I finally stayed consistent because everything was in one place and easy to follow."</blockquote>
            <blockquote>"The dietitian chat removed confusion and helped me trust the plan."</blockquote>
            <blockquote>"Food logs plus water tracker gave me daily focus and better discipline."</blockquote>
        </div>
    </section>

    <section class="final-cta section">
        <h2>Ready To Build Your Best Routine?</h2>
        <p>Start now and take control of your meals, goals, and health progress.</p>
        <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary btn-large">Login To HealthMatrix</a>
    </section>
</main>

<footer class="foot">
    <p>&copy; <?= date('Y') ?> HealthMatrix. Eat smart, live strong.</p>
</footer>
</body>
</html>
