<?php
require_once __DIR__ . '/../includes/auth.php';

logout_user();
redirect_with_message('/index.php', 'You have been logged out', 'info');
