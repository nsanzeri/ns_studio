<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';
$products = product_file_map();
$p = $products['btb'];

// Drop your images into /assets/img/ with these filenames.
$heroImage = base_url('../assets/img/blueprint-hero-nick.jpg');
$galleryImages = [
		base_url('../assets/img/blueprint-gallery-party-1.jpg'),
		base_url('../assets/img/blueprint-gallery-party-2.jpg'),
		base_url('../assets/img/blueprint-gallery-party-3.jpg'),
		base_url('../assets/img/blueprint-gallery-party-4.jpg'),
];
$coverImage = base_url('../assets/img/backing-track-blueprint-cover.jpg');

$canceled = isset($_GET['canceled']) && $_GET['canceled'] == '1';

if (empty($_SESSION['blueprint_lead_token'])) {
	$_SESSION['blueprint_lead_token'] = bin2hex(random_bytes(24));
}
$leadToken = (string)$_SESSION['blueprint_lead_token'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($p['title']) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">
<style>
:root{
  --bg:#070707;
  --panel:#111111;
  --panel-2:#171717;
  --text:#f6f2e8;
  --muted:rgba(246,242,232,.78);
  --line:rgba(255,255,255,.10);
  --gold:#d4a64a;
  --gold-2:#f3d38a;
  --danger:#ffb3b3;
  --shadow:0 18px 50px rgba(0,0,0,.35);
}
*{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;
  background:radial-gradient(circle at top, #1a1a1a 0%, var(--bg) 45%);
  color:var(--text);
  font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  line-height:1.6;
}
a{color:inherit;}
img{max-width:100%;display:block;}
.page-wrap{
  max-width:1200px;
  margin:auto;
  padding:24px 20px 70px;
}
.eyebrow{
  display:inline-block;
  font-size:13px;
  font-weight:700;
  letter-spacing:.14em;
  text-transform:uppercase;
  color:var(--gold-2);
  margin-bottom:14px;
}
.hero{
  display:grid;
  grid-template-columns:1.15fr .85fr;
  gap:34px;
  align-items:center;
  min-height:78vh;
  padding:20px 0 24px;
}
.hero-copy h1{
  font-size:clamp(40px, 6vw, 72px);
  line-height:.98;
  margin:0 0 18px;
  letter-spacing:-.03em;
}
.hero-copy .accent{color:var(--gold-2);}
.sub{
  font-size:clamp(18px,2.2vw,24px);
  color:var(--muted);
  max-width:700px;
  margin:0 0 22px;
}
.hero-points{
  display:grid;
  gap:10px;
  margin:20px 0 28px;
}
.hero-points div{
  color:#f7f1dd;
  font-weight:600;
}
.hero-card{
  position:relative;
  border-radius:28px;
  overflow:hidden;
  background:#0f0f0f;
  box-shadow:var(--shadow);
  min-height:640px;
}
.hero-card img{
  width:100%;
  height:100%;
  object-fit:cover;
}
.hero-card::after{
  content:"";
  position:absolute;
  inset:0;
  background:linear-gradient(180deg, rgba(0,0,0,.05) 0%, rgba(0,0,0,.10) 50%, rgba(0,0,0,.55) 100%);
}
.hero-badge{
  position:absolute;
  left:20px;
  bottom:18px;
  z-index:2;
  background:rgba(7,7,7,.72);
  backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.08);
  border-radius:16px;
  padding:12px 14px;
  font-size:14px;
  max-width:280px;
}
.btn-row{
  display:flex;
  gap:14px;
  flex-wrap:wrap;
  align-items:center;
}
.btn-gold{
  appearance:none;
  border:0;
  border-radius:999px;
  padding:16px 26px;
  background:linear-gradient(135deg,var(--gold-2),var(--gold));
  color:#111;
  font-size:17px;
  font-weight:800;
  cursor:pointer;
  box-shadow:0 12px 28px rgba(212,166,74,.25);
}
.btn-secondary{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:54px;
  padding:0 22px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,.12);
  text-decoration:none;
  color:var(--text);
  background:rgba(255,255,255,.03);
}
.note{
  font-size:14px;
  color:var(--muted);
  margin-top:10px;
}
.flash{
  background:rgba(212,166,74,.10);
  color:#ffefc5;
  border:1px solid rgba(212,166,74,.25);
  padding:12px 14px;
  border-radius:14px;
  margin:6px 0 22px;
}
.lead-magnet{
  display:grid;
  grid-template-columns:1.05fr .95fr;
  gap:24px;
  align-items:center;
  margin:18px 0 8px;
  padding:28px;
  border-radius:28px;
  background:linear-gradient(135deg, rgba(243,211,138,.13), rgba(255,255,255,.035));
  border:1px solid rgba(243,211,138,.24);
  box-shadow:var(--shadow);
}
.lead-magnet h2{
  font-size:clamp(30px,4vw,46px);
  line-height:1.03;
  letter-spacing:-.03em;
  margin:0 0 12px;
}
.lead-form{
  display:grid;
  gap:12px;
}
.lead-form label{
  display:block;
  color:#f8e6b7;
  font-size:14px;
  font-weight:800;
  margin-bottom:5px;
}
.lead-form input{
  width:100%;
  min-height:52px;
  border-radius:14px;
  border:1px solid rgba(255,255,255,.13);
  background:rgba(0,0,0,.24);
  color:var(--text);
  font:inherit;
  padding:0 14px;
}
.lead-form input:focus{
  outline:2px solid rgba(243,211,138,.55);
  outline-offset:2px;
}
.lead-form .hp{display:none;}
.lead-message{
  display:none;
  padding:12px 14px;
  border-radius:14px;
  font-size:14px;
}
.lead-message.is-success{
  display:block;
  color:#eaffd9;
  background:rgba(83,174,74,.16);
  border:1px solid rgba(139,222,112,.28);
}
.lead-message.is-error{
  display:block;
  color:#ffd5d5;
  background:rgba(255,91,91,.13);
  border:1px solid rgba(255,179,179,.24);
}
.section{
  padding:38px 0;
}
.section-head{
  max-width:760px;
  margin-bottom:24px;
}
.section-head h2{
  font-size:clamp(30px,4vw,48px);
  line-height:1.03;
  letter-spacing:-.03em;
  margin:0 0 12px;
}
.section-head p{
  color:var(--muted);
  font-size:19px;
  margin:0;
}
.story-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:22px;
}
.panel{
  background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
  border:1px solid var(--line);
  border-radius:24px;
  padding:28px;
  box-shadow:var(--shadow);
}
.panel p:last-child{margin-bottom:0;}
.learn-grid,
.fit-grid,
.testimonial-grid,
.gallery-grid{
  display:grid;
  gap:18px;
}
.learn-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
.fit-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
.learn-item,
.fit-card,
.testimonial,
.mini-proof{
  background:var(--panel);
  border:1px solid var(--line);
  border-radius:22px;
  padding:22px;
}
.learn-item strong,
.fit-card strong{
  display:block;
  font-size:18px;
  margin-bottom:8px;
}
.learn-item p,
.fit-card p,
.testimonial p{
  margin:0;
  color:var(--muted);
}
.mini-proof{
  display:flex;
  justify-content:space-between;
  gap:18px;
  align-items:center;
}
.mini-proof strong{
  font-size:17px;
}
.gallery-grid{grid-template-columns:1.2fr 1fr 1fr;}
.gallery-grid .tall{grid-row:span 2; min-height:620px;}
.gallery-grid .shot{position:relative; overflow:hidden; border-radius:24px; background:#0d0d0d; min-height:300px; box-shadow:var(--shadow);}
.gallery-grid .shot img{width:100%; height:100%; object-fit:cover;}
.gallery-grid .shot::after{content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(0,0,0,.04), rgba(0,0,0,.34));}
.cover-offer{
  display:grid;
  grid-template-columns:.8fr 1.2fr;
  gap:28px;
  align-items:center;
}
.cover-wrap{
  background:#080808;
  border-radius:24px;
  overflow:hidden;
  border:1px solid var(--line);
  box-shadow:var(--shadow);
}
.cover-wrap img{width:100%;height:auto;object-fit:cover;}
.feature-list,
.quick-list{
  list-style:none;
  margin:18px 0 0;
  padding:0;
  display:grid;
  gap:12px;
}
.feature-list li,
.quick-list li{
  padding-left:30px;
  position:relative;
  color:#f4eedf;
}
.feature-list li::before,
.quick-list li::before{
  content:"✔";
  position:absolute;
  left:0;
  top:0;
  color:var(--gold-2);
  font-weight:800;
}
.testimonial-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
.testimonial{
  background:linear-gradient(180deg, #141414, #101010);
}
.stars{
  color:var(--gold-2);
  letter-spacing:.18em;
  margin-bottom:12px;
  font-size:14px;
}
.quote-source{
  display:block;
  margin-top:14px;
  color:#f8e6b7;
  font-size:14px;
  font-weight:700;
}
.offer-box{
  display:grid;
  grid-template-columns:1.15fr .85fr;
  gap:22px;
  padding:28px;
  border-radius:28px;
  background:linear-gradient(135deg, rgba(212,166,74,.11), rgba(255,255,255,.03));
  border:1px solid rgba(212,166,74,.22);
  box-shadow:var(--shadow);
}
.price-tag{
  font-size:52px;
  line-height:1;
  margin:8px 0 14px;
  font-weight:900;
  color:var(--gold-2);
}
.buy-card{
  background:rgba(0,0,0,.22);
  border:1px solid rgba(255,255,255,.09);
  border-radius:22px;
  padding:22px;
}
.buy-card h3{margin-top:0; font-size:24px;}
#buyErr{
  display:none;
  margin-top:12px;
  color:var(--danger);
  font-size:14px;
}
.footer-note{
  margin-top:18px;
  color:var(--muted);
  font-size:14px;
}

