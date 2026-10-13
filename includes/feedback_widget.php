<?php
$rssHideFeedbackWidget = $rssHideFeedbackWidget ?? false;
$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$isPublicTippingScreen = str_contains($currentPath, '/studio/setmaxx/public.php')
    || str_contains($currentPath, '/studio/request.php');

if (!$rssHideFeedbackWidget && !$isPublicTippingScreen):
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'readysetshows.com');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? $currentPath);
    $pageUrl = $scheme . '://' . $host . $uri;
    $isLocal = str_contains($currentPath, '/ns_studio/');
    $siteBase = $isLocal ? '/ns_studio' : '';
    $feedbackAction = $siteBase . '/feedback-submit.php';
    $feedbackSent = (string)($_GET['feedback'] ?? '') === 'sent';
    $feedbackError = isset($_GET['feedback_error']);
?>
<details class="rss-feedback-widget">
    <summary class="rss-feedback-toggle" aria-label="Open feedback options">
        <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
        <span>Feedback</span>
    </summary>
    <div class="rss-feedback-panel" aria-label="Feedback form">
        <p class="rss-feedback-title">Send quick feedback</p>
        <?php if ($feedbackSent): ?>
            <p class="rss-feedback-status">Thanks. Your note was sent.</p>
        <?php elseif ($feedbackError): ?>
            <p class="rss-feedback-status rss-feedback-status-error">Something did not send. Please try again.</p>
        <?php endif; ?>
        <form class="rss-feedback-form" method="post" action="<?= htmlspecialchars($feedbackAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="page_url" value="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') ?>">
            <label class="rss-feedback-hp" aria-hidden="true">
                Website
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
            <div class="rss-feedback-options" aria-label="Feedback type">
                <label>
                    <input type="radio" name="feedback_type" value="bug" checked>
                    <span><i class="fa-solid fa-bug" aria-hidden="true"></i> Bug</span>
                </label>
                <label>
                    <input type="radio" name="feedback_type" value="idea">
                    <span><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Idea</span>
                </label>
                <label>
                    <input type="radio" name="feedback_type" value="confusing">
                    <span><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Confusing</span>
                </label>
            </div>
            <label class="rss-feedback-field">
                <span>Your note</span>
                <textarea name="message" rows="4" maxlength="1800" required></textarea>
            </label>
            <label class="rss-feedback-field">
                <span>Email, optional</span>
                <input type="email" name="email" autocomplete="email">
            </label>
            <button class="rss-feedback-submit" type="submit">Send Feedback</button>
        </form>
    </div>
</details>
<?php endif; ?>
