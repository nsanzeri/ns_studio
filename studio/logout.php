<?php
require __DIR__ . '/../_core/bootstrap.php';
Auth::logout();
redirect('/index.html');