.trust-strip{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:16px;
  margin:8px 0 2px;
}
.trust-pill{
  background:rgba(255,255,255,.03);
  border:1px solid var(--line);
  border-radius:18px;
  padding:16px 18px;
  box-shadow:var(--shadow);
}
.trust-pill strong{
  display:block;
  font-size:18px;
  margin-bottom:4px;
}
.trust-pill span{
  color:var(--muted);
  font-size:14px;
}
.center-cta{
  display:flex;
  flex-direction:column;
  align-items:flex-start;
  gap:12px;
  margin-top:24px;
}
.center-cta .note{margin-top:0;}
.not-for-grid,
.faq-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:18px;
}
.faq-item{
  background:var(--panel);
  border:1px solid var(--line);
  border-radius:22px;
  padding:22px;
}
.faq-item strong{
  display:block;
  font-size:18px;
  margin-bottom:8px;
}
.faq-item p{
  margin:0;
  color:var(--muted);
}
.offer-kicker{
  color:#f8e6b7;
  font-weight:700;
  margin:-2px 0 16px;
}

@media (max-width: 980px){
  .hero,
  .story-grid,
  .cover-offer,
  .offer-box,
  .testimonial-grid,
  .gallery-grid,
  .learn-grid,
  .fit-grid,
  .trust-strip,
  .not-for-grid,
  .faq-grid,
  .lead-magnet{
    grid-template-columns:1fr;
  }
  .hero-card{min-height:420px; order:-1;}
  .gallery-grid .tall{grid-row:auto; min-height:360px;}
}
@media (max-width: 640px){
  .page-wrap{padding:16px 16px 58px;}
  .panel,
  .learn-item,
  .fit-card,
  .testimonial,
  .mini-proof,
  .lead-magnet,
  .offer-box,
  .buy-card{padding:20px;}
  .btn-row{flex-direction:column; align-items:stretch;}
  .btn-gold,
  .btn-secondary{width:100%; text-align:center;}
}
</style>

