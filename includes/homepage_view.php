<?php
/**
 * Public campus homepage — accessible logged in or out.
 * Expects: $homepageAnnouncements, $homepageDocuments, $isLoggedIn, $currentUser (optional)
 */
$portalUrl = $isLoggedIn ? BASE_URL . '/dashboard.php' : BASE_URL . '/login.php';
$portalLabel = $isLoggedIn ? 'Go to Dashboard' : 'Login to Portal';
$featured = $homepageAnnouncements[0] ?? null;
$restAnnouncements = $featured ? array_slice($homepageAnnouncements, 1) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_INSTITUTION ?> - Student Portal</title>
    <link rel="icon" type="image/png" href="<?= LOGO_PATH ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="home-page">
<header class="home-header">
    <div class="home-header-inner">
        <a href="<?= BASE_URL ?>/" class="home-brand">
            <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--home">
        </a>
        <nav class="home-nav">
            <a href="#news" class="home-nav-link">News</a>
            <a href="#documents" class="home-nav-link">Calendars</a>
            <a href="#services" class="home-nav-link">Services</a>
            <?php if ($isLoggedIn): ?>
            <span class="home-user-chip"><?= sanitize($currentUser['full_name'] ?? '') ?></span>
            <a href="<?= BASE_URL ?>/modules/settings/profile.php" class="btn btn-secondary btn-sm">Profile</a>
            <?php endif; ?>
            <a href="<?= $portalUrl ?>" class="btn btn-primary home-login-btn"><?= $portalLabel ?></a>
        </nav>
    </div>
</header>

<?php if (!empty($homepageAnnouncements)): ?>
<div class="home-live-ticker" aria-label="Live campus updates">
    <span class="home-live-badge"><span class="home-live-dot"></span> Live</span>
    <div class="home-ticker-track">
        <div class="home-ticker-content">
            <?php foreach ($homepageAnnouncements as $a): ?>
            <span class="home-ticker-item"><?= sanitize($a['title']) ?> &mdash; <?= sanitize(strlen($a['message']) > 80 ? substr(strip_tags($a['message']), 0, 80) . '...' : strip_tags($a['message'])) ?></span>
            <?php endforeach; ?>
            <?php foreach ($homepageAnnouncements as $a): ?>
            <span class="home-ticker-item"><?= sanitize($a['title']) ?> &mdash; <?= sanitize(strlen($a['message']) > 80 ? substr(strip_tags($a['message']), 0, 80) . '...' : strip_tags($a['message'])) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="home-hero">
    <div class="home-hero-bg"></div>
    <div class="home-hero-pattern"></div>
    <div class="home-hero-inner">
        <span class="home-hero-tag">Kenya Methodist University</span>
        <h1>Your Gateway to Academic Life</h1>
        <p class="home-hero-lead">
            Access fees, grades, library records, and campus announcements — all in one secure student portal built for KeMU students and staff.
        </p>
        <div class="home-hero-actions">
            <a href="<?= $portalUrl ?>" class="btn btn-primary btn-lg home-hero-cta"><?= $isLoggedIn ? 'Open Dashboard' : 'Enter Student Portal' ?></a>
            <a href="#news" class="btn btn-hero-outline btn-lg">View Campus News</a>
        </div>
        <div class="home-hero-stats">
            <div class="home-stat"><strong>24/7</strong><span>Portal Access</span></div>
            <div class="home-stat"><strong>Fees</strong><span>Balance &amp; Receipts</span></div>
            <div class="home-stat"><strong>Results</strong><span>Grades &amp; Reports</span></div>
            <div class="home-stat"><strong>Library</strong><span>Books &amp; Records</span></div>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<section class="home-featured">
    <div class="home-featured-inner home-announcement-card home-announcement-card--<?= sanitize($featured['homepage_category'] ?? 'general') ?> home-featured-card">
        <div class="home-featured-glow"></div>
        <div class="home-announcement-top">
            <div class="home-announcement-icon"><?= getHomepageCategoryIcon($featured['homepage_category'] ?? 'general') ?></div>
            <div class="home-featured-badges">
                <?php if (isAnnouncementLive($featured['created_at'])): ?>
                <span class="home-live-pill"><span class="home-live-dot"></span> Live Now</span>
                <?php endif; ?>
                <span class="home-announcement-badge"><?= sanitize(getHomepageCategoryLabel($featured['homepage_category'] ?? 'general')) ?></span>
            </div>
        </div>
        <h2><?= sanitize($featured['title']) ?></h2>
        <p><?= nl2br(sanitize($featured['message'])) ?></p>
        <div class="home-announcement-footer">
            <span class="home-announcement-meta">Posted <?= formatDate($featured['created_at']) ?></span>
            <?php if (!$isLoggedIn && in_array($featured['homepage_category'] ?? '', ['portal', 'results'], true)): ?>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">Login to View</a>
            <?php elseif ($isLoggedIn): ?>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-sm">Open Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="home-services" id="services">
    <div class="home-services-inner">
        <div class="home-section-label">Portal Services</div>
        <h2>Everything you need, one login away</h2>
        <div class="home-features-grid">
            <div class="home-feature-card">
                <div class="home-feature-icon home-feature-icon--fees">&#128176;</div>
                <h3>Fee Management</h3>
                <p>Check balances, view payment history, and print receipts for any trimester.</p>
            </div>
            <div class="home-feature-card">
                <div class="home-feature-icon home-feature-icon--grades">&#9733;</div>
                <h3>Grades &amp; Results</h3>
                <p>View your marks, download report cards, and track academic progress online.</p>
            </div>
            <div class="home-feature-card">
                <div class="home-feature-icon home-feature-icon--library">&#128214;</div>
                <h3>Library</h3>
                <p>See borrowed books, due dates, and manage your library activity from anywhere.</p>
            </div>
            <div class="home-feature-card">
                <div class="home-feature-icon home-feature-icon--news">&#128227;</div>
                <h3>Announcements</h3>
                <p>Stay updated on intake, exams, results, and important campus communications.</p>
            </div>
        </div>
    </div>
