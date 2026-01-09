<?php
/**
 * Redirect file for backward compatibility
 * All profile_premium.php links now redirect to profile.php
 */
header('Location: profile.php', true, 301);
exit;