<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src='https://connect.facebook.net/en_US/fbevents.js';
s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}
(window, document,'script');

fbq('init', '512475687955029');
fbq('track', 'PageView');
</script>


</head>
<body>
<div class="page-wrap">

  <?php if ($canceled): ?>
    <div class="flash">No problem — your checkout was canceled. Your spot is still here whenever you’re ready.</div>
  <?php endif; ?>

<section class="hero">
  <div class="hero-copy">

    <div class="eyebrow">For Solo Musicians, Duos, and Bands</div>

<h1>Sound Like a <span class="accent">Full Band</span> — Even If You Show Up Alone</h1>

<p class="sub">
The exact system I use for <strong>140+ paid gigs a year</strong> to turn a solo act or small band into a room-filling live show — without adding more people or more chaos.
</p>

<p class="sub">
This 66-page PDF shows you exactly how I build and run my backing tracks, the gear that makes it reliable night after night, and the key decisions that separate a decent show from a standout one. It also includes video examples of two different approaches, plus real-world clips of the tracks in action.
</p>

    <div style="background:rgba(212,166,74,.10); border:1px solid rgba(212,166,74,.25); padding:16px 18px; border-radius:16px; margin:18px 0 20px;">
      <strong>✔ Instant PDF download</strong><br>
      <strong>✔ Real gig-tested workflow</strong><br>
      <strong>✔ Price: $27</strong>
    </div>

    <div class="hero-points">
      <div>✔ Built from real-world gigs, not bedroom theory</div>
      <div>✔ Designed for solo performers <em>and</em> bands</div>
      <div>✔ Works with your current DAW and playback setup</div>
    </div>

    <div class="btn-row">
      <button class="btn-gold" id="buyBtnTop">Get Instant Access – $27</button>
      <a class="btn-secondary" href="#free-sample">Get Free Sample Chapters</a>
    </div>

    <div class="note">
      Instant digital download. Secure checkout via Stripe.
    </div>

  </div>

  <div class="hero-card">
    <img src="<?= htmlspecialchars($heroImage) ?>" alt="Nick Sanzeri performing live with bass and microphone">
    <div class="hero-badge">
      Used in real rooms, in front of real crowds, when the first song actually matters.
    </div>
  </div>
