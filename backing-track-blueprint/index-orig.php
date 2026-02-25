<?php
// BackingTrackBlueprint landing page (Gumroad version)
// Drop-in replacement for your existing file.

// Gumroad product URL
define('PRODUCT_URL', 'https://nsanzeri.gumroad.com/l/tvagg');
define('PRICE_DISPLAY', '$27');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Backing Track Blueprint — Nick Sanzeri</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicons/favicon-16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicons/favicon-180.png">
  
  <meta name="description" content="Sound like a full band without hiring one. A practical, real-world guide to using backing tracks live — built from 20+ years of gigging experience." />
  <style>
    :root{
      --bg:#0b0c10;
      --panel:#11131a;
      --text:#f2f3f7;
      --muted:#b9bed1;
      --line:rgba(255,255,255,.12);
      --accent:#ffd36a;
      --accent2:#8ad7ff;
      --shadow: 0 18px 60px rgba(0,0,0,.55);
      --radius: 18px;
      --max: 1040px;
      --font: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:var(--font);
      background:
        radial-gradient(1200px 600px at 20% -10%, rgba(255,211,106,.20), transparent 55%),
        radial-gradient(900px 500px at 90% 0%, rgba(138,215,255,.14), transparent 50%),
        radial-gradient(900px 700px at 60% 120%, rgba(255,255,255,.06), transparent 55%),
        var(--bg);
      color:var(--text);
      line-height:1.5;
    }
    a{color:inherit}
    .wrap{max-width:var(--max); margin:0 auto; padding:28px 18px 70px}
    .topbar{
      display:flex; align-items:center; justify-content:space-between;
      gap:12px; padding:10px 0 26px;
    }
    .brand{display:flex; align-items:baseline; gap:10px; letter-spacing:.2px;}
    .brand .name{font-weight:800}
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      border:1px solid var(--line); border-radius:999px;
      padding:8px 12px; color:var(--muted);
      background:rgba(255,255,255,.04);
      font-size:13px; text-decoration:none;
    }

    .hero{
      display:grid;
      grid-template-columns: 1.15fr .85fr;
      gap:18px;
      align-items:stretch;
      margin-top:10px;
    }
    @media (max-width: 920px){ .hero{grid-template-columns:1fr} }

    .card{
      background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
      border:1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }
    .hero-left{padding:26px}
    .kicker{color:var(--accent2); font-weight:700; font-size:13px; letter-spacing:.18em; text-transform:uppercase}
    h1{margin:10px 0 10px; font-size:44px; line-height:1.06; letter-spacing:-.02em;}
    @media (max-width: 520px){ h1{font-size:36px} }

    .sub{color:var(--muted); font-size:18px; margin:0 0 18px; max-width: 62ch;}
    .bullets{margin:18px 0 22px; padding:0; list-style:none; display:grid; gap:10px}
    .bullets li{display:flex; gap:10px; align-items:flex-start; color:var(--text);}
    .check{
      width:20px; height:20px; border-radius:6px;
      background: rgba(255,211,106,.16);
      border:1px solid rgba(255,211,106,.35);
      display:flex; align-items:center; justify-content:center;
      margin-top:2px; flex:0 0 auto;
    }
    .check svg{width:14px; height:14px; fill:var(--accent)}
    .ctaRow{display:flex; gap:12px; flex-wrap:wrap; align-items:center}
    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      gap:10px;
      padding:14px 18px;
      border-radius: 14px;
      border:1px solid rgba(255,211,106,.40);
      background: linear-gradient(180deg, rgba(255,211,106,.22), rgba(255,211,106,.10));
      color: var(--text);
      font-weight:800;
      text-decoration:none;
      box-shadow: 0 10px 30px rgba(255,211,106,.12);
      transition: transform .08s ease;
      min-width: 240px;
    }
    .btn:hover{transform: translateY(-1px)}
    .btn:active{transform: translateY(0)}
    .btnSecondary{
      border:1px solid var(--line);
      background: rgba(255,255,255,.04);
      color: var(--muted);
      font-weight:700;
      min-width:auto;
    }
    .smallnote{color:var(--muted); font-size:13px}
    .divider{height:1px; background:var(--line); margin:18px 0}
    .mini{
      display:inline-flex; align-items:center; gap:10px;
      padding:10px 12px;
      border:1px solid var(--line);
      border-radius:999px;
      background: rgba(255,255,255,.03);
      color:var(--muted);
      font-size:13px;
      flex-wrap:wrap;
    }

    .hero-right{padding:18px; display:grid; gap:12px}
    .mock{
      border-radius: 14px;
      border:1px dashed rgba(255,255,255,.18);
      background: rgba(0,0,0,.20);
      padding:18px;
      min-height: 260px;
      display:flex; flex-direction:column; justify-content:space-between;
    }
    .mock .label{color:var(--muted); font-size:13px}
    .mock .title{font-weight:900; font-size:18px; margin:8px 0 0}
    .priceBox{
      display:flex; align-items:flex-end; justify-content:space-between; gap:12px;
      padding:16px 18px;
      border-radius:14px;
      border:1px solid var(--line);
      background: rgba(255,255,255,.04);
    }
    .price{font-size:30px; font-weight:900; letter-spacing:-.02em;}
    .includes{color:var(--muted); font-size:13px}

    .grid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:14px;
      margin-top:16px;
    }
    @media (max-width: 920px){ .grid{grid-template-columns:1fr} }

    .section{padding:22px}
    .section h2{margin:0 0 10px; font-size:22px}
    .section p{margin:0; color:var(--muted)}
    details{
      border:1px solid var(--line);
      border-radius: 14px;
      background: rgba(255,255,255,.03);
      padding: 14px 14px;
    }
    details + details{margin-top:10px}
    summary{cursor:pointer; font-weight:900; list-style:none;}
    summary::-webkit-details-marker{display:none}
    .detailBody{margin-top:10px; color:var(--muted)}
    .detailBody ul{margin:10px 0 0; padding-left:18px}
    .faq details summary{font-weight:800}

    blockquote{
      margin:0;
      padding:14px 14px;
      border-left:3px solid rgba(255,211,106,.55);
      background: rgba(255,255,255,.03);
      border-radius: 12px;
    }
    blockquote p{margin:0; color:var(--text); font-weight:650}
    .quoteStack{display:grid; gap:10px; margin-top:12px}

    .footer{
      margin-top:24px;
      color:var(--muted);
      font-size:13px;
      text-align:center;
      opacity:.95;
    }
    
    .about-image-wrap {
	  max-width: 280px;      /* adjust: 220–320px is typical for a book cover */
	  margin: 0 auto;        /* center it */
	}
	
	.about-image {
	  width: 100%;
	  height: auto;          /* keeps scale correct */
	  display: block;
	}
  </style>
