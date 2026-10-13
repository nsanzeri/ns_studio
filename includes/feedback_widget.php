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
    $feedbackEmail = 'nsanzeri@gmail.com';

    $feedbackLinks = [
        [
            'label' => 'Report a bug',
            'icon' => 'fa-bug',
            'subject' => 'Ready Set Shows bug report',
            'body' => "What broke?\n\nWhat did you expect to happen?\n\nPage: " . $pageUrl,
        ],
        [
            'label' => 'Suggest an improvement',
            'icon' => 'fa-lightbulb',
            'subject' => 'Ready Set Shows improvement idea',
            'body' => "What would make this better?\n\nWhy would it help?\n\nPage: " . $pageUrl,
        ],
        [
            'label' => 'Something was confusing',
            'icon' => 'fa-circle-question',
            'subject' => 'Ready Set Shows confusing experience',
            'body' => "What felt confusing?\n\nWhat were you trying to do?\n\nPage: " . $pageUrl,
        ],
    ];
?>
<details class="rss-feedback-widget">
    <summary class="rss-feedback-toggle" aria-label="Open feedback options">
        <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
        <span>Feedback</span>
    </summary>
    <div class="rss-feedback-panel" role="group" aria-label="Feedback options">
        <p class="rss-feedback-title">Send quick feedback</p>
        <?php foreach ($feedbackLinks as $link): ?>
            <?php
                $href = 'mailto:' . $feedbackEmail
                    . '?subject=' . rawurlencode($link['subject'])
                    . '&body=' . rawurlencode($link['body']);
            ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid <?= htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>