</section>

  <section class="section" id="free-sample" style="padding-top:8px;">
    <div class="lead-magnet">
      <div>
        <div class="eyebrow">Free Preview</div>
        <h2>Read the intro and first two chapters free</h2>
        <p class="sub" style="font-size:19px;margin-bottom:0;">Get the 16-page sample PDF and see whether the system clicks before you buy the full guide.</p>
        <ul class="quick-list">
          <li>Instant email delivery</li>
          <li>No login needed</li>
          <li>Includes the foundation behind the full 66-page blueprint</li>
        </ul>
      </div>

      <form class="lead-form" id="sampleForm" method="post" action="<?= htmlspecialchars(base_url('shop/blueprint-sample.php')) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($leadToken) ?>">
        <div class="hp" aria-hidden="true">
          <label for="sampleWebsite">Website</label>
          <input id="sampleWebsite" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div>
          <label for="sampleName">First name</label>
          <input id="sampleName" name="first_name" type="text" autocomplete="given-name" maxlength="120">
        </div>
        <div>
          <label for="sampleEmail">Email address</label>
          <input id="sampleEmail" name="email" type="email" autocomplete="email" required maxlength="190">
        </div>
        <button class="btn-gold" type="submit" id="sampleSubmit">Send Me the Free Sample</button>
        <div class="lead-message" id="sampleMessage" role="status" aria-live="polite"></div>
        <p class="note" style="margin:0;">I’ll send the sample and occasional practical music-business/show-building notes. Unsubscribe anytime.</p>
      </form>
    </div>
  </section>

  <section class="section" style="padding-top:8px;">
    <div class="trust-strip">
      <div class="trust-pill">
        <strong>140+ shows a year</strong>
        <span>Built from real gigs, not theory.</span>
      </div>
      <div class="trust-pill">
        <strong>Instant digital access</strong>
        <span>Buy it now, start using it tonight.</span>
      </div>
      <div class="trust-pill">
        <strong>For solo acts and bands</strong>
        <span>Use it whether you play alone or with a group.</span>
      </div>
    </div>

    <div class="center-cta">
      <button class="btn-gold" id="buyBtnMid">Jump to Checkout – $27</button>
      <div class="note">You do not need new gear to start applying these ideas.</div>
    </div>
  </section>

  <section class="section">
    <div class="mini-proof">
      <div>
     <strong>You can play everything right… and still sound small.</strong>
<p>And in a real room, small doesn’t get remembered — or rebooked.</p>
      </div>
      <div class="eyebrow" style="margin:0;">Battle-tested on stage</div>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>Why this guide exists</h2>
<p>I play over <strong>140 shows a year</strong> as a solo musician.</p><br>

<p>And almost every night, someone asks the same thing:</p><br>

<p><strong>“How are you getting that sound?”</strong></p><br>

