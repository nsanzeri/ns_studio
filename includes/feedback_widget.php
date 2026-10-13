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
    $feedbackHasStatus = $feedbackSent || $feedbackError;
?>
<style>
    .rss-feedback-widget {
        position: fixed !important;
        right: 18px !important;
        bottom: 18px !important;
        left: auto !important;
        z-index: 70 !important;
        font-family: "Poppins", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
    }

    .rss-feedback-toggle {
        list-style: none !important;
        min-height: 42px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.45rem !important;
        padding: 0.58rem 0.82rem !important;
        border-radius: 999px !important;
        border: 1px solid rgba(255,255,255,0.16) !important;
        background: rgba(9,10,18,0.88) !important;
        color: rgba(255,255,255,0.86) !important;
        box-shadow: 0 14px 38px rgba(0,0,0,0.34) !important;
        cursor: pointer !important;
        font-size: 0.78rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        backdrop-filter: blur(14px) !important;
    }

    .rss-feedback-toggle::-webkit-details-marker {
        display: none !important;
    }

    .rss-feedback-toggle:hover,
    .rss-feedback-widget[open] .rss-feedback-toggle {
        color: var(--accent, #e7c75a) !important;
        border-color: rgba(231,199,90,0.5) !important;
        background: rgba(14,15,24,0.96) !important;
    }

    .rss-feedback-panel {
        position: absolute !important;
        right: 0 !important;
        bottom: calc(100% + 0.55rem) !important;
        width: min(320px, calc(100vw - 2rem)) !important;
        padding: 0.65rem !important;
        border-radius: 12px !important;
        border: 1px solid rgba(255,255,255,0.14) !important;
        background: rgba(9,10,18,0.98) !important;
        box-shadow: 0 20px 54px rgba(0,0,0,0.48) !important;
        color: rgba(255,255,255,0.9) !important;
    }

    .rss-feedback-title {
        margin: 0 0 0.35rem !important;
        padding: 0.35rem 0.3rem 0.2rem !important;
        color: rgba(255,255,255,0.62) !important;
        font-size: 0.76rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
    }

    .rss-feedback-status {
        margin: 0 0 0.55rem !important;
        padding: 0.55rem 0.65rem !important;
        border-radius: 9px !important;
        background: rgba(101,213,138,0.12) !important;
        color: rgba(255,255,255,0.88) !important;
        font-size: 0.84rem !important;
    }

    .rss-feedback-status-error {
        background: rgba(255,122,89,0.14) !important;
    }

    .rss-feedback-form {
        display: grid !important;
        gap: 0.58rem !important;
    }

    .rss-feedback-hp {
        position: absolute !important;
        left: -9999px !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
    }

    .rss-feedback-options {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 0.35rem !important;
    }

    .rss-feedback-options label {
        min-width: 0 !important;
        margin: 0 !important;
    }

    .rss-feedback-options input {
        position: absolute !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .rss-feedback-options span {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.35rem !important;
        min-height: 36px !important;
        padding: 0.45rem 0.3rem !important;
        border-radius: 9px !important;
        border: 1px solid rgba(255,255,255,0.12) !important;
        color: rgba(255,255,255,0.72) !important;
        background: transparent !important;
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        cursor: pointer !important;
    }

    .rss-feedback-options input:checked + span,
    .rss-feedback-options span:hover {
        color: var(--accent, #e7c75a) !important;
        background: rgba(255,255,255,0.06) !important;
        border-color: rgba(231,199,90,0.42) !important;
    }

    .rss-feedback-panel i {
        width: 18px !important;
        text-align: center !important;
        color: var(--accent, #e7c75a) !important;
    }

    .rss-feedback-field {
        display: grid !important;
        gap: 0.28rem !important;
        margin: 0 !important;
    }

    .rss-feedback-field span {
        color: rgba(255,255,255,0.62) !important;
        font-size: 0.74rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.07em !important;
        text-transform: uppercase !important;
    }

    .rss-feedback-field textarea,
    .rss-feedback-field input {
        width: 100% !important;
        border: 1px solid rgba(255,255,255,0.14) !important;
        border-radius: 9px !important;
        background: rgba(255,255,255,0.06) !important;
        color: rgba(255,255,255,0.92) !important;
        font: inherit !important;
        font-size: 0.86rem !important;
        padding: 0.62rem 0.68rem !important;
        box-sizing: border-box !important;
    }

    .rss-feedback-field textarea {
        resize: vertical !important;
        min-height: 92px !important;
    }

    .rss-feedback-field textarea:focus,
    .rss-feedback-field input:focus {
        outline: 1px solid var(--accent, #e7c75a) !important;
        border-color: rgba(231,199,90,0.5) !important;
    }

    .rss-feedback-submit {
        min-height: 38px !important;
        border: 0 !important;
        border-radius: 999px !important;
        background: var(--accent, #e7c75a) !important;
        color: #111 !important;
        cursor: pointer !important;
        font: inherit !important;
        font-size: 0.78rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
    }

    .rss-feedback-submit:hover {
        filter: brightness(1.06) !important;
    }

    @media (max-width: 640px) {
        .rss-feedback-widget {
            right: 14px !important;
            bottom: 14px !important;
        }

        .rss-feedback-toggle {
            width: 42px !important;
            justify-content: center !important;
            padding-inline: 0 !important;
        }

        .rss-feedback-toggle span {
            display: none !important;
        }
    }
</style>
<details class="rss-feedback-widget"<?= $feedbackHasStatus ? ' open' : '' ?>>
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
