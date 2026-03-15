<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout canceled</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(base_url('../assets/css/style.css')) ?>">
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
  <div class="container" style="padding:80px 20px; text-align:center;">
    <h1>No worries.</h1>
    <p class="muted">Your checkout was canceled. You can try again anytime.</p>
    <a class="btn btn-primary" href="/shop/blueprint.php" style="margin-top:20px;">Back to the Blueprint</a>
    <a class="btn btn-outline" href="/shop/index.php" style="margin-top:12px;">Back to Shop</a>
  </div>
</body>
</html>