<p>Because what they’re hearing doesn’t match what they’re seeing.</p><br>

<p>One guy.<br>But it sounds like a full band.</p><br>

<p>That’s not talent.<br>That’s a system.</p><br>

<p>Once I figured it out, everything changed:</p>

<ul style="margin:10px 0 18px 18px;">
  <li>Better reactions</li>
  <li>Better gigs</li>
  <li>Better money</li>
</ul>

<p>This guide shows you exactly how to do the same thing.</p><br>

<p><strong>Most musicians are using backing tracks… wrong.</strong></p><br>

<p>Not because they’re bad players — but because nobody showed them how to use them for a live room.</p>
    </div>

    <div class="story-grid">
      <div class="panel">
        <p>If you play solo gigs, you know the feeling.</p>
        <p>Some nights the music is great, but something still feels small. The playing is solid. The songs are good. But compared to a full band, the sound just doesn’t land the same way.</p>
        <p>For years I played in a three-piece band and once in a while we’d open for bigger regional acts. Every time it happened, they sounded massive compared to us.</p>
      </div>
      <div class="panel">
        <p>Huge vocals. Tight transitions. Big energy.</p>
        <p>Eventually I realized they weren’t just better musicians. They were supplementing their sound in smart ways.</p>
        <p>Once I learned how to do that myself, my band and solo gigs changed. This guide shows you the same principles so you can sound bigger, tighter, and more professional whether you perform solo or with a band.</p>
      </div>
    </div>
  </section>

  <section class="section" id="inside">
    <div class="section-head">
      <h2>Inside the Backing Track Blueprint</h2>
      <p>A practical guide for working musicians who want their live show to hit harder without adding more chaos.</p>
    </div>

    <div class="learn-grid">
<div class="learn-item">
  <strong>How to build backing tracks that actually hold up live</strong>
  <p>Not fragile. Not overbuilt. Something you can trust on a real stage.</p>
</div>

<div class="learn-item">
  <strong>Why most songs lose energy — and how to fix it</strong>
  <p>Simple arrangement tweaks that keep momentum high and the room engaged.</p>
</div>

<div class="learn-item">
  <strong>The medley/transition trick that makes a solo act feel like a show</strong>
  <p>Keep people leaning in instead of resetting between songs.</p>
</div>

<div class="learn-item">
  <strong>The vocal layering move that makes people look for the “rest of the band”</strong>
  <p>A simple approach that instantly adds width and power.</p>
</div>

<div class="learn-item">
  <strong>Why the right track pulls your performance up</strong>
  <p>When the foundation is strong, your playing tightens and your confidence rises.</p>
</div>

<div class="learn-item">
  <strong>How to build a playback system that doesn’t fall apart live</strong>
  <p>Simple, repeatable, and reliable when it actually matters.</p>
</div>

<div class="learn-item">
  <strong>How bands use these ideas too</strong>
  <p>These concepts also tighten and modernize full bands without adding more people.</p>
</div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>What happens when the sound gets bigger</h2>
      <p>These techniques are used in real rooms, with real crowds, and they change how a performance feels.</p>
    </div>

    <div class="gallery-grid">
      <div class="shot tall"><img src="<?= htmlspecialchars($galleryImages[0]) ?>" alt="Nick Sanzeri performing at an outdoor venue"></div>
      <div class="shot"><img src="<?= htmlspecialchars($galleryImages[1]) ?>" alt="Packed dance floor at live event"></div>
      <div class="shot"><img src="<?= htmlspecialchars($galleryImages[2]) ?>" alt="Private party dance floor during live performance"></div>
      <div class="shot"><img src="<?= htmlspecialchars($galleryImages[3]) ?>" alt="Crowded ballroom event with live music"></div>
      <div class="shot"><img src="<?= htmlspecialchars($heroImage) ?>" alt="Nick Sanzeri performing live with bass and microphone"></div>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>Who this is for</h2>
      <p>This guide is a strong fit if you want your show to feel more polished, more exciting, and more competitive.</p>
    </div>

    <div class="fit-grid">
      <div class="fit-card">
        <strong>This is for you if...</strong>
        <p>You perform solo and want a bigger sound, already use tracks but want them to sound better, or you want smoother transitions and a more professional overall show.</p>
      </div>
      <div class="fit-card">
        <strong>This is also for bands</strong>
        <p>If your band wants to add support parts, tighten arrangements, or create a fuller modern sound without adding more musicians, these concepts apply to you too.</p>
      </div>
    </div>

    <div class="section-head" style="margin-top:28px;">
      <h2>This is probably not for you if...</h2>
    </div>

    <div class="not-for-grid">
