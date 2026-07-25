<?php
function sendWhatsApp(
    $phone,
    $message
){

$apikey =
"7976391";

$url =
"https://api.callmebot.com/whatsapp.php"
.
"?phone="
.urlencode($phone)

."&text="
.urlencode($message)

."&apikey="
.$apikey;

return
file_get_contents(
$url
);

}

?>