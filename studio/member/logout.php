<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
Auth::logout();
redirect('/index.html');