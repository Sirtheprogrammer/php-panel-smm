<?php
require_once 'config.php';
session_destroy();
header('Location: login.php');
exit;
?>
<?php include 'whatsapp-float.php'; ?>
</body>
</html>
