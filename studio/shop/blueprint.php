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
@media (max-width: 980px){
  .hero,
  .story-grid,
  .cover-offer,
  .offer-box,
  .testimonial-grid,
  .gallery-grid,
  .learn-grid,
  .fit-grid{
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
  .offer-box,
  .buy-card{padding:20px;}
  .btn-row{flex-direction:column; align-items:stretch;}
  .btn-gold,
  .btn-secondary{width:100%; text-align:center;}
}
</style>
</head>
<body>
<div class="page-wrap">

  <?php if ($canceled): ?>
    <div class="flash">No problem — your checkout was canceled. Your spot is still here whenever you’re ready.</div>
  <?php endif; ?>

  <section class="hero">
    <div class="hero-copy">
      <div class="eyebrow">For Solo Artists and Bands</div>
      <h1>Sound <span class="accent">Bigger</span>. Feel Tighter. Perform More Professionally.</h1>
      <p class="sub">The practical system Nick Sanzeri uses to make solo acts and bands sound fuller, hit harder, and keep crowds engaged with backing tracks that actually work live.</p>

      <div class="hero-points">
        <div>✔ Built from real-world gigs, not bedroom theory</div>
        <div>✔ Designed for solo performers <em>and</em> bands</div>
        <div>✔ Works with your current DAW and playback setup</div>
      </div>

      <div class="btn-row">
        <button class="btn-gold" id="buyBtnTop">Get Instant Access – $27</button>
        <a class="btn-secondary" href="#inside">See What’s Inside</a>
      </div>
      <div class="note">Instant digital download. Secure checkout via Stripe.</div>
    </div>

    <div class="hero-card">
      <img src="<?= htmlspecialchars($heroImage) ?>" alt="Nick Sanzeri performing live with bass and microphone">
      <div class="hero-badge">These are the same performance concepts used in real shows that fill rooms, lift energy, and make one act sound much bigger.</div>
    </div>
  </section>

  <section class="section">
    <div class="mini-proof">
      <div>
        <strong>Some nights the playing is solid... but the sound still feels small.</strong>
        <p>That’s the gap this guide closes.</p>
      </div>
      <div class="eyebrow" style="margin:0;">Battle-tested on stage</div>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>Why this guide exists</h2>
      <p>If you perform live, you already know how frustrating it is when the songs are good but the overall sound still doesn’t hit like a full production.</p>
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
      <p>A practical guide built for live players who want more impact without more chaos.</p>
    </div>

    <div class="learn-grid">
      <div class="learn-item">
        <strong>Create backing tracks that actually work live</strong>
        <p>Not overbuilt, not fragile, and not a mess to run on stage.</p>
      </div>
      <div class="learn-item">
        <strong>Edit songs for energy and flow</strong>
        <p>Keep momentum up so the set feels intentional instead of stitched together.</p>
      </div>
      <div class="learn-item">
        <strong>Use medleys and transitions intelligently</strong>
        <p>Make your show feel bigger, smoother, and more modern.</p>
      </div>
      <div class="learn-item">
        <strong>Layer vocals and instruments for a fuller sound</strong>
        <p>Create more width, excitement, and authority without adding more people.</p>
      </div>
      <div class="learn-item">
        <strong>Build a playback system that works every night</strong>
        <p>Simple enough to repeat. Strong enough to trust.</p>
      </div>
      <div class="learn-item">
        <strong>Apply it whether you’re solo or in a band</strong>
        <p>This isn’t just for one-man acts. Bands can use these ideas too.</p>
      </div>
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
      <div class="shot"><img src="<?= htmlspecialchars($coverImage) ?>" alt="Backing Track Blueprint cover"></div>
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
  </section>

  <section class="section">
    <div class="cover-offer">
      <div class="cover-wrap">
        <img src="<?= htmlspecialchars($coverImage) ?>" alt="Backing Track Blueprint ebook cover">
      </div>
      <div>
        <div class="eyebrow">Instant Digital Download</div>
        <h2 style="font-size:clamp(30px,4vw,46px);line-height:1.03;margin:0 0 14px;letter-spacing:-.03em;">A field-tested guide for musicians who want their show to hit harder.</h2>
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
      <p>These reactions matter because they sound like real people responding to a real show — not canned marketing copy.</p>
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
  </section>

  <section class="section">
    <div class="offer-box">
      <div>
        <div class="eyebrow">Get the Guide</div>
        <h2 style="font-size:clamp(32px,4vw,48px);line-height:1.02;margin:0 0 10px;letter-spacing:-.03em;">Backing Track Blueprint</h2>
        <p class="sub" style="font-size:20px;margin-bottom:0;">How solo musicians and bands sound bigger, tighter, and more professional.</p>
        <div class="price-tag">$27</div>
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
        <p class="footer-note">This is a digital product. Nothing ships. The goal is simple: give you practical ideas you can use to level up your next show fast.</p>
      </div>
    </div>
  </section>

</div>

<script>
const buyButtons = [
  document.getElementById('buyBtnTop'),
  document.getElementById('buyBtn')
].filter(Boolean);
const errEl = document.getElementById('buyErr');
const checkoutUrl = "<?= base_url('api/create_checkout_session.php') ?>";

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

    buyButtons.forEach((btn, index) => {
      btn.disabled = false;
      btn.textContent = index === 0 ? 'Get Instant Access – $27' : 'Get Instant Access';
    });
  }
}

buyButtons.forEach(btn => btn.addEventListener('click', go));
</script>
</body>
</html>
