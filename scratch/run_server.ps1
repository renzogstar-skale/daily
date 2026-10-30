while ($true) {
    php -S 127.0.0.1:8080 -t public router.php
    Start-Sleep -Seconds 1
}