<div class="fit-card">
  <strong>You’re fine sounding like background music</strong>
  <p>This is for musicians who want the show to land — not just fill space.</p>
</div>

<div class="fit-card">
  <strong>You want theory instead of results</strong>
  <p>This is practical, working-musician stuff meant to improve your sound fast.</p>
</div>

<div class="fit-card">
  <strong>You refuse to use backing tracks on principle</strong>
  <p>This is for musicians who want to use tracks tastefully and professionally.</p>
</div>
  </section>

  <section class="section">
    <div class="cover-offer">
      <div class="cover-wrap">
        <img src="<?= htmlspecialchars($coverImage) ?>" alt="Backing Track Blueprint ebook cover">
      </div>
      <div>
        <div class="eyebrow">Instant Access — Start Using It Tonight</div>
        <h2 style="font-size:clamp(30px,4vw,46px);line-height:1.03;margin:0 0 14px;letter-spacing:-.03em;">The exact system used in 140+ live shows a year</h2>
        <p class="sub" style="font-size:19px;margin-bottom:0;">No fluff. No theory dump. Just the practical concepts that help a solo act or band sound bigger, tighter, and more professional.</p>
        <ul class="feature-list">
          <li>Instant access after purchase</li>
          <li>Secure download link sent right away</li>
          <li>Start applying it to your next show tonight</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>What people are saying</h2>
      <p>Real reactions from musicians and audiences who’ve heard the difference.</p>
    </div>

    <div class="testimonial-grid">
      <div class="testimonial">
        <div class="stars">★★★★★</div>
        <p>“I bought this and have already used many of the tips that Nick suggests. But the little things he talks about have taken me to a new level. Worth every penny!”</p>
        <span class="quote-source">— Buyer review</span>
      </div>
      <div class="testimonial">
        <div class="stars">★★★★★</div>
        <p>“Best gig ever. You get bass player, guitar player, drummer, keyboard player, horns and back up singers. Genius! Keep it up.”</p>
        <span class="quote-source">— Audience feedback</span>
      </div>
      <div class="testimonial">
        <div class="stars">★★★★★</div>
        <p>“You must know something I don’t. Looks like you’re really enjoying and connecting. You play like you robbed a bank and gave away the money.”</p>
        <span class="quote-source">— Audience feedback</span>
      </div>
      <div class="testimonial">
        <div class="stars">★★★★★</div>
        <p>“As a musician I'm not crazy about full bands using backing tracks. Solo acts I can appreciate the reason why. That being said I support you. Anybody that can sing like that and play bass at the same time deserves my respect.”</p>
        <span class="quote-source">— Fellow musician</span>
      </div>
    </div>

    <div class="center-cta">
      <button class="btn-gold" id="buyBtnAfterTestimonials">Get the Guide for $27</button>
      <div class="note">If the proof makes sense, the next step is easy.</div>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>Quick questions musicians usually have</h2>
      <p>Wondering if this will work for your setup? Here are a few things you might want to know.</p>
    </div>

    <div class="faq-grid">
      <div class="faq-item">
        <strong>Do I need fancy gear?</strong>
        <p>No. The goal is to use smart concepts and a repeatable system with the tools you already have or can easily access.</p>
      </div>
      <div class="faq-item">
        <strong>Is this only for solo acts?</strong>
        <p>No. Solo musicians, duos, and bands can all use these ideas to sound fuller, tighter, and more intentional live.</p>
      </div>
      <div class="faq-item">
        <strong>Can I actually use this fast?</strong>
        <p>Yes. This was written to help you apply ideas quickly, not someday. You should be able to pull useful moves from it right away.</p>
      </div>
      <div class="faq-item">
        <strong>What am I really buying?</strong>
        <p>You are buying my field-tested framework for building tracks and running a show.  A 66 page pdf, with two video walk-throughs and various other example videos of the tracks in action. This from a working musician who has already put these ideas through real rooms, real crowds, and real gigs.</p>
        
      </div>
    </div>
  </section>

  <section class="section">
    <div class="offer-box">
      <div>
        <div class="eyebrow">Get the Guide</div>
        <h2 style="font-size:clamp(32px,4vw,48px);line-height:1.02;margin:0 0 10px;letter-spacing:-.03em;">Backing Track Blueprint</h2>
        <p class="sub" style="font-size:20px;margin-bottom:0;">How solo musicians and bands sound bigger, tighter, and more professional.</p>
        <div class="price-tag">$27</div>
        <p class="offer-kicker">Used every week on stage in real gigs.</p>
        <ul class="quick-list">
          <li>Secure Stripe checkout</li>
          <li>Instant access after purchase</li>
          <li>Simple download flow — no login required</li>
        </ul>
      </div>

      <div class="buy-card">
        <h3>Start here</h3>
        <p class="note" style="margin-top:0;">Buy now and you’ll be taken to secure checkout. After purchase you’ll see your download button right away.</p>
        <button class="btn-gold" id="buyBtn">Get Instant Access</button>
        <p id="buyErr"></p>
       <p class="footer-note">
