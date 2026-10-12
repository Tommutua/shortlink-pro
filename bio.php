<?php
/**
 * =====================================================
 * ShortLink Pro — Link-in-Bio Page
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Public bio page + inline editor.
 *
 *   bio.php?u=me            → public view
 *   bio.php?edit=1          → edit form
 *
 * Single-tenant: there's one bio page per installation
 * (slug = "me"). Add user accounts later for multi-tenant.
 * =====================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// -----------------------------------------------------
// Determine mode
// -----------------------------------------------------
$isEdit = isset($_GET['edit']) && $_GET['edit'] === '1';
$slug   = trim((string) ($_GET['u'] ?? 'me'));

// For editing, always use the default bio page.
// For viewing, use the requested slug (defaults to 'me').
$bioPage = $isEdit
    ? get_or_create_default_bio_page()
    : get_bio_page($slug);

$pageTitle = $isEdit ? 'Edit bio page' : ($bioPage['title'] ?? 'Bio');

// -----------------------------------------------------
// Handle POST actions (only in edit mode)
// -----------------------------------------------------
$flash  = '';
$errors = [];

if ($isEdit && $bioPage && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            // ---------------------------------------------
            // UPDATE PAGE (title, bio, avatar, theme)
            // ---------------------------------------------
            case 'update_page':
                $title  = trim((string) ($_POST['title'] ?? ''));
                $bio    = trim((string) ($_POST['bio'] ?? ''));
                $avatar = trim((string) ($_POST['avatar_url'] ?? ''));
                $theme  = trim((string) ($_POST['theme'] ?? 'default'));

                if ($title === '') {
                    $errors[] = 'Title cannot be empty.';
                }
                if (mb_strlen($title) > 64) {
                    $errors[] = 'Title must be 64 characters or less.';
                }
                if (mb_strlen($bio) > 300) {
                    $errors[] = 'Bio must be 300 characters or less.';
                }
                if ($avatar !== '' && !validate_url($avatar)) {
                    $errors[] = 'Avatar URL is not valid.';
                }
                if (!in_array($theme, ['default', 'sunset', 'ocean', 'forest'], true)) {
                    $theme = 'default';
                }

                if (empty($errors)) {
                    update_bio_page((int) $bioPage['id'], [
                        'title'      => $title,
                        'bio'        => $bio,
                        'avatar_url' => $avatar !== '' ? $avatar : null,
                        'theme'      => $theme,
                    ]);
                    $flash = 'page_updated';
                    // Refresh
                    $bioPage = get_bio_page_by_id((int) $bioPage['id']);
                }
                break;

            // ---------------------------------------------
            // ADD LINK
            // ---------------------------------------------
            case 'add_link':
                $label = trim((string) ($_POST['label'] ?? ''));
                $url   = trim((string) ($_POST['url'] ?? ''));
                $icon  = trim((string) ($_POST['icon'] ?? ''));

                if ($label === '') {
                    $errors[] = 'Link label is required.';
                }
                if (mb_strlen($label) > 64) {
                    $errors[] = 'Label must be 64 characters or less.';
                }
                if ($url === '') {
                    $errors[] = 'Link URL is required.';
                } elseif (!validate_url($url)) {
                    $errors[] = 'Link URL is not valid.';
                }
                if ($icon !== '' && mb_strlen($icon) > 4) {
                    $errors[] = 'Icon must be a single emoji or short text.';
                }

                if (empty($errors)) {
                    add_bio_link(
                        (int) $bioPage['id'],
                        $label,
                        $url,
                        $icon !== '' ? $icon : null
                    );
                    $flash = 'link_added';
                }
                break;

            // ---------------------------------------------
            // DELETE LINK
            // ---------------------------------------------
            case 'delete_link':
                $linkId = (int) ($_POST['link_id'] ?? 0);
                if ($linkId > 0) {
                    $deleted = delete_bio_link($linkId, (int) $bioPage['id']);
                    if ($deleted) {
                        $flash = 'link_deleted';
                    }
                }
                break;

            // ---------------------------------------------
            // MOVE LINK UP / DOWN
            // ---------------------------------------------
            case 'move_link':
                $linkId   = (int) ($_POST['link_id'] ?? 0);
                $direction = $_POST['direction'] ?? 'up';

                if ($linkId > 0 && in_array($direction, ['up', 'down'], true)) {
                    move_bio_link($linkId, (int) $bioPage['id'], $direction);
                    $flash = 'link_moved';
                }
                break;
        }
    }
}

// -----------------------------------------------------
// Fetch links (both modes)
// -----------------------------------------------------
$links = $bioPage ? get_bio_links((int) $bioPage['id']) : [];

// -----------------------------------------------------
// Render
// -----------------------------------------------------
require __DIR__ . '/includes/header.php';
?>

<?php if ($isEdit): ?>
    <!-- =================================================
         EDIT MODE
         ================================================= -->
    <div class="bio-editor">

        <div class="flex items-center justify-between mb-6" style="flex-wrap: wrap; gap: var(--space-4);">
            <div>
                <p class="text-sm text-muted" style="margin: 0;">
                    <a href="<?= e(APP_URL) ?>/dashboard.php">Dashboard</a>
                    <span aria-hidden="true"> / </span>
                    <span>Bio editor</span>
                </p>
                <h1 style="margin: var(--space-2) 0 0;">Edit bio page</h1>
                <p class="text-muted" style="margin: var(--space-2) 0 0;">
                    Your public page: 
                    <a href="<?= e(APP_URL) ?>/bio.php?u=<?= e($bioPage['slug']) ?>" target="_blank">
                        <?= e(APP_URL) ?>/bio.php?u=<?= e($bioPage['slug']) ?>
                    </a>
                </p>
            </div>
            <a href="<?= e(APP_URL) ?>/bio.php?u=<?= e($bioPage['slug']) ?>"
               target="_blank"
               class="btn btn-primary">
                Preview public page →
            </a>
        </div>

        <?php if ($flash === 'page_updated'): ?>
            <div class="alert alert-success" data-auto-dismiss="3000">✅ Page updated.</div>
        <?php elseif ($flash === 'link_added'): ?>
            <div class="alert alert-success" data-auto-dismiss="3000">✅ Link added.</div>
        <?php elseif ($flash === 'link_deleted'): ?>
            <div class="alert alert-success" data-auto-dismiss="3000">✅ Link deleted.</div>
        <?php elseif ($flash === 'link_moved'): ?>
            <div class="alert alert-success" data-auto-dismiss="3000">✅ Link reordered.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div>• <?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- =============================================
             Page settings
             ============================================= -->
        <section class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Page settings</h3>
            </div>

            <form method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_page">

                <div class="grid grid-2" style="gap: var(--space-5);">
                    <div class="form-group">
                        <label for="title" class="form-label">Title</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-input"
                            value="<?= e($bioPage['title'] ?? '') ?>"
                            maxlength="64"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="avatar_url" class="form-label">Avatar URL (optional)</label>
                        <input
                            type="url"
                            id="avatar_url"
                            name="avatar_url"
                            class="form-input"
                            value="<?= e($bioPage['avatar_url'] ?? '') ?>"
                            placeholder="https://example.com/avatar.jpg"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio" class="form-label">Bio</label>
                    <textarea
                        id="bio"
                        name="bio"
                        class="form-textarea"
                        maxlength="300"
                        rows="3"
                    ><?= e($bioPage['bio'] ?? '') ?></textarea>
                    <span class="form-hint">Max 300 characters.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Theme</label>
                    <div class="theme-picker">
                        <?php foreach (['default', 'sunset', 'ocean', 'forest'] as $t): ?>
                            <label class="theme-option theme-<?= e($t) ?>">
                                <input
                                    type="radio"
                                    name="theme"
                                    value="<?= e($t) ?>"
                                    <?= ($bioPage['theme'] ?? 'default') === $t ? 'checked' : '' ?>
                                >
                                <span><?= ucfirst($t) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save page</button>
            </form>
        </section>

        <!-- =============================================
             Links
             ============================================= -->
        <section class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Your links</h3>
                <span class="badge badge-muted"><?= count($links) ?></span>
            </div>

            <?php if (empty($links)): ?>
                <p class="text-muted text-center" style="padding: 2rem 0;">
                    No links yet. Add your first one below.
                </p>
            <?php else: ?>
                <ul class="bio-link-list">
                    <?php foreach ($links as $i => $link): ?>
                        <li class="bio-link-row">
                            <span class="bio-link-icon"><?= e($link['icon'] ?? '🔗') ?></span>

                            <div class="bio-link-body">
                                <strong><?= e($link['label']) ?></strong>
                                <div class="text-xs text-muted truncate">
                                    <?= e($link['url']) ?>
                                </div>
                            </div>

                            <span class="badge badge-muted"><?= (int) $link['click_count'] ?> clicks</span>

                            <div class="bio-link-actions">
                                <!-- Move up -->
                                <form method="POST" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move_link">
                                    <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button
                                        type="submit"
                                        class="btn btn-secondary btn-sm"
                                        <?= $i === 0 ? 'disabled' : '' ?>
                                        title="Move up">
                                        ↑
                                    </button>
                                </form>

                                <!-- Move down -->
                                <form method="POST" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move_link">
                                    <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button
                                        type="submit"
                                        class="btn btn-secondary btn-sm"
                                        <?= $i === count($links) - 1 ? 'disabled' : '' ?>
                                        title="Move down">
                                        ↓
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" style="display: inline;"
                                      data-confirm="Delete this link?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_link">
                                    <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                        ×
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- =============================================
             Add new link
             ============================================= -->
        <section class="card">
            <div class="card-header">
                <h3 class="card-title">Add a link</h3>
            </div>

            <form method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_link">

                <div class="grid grid-2" style="gap: var(--space-5);">
                    <div class="form-group">
                        <label for="label" class="form-label">Label</label>
                        <input
                            type="text"
                            id="label"
                            name="label"
                            class="form-input"
                            placeholder="My Portfolio"
                            maxlength="64"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="icon" class="form-label">Icon (emoji, optional)</label>
                        <input
                            type="text"
                            id="icon"
                            name="icon"
                            class="form-input"
                            placeholder="🌐"
                            maxlength="4"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="url" class="form-label">URL</label>
                    <input
                        type="url"
                        id="url"
                        name="url"
                        class="form-input"
                        placeholder="https://example.com"
                        required
                    >
                    <span class="form-hint">
                        Tip: use a short link from your dashboard for click tracking.
                    </span>
                </div>

                <button type="submit" class="btn btn-primary">Add link</button>
            </form>
        </section>
    </div>

<?php else: ?>
    <!-- =================================================
         PUBLIC VIEW
         ================================================= -->
    <?php if (!$bioPage): ?>
        <div class="card text-center" style="max-width: 480px; margin: 4rem auto;">
            <div style="font-size: 3rem; margin-bottom: var(--space-4);">🔍</div>
            <h1>Bio page not found</h1>
            <p class="text-muted mb-6">
                No bio page exists for <code><?= e($slug) ?></code>.
            </p>
            <a href="<?= e(APP_URL) ?>/bio.php?edit=1" class="btn btn-primary">
                Create one
            </a>
        </div>
    <?php else: ?>
        <div class="bio-page bio-theme-<?= e($bioPage['theme'] ?? 'default') ?>">
            <div class="bio-container">

                <!-- Profile -->
                <div class="bio-profile">
                    <?php if (!empty($bioPage['avatar_url'])): ?>
                        <img
                            src="<?= e($bioPage['avatar_url']) ?>"
                            alt=""
                            class="bio-avatar"
                            onerror="this.style.display='none'"
                        >
                    <?php endif; ?>

                    <h1 class="bio-title"><?= e($bioPage['title'] ?? 'My Links') ?></h1>

                    <?php if (!empty($bioPage['bio'])): ?>
                        <p class="bio-subtitle"><?= e($bioPage['bio']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Links -->
                <?php if (empty($links)): ?>
                    <p class="text-center text-muted" style="padding: 2rem 0;">
                        No links yet.
                    </p>
                <?php else: ?>
                    <ul class="bio-public-links">
                        <?php foreach ($links as $link): ?>
                            <li>
                                <a
                                    href="<?= e($link['url']) ?>"
                                    target="_blank"
                                    rel="noopener"
                                    class="bio-public-link"
                                >
                                    <?php if (!empty($link['icon'])): ?>
                                        <span class="bio-public-icon"><?= e($link['icon']) ?></span>
                                    <?php endif; ?>
                                    <span class="bio-public-label"><?= e($link['label']) ?></span>
                                    <span class="bio-public-arrow">→</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Footer -->
                <p class="bio-footer">
                    Powered by
                    <a href="<?= e(APP_URL) ?>/" target="_blank"><?= e(APP_NAME) ?></a>
                </p>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>