</head>

<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">
        <div class="name">Nick Sanzeri</div>
        <div class="pill">Backing Track Blueprint</div>
      </div>
      <a class="pill" href="https://nicksanzeri.com">Back to site</a>
    </div>

    <div class="hero">
      <div class="card hero-left">
        <div class="kicker">Wow-worthy sound • Real-world method • Built for the gig</div>
        <h1>Sound Like a Full Band<br/>Get Hired More</h1>
        <div class="about-image-wrap">
           <img src="../assets/img/BackingTrackBlueprint.png" alt="Backing Track Blueprint" class="about-image">
        </div>
        <p class="sub">
          A practical, real-world guide to using backing tracks live — built from 20+ years of gigging experience.
          This is the exact approach I use to stay in demand and keep things <em>musical</em> on stage.
        </p>

        <ul class="bullets">
          <li>
            <span class="check" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg>
            </span>
            Get that “wait… where’s the band?” reaction — without turning your gig into a tech juggling act.
          </li>
          <li>
            <span class="check" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg>
            </span>
            Build a show that holds the room: tighter transitions, less dead air, more momentum.
          </li>
          <li>
            <span class="check" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg>
            </span>
            Be more in demand and take control of your financial future all while rediscovering your love for the music again.
          </li>
        </ul>

        <div class="ctaRow">
          <a class="btn" href="<?= htmlspecialchars(PRODUCT_URL) ?>" target="_blank" rel="noopener">
            Buy & Download Instantly — <?= htmlspecialchars(PRICE_DISPLAY) ?> <span aria-hidden="true">→</span>
          </a>
          <a class="btn btnSecondary" href="#is-this-for-me">Is this for me?</a>
          <div class="smallnote">Secure checkout • Instant download • Re-download anytime</div>
        </div>

        <div class="divider"></div>

        <div class="mini">
          <strong style="color:var(--text)">Not a theory book.</strong>
          <span>Real-world method.</span>
          <span>Built for working musicians.</span>
          <span>Designed for reliability.</span>
        </div>
      </div>

      <div class="card hero-right">
        <div class="mock">
          <div>
            <div class="label">What you get</div>
            <div class="title">Backing Track Blueprint (PDF)</div>
            <p style="color:var(--muted); margin:10px 0 0;">
              Track-building rules, live flow, pacing, video examples, and the on-stage approach that makes one person sound massive —
              without making it feel canned.
            </p>
          </div>
          <div class="priceBox">
            <div>
              <div class="price"><?= htmlspecialchars(PRICE_DISPLAY) ?></div>
              <div class="includes">Instant download • Re-download anytime</div>
            </div>
            <a class="btn" style="min-width:auto; padding:12px 14px;" href="<?= htmlspecialchars(PRODUCT_URL) ?>" target="_blank" rel="noopener">Get it</a>
          </div>
        </div>

        <div class="section card" style="box-shadow:none;">
          <h2 style="margin:0 0 8px;">Built because people asked</h2>
          <p>
            After gigs, in DMs, and under my performance videos, the same question kept showing up:
            <strong style="color:var(--text)">“How are you doing that?”</strong>
            This blueprint is the answer — practical, battle-tested, and built for real rooms.
          </p>
        </div>
      </div>
    </div>

    <div class="grid">
      <div class="card section" id="is-this-for-me">
        <h2>Who this is for (and who it’s not)</h2>
        <p>Quick filter. No guessing. No buyer’s remorse.</p>
        <div style="height:12px"></div>

        <details open>
          <summary>✅ Yes — this is for you if…</summary>
          <div class="detailBody">
            <ul>
              <li>You want a bigger, tighter live sound without adding more people</li>
              <li>You gig regularly (solo, duo, or band) and want consistency night after night</li>
              <li>You value simple + reliable over complicated “pro rig” setups</li>
              <li>You want smoother transitions, medleys, and less dead air</li>
              <li>You want the freedom to explore diverse musical styles and artists without compromise</li>
              <li>You’d rather focus on singing/playing/connecting and getting paid, rather than band drama</li>
            </ul>
          </div>
        </details>

        <details>
          <summary>❌ No — this is NOT for you if…</summary>
          <div class="detailBody">
            <ul>
              <li>You believe backing tracks are “cheating” and don’t want that challenged</li>
              <li>You want an encyclopedia of every DAW, plugin, and workflow on earth</li>
              <li>You prefer endless gear-tweaking over performing</li>
              <li>You're not open to to trying a thrilling way to play more gigs, get more money and have more fun</li>
              <li>You’re looking for a magic button that replaces musicianship or prep</li>
            </ul>
          </div>
        </details>

        <div style="height:14px"></div>
        <a class="btn" href="<?= htmlspecialchars(PRODUCT_URL) ?>" target="_blank" rel="noopener">Buy & Download Instantly — <?= htmlspecialchars(PRICE_DISPLAY) ?> →</a>
        <div class="smallnote" style="margin-top:8px;">Secure checkout • Instant download • Re-download anytime</div>
      </div>

      <div class="card section">
        <h2>Real reactions from real gigs</h2>
        <p>These aren’t comments about gear. They’re reactions to how it feels in the room.</p>

        <div class="quoteStack">
          <blockquote><p>“Best gig ever. You get bass player, guitar player, drummer, keyboard player, horns and back up singers. Genius! Keep it up”</p></blockquote>
          <blockquote><p>“You must know something I don’t. Looks like you’re really enjoying and connecting. You play like you robbed a bank and gave away the money.”</p></blockquote>
          <blockquote><p>“This guy is amazing! I saw him for the first time and I was blown away! He plays a lot of instruments and sings whatever is needed; he's a master of the bass guitar.”</p></blockquote>
          <blockquote><p>“Curious how your recording your live shows? Sounds killer”</p></blockquote>
          <blockquote><p>“Alright alright!! I like this guy!! He's holds it down all by himself!!”</p></blockquote>
          <blockquote><p>“As a musician I'm not crazy about full bands using backing tracks. Solo acts I can appreciate the reason why. That being said I support you. Anybody that can sing like that and play bass at the same time deserves my respect.”</p></blockquote>
          <blockquote><p>“Holy mackerel. I’d love to be able to do that. Great gig you have goin on there. Love your stuff!”</p></blockquote>
          <blockquote><p>“Your double duty chops are pretty stellar. And your sound is absolutely amazing.”</p></blockquote>
        </div>
      </div>
    </div>

    <div class="grid">
      <div class="card section">
        <h2>What this blueprint unlocks</h2>
        <p style="margin-bottom:12px;">
          This isn’t about pressing play. It’s about walking on stage and watching people do a double take.
        </p>

        <ul class="bullets" style="margin:0;">
          <li><span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg></span>
            Sound shockingly big — the kind of sound that makes people look around for bandmates that aren’t there.
          </li>
          <li><span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg></span>
            Hold the room from the first note to the last — more momentum, less dead air.
          </li>
          <li><span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg></span>
            Become the act venues trust — polished, professional, and worth re-booking.
          </li>
          <li><span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg></span>
            Turn “How are you doing all that?” into a question you hear - every night.
          </li>
          <li><span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg></span>
            Stop fighting your setup and band members and enjoy the music again.
          </li>
          <li><span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L20.5 8.1l-1.4-1.4z"/></svg></span>
            Split the money fewer ways - or not at all.
          </li>
        </ul>

        <div style="height:14px"></div>
        <a class="btn" href="<?= htmlspecialchars(PRODUCT_URL) ?>" target="_blank" rel="noopener">Get the Blueprint →</a>
        <div class="smallnote" style="margin-top:8px;">Secure checkout • Instant download • Re-download anytime</div>
      </div>

      <div class="card section faq">
        <h2>FAQ</h2>

        <details>
          <summary>How do I pay?</summary>
          <div class="detailBody">
            You’ll checkout on a secure Gumroad-hosted page.
          </div>
        </details>

        <details>
          <summary>What happens after payment?</summary>
          <div class="detailBody">
            You’ll get instant access to download the PDF on-screen, and you’ll also receive an email receipt with a download link.
          </div>
        </details>

        <details>
          <summary>Can I re-download later?</summary>
          <div class="detailBody">
            Yes. Re-download anytime using the link in your Gumroad receipt email.
          </div>
        </details>

        <details>
          <summary>Is this a comprehensive guide to every possible setup?</summary>
          <div class="detailBody">
            No — it’s a real-world, battle-tested method designed for reliability and repeatability.
            If you want something that works in actual rooms under actual pressure, you’re in the right place.
          </div>
        </details>

        <details>
          <summary>What exactly am I getting?</summary>
          <div class="detailBody">
            A downloadable 66 page PDF guide, with video examples, that lays out the on-stage approach, track-building rules, and practical decisions that make backing tracks feel musical — not mechanical.
          </div>
        </details>

        <div style="height:14px"></div>
        <a class="btn" href="<?= htmlspecialchars(PRODUCT_URL) ?>" target="_blank" rel="noopener">Buy & Download Instantly — <?= htmlspecialchars(PRICE_DISPLAY) ?> →</a>
        <div class="smallnote" style="margin-top:8px;">Secure checkout • Instant download • Re-download anytime</div>
      </div>
    </div>

    <div class="footer">
      © <?= date('Y') ?> Nick Sanzeri • Backing Track Blueprint
    </div>
  </div>
</body>
</html>
