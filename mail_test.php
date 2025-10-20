<?php
$to = 'mrtan.dev@gmail.com'; 
$subject = 'Localhost PHP Mail Test from vTiger Server';
$message = 'If you receive this email, your local mail server is working!';
$headers = 'From: info@innovation4life.org' . "\r\n" .
           'Reply-To: info@innovation4life.org' . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

if (mail($to, $subject, $message, $headers)) {
    echo "SUCCESS: Email sent successfully!\n";
} else {
    echo "FAILURE: Email failed to send.\n";
}
?>