</section>

<section class="home-documents" id="documents">
    <div class="home-documents-inner">
        <div class="home-section-label">Downloads</div>
        <h2>Calendars &amp; Timetables</h2>
        <p class="home-documents-lead">Official PDF documents published by the administration.</p>
        <?php if (empty($homepageDocuments)): ?>
        <div class="home-docs-empty">
            <div class="home-doc-icon-large">&#128196;</div>
            <p>Calendars and timetables will appear here once uploaded by the admin.</p>
        </div>
        <?php else: ?>
        <div class="home-documents-grid">
            <?php foreach ($homepageDocuments as $doc): ?>
            <a href="<?= BASE_URL ?>/document.php?id=<?= (int) $doc['id'] ?>" target="_blank" class="home-doc-card home-doc-card--<?= sanitize($doc['doc_type']) ?>">
                <div class="home-doc-icon"><?= getDocumentTypeIcon($doc['doc_type']) ?></div>
                <div class="home-doc-body">
                    <span class="home-doc-type"><?= sanitize(getDocumentTypeLabel($doc['doc_type'])) ?></span>
                    <h3><?= sanitize($doc['title']) ?></h3>
                    <?php if ($doc['description']): ?>
                    <p><?= sanitize($doc['description']) ?></p>
                    <?php endif; ?>
                    <div class="home-doc-meta">
                        <?php if ($doc['academic_year']): ?><span><?= sanitize($doc['academic_year']) ?></span><?php endif; ?>
                        <?php if ($doc['trimester']): ?><span><?= sanitize($doc['trimester']) ?></span><?php endif; ?>
                        <span>PDF</span>
                    </div>
                </div>
                <span class="home-doc-action">View PDF &#8594;</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<main class="home-main" id="news">
    <div class="home-news-header">
        <div class="home-section-label">Campus News</div>
        <h2>Latest Updates &amp; Notices</h2>
        <p>Intake information, exam calendars, results releases, and portal access notices from the administration.</p>
    </div>

    <?php if (empty($homepageAnnouncements)): ?>
        <div class="home-empty">
            <div class="home-empty-icon">&#128227;</div>
            <h3>No announcements yet</h3>
            <p>Check back soon for intake updates, exam calendars, and results notices.</p>
            <a href="<?= $portalUrl ?>" class="btn btn-primary"><?= $portalLabel ?></a>
        </div>
    <?php elseif (empty($restAnnouncements)): ?>
        <p class="home-news-note">Featured announcement shown above. More updates will appear here as they are published.</p>
    <?php else: ?>
        <div class="home-announcements-grid">
            <?php foreach ($restAnnouncements as $i => $a):
                $cat = $a['homepage_category'] ?? 'general';
                $isLive = isAnnouncementLive($a['created_at']);
            ?>
            <article class="home-announcement-card home-announcement-card--<?= sanitize($cat) ?> home-announcement-card--animate" style="animation-delay: <?= $i * 0.08 ?>s">
                <div class="home-announcement-top">
                    <div class="home-announcement-icon"><?= getHomepageCategoryIcon($cat) ?></div>
                    <div class="home-featured-badges">
                        <?php if ($isLive): ?><span class="home-live-pill"><span class="home-live-dot"></span> Live</span><?php endif; ?>
                        <span class="home-announcement-badge"><?= sanitize(getHomepageCategoryLabel($cat)) ?></span>
                    </div>
                </div>
                <h3><?= sanitize($a['title']) ?></h3>
                <p><?= nl2br(sanitize($a['message'])) ?></p>
                <div class="home-announcement-footer">
                    <span class="home-announcement-meta"><?= formatDate($a['created_at']) ?></span>
                    <?php if (!$isLoggedIn && in_array($cat, ['portal', 'results'], true)): ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">Login Now</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<section class="home-cta-banner">
    <div class="home-cta-inner">
        <div class="home-cta-text">
            <h2><?= $isLoggedIn ? 'Welcome back!' : 'Ready to access your account?' ?></h2>
            <p><?= $isLoggedIn ? 'Return to your dashboard to manage records, fees, and academic information.' : 'Students, faculty, and staff can sign in to manage records, view results, and stay connected with campus life.' ?></p>
        </div>
        <a href="<?= $portalUrl ?>" class="btn btn-cta-gold btn-lg"><?= $isLoggedIn ? 'Open Dashboard' : 'Login to Portal' ?></a>
    </div>
</section>

<footer class="home-footer">
    <div class="home-footer-inner">
        <div class="home-footer-brand">
            <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--footer">
            <p><?= APP_NAME ?></p>
        </div>
        <div class="home-footer-links">
            <a href="<?= BASE_URL ?>/">Home</a>
            <a href="#news">News</a>
            <a href="#documents">Calendars</a>
            <a href="#services">Services</a>
            <a href="<?= $portalUrl ?>"><?= $isLoggedIn ? 'Dashboard' : 'Portal Login' ?></a>
        </div>
        <p class="home-footer-copy">&copy; <?= date('Y') ?> <?= APP_INSTITUTION ?>. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
