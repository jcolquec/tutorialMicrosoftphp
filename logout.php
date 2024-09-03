<?php
session_start();
session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión

// URL de cierre de sesión de Microsoft
$logoutUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/logout?post_logout_redirect_uri=http://localhost/tutorialmicrosoftphp/login.php';

// Redirige al usuario a la URL de cierre de sesión de Microsoft
header("Location: $logoutUrl");
exit();