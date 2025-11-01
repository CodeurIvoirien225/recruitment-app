<?php
echo "OpenSSL cafile = " . ini_get("openssl.cafile") . "\n";
var_dump(openssl_get_cert_locations());
?>
