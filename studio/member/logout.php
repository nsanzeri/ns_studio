<?php
require __DIR__ . '/../_private/_core/bootstrap.php';
Auth::logout();
flash_set('success', 'You have been logged out.');
redirect(base_url('login.php'));
