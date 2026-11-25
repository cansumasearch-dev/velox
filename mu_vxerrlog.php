<?php
// Sandbox: log every PHP warning/notice/error to wp-content/vx-php.log
ini_set('log_errors','1'); ini_set('error_log', WP_CONTENT_DIR . '/vx-php.log'); error_reporting(E_ALL);