If you’ve got gigs coming up, this isn’t something you want to “get to eventually.”<br><br>
Because the difference shows up immediately — in the first song.  And that could lead to immediate further bookings.
</p>
      </div>
    </div>
  </section>

</div>

<script>
const buyButtons = [
  document.getElementById('buyBtnTop'),
  document.getElementById('buyBtnMid'),
  document.getElementById('buyBtnAfterTestimonials'),
  document.getElementById('buyBtn')
].filter(Boolean);
const errEl = document.getElementById('buyErr');
const checkoutUrl = "<?= base_url('api/create_checkout_session.php') ?>";
const sampleForm = document.getElementById('sampleForm');
const sampleSubmit = document.getElementById('sampleSubmit');
const sampleMessage = document.getElementById('sampleMessage');

async function go(){
  if (errEl) {
    errEl.style.display = 'none';
    errEl.textContent = '';
  }

  buyButtons.forEach(btn => {
    btn.disabled = true;
    btn.textContent = 'Loading checkout...';
  });

  try {
    const response = await fetch(checkoutUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json'
      },
      body: new URLSearchParams({
        product_key: 'btb'
      })
    });

    const text = await response.text();
    const data = JSON.parse(text);

    if (data.url) {
      window.location = data.url;
      return;
    }

    throw new Error(data.error || 'Checkout error');
  } catch (e) {
    if (errEl) {
      errEl.style.display = 'block';
      errEl.textContent = e.message || 'Something went wrong';
    }

    const resetLabels = {
      buyBtnTop: 'Get Instant Access – $27',
      buyBtnMid: 'Jump to Checkout – $27',
      buyBtnAfterTestimonials: 'Get the Guide for $27',
      buyBtn: 'Get Instant Access'
    };

    buyButtons.forEach((btn) => {
      btn.disabled = false;
      btn.textContent = resetLabels[btn.id] || 'Get Instant Access';
    });
  }
}

buyButtons.forEach(btn => btn.addEventListener('click', go));

if (sampleForm && sampleSubmit && sampleMessage) {
  sampleForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    sampleMessage.className = 'lead-message';
    sampleMessage.textContent = '';
    sampleSubmit.disabled = true;
    sampleSubmit.textContent = 'Sending...';

    try {
      const response = await fetch(sampleForm.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new FormData(sampleForm)
      });
      const data = await response.json();

      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'Something went wrong. Please try again.');
      }

      sampleMessage.className = 'lead-message is-success';
      sampleMessage.textContent = data.message || 'Check your inbox. The sample is on its way.';
      sampleForm.reset();

      if (window.fbq) {
        fbq('track', 'Lead', { content_name: 'Backing Track Blueprint sample' });
      }
    } catch (error) {
      sampleMessage.className = 'lead-message is-error';
      sampleMessage.textContent = error.message || 'Something went wrong. Please try again.';
    } finally {
      sampleSubmit.disabled = false;
      sampleSubmit.textContent = 'Send Me the Free Sample';
    }
  });
}
</script>
</body>
</html>
