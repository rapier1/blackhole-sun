<?php 
// use this script to generate a uuid and pub/priv keypair for 
// ui/client message authentication

/*create uuid*/
$handle = popen('/usr/bin/dbus-uuidgen 2>&1', 'r');
$uuid = fread($handle, 2096);
pclose($handle);

// strip newlines
$uuid = str_replace(array("\n", "\r"), '', $uuid);

// generate key pair
$new_key_pair = openssl_pkey_new(array(
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
));
openssl_pkey_export($new_key_pair, $private_key_pem);

// extract public key
$details = openssl_pkey_get_details($new_key_pair);
$public_key_pem = $details['key'];

// open and write and close files
$pub_file = "./$uuid.pub";
$priv_file = "./$uuid.priv"; 

$pub_key = fopen($pub_file, 'w');
$priv_key = fopen($priv_file, 'w');

fwrite ($pub_key, $public_key_pem);
fwrite ($priv_key, $private_key_pem);

fclose($pub_key);
fclose($priv_key);
?>
