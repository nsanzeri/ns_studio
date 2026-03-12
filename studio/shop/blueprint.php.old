<?php
require_once __DIR__ . '/../_private/_core/bootstrap.php';
require_once __DIR__ . '/../_private/config/stripe.php';
$products = product_file_map();
$p = $products['btb'];
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($p['title']) ?></title>

<link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">

<style>

.page-wrap{
max-width:900px;
margin:auto;
padding:40px 20px;
}

.hero h1{
font-size:42px;
line-height:1.2;
margin-bottom:10px;
}

.sub{
font-size:20px;
opacity:.85;
margin-bottom:30px;
}

.cta{
margin:30px 0;
}

.story{
margin-top:40px;
font-size:18px;
line-height:1.6;
}

.section{
margin-top:50px;
}

.feature-list{
margin-top:20px;
}

.testimonial{
background:#111;
padding:25px;
border-radius:10px;
margin-top:30px;
font-style:italic;
}

.price{
font-size:28px;
margin-top:20px;
}

.buy-box{
margin-top:40px;
padding:30px;
background:#111;
border-radius:10px;
}

</style>

</head>

<body>

<div class="page-wrap">

<div class="hero">

<h1>
Turn Your Solo Gig Into a Full-Band Experience
</h1>

<p class="sub">
The modern performance system that makes solo musicians sound bigger,
tighter, and more professional.
</p>

</div>

<div class="story">

<p>
If you play solo gigs, you already know the feeling.
</p>

<p>
Some nights the music is great.
But something still feels… small.
</p>

<p>
The playing is solid.  
The songs are good.
</p>

<p>
But compared to a full band, the sound just doesn’t hit the same.
</p>

<p>
For years I played in a three-piece band and once in a while we’d open for bigger regional acts.
</p>

<p>
Every time it happened they sounded massive compared to us.
Huge vocals. Tight transitions. Big energy.
</p>

<p>
Eventually I realized something.
</p>

<p>
They weren’t just better musicians.
</p>

<p>
They were supplementing their sound.
</p>

<p>
Once I learned how to do that myself,
my solo gigs completely changed.
</p>

</div>


<div class="section">

<h2>Introducing the Backing Track Blueprint</h2>

<p>
This practical guide shows you how to build a professional backing-track system
that turns a solo performance into a full-band experience.
</p>

<ul class="feature-list">

<li>Create backing tracks that actually work live</li>
<li>Edit songs for energy and flow</li>
<li>Use medleys to keep audiences engaged</li>
<li>Layer vocals and instruments for a bigger sound</li>
<li>Build a simple playback system that works every night</li>

</ul>

</div>


<div class="testimonial">

⭐ ⭐ ⭐ ⭐ ⭐

<p>
"I bought this and have already used many of the tips that Nick suggests.
But the little things he talks about has taken me to a new level.
Worth every penny!"
</p>

</div>


<div class="section">

<h2>Instant Digital Download</h2>

<p>
After checkout you’ll immediately receive a secure download link.
</p>

<p>
No login required.
</p>

<div class="price">

<strong>$27 Instant Access</strong>

</div>

</div>



<div class="buy-box">

<h3>Buy Now</h3>

<p class="muted">
Checkout is secure via Stripe. After purchase you’ll see a
<strong>Download Now</strong> button instantly.
</p>

<button class="btn btn-primary btn-full" id="buyBtn">
Buy & Download
</button>

<p id="buyErr" class="muted small" style="display:none;margin-top:10px;"></p>

</div>

</div>



<script>

const btn = document.getElementById('buyBtn');
const errEl = document.getElementById('buyErr');
const checkoutUrl = "<?= base_url('api/create_checkout_session.php') ?>";

async function go(){

errEl.style.display='none';

try{

const response = await fetch(checkoutUrl,{
method:'POST',
headers:{
'Content-Type':'application/x-www-form-urlencoded',
'Accept':'application/json'
},
body:new URLSearchParams({
product_key:'btb'
})
});

const text = await response.text();
const data = JSON.parse(text);

if(data.url){
window.location = data.url;
return;
}

throw new Error(data.error || 'Checkout error');

}catch(e){

errEl.style.display='block';
errEl.textContent=e.message || 'Something went wrong';

}

}

btn.addEventListener('click',go);

</script>

</body>
</html>