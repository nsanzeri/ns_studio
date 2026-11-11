<?php
/** Advertising events for Ready Set Shows only. */
function rss_meta_pixel_enabled(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    return in_array($host, ['readysetshows.com', 'www.readysetshows.com'], true);
}

function rss_meta_pixel_registration_created(int $userId): void
{
    if (rss_meta_pixel_enabled() && $userId > 0) {
        $_SESSION['rss_meta_registration_user_id'] = $userId;
    }
}

function rss_render_meta_pixel(): void
{
    static $rendered = false;
    if ($rendered || !rss_meta_pixel_enabled()) return;
    $rendered = true;
    $registrationUserId = (int)($_SESSION['rss_meta_registration_user_id'] ?? 0);
    $registrationCompleted = $registrationUserId > 0
        && $registrationUserId === (int)($_SESSION['user_id'] ?? 0);
    unset($_SESSION['rss_meta_registration_user_id']);
    ?>
<!-- Ready Set Shows Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '4095555793918764');
fbq('trackSingle', '4095555793918764', 'PageView');
<?php if ($registrationCompleted): ?>
fbq('trackSingle', '4095555793918764', 'CompleteRegistration');
<?php endif; ?>
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
src="https://www.facebook.com/tr?id=4095555793918764&amp;ev=PageView&amp;noscript=1"
/></noscript>
<?php
}
